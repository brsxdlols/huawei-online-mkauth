<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
try{
    $cfg=addon_config();$nas=(string)($cfg['nas_ip']??'10.255.255.200');$db=db();
    $sql="SELECT r.username,r.framedipaddress,r.callingstationid,r.nasportid,r.acctstarttime,r.acctupdatetime,r.acctinterval,r.acctsessiontime,r.acctinputoctets,r.acctoutputoctets,IF(r.acctstoptime IS NULL,0,1) AS accounting_recovered,c.uuid_cliente,c.nome,c.plano,c.ramal,c.cli_ativado,c.bloqueado,c.contrato FROM radacct r LEFT JOIN sis_cliente c ON LOWER(c.login)=LOWER(r.username) WHERE r.nasipaddress=? AND (r.acctstoptime IS NULL OR (r.acctterminatecause='Lost-Service' AND r.acctupdatetime>r.acctstoptime AND r.acctupdatetime>=DATE_SUB(NOW(),INTERVAL 70 MINUTE))) ORDER BY r.acctupdatetime DESC,r.radacctid DESC";
    $st=$db->prepare($sql);$st->bind_param('s',$nas);$st->execute();$rs=$st->get_result();$a=array();$seen=array();$recovered=0;
    while($r=$rs->fetch_assoc()){$key=strtolower((string)$r['username']);if(isset($seen[$key]))continue;$seen[$key]=1;if((int)$r['accounting_recovered']===1)$recovered++;$a[]=$r;}
    json_response(array('ok'=>true,'count'=>count($a),'sessions'=>$a,'accounting_recovered'=>$recovered,'accounting_warning'=>$recovered>0?'Há sessão Huawei ainda recebendo updates depois de o MK-AUTH marcá-la como Lost-Service. Ajuste o interim-update no Huawei para 3 minutos.':''));
}catch(Throwable $e){json_response(array('ok'=>false,'error'=>'Falha ao consultar sessões.'),500);}
