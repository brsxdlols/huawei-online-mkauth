#!/bin/bash
set -eu
[ "$(id -u)" -eq 0 ] || { echo "Execute como root."; exit 1; }
rm -rf /opt/mk-auth/admin/addons/huawei_online
rm -rf /etc/mkauth-huawei-online
rm -rf /var/cache/mkauth-huawei-online
echo "Huawei Online removido. Nenhum dado do MK-AUTH foi alterado."
