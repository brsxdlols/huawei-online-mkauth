#!/bin/bash
set -euo pipefail
MYSQL=(mysql -h localhost -u root -pvertrigo mkradius)
BACKUP="/root/backup-case-mac-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
mysqldump -h localhost -u root -pvertrigo --no-data --triggers mkradius sis_cliente >"$BACKUP/sis_cliente_triggers.sql"
mysqldump -h localhost -u root -pvertrigo --no-create-info --where="mac IS NOT NULL AND mac <> ''" mkradius sis_cliente >"$BACKUP/sis_cliente_mac.sql"
"${MYSQL[@]}" <<'SQL'
DROP TRIGGER IF EXISTS sis_cliente_lowercase_mac;
DROP TRIGGER IF EXISTS sis_cliente_mac_lower_insert;
DROP TRIGGER IF EXISTS sis_cliente_mac_lower_update;
DROP TRIGGER IF EXISTS mkauth_preserva_case_mac_insert;
DROP TRIGGER IF EXISTS mkauth_preserva_case_mac_update;
DELIMITER //
CREATE TRIGGER mkauth_preserva_case_mac_insert BEFORE INSERT ON sis_cliente FOR EACH ROW
BEGIN
 DECLARE found_mac VARCHAR(100) DEFAULT NULL;
 IF COALESCE(NEW.login,'')<>'' AND COALESCE(NEW.mac,'')<>'' THEN
  SELECT callingstationid INTO found_mac FROM radacct
   WHERE LOWER(username)=LOWER(NEW.login) AND COALESCE(callingstationid,'')<>''
     AND LOWER(callingstationid)=LOWER(NEW.mac) ORDER BY radacctid DESC LIMIT 1;
  IF found_mac IS NOT NULL THEN SET NEW.mac=found_mac; END IF;
 END IF;
END//
CREATE TRIGGER mkauth_preserva_case_mac_update BEFORE UPDATE ON sis_cliente FOR EACH ROW
BEGIN
 DECLARE found_mac VARCHAR(100) DEFAULT NULL;
 IF COALESCE(NEW.login,'')<>'' AND COALESCE(NEW.mac,'')<>'' THEN
  SELECT callingstationid INTO found_mac FROM radacct
   WHERE LOWER(username)=LOWER(NEW.login) AND COALESCE(callingstationid,'')<>''
     AND LOWER(callingstationid)=LOWER(NEW.mac) ORDER BY radacctid DESC LIMIT 1;
  IF found_mac IS NOT NULL THEN SET NEW.mac=found_mac; END IF;
 END IF;
END//
DELIMITER ;
SQL
echo "Patch de MAC instalado. Backup: $BACKUP"
