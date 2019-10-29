<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-10-28
 */
namespace Uniondrug\HttpClient;

use Phalcon\Di;
use Phalcon\Logger\Adapter;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Server;
use Uniondrug\Framework\Container;
use Uniondrug\Phar\Server\XHttp;

/**
 * Http请求包装
 * @package Uniondrug\HttpClient
 */
class Client extends \GuzzleHttp\Client
{
    const VERSION = '2.4.0';
    const SLOW_SECONDS = 0.5;
    /**
     * @var XHttp
     */
    private static $server;
    private static $serverTrace = false;
    /**
     * @var Container
     */
    private static $container;
    /**
     * @var Adapter
     */
    private static $logger;
    private static $loggerOnDebug = true;
    private static $loggerOnInfo = true;
    private static $loggerOnWarn = true;
    private static $loggerOnError = true;
    /**
     * @var string
     */
    private static $userAgent;

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
        $method = strtoupper($method);
        // 1. init options
        $this->initContainer()->initServer()->initUserAgent()->initLogger();
        // 2. $options
        $options = is_array($options) ? $options : [];
        $options['headers'] = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [];
        $this->initHeaders($options['headers']);
        self::$loggerOnInfo && self::$logger->info(sprintf("HttpClient以{%s}请求{%s}开始 - %s", $method, $uri, json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        // 3. Request Progress
        $error = null;
        try {
            // 4. success
            $response = parent::request($method, $uri, $options);
            return $response;
        } catch(\Throwable $e) {
            // 5. failure
            $error = $e->getMessage();
            throw $e;
        } finally {
            // 6. end request
            $duration = (double) (microtime(true) - $begin);
            // 7. has error
            if ($error !== null) {
                self::$loggerOnError && self::$logger->error(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}出错 - %s", $duration, $method, $uri, $error));
            } else if ($duration >= self::SLOW_SECONDS) {
                self::$loggerOnWarn && self::$logger->warning(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}较慢, 超过{%s}秒阀值", $duration, $method, $uri, self::SLOW_SECONDS));
            }
            // 8. Completed
            self::$loggerOnDebug && self::$logger->debug(sprintf("[d=%.06f]HttpClient以{%s}请求{%s}完成", $duration, $method, $uri));
        }
    }

    /**
     * @return $this
     */
    private function initContainer()
    {
        if (self::$container === null) {
            self::$container = Di::getDefault();
        }
        return $this;
    }

    /**
     * @return $this
     */
    private function initLogger()
    {
        if (self::$logger === null) {
            if (self::$server) {
                self::$logger = self::$server->getLogger();
                self::$loggerOnDebug = self::$logger->debugOn();
                self::$loggerOnInfo = self::$logger->infoOn();
                self::$loggerOnWarn = self::$logger->warningOn();
                self::$loggerOnError = self::$logger->errorOn();
            } else {
                self::$logger = self::$container->getLogger();
            }
        }
        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    private function initHeaders(& $headers)
    {
        // 1. Request Chain
        if (self::$serverTrace) {
            // 1.1 新模式/兼容Java请求链
            //    X-B3-Traceid
            //    X-B3-Spanid
            //    X-B3-Parentspanid
            //    X-B3-Sampled
            $headers = array_merge($headers, self::$server->getTrace()->getAppendTrace());
            $headers['REQUEST-ID'] = self::$server->getTrace()->getRequestId();
        } else {
            // 1.2 兼容HttpClient
            if (isset($_SERVER['HTTP_REQUEST_ID']) && is_string($_SERVER['HTTP_REQUEST_ID']) && $_SERVER['HTTP_REQUEST_ID'] !== '') {
                $headers['REQUEST-ID'] = $_SERVER['HTTP_REQUEST_ID'];
            } else if (isset($_SERVER['REQUEST-ID']) && is_string($_SERVER['REQUEST-ID']) && $_SERVER['REQUEST-ID'] !== '') {
                $headers['REQUEST-ID'] = $_SERVER['REQUEST-ID'];
            }
        }
        // 2. User Agent
        $headers['User-Agent'] = self::$userAgent;
        // 3. nest
        return $this;
    }

    /**
     * Server
     * @return $this
     */
    private function initServer()
    {
        if (self::$server === null) {
            $server = self::$container->getShared('server');
            if ($server instanceof Server) {
                self::$server = $server;
                self::$serverTrace = method_exists($server, 'getTrace');
                return $this;
            }
        }
        self::$server = false;
        return $this;
    }

    /**
     * UA名称
     * @return $this
     */
    private function initUserAgent()
    {
        if (self::$userAgent === null) {
            $appName = (string) self::$container->getConfig()->path('app.appName');
            $appVersion = (string) self::$container->getConfig()->path('app.appVersion');
            self::$userAgent = "HttpClient/".self::VERSION." GuzzleHttp/".parent::VERSION;
            if ($appName !== '' && $appVersion !== '') {
                self::$userAgent .= " ".$appName."/".$appVersion;
            }
        }
        return $this;
    }
}
