<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$nas = (string)$huaweiConfig['nas_ip'];
$sql = <<<'SQL'
SELECT r.username, COALESCE(c.nome, '') AS nome,
       COALESCE(c.plano, r.groupname, '') AS plano,
       r.framedipaddress, r.callingstationid, r.nasportid,
       r.acctstarttime, r.acctupdatetime,
       COALESCE(r.acctsessiontime, TIMESTAMPDIFF(SECOND,r.acctstarttime,NOW())) AS segundos,
       COALESCE(r.acctinputoctets,0) AS entrada,
       COALESCE(r.acctoutputoctets,0) AS saida,
       COALESCE(r.acctinterval,0) AS intervalo,
       TIMESTAMPDIFF(SECOND,COALESCE(r.acctupdatetime,r.acctstarttime),NOW()) AS atraso
FROM radacct r
LEFT JOIN sis_cliente c ON BINARY c.login=BINARY r.username
WHERE r.acctstoptime IS NULL AND BINARY r.nasipaddress=BINARY ?
ORDER BY r.acctupdatetime DESC, r.username
SQL;
$stmt = $db->prepare($sql);
$stmt->bind_param('s', $nas);
$stmt->execute();
$result = $stmt->get_result();
$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = [
        'login' => $row['username'],
        'nome' => $row['nome'],
        'plano' => $row['plano'],
        'ip' => $row['framedipaddress'],
        'mac' => strtolower((string)$row['callingstationid']),
        'porta' => $row['nasportid'],
        'inicio' => $row['acctstarttime'],
        'atualizado' => $row['acctupdatetime'],
        'segundos' => (int)$row['segundos'],
        'entrada' => (int)$row['entrada'],
        'saida' => (int)$row['saida'],
        'intervalo' => (int)$row['intervalo'],
        'atraso' => (int)$row['atraso'],
    ];
}
$stmt->close();
echo json_encode(['ok'=>true,'nas'=>$nas,'sessions'=>$sessions,'generated'=>date('c')],
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
