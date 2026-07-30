<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$nas = (string)$huaweiConfig['nas_ip'];
$stale = (int)($huaweiConfig['stale_radius_seconds'] ?? 900);
$sql = <<<'SQL'
SELECT COUNT(*) AS total,
       COALESCE(SUM(acctstoptime IS NULL),0) AS abertas,
       COALESCE(SUM(acctupdatetime > acctstarttime),0) AS com_interim,
       MAX(acctupdatetime) AS ultima_atualizacao,
       MAX(acctstoptime) AS ultimo_stop
FROM radacct
WHERE BINARY nasipaddress=BINARY ?
SQL;
$stmt = $db->prepare($sql);
$stmt->bind_param('s', $nas);
$stmt->execute();
$status = $stmt->get_result()->fetch_assoc();
$stmt->close();

$latest = $status['ultima_atualizacao'] ? strtotime($status['ultima_atualizacao']) : false;
$age = $latest === false ? null : max(0, time() - $latest);
$warnings = [];
if ((int)$status['total'] === 0) {
    $warnings[] = 'Nenhum accounting RADIUS recebido deste NAS.';
} elseif ((int)$status['com_interim'] === 0) {
    $warnings[] = 'Nenhum Interim-Update detectado; bytes e tempo só serão consolidados no Stop.';
}
if ($age !== null && $age > $stale) {
    $warnings[] = 'Accounting sem atualização recente; confira o shared secret e a origem do pacote.';
}

echo json_encode([
    'ok' => true,
    'nas' => $nas,
    'total' => (int)$status['total'],
    'online' => (int)$status['abertas'],
    'with_interim' => (int)$status['com_interim'],
    'last_update' => $status['ultima_atualizacao'],
    'last_stop' => $status['ultimo_stop'],
    'age_seconds' => $age,
    'warnings' => $warnings,
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
