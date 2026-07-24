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
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    private function resolveHttpStatus(DomainErrorCode $errorCode): int
    {
        return match ($errorCode) {
            // HTTP_NOT_FOUND
            DomainErrorCode::USER_NOT_FOUND,
            DomainErrorCode::LOAN_NOT_FOUND => Response::HTTP_NOT_FOUND,

            // HTTP_CONFLICT
            DomainErrorCode::LOAN_ALREADY_PAID_OFF,
            DomainErrorCode::USER_ALREADY_EXISTS => Response::HTTP_CONFLICT,

            // HTTP_UNPROCESSABLE_ENTITY
            DomainErrorCode::PAYMENT_CURRENCY_MISMATCH,
            DomainErrorCode::UNSUPPORTED_CURRENCY,
            DomainErrorCode::MIN_PAYMENT_AMOUNT => Response::HTTP_UNPROCESSABLE_ENTITY,

            // HTTP_SERVICE_UNAVAILABLE
            DomainErrorCode::EXCHANGE_RATE_UNAVAILABLE => Response::HTTP_SERVICE_UNAVAILABLE,

            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof DomainException) {
            return;
        }

        $errorCode = $exception->errorCode();
        $httpStatus = $this->resolveHttpStatus($errorCode);

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
