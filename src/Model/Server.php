<?php
namespace Dleno\AliYunAcm\Model;
/**
 * Class Aliyun_ACM_Model_Server
 * the instance of ACM server
 */
class Server{

    public $url;

    public $port;

    public $isIpv4;

    public function __construct($url, $port, $isIpv4)
    {
        $this->url = $url;
        $this->port = $port;
        $this->isIpv4 = $isIpv4;
    }
}