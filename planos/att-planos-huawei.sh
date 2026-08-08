#!/bin/bash
set -euo pipefail

DB_CNF="/etc/mkauth-huawei-online/db.cnf"
LOCK="/run/lock/mkauth-huawei-planos.lock"

[ -r "$DB_CNF" ] || { echo "Configuração ausente: $DB_CNF" >&2; exit 1; }

exec 9>"$LOCK"
flock -n 9 || exit 0

mysql --defaults-extra-file="$DB_CNF" mkradius <<'SQL'
START TRANSACTION;

UPDATE radgroupreply r
JOIN sis_plano p ON p.nome = r.groupname
SET r.op = '=', r.value = CAST(CAST(COALESCE(NULLIF(p.velup, ''), '0') AS UNSIGNED) * 1000 AS CHAR)
WHERE r.attribute = 'Huawei-Input-Average-Rate';

UPDATE radgroupreply r
JOIN sis_plano p ON p.nome = r.groupname
SET r.op = '=', r.value = CAST(CAST(COALESCE(NULLIF(p.veldown, ''), '0') AS UNSIGNED) * 1000 AS CHAR)
WHERE r.attribute = 'Huawei-Output-Average-Rate';

INSERT INTO radgroupreply (groupname, attribute, op, value)
SELECT p.nome, 'Huawei-Input-Average-Rate', '=',
       CAST(CAST(COALESCE(NULLIF(p.velup, ''), '0') AS UNSIGNED) * 1000 AS CHAR)
FROM sis_plano p
WHERE NOT EXISTS (
  SELECT 1 FROM radgroupreply r
  WHERE r.groupname = p.nome AND r.attribute = 'Huawei-Input-Average-Rate'
);

INSERT INTO radgroupreply (groupname, attribute, op, value)
SELECT p.nome, 'Huawei-Output-Average-Rate', '=',
       CAST(CAST(COALESCE(NULLIF(p.veldown, ''), '0') AS UNSIGNED) * 1000 AS CHAR)
FROM sis_plano p
WHERE NOT EXISTS (
  SELECT 1 FROM radgroupreply r
  WHERE r.groupname = p.nome AND r.attribute = 'Huawei-Output-Average-Rate'
);

COMMIT;
SQL

touch /var/run/mkauth-huawei-planos.last

