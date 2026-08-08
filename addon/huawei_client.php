<?php
declare(strict_types=1);
function huawei_exec(array $c,string $commands):string{
    foreach(['ssh_host','ssh_port','ssh_user','ssh_password']as$k)if(empty($c[$k]))throw new RuntimeException('SSH do Huawei não configurado.');
    $failure=sys_get_temp_dir().'/mkauth-huawei-online-ssh-failure';if(is_file($failure)&&time()-(int)filemtime($failure)<3600)throw new RuntimeException('SSH pausado por segurança após uma falha. Corrija/salve as credenciais ou aguarde 1 hora para evitar bloqueio no Huawei.');
    $cmd='SSHPASS='.escapeshellarg((string)$c['ssh_password']).' /usr/bin/timeout 12 /usr/bin/sshpass -e /usr/bin/ssh -tt -o PreferredAuthentications=keyboard-interactive,password -o PubkeyAuthentication=no -o StrictHostKeyChecking=accept-new -o ConnectTimeout=4 -o LogLevel=ERROR -p '.(int)$c['ssh_port'].' -l '.escapeshellarg((string)$c['ssh_user']).' '.escapeshellarg((string)$c['ssh_host']).' 2>&1';
    $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Falha ao iniciar SSH.');
    fwrite($pipes[0],"screen-length 0 temporary\n".$commands."\nquit\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$out.=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($p);
    if(stripos($out,'locked')!==false||stripos($out,'blocked')!==false){@touch($failure);throw new RuntimeException('Usuário ou IP bloqueado temporariamente pelo Huawei.');}
    if(preg_match('/access denied|authentication failed|configured password was not accepted|permission denied/i',$out)){@touch($failure);throw new RuntimeException('Usuário ou senha SSH recusados. As tentativas foram pausadas por 1 hora.');}
    if(preg_match('/connection refused|no route to host|connection timed out|could not resolve/i',$out)){@touch($failure);throw new RuntimeException('IP ou porta SSH inacessível. As tentativas foram pausadas por 1 hora.');}
    $executed=preg_match('/<[^>]+>/', $out)===1;if($code!==0&&!$executed)throw new RuntimeException('Falha na comunicação SSH com o Huawei.');
    @unlink($failure);return $out;
}
function huawei_value(string $out,string $label):?string{return preg_match('/^\s*'.preg_quote($label,'/').'\s*:\s*(.+?)\s*$/mi',$out,$m)?trim($m[1]):null;}
function huawei_user_id(array $c,string $login):int{
    $cache='/var/cache/mkauth-huawei-online/userid-'.hash('sha256',strtolower($login));$id=is_file($cache)?trim((string)file_get_contents($cache)):'';
    if(!preg_match('/^\d+$/',$id)){$out=huawei_exec($c,'display access-user username '.$login);if(!preg_match('/(?:^|\R)\s*(\d+)\s+'.preg_quote($login,'/').'(?:\s+|\R)/i',$out,$m))throw new RuntimeException('Sessão não localizada no Huawei.');$id=$m[1];@file_put_contents($cache,$id,LOCK_EX);}
    return (int)$id;
}
