<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
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
     * @var Client
     */
    private $client;
    /**
     * @var string[]
     */
    private $targetBaseUrls;
    /**
     * @var Uri[]
     */
    private $targetUriObjects;
    /**
     * @var Response[]
     */
    private $targetResponses;
    /**
     * @var ResponseInterface
     */
    private $response;

    protected function setUp()
    {
        parent::setUp();
        $this->historyContainer = [];
        $this->mockHandler = new MockHandler();
        $handler = HandlerStack::create($this->mockHandler);
        $handler->push(Middleware::history($this->historyContainer));
        $this->client = new Client(['handler' => $handler]);
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

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    private function given_targets_with_response_statuses(int ...$responseStatuses): void
    {
        $this->targetBaseUrls = array_map(
            function ($i) {
                return "https://target{$i}.example.com/";
            },
            range(1, count($responseStatuses))
        );
        $this->targetUriObjects = array_map(
            function (string $url) {
                return Uri::createFromString($url);
            },
            $this->targetBaseUrls
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
    private function when_request_is_dispatched(
        $method = self::DEFAULT_REQUEST_METHOD,
        $requestPath = self::DEFAULT_REQUEST_PATH
    ) {
        $dispatcher = new Dispatcher(
            $this->client,
            ...$this->targetUriObjects
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
        $this->mockHandler->append($this->uniqueResponse($responseStatus)->withHeader('Location', '/'));
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
