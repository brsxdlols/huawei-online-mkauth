<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $c = addon_config();
    json_response(['ok'=>true,'config'=>[
        'nas_ip'=>(string)($c['nas_ip']??''),
        'snmp_host'=>(string)($c['snmp_host']??''),
        'ssh_host'=>(string)($c['ssh_host']??''),
        'ssh_port'=>(int)($c['ssh_port']??22),
        'ssh_user'=>(string)($c['ssh_user']??''),
        'has_snmp_community'=>!empty($c['snmp_community']),
        'has_ssh_password'=>!empty($c['ssh_password'])
    ]]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'Método não permitido.'],405);

try {
    require_csrf((string)($_POST['csrf']??''));
    $old=addon_config();
    $nasIp=trim((string)($_POST['nas_ip']??''));
    $snmpHost=trim((string)($_POST['snmp_host']??''));
    $sshHost=trim((string)($_POST['ssh_host']??''));
    $sshPort=(int)($_POST['ssh_port']??22);
    $sshUser=trim((string)($_POST['ssh_user']??''));
    foreach ([$nasIp,$snmpHost,$sshHost] as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP)) throw new RuntimeException('Informe IPs válidos.');
    if ($sshPort<1||$sshPort>65535) throw new RuntimeException('Porta SSH inválida.');
    if ($sshUser===''||strlen($sshUser)>64) throw new RuntimeException('Usuário SSH inválido.');
    $new=[
        'nas_ip'=>$nasIp,
        'snmp_host'=>$snmpHost,
        'snmp_community'=>trim((string)($_POST['snmp_community']??''))?:($old['snmp_community']??''),
        'ssh_host'=>$sshHost,
        'ssh_port'=>$sshPort,
        'ssh_user'=>$sshUser,
        'ssh_password'=>(string)($_POST['ssh_password']??'')!==''?(string)$_POST['ssh_password']:($old['ssh_password']??'')
    ];
    if ($new['snmp_community']===''||$new['ssh_password']==='') throw new RuntimeException('Comunidade SNMP e senha SSH são obrigatórias.');
    save_addon_config($new);
    @unlink(sys_get_temp_dir().'/mkauth-huawei-online-ssh-failure');
    json_response(['ok'=>true,'message'=>'Configurações salvas no banco do MK-AUTH.']);
} catch(Throwable $e) {
    json_response(['ok'=>false,'error'=>$e->getMessage()],422);
}
