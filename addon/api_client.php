<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
try{
 $login=valid_login((string)($_GET['login']??''));$cfg=addon_config();$nas=(string)($cfg['nas_ip']??'');$db=db();
 $sql="SELECT r.username,r.framedipaddress,r.callingstationid,r.nasportid,r.acctstarttime,r.acctupdatetime,r.acctsessiontime,r.acctinputoctets,r.acctoutputoctets,c.uuid_cliente,c.nome,c.plano,c.ramal,c.cli_ativado,c.bloqueado,c.contrato,p.velup,p.veldown,hin.value qos_input,hout.value qos_output FROM radacct r LEFT JOIN sis_cliente c ON LOWER(c.login)=LOWER(r.username) LEFT JOIN sis_plano p ON p.nome=c.plano LEFT JOIN radgroupreply hin ON hin.groupname=c.plano AND hin.attribute='Huawei-Input-Average-Rate' LEFT JOIN radgroupreply hout ON hout.groupname=c.plano AND hout.attribute='Huawei-Output-Average-Rate' WHERE r.nasipaddress=? AND r.acctstoptime IS NULL AND LOWER(r.username)=LOWER(?) ORDER BY r.radacctid DESC LIMIT 1";
 $st=$db->prepare($sql);$st->bind_param('ss',$nas,$login);$st->execute();$r=$st->get_result()->fetch_assoc();if(!$r)json_response(['ok'=>false,'error'=>'Cliente não está online no Huawei.'],404);
 $expectedIn=(int)($r['velup']??0)*1000;$expectedOut=(int)($r['veldown']??0)*1000;$actualIn=(int)($r['qos_input']??0);$actualOut=(int)($r['qos_output']??0);
 $qos=['ok'=>$expectedIn>0&&$expectedOut>0&&$actualIn===$expectedIn&&$actualOut===$expectedOut,'input_bps'=>$actualIn,'output_bps'=>$actualOut,'expected_input_bps'=>$expectedIn,'expected_output_bps'=>$expectedOut,'status'=>'Atributos RADIUS do plano'];
 json_response(['ok'=>true,'client'=>$r,'qos'=>$qos]);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],400);}
