<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
try{
    $c=addon_config();$host=(string)($c['ssh_host']??'');$port=(int)($c['ssh_port']??22);$user=(string)($c['ssh_user']??'');$password=(string)($c['ssh_password']??'');
    if($host===''||$port<1||$user===''||$password==='')throw new RuntimeException('SSH não configurado no addon.');
    $started=microtime(true);$socket=@fsockopen($host,$port,$errno,$errstr,3);if(!$socket)throw new RuntimeException("Porta SSH sem resposta em $host:$port.");fclose($socket);
    $cmd='SSHPASS='.escapeshellarg($password).' /usr/bin/timeout 10 /usr/bin/sshpass -e /usr/bin/ssh -tt -o PreferredAuthentications=keyboard-interactive,password -o PubkeyAuthentication=no -o StrictHostKeyChecking=accept-new -o ConnectTimeout=4 -o LogLevel=ERROR -p '.$port.' -l '.escapeshellarg($user).' '.escapeshellarg($host).' 2>&1';
    $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Não foi possível iniciar o cliente SSH.');
    fwrite($pipes[0],"screen-length 0 temporary\ndisplay access-user username __mkauth_probe__\nquit\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$out.=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($p);
    if(stripos($out,'locked')!==false||stripos($out,'blocked')!==false)throw new RuntimeException('Usuário ou IP temporariamente bloqueado pelo Huawei.');
    if($code!==0)throw new RuntimeException('Falha na autenticação SSH. Confira usuário, senha e ACL do Huawei.');
    if(stripos($out,'Normal users')===false&&stripos($out,'Total users')===false)throw new RuntimeException('SSH autenticou, mas o usuário não conseguiu executar display access-user.');
    $elapsed=(int)round((microtime(true)-$started)*1000);@unlink('/var/cache/mkauth-huawei-online/ssh-failure');
    json_response(['ok'=>true,'message'=>"SSH OK — $user@$host:$port — comando display access-user permitido — {$elapsed} ms."]);
}catch(Throwable $e){@touch('/var/cache/mkauth-huawei-online/ssh-failure');json_response(['ok'=>false,'error'=>$e->getMessage()],503);}
