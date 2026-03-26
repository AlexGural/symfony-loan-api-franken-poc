<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class DomainExceptionListener
{
    private const array HTTP_STATUS_MAP = [
        DomainErrorCode::USER_NOT_FOUND->value => Response::HTTP_NOT_FOUND,
        DomainErrorCode::USER_ALREADY_EXISTS->value => Response::HTTP_CONFLICT,
        DomainErrorCode::LOAN_NOT_FOUND->value => Response::HTTP_NOT_FOUND,
        DomainErrorCode::LOAN_ALREADY_PAID_OFF->value => Response::HTTP_CONFLICT,
        DomainErrorCode::PAYMENT_CURRENCY_MISMATCH->value => Response::HTTP_UNPROCESSABLE_ENTITY,
        DomainErrorCode::EXCHANGE_RATE_UNAVAILABLE->value => Response::HTTP_SERVICE_UNAVAILABLE,
        DomainErrorCode::UNSUPPORTED_CURRENCY->value => Response::HTTP_UNPROCESSABLE_ENTITY,
        DomainErrorCode::MIN_PAYMENT_AMOUNT->value => Response::HTTP_UNPROCESSABLE_ENTITY,
    ];

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof DomainException) {
            return;
        }

        $errorCode = $exception->errorCode();
        $httpStatus = self::HTTP_STATUS_MAP[$errorCode->value] ?? Response::HTTP_INTERNAL_SERVER_ERROR;

        $request = $event->getRequest();
        $this->logger->warning('domain_error', [
            'code' => $errorCode->value,
            'status' => $httpStatus,
            'exception' => $exception::class,
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ]);

        $event->setResponse(new JsonResponse(
            data: [
                'error' => [
                    'code' => $errorCode->value,
                ],
            ],
            status: $httpStatus,
        ));
    }
}
