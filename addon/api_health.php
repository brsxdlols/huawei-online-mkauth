<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';require __DIR__.'/snmp_client.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
try{
    $c=addon_config();$h=(string)($c['snmp_host']??'');$started=microtime(true);$result=snmp_run($c,'1.3.6.1.2.1.1.3.0');$elapsed=(int)round((microtime(true)-$started)*1000);
    if(!$result['ok'])throw new RuntimeException('Huawei '.$h.' não respondeu via SNMP '.strtoupper($result['version']).'. Verifique credenciais, versão e ACL.');
    json_response(['ok'=>true,'message'=>'SNMP '.strtoupper($result['version']).' respondendo em '.$h.' — '.$elapsed.' ms.','version'=>$result['version']]);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],500);}
