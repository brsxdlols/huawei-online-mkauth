#!/bin/bash
set -eu

API="https://api.github.com/repos/brsxdlols/huawei-online-mkauth/contents"
DEST="/opt/mk-auth/admin/addons/huawei_online"
CONF="/etc/mkauth-huawei-online"
CACHE="/var/cache/mkauth-huawei-online"

[ "$(id -u)" -eq 0 ] || { echo "Execute como root."; exit 1; }
command -v curl >/dev/null
command -v snmpwalk >/dev/null || { echo "Instale o pacote snmp."; exit 1; }

mkdir -p "$DEST" "$CONF" "$CACHE"
for file in index.php bootstrap.php api_sessions.php api_interfaces.php api_health.php manifest.json; do
  curl -fsSL -H 'Accept: application/vnd.github.raw' \
    "${API}/addon/${file}?ref=main" -o "${DEST}/${file}"
done
curl -fsSL -H 'Accept: application/vnd.github.raw' \
  "${API}/README.md?ref=main" -o "/root/huawei-online-mkauth-README.md"

if [ ! -f "${CONF}/config.php" ]; then
  SNMP_COMMUNITY="${HUAWEI_SNMP_COMMUNITY:-}"
  if [ -z "$SNMP_COMMUNITY" ] && [ -t 0 ]; then
    printf "Comunidade SNMP do Huawei: "
    stty -echo
    read -r SNMP_COMMUNITY
    stty echo
    printf "\n"
  fi
  [ -n "$SNMP_COMMUNITY" ] || {
    echo "Informe HUAWEI_SNMP_COMMUNITY antes do curl ou execute em terminal interativo."
    exit 1
  }
cat >"${CONF}/config.php" <<'PHP'
<?php
return [
    'nas_ip' => '10.255.255.200',
    'snmp_host' => '10.255.255.200',
    'snmp_version' => '2c',
    'snmp_community' => '__SNMP_COMMUNITY__',
    'refresh_seconds' => 5,
    'stale_radius_seconds' => 900,
];
PHP
  SNMP_ESCAPED=$(printf '%s' "$SNMP_COMMUNITY" | sed 's/[\/&]/\\&/g')
  sed -i "s/__SNMP_COMMUNITY__/${SNMP_ESCAPED}/" "${CONF}/config.php"
fi

chown -R root:www-data "$DEST" "$CONF" "$CACHE"
chmod 750 "$DEST" "$CONF"
chmod 770 "$CACHE"
chmod 640 "$DEST"/* "${CONF}/config.php"

echo
echo "Huawei Online instalado SEM alterar o menu principal."
echo "Abra: /admin/addons/huawei_online/"
echo
echo "Teste RADIUS no Huawei:"
echo "  test-aaa ne8k 1 radius-group radius-server-pppoe chap"
echo
echo "No MK-AUTH, o ramal deve usar o NAS-IP configurado no Huawei."
echo "Ambiente atual: 10.255.255.200 (BNG-HUAWEI)."
echo
echo "Accounting periódico (apenas novos logins):"
echo "  aaa"
echo "   accounting-scheme radius-pppoe"
echo "    accounting realtime 3"
echo
echo "Guia completo salvo em:"
echo "  /root/huawei-online-mkauth-README.md"
echo "  https://github.com/brsxdlols/huawei-online-mkauth"
