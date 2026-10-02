<?php
declare(strict_types=1);

function session_snmp_column(array $cfg,int $column):array{
    $result=snmp_run($cfg,'1.3.6.1.4.1.2011.5.2.1.15.1.'.$column,true);
    if(!$result['ok'])throw new RuntimeException($result['error']);
    $values=[];
    foreach(preg_split('/\R/',(string)$result['output'])?:[] as $line){
        if(preg_match('/\.([0-9]+)\s+=\s+(.+)$/',trim($line),$m))$values[(int)$m[1]]=trim($m[2]);
    }
    return $values;
}
function session_snmp_text(string $raw):string{
    return preg_match('/^(?:STRING):\s*"?(.*?)"?$/i',$raw,$m)?trim($m[1]):'';
}
function session_snmp_ipv6(string $raw):string{
    if(!preg_match('/Hex-STRING:\s*((?:[0-9A-F]{2}\s*){16})/i',$raw,$m))return'';
    $hex=preg_replace('/\s+/','',$m[1]);
    if($hex===str_repeat('00',16))return'';
    $ip=inet_ntop(pack('H*',$hex));
    return $ip===false?'':$ip;
}
function session_ipv6_map(array $cfg):array{
    $cache='/var/cache/mkauth-huawei-online/session-ipv6.json';
    if(is_file($cache)&&time()-(int)filemtime($cache)<45){
        $saved=json_decode((string)file_get_contents($cache),true);
        if(is_array($saved))return$saved;
    }
    $names=session_snmp_column($cfg,3);$wan=session_snmp_column($cfg,59);$pd=session_snmp_column($cfg,61);$length=session_snmp_column($cfg,62);$map=[];
    foreach($names as$index=>$raw){
        $login=strtolower(session_snmp_text($raw));if($login==='')continue;
        $pdIp=session_snmp_ipv6((string)($pd[$index]??''));$prefix=0;
        if(isset($length[$index])&&preg_match('/INTEGER:\s*(\d+)/i',(string)$length[$index],$m))$prefix=(int)$m[1];
        $map[$login]=['ipv6_address'=>session_snmp_ipv6((string)($wan[$index]??'')),'ipv6_pd_prefix'=>$pdIp!==''&&$prefix>0?$pdIp.'/'.$prefix:''];
    }
    @file_put_contents($cache,json_encode($map,JSON_UNESCAPED_SLASHES),LOCK_EX);
    return$map;
}
function ip_in_prefix(string $ip,string $network,int $bits):bool{
    $ipBin=@inet_pton(trim(explode('/',$ip)[0]));$netBin=@inet_pton($network);
    if($ipBin===false||$netBin===false||strlen($ipBin)!==strlen($netBin))return false;
    $bytes=intdiv($bits,8);$remaining=$bits%8;
    if($bytes>0&&substr($ipBin,0,$bytes)!==substr($netBin,0,$bytes))return false;
    if($remaining===0)return true;
    $mask=(0xff<<(8-$remaining))&0xff;
    return(ord($ipBin[$bytes])&$mask)===(ord($netBin[$bytes])&$mask);
}
function huawei_cut_status(array $row):array{
    $blocked=strtolower((string)($row['bloqueado']??''))==='sim';
    $ipv4=ip_in_prefix((string)($row['framedipaddress']??''),'10.10.14.0',24);
    $ipv6=ip_in_prefix((string)($row['ipv6_address']??''),'2001:dc8:100::',40);
    $pd=ip_in_prefix((string)($row['ipv6_pd_prefix']??''),'2001:db8:900::',40);
    $hasV6=(string)($row['ipv6_address']??'')!==''||(string)($row['ipv6_pd_prefix']??'')!=='';
    $ipv6Ok=$ipv6&&$pd;$ipv6Safe=$ipv6Ok||!$hasV6;
    return[
        'system_blocked'=>$blocked,'ipv4_cut'=>$ipv4,'ipv6_wan_cut'=>$ipv6,'ipv6_pd_cut'=>$pd,
        'ipv6_cut'=>$ipv6Ok,'ipv6_safe'=>$ipv6Safe,'has_ipv6'=>$hasV6,
        'divergent'=>$blocked?(!$ipv4||!$ipv6Safe):($ipv4||$ipv6||$pd)
    ];
}
