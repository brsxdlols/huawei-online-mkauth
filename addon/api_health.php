<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
try{
    $c=addon_config();$h=(string)($c['snmp_host']??$c['nas_ip']??'');$k=(string)($c['snmp_community']??'');
    if(!filter_var($h,FILTER_VALIDATE_IP)||$k==='')throw new RuntimeException('SNMP não configurado no addon.');
    $binary=is_executable('/usr/bin/snmpget')?'/usr/bin/snmpget':(is_executable('/usr/local/bin/snmpget')?'/usr/local/bin/snmpget':'');
    if($binary==='')throw new RuntimeException('Comando snmpget não está instalado no MK-AUTH.');
    $started=microtime(true);$cmd=sprintf('%s -v2c -c %s -t 3 -r 1 %s 1.3.6.1.2.1.1.3.0 2>&1',escapeshellcmd($binary),escapeshellarg($k),escapeshellarg($h));
    $out=[];$code=1;exec($cmd,$out,$code);$elapsed=(int)round((microtime(true)-$started)*1000);
    if($code!==0)throw new RuntimeException('Huawei '.$h.' não respondeu (timeout ou comunidade incorreta).');
    json_response(['ok'=>true,'message'=>'SNMP respondendo em '.$h.' — '.$elapsed.' ms.']);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],500);}
