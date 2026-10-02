#!/bin/bash
set -euo pipefail

DB_CNF="/etc/mkauth-huawei-online/db.cnf"
LOCK="/run/lock/mkauth-huawei-planos.lock"

[ -r "$DB_CNF" ] || { echo "Configuração ausente: $DB_CNF" >&2; exit 1; }

exec 9>"$LOCK"
flock -n 9 || exit 0

mysql --defaults-extra-file="$DB_CNF" mkradius <<'SQL'
START TRANSACTION;

-- Remove resíduos de versões antigas e duplicidades antes de contabilizar planos.
DELETE r
FROM radgroupreply r
LEFT JOIN sis_plano p ON p.nome = r.groupname
WHERE r.attribute IN ('Huawei-Input-Average-Rate','Huawei-Output-Average-Rate')
  AND (TRIM(r.groupname) = '' OR p.nome IS NULL);

DELETE newer
FROM radgroupreply newer
JOIN radgroupreply older
  ON older.groupname = newer.groupname
 AND older.attribute = newer.attribute
 AND older.id < newer.id
WHERE newer.attribute IN ('Huawei-Input-Average-Rate','Huawei-Output-Average-Rate');

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
WHERE TRIM(COALESCE(p.nome,'')) <> ''
  AND NOT EXISTS (
  SELECT 1 FROM radgroupreply r
  WHERE r.groupname = p.nome AND r.attribute = 'Huawei-Input-Average-Rate'
);

INSERT INTO radgroupreply (groupname, attribute, op, value)
SELECT p.nome, 'Huawei-Output-Average-Rate', '=',
       CAST(CAST(COALESCE(NULLIF(p.veldown, ''), '0') AS UNSIGNED) * 1000 AS CHAR)
FROM sis_plano p
WHERE TRIM(COALESCE(p.nome,'')) <> ''
  AND NOT EXISTS (
  SELECT 1 FROM radgroupreply r
  WHERE r.groupname = p.nome AND r.attribute = 'Huawei-Output-Average-Rate'
);

-- O MK-AUTH já cria Framed-Pool=pgcorte. Preserve os atributos MikroTik e
-- acrescente os equivalentes reconhecidos pelo Huawei NE8K.
DELETE stale
FROM radreply stale
LEFT JOIN radreply pool
  ON pool.username = stale.username
 AND pool.attribute = 'Framed-Pool'
 AND pool.value = 'pgcorte'
WHERE stale.attribute IN ('Framed-IPv6-Pool','Huawei-Delegated-IPv6-Prefix-Pool')
  AND pool.id IS NULL;

DELETE newer
FROM radreply newer
JOIN radreply older
  ON older.username = newer.username
 AND older.attribute = newer.attribute
 AND older.id < newer.id
WHERE newer.attribute IN ('Framed-IPv6-Pool','Huawei-Delegated-IPv6-Prefix-Pool');

UPDATE radreply r
JOIN radreply pool
  ON pool.username = r.username
 AND pool.attribute = 'Framed-Pool'
 AND pool.value = 'pgcorte'
SET r.op = '=',
    r.value = CASE r.attribute
      WHEN 'Framed-IPv6-Pool' THEN 'bloqueiov6prefix'
      ELSE 'pgcorte'
    END
WHERE r.attribute IN ('Framed-IPv6-Pool','Huawei-Delegated-IPv6-Prefix-Pool');

INSERT INTO radreply (username, attribute, op, value)
SELECT DISTINCT pool.username, 'Framed-IPv6-Pool', '=', 'bloqueiov6prefix'
FROM radreply pool
WHERE pool.attribute = 'Framed-Pool' AND pool.value = 'pgcorte'
  AND NOT EXISTS (
    SELECT 1 FROM radreply r
    WHERE r.username = pool.username AND r.attribute = 'Framed-IPv6-Pool'
  );

INSERT INTO radreply (username, attribute, op, value)
SELECT DISTINCT pool.username, 'Huawei-Delegated-IPv6-Prefix-Pool', '=', 'pgcorte'
FROM radreply pool
WHERE pool.attribute = 'Framed-Pool' AND pool.value = 'pgcorte'
  AND NOT EXISTS (
    SELECT 1 FROM radreply r
    WHERE r.username = pool.username AND r.attribute = 'Huawei-Delegated-IPv6-Prefix-Pool'
  );

COMMIT;
SQL

touch /var/run/mkauth-huawei-planos.last

