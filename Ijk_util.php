<?php

define('ERROR_INVALID_PWD', 1403);
define('ERROR_REQUERIY_FIELD', 1404);
define('ERROR_PARA', 1405);
define('ERROR_SYSTEM', 1500);

function keydatas_successRsp($data = "", $msg = "") {
    keydatas_rsp(1,0, $data, $msg);
}

function keydatas_failRsp($code = 0, $data = "", $msg = "") {
    keydatas_rsp(0,$code, $data, $msg);
}

function keydatas_rsp($result = 1,$code = 0, $data = "", $msg = "") {
	die(json_encode(array("rs" => $result, "code" => $code, "data" => $data, "msg" => urlencode($msg))));
}





