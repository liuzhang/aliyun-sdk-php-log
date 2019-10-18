<?php
/**
 * LogHandler class file
 *
 * @author: Liu Zhang <liuzhang@99xs.com>
 * @link: http://c.99xs.com
 * @copyright: Copyright &copy; 2019 HangZhou XiaoShan Technology Co., Ltd
 */

namespace AliyunLog;

use Monolog\Handler\AbstractSyslogHandler;


class LogHandler extends AbstractSyslogHandler
{
    protected $client;
    protected $ident;

    /**
     * @param string $host
     * @param int    $port
     * @param mixed  $facility
     * @param int    $level    The minimum logging level at which this handler will be triggered
     * @param bool   $bubble   Whether the messages that are handled can bubble up the stack or not
     * @param string $ident    Program name or tag for each log message.
     */
    public function __construct($facility = LOG_USER, $level = Logger::DEBUG, $bubble = true, $ident = 'php')
    {
        parent::__construct($facility, $level, $bubble);

        $this->ident = $ident;

        $endpoint = 'http://cn-shenzhen.log.aliyuncs.com';
        $accessKeyId = 'LTAI4Fg2owzdbVDUPvw1xqda';
        $accessKey = 'OSnglJmXiiUeMfDpy4kjzqL9LGdpDM';
        $token = "";

        $this->client = new Client($endpoint, $accessKeyId, $accessKey,$token);
    }

    protected function write(array $record)
    {
        $lines = $this->splitMessageIntoLines($record['formatted']);

        $header = $this->makeCommonSyslogHeader($this->logLevels[$record['level']]);


        $topic = 'TestTopic';

        $project = 'asr-php-user-server';
        $logstore = 'user-info';

        $contents = [];
        $logItem = new LogItem();
        $logItem->setTime(time());
        $logItem->setContents($lines);
        $logitems = array($logItem);
        $request = new PutLogsRequest($project, $logstore,
            $topic, null, $logitems);

        $response = $this->client->putLogs($request);
    }

    public function close()
    {
        $this->client = null;
    }

    private function splitMessageIntoLines($message)
    {
        if (is_array($message)) {
            $message = implode("\n", $message);
        }

        return preg_split('/$\R?^/m', $message, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Make common syslog header (see rfc5424)
     */
    protected function makeCommonSyslogHeader($severity)
    {
        $priority = $severity + $this->facility;

        if (!$pid = getmypid()) {
            $pid = '-';
        }

        if (!$hostname = gethostname()) {
            $hostname = '-';
        }

        return "<$priority>1 " .
            $this->getDateTime() . " " .
            $hostname . " " .
            $this->ident . " " .
            $pid . " - - ";
    }

    protected function getDateTime()
    {
        return date(\DateTime::RFC3339);
    }

}
