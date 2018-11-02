<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy\DispatchStrategy;

use IntegerNet\CallbackProxy\DispatchStrategy;
use IntegerNet\CallbackProxy\HttpClient;
use IntegerNet\CallbackProxy\Targets;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class StopOnFirstSuccess implements DispatchStrategy
{
    public function execute(
        HttpClient $client,
        Targets $targets,
        RequestInterface $request,
        ResponseInterface $response,
        string $action
    ): ResponseInterface {
        foreach ($targets as $target) {
            $response = $client->send($request, $target, $action);
            if ($this->responseIsSuccessful($response)) {
                return $response;
            }
        }
        return $response;
    }

    private function responseIsSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
