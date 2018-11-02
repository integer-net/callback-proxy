<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use IntegerNet\CallbackProxy\DispatchStrategy\DispatchAllReturnFirstSuccess;
use IntegerNet\CallbackProxy\DispatchStrategy\StopOnFirstSuccess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Headers;
use Slim\Http\Request as SlimRequest;
use Slim\Http\RequestBody;
use Slim\Http\Response as SlimResponse;
use Slim\Http\Uri;

class DispatcherTest extends TestCase
{
    private const PROXY_ENDPOINT = 'https://proxy.example.com/endpoint/';
    private const DEFAULT_REQUEST_PATH = 'foo/bar';
    private const DEFAULT_REQUEST_METHOD = 'POST';
    /**
     * @var array
     */
    private $historyContainer;
    /**
     * @var MockHandler
     */
    private $mockHandler;
    /**
     * @var HttpClient
     */
    private $client;
    /**
     * @var DispatchStrategy
     */
    private $strategy;
    /**
     * @var string[]
     */
    private $targetBaseUrls;
    /**
     * @var Targets
     */
    private $targetObjects;
    /**
     * @var Response[]
     */
    private $targetResponses;
    /**
     * @var ResponseInterface
     */
    private $response;
    /**
     * @var int
     */
    private $requestIndexOffset = 0;

    protected function setUp()
    {
        parent::setUp();
        $this->historyContainer = [];
        $this->mockHandler = new MockHandler();
        $handler = HandlerStack::create($this->mockHandler);
        $handler->push(Middleware::history($this->historyContainer));
        $this->client = new HttpClient(new Client(['handler' => $handler]));
        $this->strategy = new DispatchAllReturnFirstSuccess;
    }

    public function testReturnsFirstSuccessfulResponse()
    {
        $this->given_targets_with_response_statuses(500, 200);
        $this->when_request_is_dispatched();
        $this->then_requests_should_be_dispatched_to_targets(0, 1);
        $this->and_response_should_be_returned_from_target(1);
    }

    public function testSendsRequestsToAllTargets()
    {
        $this->given_targets_with_response_statuses(200, 500, 200);
        $this->when_request_is_dispatched();
        $this->then_requests_should_be_dispatched_to_targets(0, 1, 2);
        $this->and_response_should_be_returned_from_target(0);
    }

    public function testStopOnFirstSuccessStrategyWithSuccess()
    {
        $this->given_dispatch_strategy(new StopOnFirstSuccess());
        $this->given_targets_with_response_statuses(404, 200, 500, 200);
        $this->when_request_is_dispatched();
        $this->then_requests_should_be_dispatched_to_targets(0, 1);
        $this->and_response_should_be_returned_from_target(1);
    }

    public function testStopOnFirstSuccessStrategyWithoutSuccess()
    {
        $this->given_dispatch_strategy(new StopOnFirstSuccess());
        $this->given_targets_with_response_statuses(500, 500);
        $this->when_request_is_dispatched();
        $this->then_requests_should_be_dispatched_to_targets(0, 1);
        $this->and_response_should_be_returned_from_target(1);
    }

    public function testReturnsLastResponseIfNoSuccess()
    {
        $this->given_targets_with_response_statuses(400, 401, 403, 404, 405, 500, 503, 504);
        $this->when_request_is_dispatched();
        $this->then_requests_should_be_dispatched_to_targets(0, 1, 2, 3, 4, 5, 6, 7);
        $this->and_response_should_be_returned_from_target(7);
    }

    public function testFollowsRedirects()
    {
        $this->given_first_response_is_redirect(301);
        $this->given_targets_with_response_statuses(200, 200);
        $this->when_request_is_dispatched();
        $this->then_requests_should_be_dispatched_to_targets(0, 0, 1);
        $this->and_response_should_be_returned_from_target(0);
    }

    /**
     * @dataProvider successfulStatusCodes
     */
    public function testAcceptsAll2xxStatusCodes(int $successfulStatusCode)
    {
        $this->given_targets_with_response_statuses($successfulStatusCode, 200);
        $this->when_request_is_dispatched();
        $this->then_requests_should_be_dispatched_to_targets(0, 1);
        $this->and_response_should_be_returned_from_target(0);
    }

