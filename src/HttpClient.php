<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Client to forward requests to configured Target
 */
class HttpClient
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function send(RequestInterface $request, Target $target, string $action): ResponseInterface
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
}
