<?php
/**
 * PutLogsResponse class file
 *
 * @author: Liu Zhang <liuzhang@99xs.com>
 * @link: http://c.99xs.com
 * @copyright: Copyright &copy; 2019 HangZhou XiaoShan Technology Co., Ltd
 */

namespace AliyunLog;


class PutLogsResponse
{
    /**
     * @var array HTTP response header
     */
    private $headers;

    /**
     *
     * @param array $header
     *            HTTP response header
     */
    public function __construct($headers) {
        $this->headers = $headers;
    }

    /**
     * Get all http headers
     *
     * @return array HTTP response header
     */
    public function getAllHeaders() {
        return $this->headers;
    }

    /**
     * Get specified http header
     *
     * @param string $key
     *            key to get header
     *
     * @return string HTTP response header. '' will be return if not set.
     */
    public function getHeader($key) {
        return isset ($this->headers[$key]) ? $this->headers [$key] : '';
    }

    /**
     * Get the request id of the response. '' will be return if not set.
     *
     * @return string request id
     */
    public function getRequestId() {
        return isset ( $this->headers ['x-log-requestid'] ) ? $this->headers ['x-log-requestid'] : '';
    }
}
