<?php
/**
 * Client class file
 *
 * @author: Liu Zhang <liuzhang@99xs.com>
 * @link: http://c.99xs.com
 * @copyright: Copyright &copy; 2019 HangZhou XiaoShan Technology Co., Ltd
 */

namespace AliyunLog;

define('USER_AGENT', 'log-php-sdk-v-0.6.0');

class Client
{
    /**
     * @var string aliyun accessKey
     */
    protected $accessKey;

    /**
     * @var string aliyun accessKeyId
     */
    protected $accessKeyId;

    /**
     *@var string aliyun sts token
     */
    protected $stsToken;

    /**
     * @var string LOG endpoint
     */
    protected $endpoint;

    /**
     * @var string Check if the host if row ip.
     */
    protected $isRowIp;

    /**
     * @var integer Http send port. The dafault value is 80.
     */
    protected $port;

    /**
     * @var string log sever host.
     */
    protected $logHost;

    /**
     * @var string the local machine ip address.
     */
    protected $source;

    /**
     * Aliyun_Log_Client constructor
     *
     * @param string $endpoint
     *            LOG host name, for example, http://cn-hangzhou.sls.aliyuncs.com
     * @param string $accessKeyId
     *            aliyun accessKeyId
     * @param string $accessKey
     *            aliyun accessKey
     */
    public function __construct($endpoint, $accessKeyId, $accessKey, $token = "") {
        $this->setEndpoint ( $endpoint ); // set $this->logHost
        $this->accessKeyId = $accessKeyId;
        $this->accessKey = $accessKey;
        $this->stsToken = $token;
        $this->source = Util::getLocalIp();
    }
    private function setEndpoint($endpoint) {
        $pos = strpos ( $endpoint, "://" );
        if ($pos !== false) { // be careful, !==
            $pos += 3;
            $endpoint = substr ( $endpoint, $pos );
        }
        $pos = strpos ( $endpoint, "/" );
        if ($pos !== false) // be careful, !==
            $endpoint = substr ( $endpoint, 0, $pos );
        $pos = strpos ( $endpoint, ':' );
        if ($pos !== false) { // be careful, !==
            $this->port = ( int ) substr ( $endpoint, $pos + 1 );
            $endpoint = substr ( $endpoint, 0, $pos );
        } else
            $this->port = 80;
        $this->isRowIp = Util::isIp ( $endpoint );
        $this->logHost = $endpoint;
        $this->endpoint = $endpoint . ':' . ( string ) $this->port;
    }

    /**
     * GMT format time string.
     *
     * @return string
     */
    protected function getGMT() {
        return gmdate ( 'D, d M Y H:i:s' ) . ' GMT';
    }


    /**
     * Decodes a JSON string to a JSON Object.
     * Unsuccessful decode will cause an Aliyun_Log_Exception.
     *
     * @return string
     * @throws Aliyun_Log_Exception
     */
    protected function parseToJson($resBody, $requestId) {
        if (! $resBody)
            return NULL;

        $result = json_decode ( $resBody, true );
        if ($result === NULL){
            throw new \Exception ( 'BadResponse', "Bad format,not json: $resBody", $requestId );
        }
        return $result;
    }

    /**
     * @return array
     */
    protected function getHttpResponse($method, $url, $body, $headers) {
        $request = new RequestCore ( $url );
        foreach ( $headers as $key => $value )
            $request->add_header ( $key, $value );
        $request->set_method ( $method );
        $request->set_useragent(USER_AGENT);
        if ($method == "POST" || $method == "PUT")
            $request->set_body ( $body );
        $request->send_request ();
        $response = array ();
        $response [] = ( int ) $request->get_response_code ();
        $response [] = $request->get_response_header ();
        $response [] = $request->get_response_body ();
        return $response;
    }

    /**
     * @return array
     * @throws Exception
     */
    private function sendRequest($method, $url, $body, $headers) {
        try {
            list ( $responseCode, $header, $resBody ) =
                $this->getHttpResponse ( $method, $url, $body, $headers );
        } catch ( \Exception $ex ) {
            throw new \Exception ( $ex->getMessage ());
        }

        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';

        if ($responseCode == 200) {
            return array ($resBody,$header);
        }
        else {
            $exJson = $this->parseToJson ( $resBody, $requestId );
            if (isset($exJson ['error_code']) && isset($exJson ['error_message'])) {
                throw new \Exception ( $exJson ['error_code'],
                    $exJson ['error_message'], $requestId );
            } else {
                if ($exJson) {
                    $exJson = ' The return json is ' . json_encode($exJson);
                } else {
                    $exJson = '';
                }
                throw new \Exception ( 'RequestError',
                    "Request is failed. Http code is $responseCode.$exJson", $requestId );
            }
        }
    }

