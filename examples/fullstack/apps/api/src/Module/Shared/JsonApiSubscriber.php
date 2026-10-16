<?php

declare(strict_types=1);

namespace App\Module\Shared;

use PayKit\Bundle\Exception\PayKitException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class JsonApiSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 16], KernelEvents::EXCEPTION => 'onException'];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->isMethodSafe()) {
            return;
        }
        $origin = $request->headers->get('Origin');
        $webPort = $_SERVER['WEB_PORT'] ?? $_ENV['WEB_PORT'] ?? getenv('WEB_PORT') ?: '5184';
        $apiPort = $_SERVER['API_PORT'] ?? $_ENV['API_PORT'] ?? getenv('API_PORT') ?: '8087';
        if (null !== $origin && !in_array($origin, [
            'http://localhost:'.$webPort, 'http://127.0.0.1:'.$webPort,
            'http://localhost:'.$apiPort, 'http://127.0.0.1:'.$apiPort,
        ], true)) {
            throw new AccessDeniedHttpException('Origine non autorisée pour cette démo locale.');
        }
        if ('json' !== $request->getContentTypeFormat()) {
            throw new UnsupportedMediaTypeHttpException('Utilise Content-Type: application/json.');
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        $status = match (true) {
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            $error instanceof \InvalidArgumentException, $error instanceof PayKitException => 400,
            default => 500,
        };
        $event->setResponse(new JsonResponse([
            'error' => $status < 500 ? $error->getMessage() : 'Erreur API. Consulte les logs Symfony dans le terminal.',
        ], $status, $error instanceof HttpExceptionInterface ? $error->getHeaders() : []));
    }
}
