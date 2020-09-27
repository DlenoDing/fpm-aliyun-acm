<?php
/**
 * Copyright (C) Alibaba Cloud Computing
 * All rights reserved
 */
require_once realpath(dirname(__FILE__) . '/autoload.php');



$client = new \Dleno\AliYunAcm\ACMClient('acm.aliyun.com','8080');
$resp = $client->getServerList();
$client->refreshServerList();

$client->setNameSpace('namespace');
$client->setAccessKey('accesskey');
$client->setSecretKey('secretkey');
$client->setAppName("appname");

echo $client->getConfig('test.test',null)."\n";

$client->publishConfig('test.test',null,"{\"test\":\"asdfasdfasdf\"}")."\n";

$ts = round(microtime(true) * 1000);
$client->publishConfig('test'.$ts,null,"{\"test\":\"asdfasdfasdf\"}")."\n";

$client->removeConfig('test.test',null);

var_dump(array_values($client->getServerList())[0]);

echo "hello world";

echo strval(\Dleno\AliYunAcm\Util::isValid('data_id'));

echo strval(\Dleno\AliYunAcm\Util::isValid('data*id'));

