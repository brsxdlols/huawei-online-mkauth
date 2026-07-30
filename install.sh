#!/bin/bash
set -euo pipefail
REPO="https://raw.githubusercontent.com/brsxdlols/huawei-online-mkauth/main"
DEST="/opt/mk-auth/admin/addons/huawei_online"
CONF="/etc/mkauth-huawei-online"
CACHE="/var/cache/mkauth-huawei-online"
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
install -d -o root -g www-data -m 0750 "$DEST" "$CONF"
install -d -o root -g www-data -m 0770 "$CACHE"
files=(index.php detail.php bootstrap.php api_sessions.php api_client.php api_realtime.php api_health.php manifest.json)
for f in "${files[@]}"; do curl -fsSL "$REPO/addon/$f" -o "$DEST/$f"; done
ln -sfn /opt/mk-auth/include/addons.inc.hhvm "$DEST/addons.class.php"
[[ "$HUAWEI_SSH_PORT" =~ ^[0-9]+$ ]] || { echo "HUAWEI_SSH_PORT deve ser numérica."; exit 1; }
b64(){ printf '%s' "$1" | base64 | tr -d '\r\n'; }
cat >"$CONF/config.php" <<PHP
<?php return [
'nas_ip'=>base64_decode('$(b64 "$HUAWEI_NAS_IP")'),
'snmp_host'=>base64_decode('$(b64 "$HUAWEI_NAS_IP")'),
'snmp_community'=>base64_decode('$(b64 "$HUAWEI_SNMP_COMMUNITY")'),
'ssh_host'=>base64_decode('$(b64 "$HUAWEI_SSH_HOST")'),
'ssh_port'=>(int)base64_decode('$(b64 "$HUAWEI_SSH_PORT")'),
'ssh_user'=>base64_decode('$(b64 "$HUAWEI_SSH_USER")'),
'ssh_password'=>base64_decode('$(b64 "$HUAWEI_SSH_PASSWORD")'),
];
PHP
chown -R root:www-data "$DEST" "$CONF"; find "$DEST" -type f -exec chmod 0640 {} \;; chmod 0640 "$CONF/config.php"
curl -fsSL "$REPO/install-mac-case-patch.sh" -o /root/install-mac-case-patch.sh
chmod 0700 /root/install-mac-case-patch.sh
/root/install-mac-case-patch.sh
echo "Instalado: http://IP-DO-MKAUTH/admin/addons/huawei_online/"
