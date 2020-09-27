<?php

namespace Dleno\AliYunAcm;

define('DEFAULT_PORT', 8080);

/**
 * Class ACMClient
 * The basic client to manage ACM
 */
class ACMClient
{

    protected $accessKey;

    protected $secretKey;

    protected $endPoint;

    protected $nameSpace;

    protected $port;

    protected $appName;

    public $serverList = array();

    public function __construct($endpoint, $port)
    {
        $this->endPoint = $endpoint;
        $this->port = $port;
    }

    /**
     * @param mixed $accessKey
     */
    public function setAccessKey($accessKey)
    {
        $this->accessKey = $accessKey;
    }

    /**
     * @param mixed $secretKey
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @param mixed $nameSpace
     */
    public function setNameSpace($nameSpace)
    {
        $this->nameSpace = $nameSpace;
    }

    /**
     * @param mixed $appName
     */
    public function setAppName($appName)
    {
        $this->appName = $appName;
    }

    private function getServerListStr()
    {
        $serverHost = str_replace(array('host', 'port'), array($this->endPoint, $this->port),
            'http://host:port/diamond-server/diamond');
        $request = new RequestCore();
        $request->setRequestUrl($serverHost);
        $request->sendRequest(true);
        if ($request->getResponseCode() != '200') {
            print '[getServerList] got invalid http response: (' . $serverHost . '.';
        }
        $serverRawList = $request->getResponseBody();
        return $serverRawList;
    }

    public function refreshServerList()
    {
        $this->serverList = array();
        $serverRawList = $this->getServerListStr();
        if (is_string($serverRawList)) {
            $serverArray = explode("\n", $serverRawList);
            $serverArray = array_filter($serverArray);
            foreach ($serverArray as $value) {
                $value = trim($value);
                $singleServerList = explode(':', $value);
                $singleServer = null;
                if (count($singleServerList) == 1) {
                    $singleServer = new Model\Server($value,
                        constant('DEFAULT_PORT'),
                        Util::isIpv4($value));
                } else {
                    $singleServer = new Model\Server($singleServerList[0],
                        $singleServerList[1],
                        Util::isIpv4($value));
                }
                $this->serverList[$singleServer->url] = $singleServer;
            }
        }
    }

    public function getServerList()
    {
        return $this->serverList;
    }

    public function getConfig($dataId, $group)
    {
        if (!is_string($this->secretKey) ||
            !is_string($this->accessKey)) {
            throw new Exception\ACMException ('Invalid auth string', "invalid auth info for dataId: $dataId");
        }

        Util::checkDataId($dataId);
        $group = Util::checkGroup($group);

        $servers = $this->serverList;
        $singleServer = $servers[array_rand($servers)];

        $acmHost = str_replace(array('host', 'port'), array($singleServer->url, $singleServer->port),
            'http://host:port/diamond-server/config.co');

        $acmHost .= "?dataId=" . urlencode($dataId) . "&group=" . urlencode($group)
            . "&tenant=" . urlencode($this->nameSpace);

        $request = new RequestCore();
        $request->setRequestUrl($acmHost);

        $headers = $this->getCommonHeaders($group);

        foreach ($headers as $headerKey => $headerWal) {
            $request->addHeader($headerKey, $headerWal);
        }

        $request->sendRequest(true);
        if ($request->getResponseCode() != '200') {
            //print '[GETCONFIG] got invalid http response: (' . $acmHost . '.';
            //throw new Exception\ACMException ('[GETCONFIG] got invalid http response: (' . $acmHost . '.');
            return '';
        }
        $rawData = $request->getResponseBody();
        return $rawData;
    }

    public function publishConfig($dataId, $group, $content)
    {
        if (!is_string($this->secretKey) || !is_string($this->accessKey)) {
            throw new Exception\ACMException ('Invalid auth string', "invalid auth info for dataId: $dataId");
        }

        Util::checkDataId($dataId);
        $group = Util::checkGroup($group);

        $servers = $this->serverList;
        $singleServer = $servers[array_rand($servers)];

        $acmHost = str_replace(array('host', 'port'), array($singleServer->url, $singleServer->port),
            'http://host:port/diamond-server/basestone.do?method=syncUpdateAll');

        $acmBody = "dataId=" . urlencode($dataId) . "&group=" . urlencode($group)
            . "&tenant=" . urlencode($this->nameSpace)
            . "&content=" . urlencode($content);
        if (is_string($this->appName)) {
            $acmBody .= "&appName=" . $this->appName;
        }

        $request = new RequestCore();
        $request->setBody($acmBody);
        $request->setRequestUrl($acmHost);

        $headers = $this->getCommonHeaders($group);

        foreach ($headers as $headerKey => $headerWal) {
            $request->addHeader($headerKey, $headerWal);
        }
        $request->setMethod("post");
        $request->sendRequest(true);
        if ($request->getResponseCode() != '200') {
            print '[PUBLISHCONFIG] got invalid http response: (' . $acmHost . '#' . $request->getResponseCode();
        }
        $rawData = $request->getResponseBody();
        return $rawData;
    }

    public function removeConfig($dataId, $group)
    {
        if (!is_string($this->secretKey) || !is_string($this->accessKey)) {
            throw new Exception\ACMException ('Invalid auth string', "invalid auth info for dataId: $dataId");
        }

        Util::checkDataId($dataId);
        $group = Util::checkGroup($group);

        $servers = $this->serverList;
        $singleServer = $servers[array_rand($servers)];

        $acmHost = str_replace(array('host', 'port'), array($singleServer->url, $singleServer->port),
            'http://host:port/diamond-server//datum.do?method=deleteAllDatums');

        $acmBody = "dataId=" . urlencode($dataId) . "&group=" . urlencode($group)
            . "&tenant=" . urlencode($this->nameSpace);

        $request = new RequestCore();
        $request->setBody($acmBody);
        $request->setRequestUrl($acmHost);

        $headers = $this->getCommonHeaders($group);

        foreach ($headers as $headerKey => $headerWal) {
            $request->addHeader($headerKey, $headerWal);
        }
        $request->setMethod("post");
        $request->sendRequest(true);
        if ($request->getResponseCode() != '200') {
            print '[REMOVECONFIG] got invalid http response: (' . $acmHost . '#' . $request->getResponseCode();
        }
        $rawData = $request->getResponseBody();
        return $rawData;
    }

    private function getCommonHeaders($group)
    {
        $headers = array();
        $headers['Diamond-Client-AppName'] = 'ACM-SDK-PHP';
        $headers['Client-Version'] = '0.0.1';
        $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
        $headers['exConfigInfo'] = 'true';
        $headers['Spas-AccessKey'] = $this->accessKey;

        $ts = round(microtime(true) * 1000);
        $headers['timeStamp'] = $ts;

        $signStr = $this->nameSpace . '+';
        if (is_string($group)) {
            $signStr .= $group . "+";
        }
        $signStr = $signStr . $ts;
        $headers['Spas-Signature'] = base64_encode(hash_hmac('sha1', $signStr, $this->secretKey, true));
        return $headers;
    }

}