<?php
include('./include/xmlrpc-master/src/Xmlrpc.php');

$parser = new XmlrpcEncoder;

$tmp = new \stdClass();
$tmp->scalar = '1234';
$tmp->xmlrpc_type = 'base64';



$resp = array(
    'int'   => (int)1,
    'str'   => 'string',
    'array' => $tmp
);

$a = $parser->encodeResponse($resp);

print_r($a);