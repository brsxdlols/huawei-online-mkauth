#!/bin/bash
set -euo pipefail
DB_CNF=/etc/mkauth-huawei-online/db.cnf
CRON_FILE=/etc/cron.d/mkauth-huawei-planos
PLAN_SCRIPT=/root/planos/att-planos-huawei.sh
MAC_SCRIPT=/root/install-mac-case-patch.sh

sql(){ mysql --defaults-extra-file="$DB_CNF" -N -B mkradius -e "$1"; }
status(){
  local mac_insert mac_update mac_conflicts plans_total input_rows output_rows mismatches cron_enabled last_run
  mac_insert="$(sql "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mkradius' AND TRIGGER_NAME='mkauth_preserva_case_mac_insert';")"
  mac_update="$(sql "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mkradius' AND TRIGGER_NAME='mkauth_preserva_case_mac_update';")"
  mac_conflicts="$(sql "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mkradius' AND EVENT_OBJECT_TABLE='sis_cliente' AND TRIGGER_NAME IN ('sis_cliente_lowercase_mac','sis_cliente_mac_lower_insert','sis_cliente_mac_lower_update');")"
  plans_total="$(sql "SELECT COUNT(*) FROM sis_plano;")"
  input_rows="$(sql "SELECT COUNT(DISTINCT groupname) FROM radgroupreply WHERE attribute='Huawei-Input-Average-Rate';")"
  output_rows="$(sql "SELECT COUNT(DISTINCT groupname) FROM radgroupreply WHERE attribute='Huawei-Output-Average-Rate';")"
  mismatches="$(sql "SELECT COUNT(*) FROM sis_plano p LEFT JOIN radgroupreply i ON i.groupname=p.nome AND i.attribute='Huawei-Input-Average-Rate' LEFT JOIN radgroupreply o ON o.groupname=p.nome AND o.attribute='Huawei-Output-Average-Rate' WHERE i.id IS NULL OR o.id IS NULL OR i.op<>'=' OR o.op<>'=' OR CAST(i.value AS UNSIGNED)<>CAST(COALESCE(NULLIF(p.velup,''),'0') AS UNSIGNED)*1000 OR CAST(o.value AS UNSIGNED)<>CAST(COALESCE(NULLIF(p.veldown,''),'0') AS UNSIGNED)*1000;")"
  cron_enabled=0; [ -f "$CRON_FILE" ] && grep -qF '/root/planos/att-planos-huawei.sh' "$CRON_FILE" && cron_enabled=1
  last_run='nunca'; [ -f /var/log/mkauth-huawei-planos.log ] && last_run="$(date -r /var/log/mkauth-huawei-planos.log '+%Y-%m-%d %H:%M:%S')"
  printf '%s\n' "MAC_INSERT=$mac_insert" "MAC_UPDATE=$mac_update" "MAC_CONFLICTS=$mac_conflicts" "PLANS_TOTAL=$plans_total" "PLAN_INPUT=$input_rows" "PLAN_OUTPUT=$output_rows" "PLAN_MISMATCHES=$mismatches" "CRON_ENABLED=$cron_enabled" "LAST_RUN=$last_run"
}

apply(){
  [ -x "$MAC_SCRIPT" ] || { echo 'ERRO=Script do patch MAC ausente.'; exit 1; }
  [ -x "$PLAN_SCRIPT" ] || { echo 'ERRO=Script do patch de planos ausente.'; exit 1; }
  "$MAC_SCRIPT"
  if command -v mysqldump >/dev/null; then
    backup="/root/planos/backup-radgroupreply-huawei-$(date +%Y%m%d-%H%M%S).sql"
    mysqldump --defaults-extra-file="$DB_CNF" mkradius radgroupreply --where="attribute IN ('Huawei-Input-Average-Rate','Huawei-Output-Average-Rate')" >"$backup"
    chmod 0600 "$backup"
  fi
  "$PLAN_SCRIPT"
  cat >"$CRON_FILE" <<'CRON'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/1 * * * * root /root/planos/att-planos-huawei.sh >>/var/log/mkauth-huawei-planos.log 2>&1
CRON
  chmod 0644 "$CRON_FILE"
  cat >/etc/logrotate.d/mkauth-huawei-planos <<'ROTATE'
/var/log/mkauth-huawei-planos.log {
  weekly
  rotate 4
  compress
  missingok
  notifempty
}
ROTATE
  chmod 0644 /etc/logrotate.d/mkauth-huawei-planos
  echo 'APPLIED=1'
  status
}

process(){
  local id log_file
  id="$(sql "SELECT id FROM addon_huawei_patch_jobs WHERE status='pending' ORDER BY id LIMIT 1;")"
  [ -n "$id" ] || exit 0
  sql "UPDATE addon_huawei_patch_jobs SET status='running',message='Aplicação iniciada pelo executor root' WHERE id=$id AND status='pending';"
  log_file="/var/log/mkauth-huawei-patch-job-$id.log"
  if apply >"$log_file" 2>&1; then
    sql "UPDATE addon_huawei_patch_jobs SET status='success',finished_at=NOW(),message='Patch aplicado e validado com sucesso' WHERE id=$id;"
  else
    sql "UPDATE addon_huawei_patch_jobs SET status='error',finished_at=NOW(),message='Falha na aplicação; consulte $log_file' WHERE id=$id;"
    return 1
  fi
}

case "${1:-}" in status) status;; apply) apply;; process) process;; *) echo 'Uso: patch-manager.sh status|apply|process' >&2; exit 2;; esac
