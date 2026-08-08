#!/bin/bash
set -euo pipefail
REPO="https://raw.githubusercontent.com/brsxdlols/huawei-online-mkauth/main"
DEST="/opt/mk-auth/admin/addons/huawei_online"
CONF="/etc/mkauth-huawei-online"
CACHE="/var/cache/mkauth-huawei-online"
PLANOS="/root/planos"
[ "$(id -u)" -eq 0 ] || { echo "Execute como root."; exit 1; }
for cmd in curl mysql; do command -v "$cmd" >/dev/null || { echo "Falta o comando: $cmd"; exit 1; }; done
if ! command -v snmpget >/dev/null || ! command -v sshpass >/dev/null || ! command -v ssh >/dev/null; then
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y snmp sshpass openssh-client
fi
: "${HUAWEI_SNMP_COMMUNITY:?Informe HUAWEI_SNMP_COMMUNITY}"
: "${HUAWEI_SSH_HOST:?Informe HUAWEI_SSH_HOST}"
: "${HUAWEI_SSH_PORT:=22}"
: "${HUAWEI_SSH_USER:?Informe HUAWEI_SSH_USER}"
: "${HUAWEI_SSH_PASSWORD:?Informe HUAWEI_SSH_PASSWORD}"
: "${HUAWEI_NAS_IP:=10.255.255.200}"
: "${HUAWEI_SNMP_HOST:=$HUAWEI_NAS_IP}"
install -d -o root -g www-data -m 0750 "$DEST"
install -d -o root -g www-data -m 0770 "$DEST/data" "$CONF" "$CACHE"
install -d -o root -g root -m 0700 "$PLANOS"
files=(index.php detail.php bootstrap.php huawei_client.php api_sessions.php api_client.php api_realtime.php api_health.php api_ssh_health.php api_config.php api_wizard.php api_disconnect.php api_patch.php manifest.json)
for f in "${files[@]}"; do curl -fsSL "$REPO/addon/$f" -o "$DEST/$f"; done
cat >"$DEST/data/.htaccess" <<'HTACCESS'
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Deny from all
</IfModule>
HTACCESS
for f in att-planos-huawei.sh att-planos.sh planos.sh; do curl -fsSL "$REPO/planos/$f" -o "$PLANOS/$f"; done
chmod 0700 "$PLANOS"/*.sh
ln -sfn /opt/mk-auth/include/addons.inc.hhvm "$DEST/addons.class.php"
[[ "$HUAWEI_SSH_PORT" =~ ^[0-9]+$ ]] || { echo "HUAWEI_SSH_PORT deve ser numérica."; exit 1; }
b64(){ printf '%s' "$1" | base64 | tr -d '\r\n'; }
cat >"$CONF/config.php" <<PHP
<?php return [
'nas_ip'=>base64_decode('$(b64 "$HUAWEI_NAS_IP")'),
'snmp_host'=>base64_decode('$(b64 "$HUAWEI_SNMP_HOST")'),
'snmp_community'=>base64_decode('$(b64 "$HUAWEI_SNMP_COMMUNITY")'),
'ssh_host'=>base64_decode('$(b64 "$HUAWEI_SSH_HOST")'),
'ssh_port'=>(int)base64_decode('$(b64 "$HUAWEI_SSH_PORT")'),
'ssh_user'=>base64_decode('$(b64 "$HUAWEI_SSH_USER")'),
'ssh_password'=>base64_decode('$(b64 "$HUAWEI_SSH_PASSWORD")'),
];
PHP
cat >"$CONF/db.cnf" <<'CNF'
[client]
host=localhost
user=root
password=vertrigo
CNF
chmod 0600 "$CONF/db.cnf"
mysql --defaults-extra-file="$CONF/db.cnf" mkradius -e "CREATE TABLE IF NOT EXISTS addon_huawei_config (id TINYINT UNSIGNED NOT NULL PRIMARY KEY,config_json LONGTEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
chown -R root:www-data "$DEST" "$CONF"
find "$DEST" -type f -exec chmod 0640 {} \;
chmod 0770 "$DEST/data"
chmod 0660 "$CONF/config.php"
curl -fsSL "$REPO/install-mac-case-patch.sh" -o /root/install-mac-case-patch.sh
chmod 0700 /root/install-mac-case-patch.sh
curl -fsSL "$REPO/patch-manager.sh" -o /usr/local/sbin/mkauth-huawei-patch-manager
chmod 0750 /usr/local/sbin/mkauth-huawei-patch-manager
chown root:root /usr/local/sbin/mkauth-huawei-patch-manager
mysql --defaults-extra-file="$CONF/db.cnf" mkradius -e "CREATE TABLE IF NOT EXISTS addon_huawei_patch_jobs(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,status VARCHAR(16) NOT NULL,requested_by VARCHAR(100) NOT NULL,requested_at DATETIME NOT NULL,finished_at DATETIME NULL,message TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
cat >/etc/cron.d/mkauth-huawei-patch-worker <<'CRON'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * root /usr/local/sbin/mkauth-huawei-patch-manager process >/dev/null 2>&1
CRON
chmod 0644 /etc/cron.d/mkauth-huawei-patch-worker
rm -f /etc/sudoers.d/mkauth-huawei-patch-manager
echo "Addon instalado sem aplicar patches no banco. Abra http://IP-DO-MKAUTH/admin/addons/huawei_online/ e use Analisar PATCH."
