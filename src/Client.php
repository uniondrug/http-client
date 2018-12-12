<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-12
 */
namespace Uniondrug\HttpClient;

use Psr\Http\Message\ResponseInterface;

/**
 * Http请求包装
 * @package Uniondrug\HttpClient
 */
class Client extends \GuzzleHttp\Client
{
    /**
     * 发起HTTP请求
     * @param string $method
     * @param string $uri
     * @param array  $options
     * @return ResponseInterface
     * @throws \Throwable
     */
    public function request($method, $uri = '', array $options = [])
    {
        $begin = microtime(true);
        $logs = sprintf("[HttpClient][method=%s][url=%s][begin=%f]", $method, $uri, $begin);
        try {
            $response = parent::request($method, $uri, $options);
            $duration = microtime(true) - $begin;
            logger('trace')->info(sprintf("%s[duration=%.06f] %s", $logs, $duration, json_encode($options, JSON_UNESCAPED_UNICODE)));
            return $response;
        } catch(\Throwable $e) {
            $duration = microtime(true) - $begin;
            logger('trace')->error(sprintf("%s[duration=%.06f] %s", $logs, $duration, $e->getMessage()));
            throw $e;
        }
    }
}
