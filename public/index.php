<?php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;
use IntegerNet\CallbackProxy\Dispatcher;
use Psr\Http\Message\RequestInterface;
use Slim\Container;
use Slim\Http\Response;
use Slim\Http\Uri;

require __DIR__ . '/../vendor/autoload.php';

$config = [
    'settings' => include '../config.php',
    'httpClient' => function(Container $container) {
        return new Client(
            [
                'handler' => new StreamHandler(),
                'verify' => false,
            ]
        );
    },
    'proxyTargets' => function(Container $container) {
        return array_map(
            function($uris) {
                return array_map(
                    function(string $uri) {
                        return Uri::createFromString($uri);
                    },
                    $uris
                );
            },
            $container->settings['proxy']['targets']
        );
    },
];
$app = new \Slim\App($config);

$app->any('/{target}/{action}', function (RequestInterface $request, Response $response, array $args) {
    /** @var $this Container */
    $targets = $this->get('proxyTargets');
    if (!isset($targets[$args['target']])) {
        return $response->withStatus(500)->write("Target {$args['target']} does not exist.");
    }
    $dispatcher = new Dispatcher(
        $this->get('httpClient'),
        ...$targets[$args['target']]
    );
    return $dispatcher->dispatch($request, $response, $args['action']);
});

$app->run();