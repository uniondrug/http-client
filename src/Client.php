<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-10-14
 */
namespace Uniondrug\HttpClient;

use Phalcon\Di;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Uniondrug\Framework\Container;
use Uniondrug\Phar\Server\Logs\Abstracts\Adapter;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XSocket;

/**
 * Http请求包装
 * @package Uniondrug\HttpClient
 */
class Client extends \GuzzleHttp\Client
{
    const SLOW_SECONDS = 0.5;
    const VERSION = '2.4.0';
    /**
     * Server选项
     * @var Adapter|Logger
     */
    private static $logger;
    private static $debugOn = true;
    private static $infoOn = true;
    private static $userAgent;
    /**
     * @var Container
     */
    private static $container;

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
        // 1. prepare
        $error = null;
        $response = null;
        $begin = microtime(true);
        $method = strtoupper($method);
        $this->initLogger();
        $this->initUserAgent();
        // 2. init options
        $options = is_array($options) ? $options : [];
        $options['headers'] = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [];
        if (self::$userAgent !== false) {
            $options['headers']['User-Agent'] = self::$userAgent;
        }
        // 3. request chain
        if (isset($_SERVER['HTTP_REQUEST_ID'])) {
            $options['headers']['REQUEST-ID'] = $_SERVER['HTTP_REQUEST_ID'];
        }
        // 4. begin
        self::$infoOn && self::$logger->info(sprintf("HttpClient以{%s}请求{%s}开始 - %s", $method, $uri, json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        try {
            // 5. call parent request
            $response = parent::request($method, $uri, $options);
            return $response;
        } catch(\Throwable $e) {
            // 6. catch exception
            $error = $e->getMessage();
            throw $e;
        } finally {
            // 7. end request
            $duration = (double) (microtime(true) - $begin);
            // 8. response contents
            if (self::$infoOn && $response !== null) {
                $responseBody = $response->getBody();
                if ($responseBody instanceof StreamInterface) {
                    $contents = $responseBody->getContents();
                    if (strlen($contents) > 3000) {
                        $contents = substr($contents, 0, 1500).''.substr($contents, -1500);
                    }
                    $contents = preg_replace("/[\r|\n|\t]\s*/", "", $contents);
                    self::$logger->info(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}结果 - %s", $duration, $method, $uri, $contents));
                }
            }
            // 9. has error
            if ($error !== null) {
                self::$logger->error(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}出错 - %s", $duration, $method, $uri, $error));
            } else if ($duration >= self::SLOW_SECONDS) {
                self::$logger->warning(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}较慢, 超过{%s}秒阀值", $duration, $method, $uri, self::SLOW_SECONDS));
            }
            // 10. 完成
            self::$infoOn && self::$logger->info(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}完成", $duration, $method, $uri));
        }
    }

    /**
     * 初始化Logger实例
     * @return void
     */
    private function initLogger()
    {
        // 1. initialized
        if (self::$logger !== null) {
            return;
        }
        /**
         * 2. open container
         */
        self::$container = Di::getDefault();
        // 3. with swoole
        if (self::$container->hasSharedInstance('server')) {
            $server = self::$container->getShared('server');
            if (($server instanceof XHttp) || $server instanceof XSocket) {
                self::$logger = $server->getLogger();
                self::$infoOn = self::$logger->infoOn();
                self::$debugOn = self::$logger->debugOn();
                return;
            }
        }
        // 4. with php-fpm
        self::$logger = self::$container->getLogger();
    }

    /**
     * 初始化UserAgent
     */
    private function initUserAgent()
    {
        if (self::$userAgent === null) {
            $appName = self::$container->getConfig()->path('app.appName');
            $appVersion = self::$container->getConfig()->path('app.appVersion');
            self::$userAgent = "HttpClient/".self::VERSION." GuzzleHttp/".parent::VERSION;
            if ($appName !== null && $appVersion !== null) {
                self::$userAgent .= " {$appName}/{$appVersion}";
            }
        }
    }
}