    public function testSetsBasicAuthHeaders()
    {
        $basicAuth = 'janedoe:password123';
        $this->given_targets_with_config(['uri' => 'https://target1.example.com', 'basic-auth' => $basicAuth]);
        $this->when_request_is_dispatched();
        $this->then_request_should_contain_headers(0, ['Authorization' => 'Basic ' . base64_encode($basicAuth)]);
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function given_dispatch_strategy(DispatchStrategy $strategy)
    {
        $this->strategy = $strategy;
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function given_targets_with_response_statuses(int ...$responseStatuses): void
    {
        $this->targetBaseUrls = array_map(
            function ($i) {
                return "https://target{$i}.example.com/";
            },
            range(1, count($responseStatuses))
        );
        $this->targetObjects = new Targets(
            ...array_map(
                function (string $url) {
                    return new Target(Uri::createFromString($url));
                },
                $this->targetBaseUrls
            )
        );
        $this->targetResponses = array_map(
            function (int $statusCode) {
                return $this->uniqueResponse($statusCode);
            },
            $responseStatuses
        );
        $this->mockHandler->append(...$this->targetResponses);
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function given_targets_with_config(...$targetConfigs): void
    {
        $this->targetBaseUrls = array_map(
            function ($config) {
                return $config['uri'];
            },
            $targetConfigs
        );
        $this->targetObjects = Targets::fromConfig($targetConfigs);
        $this->targetResponses = array_map(
            function ($config) {
                return $this->uniqueResponse(200);
            },
            $targetConfigs
        );
        $this->mockHandler->append(...$this->targetResponses);
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function when_request_is_dispatched(
        $method = self::DEFAULT_REQUEST_METHOD,
        $requestPath = self::DEFAULT_REQUEST_PATH
    ) {
        $dispatcher = new Dispatcher(
            $this->client,
            $this->targetObjects,
            $this->strategy
        );
        $response = $dispatcher->dispatch(
            new SlimRequest(
                $method,
                Uri::createFromString(self::PROXY_ENDPOINT . $requestPath),
                new Headers(),
                [],
                [],
                new RequestBody()
            ),
            new SlimResponse(),
            $requestPath
        );
        $this->response = $response;
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function then_requests_should_be_dispatched_to_targets(int ...$requestedTargets): void
    {
        $this->assertCount(
            count($requestedTargets),
            $this->historyContainer,
            'Expected responses from targets ' . implode(',', $requestedTargets)
        );
        foreach ($requestedTargets as $requestIndex) {
            /** @var ServerRequest $request */
            $request = $this->historyContainer[$requestIndex + $this->requestIndexOffset]['request'];
            $this->assertEquals(
                $this->targetBaseUrls[$requestIndex] . self::DEFAULT_REQUEST_PATH,
                (string)$request->getUri()
            );
        }
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function and_response_should_be_returned_from_target(
        $targetIndex,
        $requestPath = self::DEFAULT_REQUEST_PATH
    ): void {
        $this->assertEquals(
            $this->targetResponses[$targetIndex]->withHeader(
                'X-Response-From',
                $this->targetBaseUrls[$targetIndex] . $requestPath
            ),
            $this->response,
            'Expected response from target ' . $targetIndex
        );
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function given_first_response_is_redirect($responseStatus): void
    {
        $this->mockHandler->append(
            $this->uniqueResponse($responseStatus)->withHeader('Location', '/' . self::DEFAULT_REQUEST_PATH)
        );
        $this->requestIndexOffset = 1;
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function then_request_should_contain_headers($requestIndex, $expectedHeaders)
    {
        /** @var ServerRequest $request */
        $request = $this->historyContainer[$requestIndex]['request'];
        foreach ($expectedHeaders as $name => $value) {
            $this->assertEquals(
                $value,
                $request->getHeaderLine($name),
                "Header '$name' should equal '$value'"
            );
        }
    }

    private function uniqueResponse($statusCode): Response
    {
        return new Response($statusCode, ['ETag' => uniqid('', true)]);
    }

    public static function successfulStatusCodes()
    {
        return [
            [201],
            [202],
            [203],
            [204],
            [205],
            [206],
            [207],
            [208],
            [226],
        ];
    }
}