    /**
     * @return array
     * @throws Aliyun_Log_Exception
     */
    private function send($method, $project, $body, $resource, $params, $headers) {
        if ($body) {
            $headers ['Content-Length'] = strlen ( $body );
            if(isset($headers ["x-log-bodyrawsize"])==false)
                $headers ["x-log-bodyrawsize"] = 0;
            $headers ['Content-MD5'] = Util::calMD5 ( $body );
        } else {
            $headers ['Content-Length'] = 0;
            $headers ["x-log-bodyrawsize"] = 0;
            $headers ['Content-Type'] = ''; // If not set, http request will add automatically.
        }

        $headers ['x-log-apiversion'] = '0.6.0';
        $headers ['x-log-signaturemethod'] = 'hmac-sha1';
        if(strlen($this->stsToken) >0)
            $headers ['x-acs-security-token'] = $this -> stsToken;
        if(is_null($project))$headers ['Host'] = $this->logHost;
        else $headers ['Host'] = "$project.$this->logHost";
        $headers ['Date'] = $this->GetGMT ();
        $signature = Util::getRequestAuthorization ( $method, $resource, $this->accessKey,$this->stsToken, $params, $headers );
        $headers ['Authorization'] = "LOG $this->accessKeyId:$signature";

        $url = $resource;
        if ($params)
            $url .= '?' . Util::urlEncode ( $params );
        if ($this->isRowIp)
            $url = "http://$this->endpoint$url";
        else{
            if(is_null($project))
                $url = "http://$this->endpoint$url";
            else  $url = "http://$project.$this->endpoint$url";
        }
        return $this->sendRequest ( $method, $url, $body, $headers );
    }

    /**
     * Put logs to Log Service.
     * Unsuccessful opertaion will cause an Aliyun_Log_Exception.
     *
     * @param PutLogsRequest $request the PutLogs request parameters class
     * @throws Aliyun_Log_Exception
     * @return PutLogsResponse
     */
    public function putLogs(PutLogsRequest $request) {
        if (count ( $request->getLogitems () ) > 4096)
            throw new \Exception ( 'InvalidLogSize', "logItems' length exceeds maximum limitation: 4096 lines." );

        $logGroup = new LogGroup ();
        $topic = $request->getTopic () !== null ? $request->getTopic () : '';
        $logGroup->setTopic ( $request->getTopic () );
        $source = $request->getSource ();

        if ( ! $source )
            $source = $this->source;
        $logGroup->setSource ( $source );
        $logitems = $request->getLogitems ();
        foreach ( $logitems as $logItem ) {
            $log = new Log ();
            $log->setTime ( $logItem->getTime () );
            $content = $logItem->getContents ();
            foreach ( $content as $key => $value ) {
                $content = new LogContent ();
                $content->setKey ( $key );
                $content->setValue ( $value );
                $log->addContents ( $content );
            }

            $logGroup->addLogs ( $log );
        }

        $body = Util::toBytes( $logGroup );
        unset ( $logGroup );

        $bodySize = strlen ( $body );
        if ($bodySize > 3 * 1024 * 1024) // 3 MB
            throw new \Exception ( 'InvalidLogSize', "logItems' size exceeds maximum limitation: 3 MB." );
        $params = array ();
        $headers = array ();
        $headers ["x-log-bodyrawsize"] = $bodySize;
        $headers ['x-log-compresstype'] = 'deflate';
        $headers ['Content-Type'] = 'application/x-protobuf';
        $body = gzcompress ( $body, 6 );

        $logstore = $request->getLogstore () !== null ? $request->getLogstore () : '';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $shardKey = $request -> getShardKey();
        $resource = "/logstores/" . $logstore.($shardKey== null?"/shards/lb":"/shards/route");
        if($shardKey)
            $params["key"]=$shardKey;
        list ( $resp, $header ) = $this->send ( "POST", $project, $body, $resource, $params, $headers );
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new PutLogsResponse( $header );
    }
}
