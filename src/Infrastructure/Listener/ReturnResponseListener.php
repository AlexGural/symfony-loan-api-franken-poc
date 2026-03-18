<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Presentation\Attribute\HttpStatus;
use App\Presentation\Response\ApiResponseInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

#[AsEventListener(event: KernelEvents::VIEW)]
final readonly class ReturnResponseListener
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$this->supportsResult($result)) {
            return;
        }

        $status = $this->resolveStatusCode($event);
        $json = $this->serializer->serialize($result, 'json');

        $event->setResponse(new JsonResponse($json, $status, json: true));
    }

    private function supportsResult(mixed $result): bool
    {
        if ($result instanceof ApiResponseInterface) {
            return true;
        }

        if (!\is_array($result)) {
            return false;
        }

        return [] === $result || reset($result) instanceof ApiResponseInterface;
    }

    private function resolveStatusCode(ViewEvent $event): int
    {
        $controller = $event->getRequest()->attributes->get('_controller');

        if (!\is_string($controller)) {
            return 200;
        }

        if (str_contains($controller, '::')) {
            [$class, $method] = explode('::', $controller);
        } else {
            [$class, $method] = [$controller, '__invoke'];
        }

        try {
            /** @psalm-var class-string $class */
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return 200;
        }

        $attributes = $reflection->getAttributes(HttpStatus::class);

        if ([] !== $attributes) {
            return $attributes[0]->newInstance()->code;
        }

        return 200;
    }
}
