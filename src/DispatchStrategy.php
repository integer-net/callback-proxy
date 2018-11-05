<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface DispatchStrategy
{
    public function execute(
        HttpClient $client,
        Targets $targets,
        RequestInterface $request,
        ResponseInterface $response,
        string $action
    ): ResponseInterface;
}
