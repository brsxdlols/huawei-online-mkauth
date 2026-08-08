<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
$action=$_SERVER['REQUEST_METHOD']==='POST'?(string)($_POST['action']??'status'):'status';
try{
 if($action==='apply')require_csrf((string)($_POST['csrf']??''));elseif($action!=='status')throw new RuntimeException('Ação inválida.');
 $cmd='/usr/bin/sudo -n /usr/local/sbin/mkauth-huawei-patch-manager '.escapeshellarg($action).' 2>&1';$lines=[];$rc=1;exec($cmd,$lines,$rc);$values=[];
 foreach($lines as $line){if(preg_match('/^([A-Z_]+)=(.*)$/',$line,$m))$values[$m[1]]=$m[2];}
 if($rc!==0)throw new RuntimeException($values['ERRO']??implode(' ',array_slice($lines,-3))?:'Falha ao executar o gerenciador do patch.');
 $macOk=($values['MAC_INSERT']??'0')==='1'&&($values['MAC_UPDATE']??'0')==='1'&&($values['MAC_CONFLICTS']??'1')==='0';
 $plansOk=($values['PLAN_MISMATCHES']??'-1')==='0'&&(int)($values['PLAN_INPUT']??0)===(int)($values['PLANS_TOTAL']??-1)&&(int)($values['PLAN_OUTPUT']??0)===(int)($values['PLANS_TOTAL']??-1);
 $cronOk=($values['CRON_ENABLED']??'0')==='1';
 json_response(['ok'=>true,'applied'=>($values['APPLIED']??'0')==='1','mac'=>['ok'=>$macOk,'insert'=>(int)($values['MAC_INSERT']??0),'update'=>(int)($values['MAC_UPDATE']??0),'conflicts'=>(int)($values['MAC_CONFLICTS']??0)],'plans'=>['ok'=>$plansOk,'total'=>(int)($values['PLANS_TOTAL']??0),'input'=>(int)($values['PLAN_INPUT']??0),'output'=>(int)($values['PLAN_OUTPUT']??0),'mismatches'=>(int)($values['PLAN_MISMATCHES']??0)],'automatic'=>['ok'=>$cronOk,'last_run'=>(string)($values['LAST_RUN']??'nunca')],'message'=>$macOk&&$plansOk&&$cronOk?'Patch de planos e MAC aplicado, validado e automático.':'Análise concluída. Existem itens pendentes ou ainda não ativados.']);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],422);}
