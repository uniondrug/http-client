<?php

namespace Uniondrug\HttpClient;

use Phalcon\Di\ServiceProviderInterface;

class HttpClientServiceProvider implements ServiceProviderInterface
{
    public function register(\Phalcon\DiInterface $di)
    {
        $di->setShared(
            'httpClient',
            function () {
                return new Client();
            }
        );
    }
}
