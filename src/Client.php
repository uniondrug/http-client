<?php
/**
 * 封装HttpClient，加入跟踪信息，并且记录日志
 */

namespace Uniondrug\HttpClient;

use Phalcon\Http\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends \GuzzleHttp\Client
{
    /**
     * @param        $method
     * @param string $uri
     * @param array  $options
     *
     * @return mixed|null|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($method, $uri = '', array $options = [])
    {
        /* @var RequestInterface $request */
        $request = app()->getShared('request');
        $service = app()->getConfig()->path('app.appName', '');

        // 1. 提取当前的Trace信息，并且附加在请求头中
        $traceId = $request->getHeader('X-TRACE-ID');
        if (!$traceId) {
            $traceId = app()->getShared('security')->getRandom()->hex(10);
        }
        $options['headers']['X-TRACE-ID'] = $traceId;

        $spanId = $request->getHeader('X-SPAN-ID');
        if (!$spanId) {
            $spanId = app()->getShared('security')->getRandom()->hex(10);
        }
        $options['headers']['X-SPAN-ID'] = $spanId;

        // 2. 发起请求
        $sTime = microtime(1);
        $exception = null;
        $error = '';
        $result = null;
        $statusCode = '';
        try {
            $result = parent::request($method, $uri, $options);
            $statusCode = $result->getStatusCode();
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $exception = $e;
        }
        $rTime = microtime(1);

        // 3. 从响应结果中获取子节点的SPAN_ID
        $childSpanId = '';
        if (null === $exception && null !== $result && ($result instanceof ResponseInterface)) {
            $childSpanId = $result->getHeader('X-SPAN-ID');
            if (is_array($childSpanId)) {
                $childSpanId = implode('; ', $childSpanId);
            }
        }

        // 4. 计算时间
        $time = $rTime - $sTime;

        // 5. LOG
        logger('trace')->debug(sprintf("[HttpClient] service=%s, traceId=%s, spanId=%s, childSpanId=%s, cs=%s, cr=%s, t=%s, method=%s, uri=%s, statusCode=%s, error=%s",
            $service, $traceId, $spanId, $childSpanId, $sTime, $rTime, $time, strtoupper($method), $uri, $statusCode, $error
        ));

        // 6. 发送到中心
        if (config()->path('trace.enable', false)) {
            if (!isset($options['no_trace']) || !$options['no_trace']) {
                try {
                    if (app()->has('traceClient')) {
                        app()->getShared('traceClient')->send([
                            'service'     => $service,
                            'traceId'     => $traceId,
                            'spanId'      => $spanId,
                            'childSpanId' => $childSpanId,
                            'timestamp'   => $sTime,
                            'duration'    => $time,
                            'cs'          => $sTime, // Client Start
                            'cr'          => $rTime, // Client Receive
                            'method'      => strtoupper($method),
                            'uri'         => $uri,
                            'statusCode'  => $statusCode,
                            'error'       => $error,
                        ]);
                    }
                } catch (\Exception $e) {
                    logger('trace')->error(sprintf("[HttpClient] Send to trace server failed: %s", $e->getMessage()));
                }
            }
        }

        // 7. 返回结果
        if ($exception !== null) {
            throw $exception;
        } else {
            return $result;
        }
    }
}
