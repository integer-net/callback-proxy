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
     * @var UriInterface[]
     */
    private $targets;

    public function __construct(Client $client, UriInterface ...$targets)
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

    private function dispatchSingle(RequestInterface $request, UriInterface $target, string $action) : ResponseInterface
    {
        $target = $target->withPath($target->getPath() . $action);
        try {
            return $this->responseWithTargetHeader($this->client->send($request->withUri($target)), $target);
        } catch (RequestException $e) {
            if ($e->getResponse() instanceof ResponseInterface) {
                return $this->responseWithTargetHeader($e->getResponse(), $target);
            }
            throw $e;
        }
    }

    private function responseWithTargetHeader(ResponseInterface $response, UriInterface $target)
    {
        return $response->withHeader('X-Response-From', $target->__toString());
    }

    private function responseIsSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
