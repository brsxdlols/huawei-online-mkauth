<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/snmp_client.php';

function realtime_snmp_value(array $config, string $oid): int {
    $result=snmp_run($config,$oid,false);
    if(!$result['ok'])throw new RuntimeException('Falha SNMP no contador individual: '.$result['error']);
    if(!preg_match('/(?:Counter64|INTEGER|Gauge32|Counter32):\s*([0-9]+)/i',$result['output'],$match))throw new RuntimeException('O Huawei não retornou um contador válido.');
    return (int)$match[1];
}
function realtime_subscriber_index(array $config, string $login): int {
    $cacheDir='/var/cache/mkauth-huawei-online';if(!is_dir($cacheDir))@mkdir($cacheDir,0750,true);
    $cache=$cacheDir.'/subscriber-'.sha1(strtolower($login)).'.json';
    if(is_file($cache)&&time()-(int)filemtime($cache)<60){$saved=json_decode((string)file_get_contents($cache),true);if(is_array($saved)&&isset($saved['index']))return (int)$saved['index'];}
    $result=snmp_run($config,'1.3.6.1.4.1.2011.5.2.1.15.1.3',true);
    if(!$result['ok'])throw new RuntimeException('Não foi possível consultar a tabela de assinantes Huawei via SNMP: '.$result['error']);
    foreach(preg_split('/\R/',(string)$result['output']) as $line){
        if(!preg_match('/\.([0-9]+)\s+=\s+(?:STRING|Hex-STRING):\s*"?([^"\r\n]+)"?\s*$/i',trim($line),$match))continue;
        $snmpLogin=trim($match[2]);$baseLogin=preg_replace('/@[^@]+$/','',$snmpLogin);
        if(strcasecmp($snmpLogin,$login)===0||strcasecmp((string)$baseLogin,$login)===0){$index=(int)$match[1];@file_put_contents($cache,json_encode(array('index'=>$index,'login'=>$login)),LOCK_EX);return $index;}
    }
    throw new RuntimeException('Login não localizado na HUAWEI-AAA-MIB. Confirme a conexão neste Huawei e libere a árvore 1.3.6.1.4.1.2011 na view SNMP.');
}
try{
    $login=valid_login((string)($_GET['login']??''));$config=addon_config();$index=realtime_subscriber_index($config,$login);
    $up=realtime_snmp_value($config,'1.3.6.1.4.1.2011.5.2.1.15.1.36.'.$index);
    $down=realtime_snmp_value($config,'1.3.6.1.4.1.2011.5.2.1.15.1.37.'.$index);
    json_response(array('ok'=>true,'source'=>'SNMP HUAWEI-AAA-MIB','sample_ms'=>(int)round(microtime(true)*1000),'up_bytes'=>$up,'down_bytes'=>$down,'subscriber_index'=>$index,'interface'=>'Huawei SNMP · assinante '.$index,'online_time'=>'tempo real'));
}catch(Throwable $e){json_response(array('ok'=>false,'error'=>$e->getMessage()),500);}
