<?php
namespace IntegerNet\CallbackProxy;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/*
 * Dispatches a request to multiple targets, returns first successful response (HTTP 200) or last response if none are successful
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

    public function dispatch(RequestInterface $request, ResponseInterface $response, string $action)
    {
        foreach ($this->targets as $target) {
            $response = $this->dispatchSingle($request, $target, $action);
            if ($response->getStatusCode() === 200) {
                return $response;
            }
        }
        return $response;
    }

    private function dispatchSingle(RequestInterface $request, UriInterface $target, string $action) : ResponseInterface
    {
        $target = $target->withPath($target->getPath() . $action);
        return $this->client->send($request->withUri($target))->withHeader('X-Response-From', $target->__toString());
    }
}
