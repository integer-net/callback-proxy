<?php

namespace IntegerNet\CallbackProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Dispatches a request to multiple targets, returns first successful response (HTTP 200) or last response if none are
 * successful
 */
class Dispatcher
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var Target[]
     */
    private $targets;

    public function __construct(Client $client, Target ...$targets)
    {
        $this->client = $client;
        $this->targets = $targets;
    }

    public function dispatch(RequestInterface $request, ResponseInterface $response, string $action): ResponseInterface
    {
        $firstSuccessfulResponse = null;
        foreach ($this->targets as $target) {
            $response = $this->dispatchSingle($request, $target, $action);
            if ($firstSuccessfulResponse === null && $this->responseIsSuccessful($response)) {
                $firstSuccessfulResponse = $response;
            }
        }
        return $firstSuccessfulResponse ?? $response;
    }

    private function dispatchSingle(RequestInterface $request, Target $target, string $action): ResponseInterface
    {
        try {
            $response = $this->client->send($target->applyToRequest($request, $action));
            return $target->applyToResponse($response, $action);
        } catch (RequestException $e) {
            if ($e->getResponse() instanceof ResponseInterface) {
                return $target->applyToResponse($e->getResponse(), $action);
            }
            throw $e;
        }
    }

    private function responseIsSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
