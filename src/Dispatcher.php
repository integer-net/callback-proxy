<?php

namespace IntegerNet\CallbackProxy;

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Headers;
use Slim\Http\Request as SlimRequest;

/**
 * Dispatches a request to multiple targets, returns first successful response (HTTP 200) or last response if none are
 * successful
 */
class Dispatcher
{
    /**
     * @var HttpClient
     */
    private $client;
    /**
     * @var Targets
     */
    private $targets;
    /**
     * @var DispatchStrategy
     */
    private $strategy;

    public function __construct(HttpClient $client, Targets $targets, DispatchStrategy $strategy)
    {
        $this->client = $client;
        $this->targets = $targets;
        $this->strategy = $strategy;
    }

    public function dispatch(SlimRequest $request, ResponseInterface $response, string $action): ResponseInterface
    {
        $this->resetHeaderOriginalKeys($request);
        return $this->strategy->execute($this->client, $this->targets, $request, $response, $action);
    }

    /**
     * Workaround for https://github.com/slimphp/Slim-Psr7/issues/11
     */
    private function resetHeaderOriginalKeys(SlimRequest $request): void
    {
        $headersProperty = new \ReflectionProperty(SlimRequest::class, 'headers');
        $headersProperty->setAccessible(true);
        /** @var Headers $headers */
        $headers = $headersProperty->getValue($request);
        foreach ($headers as $key => $header) {
            $headers->set($key, $header['value']);
        }
        $headersProperty->setAccessible(false);
    }
}
