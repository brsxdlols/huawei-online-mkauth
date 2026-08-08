<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
try{
    $login=valid_login((string)($_GET['login']??''));$c=addon_config();$nas=(string)($c['nas_ip']??$c['snmp_host']??'');$d=db();$st=$d->prepare("SELECT username,nasportid,acctstarttime,acctupdatetime,acctsessiontime,acctinputoctets,acctoutputoctets FROM radacct WHERE LOWER(username)=LOWER(?) AND nasipaddress=? AND acctstoptime IS NULL ORDER BY radacctid DESC LIMIT 1");$st->bind_param('ss',$login,$nas);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();$d->close();if(!$row)json_response(['ok'=>false,'error'=>'Sessão não localizada no RADIUS.'],404);
    $updated=strtotime((string)$row['acctupdatetime']);if(!$updated||time()-$updated>180)json_response(['ok'=>false,'error'=>'O Huawei não atualiza esta sessão no RADIUS há mais de 3 minutos. Confira o interim-update.'],409);
    $seconds=(int)($row['acctsessiontime']??0);$online=sprintf('%dd %02d:%02d:%02d',intdiv($seconds,86400),intdiv($seconds%86400,3600),intdiv($seconds%3600,60),$seconds%60);
    json_response(['ok'=>true,'source'=>'RADIUS interim-update','sample_ms'=>(int)round(microtime(true)*1000),'up_bytes'=>(int)($row['acctinputoctets']??0),'down_bytes'=>(int)($row['acctoutputoctets']??0),'interface'=>(string)($row['nasportid']??''),'online_time'=>$online,'acct_update'=>(string)$row['acctupdatetime']]);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],500);}
