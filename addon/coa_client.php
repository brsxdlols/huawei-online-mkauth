<?php
declare(strict_types=1);

function coa_secret(mysqli $db,string $nas):string{
    $st=$db->prepare('SELECT secret FROM nas WHERE nasname=? OR shortname=? ORDER BY (nasname=?) DESC LIMIT 1');$st->bind_param('sss',$nas,$nas,$nas);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();
    $secret=trim((string)($row['secret']??''));if($secret==='')throw new RuntimeException('Chave RADIUS/CoA não encontrada no ramal NAS do MK-AUTH.');return$secret;
}
function coa_send(string $nas,string $secret,array $attributes):array{
    $binary=is_executable('/usr/bin/radclient')?'/usr/bin/radclient':'/usr/local/bin/radclient';if(!is_executable($binary))throw new RuntimeException('radclient não está instalado no MK-AUTH.');
    $lines=array();foreach($attributes as$name=>$value){if($value===null||$value==='')continue;$clean=str_replace(array("\r","\n",'"'),array('','','\\"'),(string)$value);$lines[]=$name.' = "'.$clean.'"';}$input=implode("\n",$lines)."\n";
    $command=escapeshellarg($binary).' -x -t 3 -r 1 '.escapeshellarg($nas.':3799').' disconnect '.escapeshellarg($secret);$spec=array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w'));$process=proc_open($command,$spec,$pipes);if(!is_resource($process))throw new RuntimeException('Não foi possível iniciar o cliente CoA.');fwrite($pipes[0],$input);fclose($pipes[0]);$out=(string)stream_get_contents($pipes[1]);$err=(string)stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);$all=trim($out."\n".$err);
    return array('ok'=>stripos($all,'Received Disconnect-ACK')!==false,'communicated'=>stripos($all,'Received Disconnect-')!==false,'nak'=>stripos($all,'Received Disconnect-NAK')!==false,'code'=>$code,'output'=>$all);
}
