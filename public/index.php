<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;
use IntegerNet\CallbackProxy\Dispatcher;
use IntegerNet\CallbackProxy\DispatchStrategy;
use IntegerNet\CallbackProxy\HttpClient;
use IntegerNet\CallbackProxy\Targets;
use Psr\Http\Message\RequestInterface;
use Slim\Container;
use Slim\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$config = [
    'settings'     => include '../config.php',
    'httpClient'   => function (Container $container) : HttpClient {
        return new HttpClient(
            new Client(
                [
                    'handler' => new StreamHandler(),
                    'verify'  => false,
                ]
            )
        );
    },
    'proxyTargets' => function (Container $container) {
        return array_map(
            function ($config) {
                return Targets::fromConfig($config);
            },
            $container->settings['proxy']['targets']
        );
    },
    'dispatchStrategy' => function (Container $container) : DispatchStrategy {
        $class = $container->settings['proxy']['strategy'] ?? DispatchStrategy\DispatchAllReturnFirstSuccess::class;
        return new $class;
    },
];
$app = new \Slim\App($config);

$app->any(
    '/{target}/{action}',
    function (RequestInterface $request, Response $response, array $args) {
        /** @var $this Container */
        $targets = $this->get('proxyTargets');
        if (!isset($targets[$args['target']])) {
            return $response->withStatus(500)->write("Target {$args['target']} does not exist.");
        }
        $dispatcher = new Dispatcher(
            $this->get('httpClient'),
            $targets[$args['target']],
            $this->get('dispatchStrategy')
        );
        $response = $dispatcher->dispatch($request, $response, $args['action']);

        return $response->withoutHeader('Transfer-Encoding');
    }
);

$app->run();
