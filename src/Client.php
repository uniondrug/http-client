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
        // 1. 请求方式名称统一大写
        $method = strtoupper($method);
        // 2. 请求链透传
        $options['headers'] = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [];
        if (isset($_SERVER['REQUEST-ID']) && is_string($_SERVER['REQUEST-ID']) && $_SERVER['REQUEST-ID'] !== '') {
            $options['headers']['REQUEST-ID'] = $_SERVER['REQUEST-ID'];
        }
        if (isset($_SERVER['HTTP_REQUEST_ID']) && $_SERVER['HTTP_REQUEST_ID'] !== '') {
            $options['headers']['HTTP_REQUEST_ID'] = $_SERVER['HTTP_REQUEST_ID'];
        }
        // 3. 开始CURL请求
        logger()->debug(sprintf("HttpClient以{%s}请求{%s} - %s", $method, $uri, json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        $begin = microtime(true);
        $error = null;
        try {
            // 4. 请求成功
            $response = parent::request($method, $uri, $options);
            return $response;
        } catch(\Throwable $e) {
            // 5. 请求失败
            $error = $e->getMessage();
            throw $e;
        } finally {
            // 6. 请求日志
            if ($error === null) {
                $duration = (double) microtime(true) - $begin;
                logger()->info(sprintf("[%.06f]HttpClient以{%s}请求{%s}完成", $duration, $method, $uri));
                if ($duration > self::CLIENT_SLOW_RESPONSE) {
                    logger()->warning(sprintf("HttpClient以{%s}请求{%s}用时{%.02f}秒, 超过{%.02f}阀值", $method, $uri, $duration, self::CLIENT_SLOW_RESPONSE));
                }
            } else {
                logger()->error(sprintf("HttpClient以{%s}请求{%s}出错 - %s", $method, $uri, $error));
            }
        }
    }
}
