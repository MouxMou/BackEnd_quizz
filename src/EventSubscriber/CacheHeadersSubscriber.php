<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds cache headers to API responses
 */
class CacheHeadersSubscriber implements EventSubscriberInterface
{
    private array $routes = [
        'app_quizz_getall' => 3600, // 1 hour
        'app_quizz_get' => 3600,    // 1 hour
        'app_question_getbyquizid' => 3600 // 1 hour
    ];

    public function __construct(private bool $debug = false)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Skip non-successful responses
        if ($response->getStatusCode() >= 400) {
            return;
        }

        // Skip non-GET requests
        if ($request->getMethod() !== Request::METHOD_GET) {
            return;
        }

        $route = $request->attributes->get('_route');

        // Add cache headers for specific routes
        if (isset($this->routes[$route])) {
            $ttl = $this->routes[$route];
            $this->setCacheHeaders($response, $ttl);
        }
    }

    private function setCacheHeaders(Response $response, int $ttl): void
    {
        // In debug mode, set no-cache headers
        if ($this->debug) {
            $response->headers->set('Cache-Control', 'no-cache, private');
            return;
        }

        // Otherwise set proper cache headers
        $response->setMaxAge($ttl);
        $response->setSharedMaxAge($ttl);
        $response->setPublic();

        // Set ETag for conditional requests
        if (!$response->headers->has('ETag')) {
            $response->setEtag(md5($response->getContent()));
        }

        // Set Last-Modified if not already set
        if (!$response->headers->has('Last-Modified')) {
            $response->setLastModified(new \DateTime());
        }

        // Enable validation
        $response->setVary(['Accept', 'Authorization']);
    }
}
