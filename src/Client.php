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
    const CLIENT_SLOW_RESPONSE = 0.5;

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
        /**
         * Header头透传
         */
        $options['headers'] = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [];
        if (isset($_SERVER['X-REQUESTED-ID']) && $_SERVER['X-REQUESTED-ID'] !== '') {
            $options['headers']['X-Requested-Id'] = $_SERVER['X-REQUESTED-ID'];
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_ID']) && $_SERVER['HTTP_X_REQUESTED_ID'] !== '') {
            $options['headers']['HTTP_X_REQUESTED_ID'] = $_SERVER['HTTP_X_REQUESTED_ID'];
        }
        /**
         * CURL/请求过程
         */
        $begin = microtime(true);
        try {
            $response = parent::request($method, $uri, $options);
            $duration = (double) microtime(true) - $begin;
            logger()->info(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}完成", $duration, $method, $uri));
            if ($duration > self::CLIENT_SLOW_RESPONSE){
                logger()->warning(sprintf("HttpClient以{%s}请求{%s}用时{%.06f}秒, 超过{%.02f}阀值", $method, $uri, $duration, self::CLIENT_SLOW_RESPONSE));
            }
            return $response;
        } catch(\Throwable $e) {
            $duration = (double) microtime(true) - $begin;
            logger()->error(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}出错 - %s", $duration, $method, $uri, $e->getMessage()));
            throw $e;
        }
    }
}
