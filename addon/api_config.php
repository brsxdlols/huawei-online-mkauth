<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $c = addon_config();
    json_response(['ok'=>true,'config'=>[
        'nas_ip'=>(string)($c['nas_ip']??''),
        'snmp_host'=>(string)($c['snmp_host']??''),
        'snmp_version'=>(string)($c['snmp_version']??'2c'),
        'snmp_v3_user'=>(string)($c['snmp_v3_user']??''),
        'snmp_v3_level'=>(string)($c['snmp_v3_level']??'authPriv'),
        'snmp_v3_auth_protocol'=>(string)($c['snmp_v3_auth_protocol']??'SHA'),
        'snmp_v3_priv_protocol'=>(string)($c['snmp_v3_priv_protocol']??'AES'),
        'ssh_host'=>(string)($c['ssh_host']??''),
        'ssh_port'=>(int)($c['ssh_port']??22),
        'ssh_user'=>(string)($c['ssh_user']??''),
        'has_snmp_community'=>!empty($c['snmp_community']),
        'has_snmp_v3_auth_password'=>!empty($c['snmp_v3_auth_password']),
        'has_snmp_v3_priv_password'=>!empty($c['snmp_v3_priv_password']),
        'has_ssh_password'=>!empty($c['ssh_password'])
    ]]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'Método não permitido.'],405);

try {
    require_csrf((string)($_POST['csrf']??''));
    $old=addon_config();
    $nasIp=trim((string)($_POST['nas_ip']??''));
    $snmpHost=trim((string)($_POST['snmp_host']??''));
    $snmpVersion=(string)($_POST['snmp_version']??'2c');
    $sshHost=trim((string)($_POST['ssh_host']??''));
    $sshPort=(int)($_POST['ssh_port']??22);
    $sshUser=trim((string)($_POST['ssh_user']??''));
    foreach ([$nasIp,$snmpHost] as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP)) throw new RuntimeException('Informe NAS-IP-Address e IP SNMP válidos.');
    if($sshHost!==''&&!filter_var($sshHost,FILTER_VALIDATE_IP))throw new RuntimeException('IP SSH inválido.');
    if ($sshPort<1||$sshPort>65535) throw new RuntimeException('Porta SSH inválida.');
    if(strlen($sshUser)>64) throw new RuntimeException('Usuário SSH inválido.');
    if(!in_array($snmpVersion,['1','2c','3'],true))throw new RuntimeException('Versão SNMP inválida.');
    $new=[
        'nas_ip'=>$nasIp,
        'snmp_host'=>$snmpHost,
        'snmp_version'=>$snmpVersion,
        'snmp_community'=>trim((string)($_POST['snmp_community']??''))?:($old['snmp_community']??''),
        'snmp_v3_user'=>trim((string)($_POST['snmp_v3_user']??'')),
        'snmp_v3_level'=>(string)($_POST['snmp_v3_level']??'authPriv'),
        'snmp_v3_auth_protocol'=>(string)($_POST['snmp_v3_auth_protocol']??'SHA'),
        'snmp_v3_auth_password'=>(string)($_POST['snmp_v3_auth_password']??'')!==''?(string)$_POST['snmp_v3_auth_password']:($old['snmp_v3_auth_password']??''),
        'snmp_v3_priv_protocol'=>(string)($_POST['snmp_v3_priv_protocol']??'AES'),
        'snmp_v3_priv_password'=>(string)($_POST['snmp_v3_priv_password']??'')!==''?(string)$_POST['snmp_v3_priv_password']:($old['snmp_v3_priv_password']??''),
        'ssh_host'=>$sshHost,
        'ssh_port'=>$sshPort,
        'ssh_user'=>$sshUser,
        'ssh_password'=>(string)($_POST['ssh_password']??'')!==''?(string)$_POST['ssh_password']:($old['ssh_password']??'')
    ];
    if($snmpVersion!=='3'&&$new['snmp_community']==='')throw new RuntimeException('Community SNMP é obrigatória para v1/v2c.');
    if($snmpVersion==='3'){
        if($new['snmp_v3_user']==='')throw new RuntimeException('Usuário SNMPv3 é obrigatório.');
        if(!in_array($new['snmp_v3_level'],['noAuthNoPriv','authNoPriv','authPriv'],true))throw new RuntimeException('Nível de segurança SNMPv3 inválido.');
        if($new['snmp_v3_level']!=='noAuthNoPriv'&&$new['snmp_v3_auth_password']==='')throw new RuntimeException('Senha de autenticação SNMPv3 é obrigatória.');
        if($new['snmp_v3_level']==='authPriv'&&$new['snmp_v3_priv_password']==='')throw new RuntimeException('Senha de privacidade SNMPv3 é obrigatória.');
    }
    $sshAny=$new['ssh_host']!==''||$new['ssh_user']!==''||$new['ssh_password']!=='';
    if($sshAny&&($new['ssh_host']===''||$new['ssh_user']===''||$new['ssh_password']===''))throw new RuntimeException('Para usar ações administrativas, preencha IP, usuário e senha SSH. Caso contrário, deixe os três vazios.');
    save_addon_config($new);
    @unlink(sys_get_temp_dir().'/mkauth-huawei-online-ssh-failure');
    json_response(['ok'=>true,'message'=>'Configurações salvas no banco do MK-AUTH.']);
} catch(Throwable $e) {
    json_response(['ok'=>false,'error'=>$e->getMessage()],422);
}
