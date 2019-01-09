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
        logger()->debug(sprintf("HttpClient开始以{%s}请求{%s}", $method, $uri));
        $begin = microtime(true);
        try {
            $response = parent::request($method, $uri, $options);
            $duration = microtime(true) - $begin;
            logger()->info(sprintf("HttpClient完成{%.06f秒}", $duration));
            return $response;
        } catch(\Throwable $e) {
            $duration = microtime(true) - $begin;
            logger()->error(sprintf("HttpClient失败{%.06f秒} - %s", $duration, $e->getMessage()));
            throw $e;
        }
    }
}
