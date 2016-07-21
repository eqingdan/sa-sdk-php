<?php

namespace EQingdan\SensorsAnalytics\Consumers;

class BatchConsumer extends AbstractConsumer
{

    private $buffers;
    private $maxSize;
    private $urlPrefix;
    private $requestTimeout;

    /**
     * @param string $urlPrefix   服务器的 URL 地址。
     * @param int $maxSize        批量发送的阈值。
     * @param int $requestTimeout 请求服务器的超时时间，单位毫秒。
     */
    public function __construct($urlPrefix, $maxSize = 50, $requestTimeout = 1000)
    {
        $this->buffers = array();
        $this->maxSize = $maxSize;
        $this->urlPrefix = $urlPrefix;
        $this->requestTimeout = $requestTimeout;
    }

    public function send($msg)
    {
        $this->buffers[] = $msg;
        if (count($this->buffers) >= $this->maxSize) {
            return $this->flush();
        }

        return true;
    }

    public function flush()
    {
        $ret = $this->doRequest(array(
            "data_list" => $this->encodeMsgList($this->buffers),
            "gzip" => 1,
        ));
        if ($ret) {
            $this->buffers = array();
        }

        return $ret;
    }

    /**
     * 发送数据包给远程服务器。
     *
     * @param array $data
     *
     * @return bool 请求是否成功
     */
    protected function doRequest($data)
    {
        $params = array();
        foreach ($data as $key => $value) {
            $params[] = $key . '=' . urlencode($value);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->urlPrefix);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->requestTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->requestTimeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $params));
        curl_setopt($ch, CURLOPT_USERAGENT, "PHP SDK");
        $ret = curl_exec($ch);

        if (false === $ret) {
            curl_close($ch);

            return false;
        } else {
            curl_close($ch);

            return true;
        }
    }

    /**
     * 对待发送的数据进行编码
     *
     * @param string $msg_list
     *
     * @return string
     */
    private function encodeMsgList($msg_list)
    {
        return base64_encode($this->gzipString("[" . implode(",", $msg_list) . "]"));
    }

    /**
     * GZIP 压缩一个字符串
     *
     * @param string $data
     *
     * @return string
     */
    private function gzipString($data)
    {
        return gzencode($data);
    }

    public function close()
    {
        return $this->flush();
    }
}
