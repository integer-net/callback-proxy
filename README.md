# Integer_Net Callback Proxy

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]


This is a service to integrate third party integrations with multiple environments, typically test systems.

It can distribute callbacks to several systems, for example:

    proxy.example.com/paypal-dev/postBack
    
    =>
    
    dev1.example.com/paypal/postBack
    dev2.example.com/paypal/postBack
    dev3.example.com/paypal/postBack
    
The first successful response is returned. If no response was successful (HTTP status code 200), the last response is returned.

If it is used for dev systems, only the proxy must be made accessible from outside by the third party, instead of all target systems.

## Installation

1. Create project via composer
    ```
    composer create-project integer-net/callback-proxy
    ```
2. Set up web server with document root in `public`.

## Configuration

1. Copy [`config.php.sample`](config.php.sample) to `config.php`.
2. Adjust the `proxy/targets` configuration, e.g.:

    ```
    'proxy' => [
        'targets' => [
            'paypal-dev' => [
                'https://dev1.example.com/paypal/',
                'https://dev2.example.com/paypal/',
            ],
        ],
    ],
    ```
    
    This example routes `/paypal-dev/*` to `https://dev1.example.com/paypal/*` and `https://dev2.example.com/paypal/*`.
    
## Advanced Configuration

Instead of a plain URI string, each target can also be configured with additional options:

```
[
  'uri' => 'https://dev1.example.com/paypal/',
  'basic-auth' => 'username:password',
]
```

- **uri** (required) - the base URI
- **basic-auth** - HTTP basic authentication in the form "username:password"
## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
composer test
```

Runs unit tests, mutation tests and static analysis

```
php -S localhost:9000 -t public 
```

Starts the proxy locally on port 9000 for manual testing. Needs a valid configuration in `config.php`. As a generic target URI, you can use `https://httpbin.org/anything/`

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email fs@integer-net.de instead of using the issue tracker.

## Credits

- [Fabian Schmengler][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.txt) for more information.

[ico-version]: https://img.shields.io/packagist/v/integer-net/callback-proxy.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/integer-net/callback-proxy/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/integer-net/callback-proxy.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/integer-net/callback-proxy.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/integer-net/callback-proxy.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/integer-net/callback-proxy
[link-travis]: https://travis-ci.org/integer-net/callback-proxy
[link-scrutinizer]: https://scrutinizer-ci.com/g/integer-net/callback-proxy/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/integer-net/callback-proxy
[link-downloads]: https://packagist.org/packages/integer-net/callback-proxy
[link-author]: https://github.com/schmengler
[link-contributors]: ../../contributors