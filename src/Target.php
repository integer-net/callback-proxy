<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Uri;

class Target
{
    /**
     * @var \Psr\Http\Message\UriInterface
     */
    private $uri;

    /**
     * @var string[]
     */
    private $additionalHeaders;

    public function __construct(\Psr\Http\Message\UriInterface $uri, array $additionalHeaders = [])
    {
        $this->uri = $uri;
        $this->additionalHeaders = $additionalHeaders;
    }

    public static function fromConfig($config): self
    {
        if (is_string($config)) {
            return new self(Uri::createFromString($config));
        }
        if (!isset($config['uri'])) {
            throw new \InvalidArgumentException(
                '$config argument must be a string or an array containing an "uri" element'
            );
        }
        $additionalHeaders = [];
        if (isset($config['basic-auth'])) {
            $additionalHeaders['Authorization'] = 'Basic ' . \base64_encode($config['basic-auth']);
        }
        return new self(Uri::createFromString($config['uri']), $additionalHeaders);
    }

    public function applyToRequest(RequestInterface $request, string $action): RequestInterface
    {
        $request = $request->withUri($this->uri->withPath($this->uri->getPath() . $action));
        foreach ($this->additionalHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function applyToResponse(ResponseInterface $response, string $action): ResponseInterface
    {
        return $response->withHeader(
            'X-Response-From',
            $this->uri->withPath($this->uri->getPath() . $action)->__toString()
        );
    }
}
