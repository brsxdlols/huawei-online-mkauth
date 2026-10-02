#!/usr/bin/env php
<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

$db = new mysqli('127.0.0.1', 'root', 'vertrigo', 'mkradius');
if ($db->connect_error) exit(2);
$db->set_charset('utf8mb4');

$log = '/var/log/mkauth_huawei_block.log';
$write = static function (string $message) use ($log): void {
    $line = date('Y-m-d H:i:s').' '.$message.PHP_EOL;
    echo $line;
    file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
};

$cfg = [];
$result = $db->query("SELECT config_json FROM addon_huawei_config WHERE id=1");
if ($result && $row = $result->fetch_assoc()) {
    $cfg = json_decode((string)$row['config_json'], true) ?: [];
}
$nas = (string)($cfg['nas_ip'] ?? '');
if ($nas === '') {
    $write('FAIL nas_ip_not_configured');
    exit(2);
}

$db->query("INSERT INTO mkauth_huawei_block_queue(login,desired_state,status,attempts,created_at,updated_at,processed_at,result_message)
 SELECT c.login,c.bloqueado,'pending',0,NOW(),NOW(),NULL,NULL
 FROM sis_cliente c JOIN mkauth_huawei_block_state s ON s.login=c.login
 WHERE NOT(s.blocked_state<=>c.bloqueado)
 ON DUPLICATE KEY UPDATE desired_state=VALUES(desired_state),status='pending',attempts=0,updated_at=NOW(),processed_at=NULL,result_message=NULL");
$db->query("INSERT INTO mkauth_huawei_block_state(login,blocked_state,updated_at)
 SELECT login,bloqueado,NOW() FROM sis_cliente WHERE TRIM(COALESCE(login,''))<>''
 ON DUPLICATE KEY UPDATE blocked_state=VALUES(blocked_state),updated_at=IF(NOT(blocked_state<=>VALUES(blocked_state)),NOW(),updated_at)");

$ready = static function (string $login, string $state) use ($db): bool {
    $quoted = $db->real_escape_string($login);
    $result = $db->query("SELECT attribute,value FROM radreply WHERE username='$quoted' AND attribute IN ('Framed-Pool','Framed-IPv6-Pool','Huawei-Delegated-IPv6-Prefix-Pool')");
    $attributes = [];
    while ($result && $row = $result->fetch_assoc()) $attributes[$row['attribute']] = $row['value'];
    $cut = ($attributes['Framed-Pool'] ?? '') === 'pgcorte'
        && ($attributes['Framed-IPv6-Pool'] ?? '') === 'bloqueiov6prefix'
        && ($attributes['Huawei-Delegated-IPv6-Prefix-Pool'] ?? '') === 'pgcorte';
    return $state === 'sim'
        ? $cut
        : !isset($attributes['Framed-Pool'], $attributes['Framed-IPv6-Pool'], $attributes['Huawei-Delegated-IPv6-Prefix-Pool']);
};

$send = static function (string $login, string $session, string $ip) use ($db, $nas): array {
    $quotedNas = $db->real_escape_string($nas);
    $result = $db->query("SELECT secret FROM nas WHERE nasname='$quotedNas' LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row || trim((string)$row['secret']) === '') return [false, 'secret_missing'];
    $input = 'User-Name = "'.addcslashes($login, "\\\"")."\"\n"
        .'Acct-Session-Id = "'.addcslashes($session, "\\\"")."\"\n"
        ."Framed-IP-Address = $ip\nNAS-IP-Address = $nas\n";
    $command = '/usr/bin/radclient -x -r 1 -t 4 '.escapeshellarg($nas.':3799').' disconnect '.escapeshellarg((string)$row['secret']);
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) return [false, 'start_failed'];
    fwrite($pipes[0], $input); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    if (strpos($output, 'Disconnect-ACK') !== false) return [true, 'disconnect_ack'];
    if (strpos($output, 'Session-Context-Not-Found') !== false) return [true, 'already_offline'];
    return [false, 'disconnect_failed'];
};

$result = $db->query("SELECT q.id,q.login,c.bloqueado
 FROM mkauth_huawei_block_queue q JOIN sis_cliente c ON c.login=q.login
 WHERE q.status IN ('pending','failed') AND q.attempts<10 ORDER BY q.id LIMIT 100");
$seen = $done = $failed = 0;
while ($result && $row = $result->fetch_assoc()) {
    $seen++; $id = (int)$row['id']; $state = (string)$row['bloqueado'];
    if (!$ready((string)$row['login'], $state)) {
        $db->query("UPDATE mkauth_huawei_block_queue SET result_message='aguardando_atributos_radius',updated_at=NOW() WHERE id=$id");
        continue;
    }
    $login = $db->real_escape_string((string)$row['login']);
    $quotedNas = $db->real_escape_string($nas);
    $sessions = $db->query("SELECT acctsessionid,framedipaddress FROM radacct WHERE username='$login' AND nasipaddress='$quotedNas' AND acctstoptime IS NULL ORDER BY radacctid DESC LIMIT 1");
    $session = $sessions ? $sessions->fetch_assoc() : null;
    if (!$session) {
        $db->query("UPDATE mkauth_huawei_block_queue SET status='done',processed_at=NOW(),updated_at=NOW(),result_message='offline' WHERE id=$id");
        $done++; continue;
    }
    [$ok, $message] = $send((string)$row['login'], (string)$session['acctsessionid'], (string)$session['framedipaddress']);
    $safe = $db->real_escape_string($message); $status = $ok ? 'done' : 'failed';
    $db->query("UPDATE mkauth_huawei_block_queue SET status='$status',attempts=attempts+1,processed_at=".($ok?'NOW()':'NULL').",updated_at=NOW(),result_message='$safe' WHERE id=$id");
    $write(($ok?'OK':'FAIL')." login={$row['login']} state=$state $message");
    $ok ? $done++ : $failed++;
}
$write("SUMMARY seen=$seen done=$done failed=$failed");
$db->close();
exit($failed ? 1 : 0);
