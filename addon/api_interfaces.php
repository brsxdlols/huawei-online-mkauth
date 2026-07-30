<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function snmpWalkNumeric(string $host, string $community, string $oid): array
{
    $cmd = ['/usr/bin/snmpwalk','-v2c','-c',$community,'-On','-t','2','-r','1',$host,$oid];
    $spec = [1=>['pipe','w'],2=>['pipe','w']];
    $proc = proc_open($cmd,$spec,$pipes);
    if (!is_resource($proc)) return [];
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $values = [];
    foreach (preg_split('/\R/',trim((string)$stdout)) as $line) {
        if (!preg_match('/\.([0-9]+)\s+=\s+(?:STRING:\s+"(.*)"|Counter64:\s+([0-9]+)|INTEGER:\s+(?:[^(]+\()?([0-9]+)\)?)$/',$line,$m)) continue;
        $values[(int)$m[1]] = ($m[2] ?? '') !== '' ? $m[2] : (int)(($m[3] ?? '') !== '' ? $m[3] : $m[4]);
    }
    return $values;
}

$host = (string)$huaweiConfig['snmp_host'];
$community = (string)$huaweiConfig['snmp_community'];
$names = snmpWalkNumeric($host,$community,'1.3.6.1.2.1.31.1.1.1.1');
$in = snmpWalkNumeric($host,$community,'1.3.6.1.2.1.31.1.1.1.6');
$out = snmpWalkNumeric($host,$community,'1.3.6.1.2.1.31.1.1.1.10');
$oper = snmpWalkNumeric($host,$community,'1.3.6.1.2.1.2.2.1.8');
$now = microtime(true);
$cacheFile = '/var/cache/mkauth-huawei-online/interfaces.json';
$previous = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile),true) : [];
$current = ['time'=>$now,'in'=>$in,'out'=>$out];
$tmp = $cacheFile.'.'.getmypid();
file_put_contents($tmp,json_encode($current),LOCK_EX);
rename($tmp,$cacheFile);
$elapsed = isset($previous['time']) ? max(0.001,$now-(float)$previous['time']) : 0;
$interfaces = [];
foreach ($names as $idx=>$name) {
    $rx = $tx = null;
    if ($elapsed > 0 && isset($previous['in'][$idx],$previous['out'][$idx],$in[$idx],$out[$idx])) {
        $di = (int)$in[$idx]-(int)$previous['in'][$idx];
        $do = (int)$out[$idx]-(int)$previous['out'][$idx];
        if ($di >= 0 && $do >= 0) {
            $rx = $di*8/$elapsed;
            $tx = $do*8/$elapsed;
        }
    }
    $interfaces[] = [
        'index'=>$idx,'name'=>$name,'up'=>(int)($oper[$idx]??0)===1,
        'rx_bps'=>$rx,'tx_bps'=>$tx,
        'in_octets'=>(int)($in[$idx]??0),'out_octets'=>(int)($out[$idx]??0)
    ];
}
echo json_encode(['ok'=>true,'host'=>$host,'elapsed'=>$elapsed,'interfaces'=>$interfaces,'generated'=>date('c')],
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
