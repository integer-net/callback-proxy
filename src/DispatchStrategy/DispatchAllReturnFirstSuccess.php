<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy\DispatchStrategy;

use IntegerNet\CallbackProxy\DispatchStrategy;
use IntegerNet\CallbackProxy\HttpClient;
use IntegerNet\CallbackProxy\Targets;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DispatchAllReturnFirstSuccess implements DispatchStrategy
{
    public function execute(
        HttpClient $client,
        Targets $targets,
        RequestInterface $request,
        ResponseInterface $response,
        string $action
    ): ResponseInterface {
        $firstSuccessfulResponse = null;
        foreach ($targets as $target) {
            $response = $client->send($request, $target, $action);
            if ($firstSuccessfulResponse === null && $this->responseIsSuccessful($response)) {
                $firstSuccessfulResponse = $response;
            }
        }
        return $firstSuccessfulResponse ?? $response;
    }

    private function responseIsSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
