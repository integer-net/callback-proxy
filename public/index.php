<?php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use IntegerNet\CallbackProxy\Dispatcher;
use IntegerNet\CallbackProxy\DispatchStrategy;
use IntegerNet\CallbackProxy\HttpClient;
use IntegerNet\CallbackProxy\Targets;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Namshi\Cuzzle\Formatter\CurlFormatter;
use Namshi\Cuzzle\Middleware\CurlFormatterMiddleware;
use Psr\Http\Message\RequestInterface;
use Slim\Container;
use Slim\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$config = [
    'settings'     => include '../config.php',
    'log'          => function (Container $container) : Logger {
        $log = new Logger('proxy');
        $log->pushHandler(new StreamHandler(dirname(__DIR__) . '/proxy.log'));
        return $log;
    },
    'httpClient'   => function (Container $container) : HttpClient {
        $httpHandler = HandlerStack::create();
        $httpHandler->after('cookies', new CurlFormatterMiddleware($container->get('log')));
        return new HttpClient(
            new Client(
                [
                    'handler' => $httpHandler,
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
        $this->get('log')->debug('INCOMING: ' . (new CurlFormatter)->format($request, []));
        $dispatcher = new Dispatcher(
            $this->get('httpClient'),
            $targets[$args['target']],
            $this->get('dispatchStrategy')
        );

        $response = $dispatcher->dispatch($request, $response, $args['action']);

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $previousPosition = $body->tell();
            $body->rewind();
        }
        $contents = $body->getContents();
        if ($body->isSeekable()) {
            $body->seek($previousPosition);
        }
        $this->get('log')->debug('RESPONSE BODY:' . $contents);

        return $response;
    }
);

$app->run();
