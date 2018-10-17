# Integer_Net Callback Proxy

This is a service to integrate third party integrations with multiple environments, typically test systems.

It can distribute callbacks to several systems, for example:

    proxy.example.com/paypal/dev/postBack
    
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
            'paypal/dev' => [
                'https://dev1.example.com/paypal/',
                'https://dev2.example.com/paypal/',
            ],
        ],
    ],
    ```
    
    This example routes `/paypal/dev/*` to `https://dev1.example.com/paypal/*` and `https://dev2.example.com/paypal/*`.