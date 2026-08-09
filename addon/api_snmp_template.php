<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(array('ok'=>false,'error'=>'Método não permitido.'),405);
try{
    require_csrf((string)($_POST['csrf']??''));$c=addon_config();$host=(string)($c['snmp_host']??'');
    if(!filter_var($host,FILTER_VALIDATE_IP))throw new RuntimeException('Configure primeiro o IP de gerência do Huawei.');
    $version=(string)($_POST['version']??'2c');if(!in_array($version,array('1','2c','3'),true))throw new RuntimeException('Versão SNMP inválida.');
    $acl=(int)($_POST['acl']??2998);if($acl<2000||$acl>3999)throw new RuntimeException('Use uma ACL Huawei entre 2000 e 3999.');
    $source='';$sock=@stream_socket_client('udp://'.$host.':161',$errno,$errstr,2,STREAM_CLIENT_CONNECT);if(is_resource($sock)){$local=(string)stream_socket_get_name($sock,false);fclose($sock);if(preg_match('/^(.+):\d+$/',$local,$m))$source=trim($m[1],'[]');}
    $source=trim((string)($_POST['source_ip']??$source));if(!filter_var($source,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new RuntimeException('Não foi possível identificar o IP de origem do MK-AUTH. Informe-o manualmente.');
    $lines=array('system-view','snmp-agent','acl number '.$acl,' rule 5 permit source '.$source.' 0','quit');
    if($version==='3'){$user=trim((string)($_POST['v3_user']??''));$auth=trim((string)($_POST['v3_auth']??''));$priv=trim((string)($_POST['v3_priv']??''));if(!preg_match('/^[A-Za-z0-9_.-]{3,32}$/',$user)||strlen($auth)<8||strlen($priv)<8)throw new RuntimeException('No SNMPv3, informe usuário e senhas com pelo menos 8 caracteres.');$lines=array_merge($lines,array('snmp-agent sys-info version v3','snmp-agent mib-view included MKAUTH_VIEW iso','snmp-agent group v3 MKAUTH_GROUP privacy read-view MKAUTH_VIEW acl '.$acl,'snmp-agent usm-user v3 '.$user.' group MKAUTH_GROUP','snmp-agent usm-user v3 '.$user.' authentication-mode sha cipher '.$auth,'snmp-agent usm-user v3 '.$user.' privacy-mode aes128 cipher '.$priv));}
    else{$community=trim((string)($_POST['community']??''));if(strlen($community)<8)throw new RuntimeException('Informe uma community com pelo menos 8 caracteres.');$lines[]='snmp-agent sys-info version v'.$version;$lines[]='snmp-agent community read cipher '.$community.' acl '.$acl;}
    $lines[]='commit';$lines[]='return';json_response(array('ok'=>true,'source_ip'=>$source,'commands'=>implode("\n",$lines),'message'=>'Comandos gerados para liberar somente o IP '.$source.' do MK-AUTH.'));
}catch(Throwable $e){json_response(array('ok'=>false,'error'=>$e->getMessage()),400);}
