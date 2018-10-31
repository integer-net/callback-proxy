<?php
declare(strict_types=1);

namespace IntegerNet\CallbackProxy;

use PHPUnit\Framework\TestCase;
use Slim\Http\Uri;

class TargetsTest extends TestCase
{
    public function testCanBeInstantiatedFromSimpleConfig()
    {
        $config = [
            'https://dev1.example.com/paypal/',
            'https://dev2.example.com/paypal/',
        ];
        $targets = Targets::fromConfig($config);
        $this->assertEquals(
            [
                new Target(Uri::createFromString($config[0])),
                new Target(Uri::createFromString($config[1]))
            ],
            \iterator_to_array($targets)
        );
    }
    public function testCanBeInstantiatedFromAdvancedConfig()
    {
        $config = [
            ['uri' => 'https://dev1.example.com/paypal/', 'basic-auth' => 'johndoe:password123'],
            'https://dev2.example.com/paypal/',
        ];
        $targets = Targets::fromConfig($config);
        $this->assertEquals(
            [
                new Target(
                    Uri::createFromString($config[0]['uri']),
                    ['Authorization' => 'Basic ' . base64_encode($config[0]['basic-auth'])]
                ),
                new Target(Uri::createFromString($config[1]))
            ],
            \iterator_to_array($targets)
        );
    }
    public function testFailsToInstantiateFromInvalidConfig()
    {
        $this->expectException(\InvalidArgumentException::class);
        $configWithMissingUriKey = [
            ['url' => 'https://dev1.example.com/paypal/']
        ];
        Targets::fromConfig(
            $configWithMissingUriKey
        );
    }
}
