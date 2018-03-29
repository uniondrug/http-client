<?php
/**
 * 封装HttpClient，加入跟踪信息，并且记录日志
 */

namespace Uniondrug\HttpClient;

use GuzzleHttp\Psr7\Response;
use Phalcon\Di;
use Phalcon\Http\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends \GuzzleHttp\Client
{
    public function request($method, $uri = '', array $options = [])
    {
        /* @var RequestInterface $request */
        $request = Di::getDefault()->getShared('request');

        // 1. 提取当前的Trace信息，并且附加在请求头中
        $traceId = $request->getHeader('X-TRACE-ID');
        if ($traceId) {
            $options['headers']['X-TRACE-ID'] = $traceId;
        } else {
            $traceId = '';
        }

        $spanId = $request->getHeader('X-SPAN-ID');
        if ($spanId) {
            $options['headers']['X-SPAN-ID'] = $spanId;
        } else {
            $spanId = '';
        }

        // 2. 发起请求
        $sTime = microtime(1);
        $exception = null;
        $error = '';
        $result = null;
        try {
            $result = parent::request($method, $uri, $options);
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $exception = $e;
        }
        $rTime = microtime(1);

        // 3. 从响应结果中获取子节点的SPAN_ID
        if (null === $exception && null !== $result && ($result instanceof ResponseInterface)) {
            $childSpanId = $result->getHeader('X-SPAN-ID');
            if (!$childSpanId) {
                $childSpanId = '';
            } else {
                $childSpanId = implode('; ', $childSpanId);
            }
        }

        // 4. 计算时间
        $time = $rTime - $sTime;

        // 5. LOG
        Di::getDefault()->getLogger('trace')->debug(sprintf("[HttpClient] traceId=%s, spanId=%s, childSpanId=%s, ss=%s, sr=%s, t=%s, uri=%s, error=%s",
            $traceId, $spanId, $childSpanId, $sTime, $rTime, $time, $uri, $error
        ));

        // 6. 发送到中心
        try {
            if (Di::getDefault()->has('traceClient')) {
                Di::getDefault()->getShared('traceClient')->send([
                    'traceId'     => $traceId,
                    'childSpanId' => $childSpanId,
                    'spanId'      => $spanId,
                    'timestamp'   => $sTime,
                    'duration'    => $time,
                    'cs'          => $sTime,
                    'cr'          => $rTime,
                    'uri'         => $uri,
                    'error'       => $error,
                ]);
            }
        } catch (\Exception $e) {
            Di::getDefault()->getLogger('trace')->error(sprintf("[HttpClient] Send to trace server failed: %s", $e->getMessage()));
        }

        // 7. 返回结果
        if ($exception !== null) {
            throw $exception;
        } else {
            return $result;
        }
    }
}
