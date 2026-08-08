<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';require __DIR__.'/huawei_client.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
try{
    $c=addon_config();$host=(string)($c['ssh_host']??'');$port=(int)($c['ssh_port']??22);$user=(string)($c['ssh_user']??'');$password=(string)($c['ssh_password']??'');
    if($host===''||$port<1||$user===''||$password==='')throw new RuntimeException('SSH não configurado no addon.');
    $started=microtime(true);$out=huawei_exec($c,'display access-user username __mkauth_probe__');
    if(stripos($out,'Normal users')===false&&stripos($out,'Total users')===false)throw new RuntimeException('SSH autenticou, mas o usuário não conseguiu executar display access-user.');
    $elapsed=(int)round((microtime(true)-$started)*1000);
    json_response(['ok'=>true,'message'=>"SSH OK — $user@$host:$port — comando display access-user permitido — {$elapsed} ms."]);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],503);}
