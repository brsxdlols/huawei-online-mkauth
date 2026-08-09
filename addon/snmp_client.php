<?php
declare(strict_types=1);

function snmp_args(array $c,string $binary,string $oid,bool $walk=false):array{
    $host=(string)($c['snmp_host']??'');
    if(!filter_var($host,FILTER_VALIDATE_IP))throw new RuntimeException('IP SNMP não configurado.');
    $version=(string)($c['snmp_version']??'2c');
    $args=[$binary,'-v',$version,'-On','-t','3','-r','1'];
    if($version==='3'){
        $user=(string)($c['snmp_v3_user']??'');$level=(string)($c['snmp_v3_level']??'authPriv');
        if($user===''||!in_array($level,['noAuthNoPriv','authNoPriv','authPriv'],true))throw new RuntimeException('Usuário/nível SNMPv3 não configurado.');
        array_push($args,'-l',$level,'-u',$user);
        if($level!=='noAuthNoPriv'){
            $auth=(string)($c['snmp_v3_auth_password']??'');if($auth==='')throw new RuntimeException('Senha de autenticação SNMPv3 não configurada.');
            array_push($args,'-a',(string)($c['snmp_v3_auth_protocol']??'SHA'),'-A',$auth);
        }
        if($level==='authPriv'){
            $priv=(string)($c['snmp_v3_priv_password']??'');if($priv==='')throw new RuntimeException('Senha de privacidade SNMPv3 não configurada.');
            array_push($args,'-x',(string)($c['snmp_v3_priv_protocol']??'AES'),'-X',$priv);
        }
    }else{
        $community=(string)($c['snmp_community']??'');if($community==='')throw new RuntimeException('Community SNMP não configurada.');
        array_push($args,'-c',$community);
    }
    array_push($args,$host,$oid);return$args;
}

function snmp_run(array $c,string $oid,bool $walk=false):array{
    $name=$walk?'snmpwalk':'snmpget';$binary=is_executable('/usr/bin/'.$name)?'/usr/bin/'.$name:'/usr/local/bin/'.$name;
    if(!is_executable($binary))throw new RuntimeException("Comando $name não instalado no MK-AUTH.");
    $args=snmp_args($c,$binary,$oid,$walk);$spec=[1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($args,$spec,$pipes);
    if(!is_resource($p))throw new RuntimeException('Não foi possível iniciar a consulta SNMP.');
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
    return['ok'=>$code===0,'output'=>trim((string)$out),'error'=>trim((string)$err),'version'=>(string)($c['snmp_version']??'2c')];
}
