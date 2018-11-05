<?php

namespace IntegerNet\CallbackProxy;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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

    public function dispatch(RequestInterface $request, ResponseInterface $response, string $action): ResponseInterface
    {
        return $this->strategy->execute($this->client, $this->targets, $request, $response, $action);
    }
}
