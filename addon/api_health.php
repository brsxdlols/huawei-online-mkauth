<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';require __DIR__.'/snmp_client.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
try{
    // Valida exatamente a mesma HUAWEI-AAA-MIB usada pelo monitor individual.
    // Algumas views SNMP do BNG permitem os contadores de assinantes, mas não
    // expõem sysUpTime; nesse caso o gráfico funcionava e o teste dava falso erro.
    $c=addon_config();$h=(string)($c['snmp_host']??'');$started=microtime(true);$result=snmp_run($c,'1.3.6.1.4.1.2011.5.2.1.15.1.3',true);$elapsed=(int)round((microtime(true)-$started)*1000);
    if(!$result['ok'])throw new RuntimeException('Huawei '.$h.' não respondeu via SNMP '.strtoupper($result['version']).'. Verifique credenciais, versão e ACL.');
    json_response(['ok'=>true,'message'=>'SNMP '.strtoupper($result['version']).' respondendo em '.$h.' — '.$elapsed.' ms.','version'=>$result['version']]);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],500);}
