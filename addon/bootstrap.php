<?php
declare(strict_types=1);

include('addons.class.php');
session_name('mka');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['MKA_Logado'])) {
    http_response_code(403);
    exit('Acesso negado. Faça login novamente no MK-AUTH.');
}

require_once('/opt/mk-auth/include/conexao.php');
if (!isset($LOADMYSQL) || !($LOADMYSQL instanceof mysqli)) {
    http_response_code(500);
    exit('Banco do MK-AUTH indisponível.');
}

$db = $LOADMYSQL;
$configFile = '/etc/mkauth-huawei-online/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuração do Huawei Online não encontrada.');
}
$huaweiConfig = require $configFile;
