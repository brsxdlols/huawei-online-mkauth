#!/bin/bash
set -euo pipefail
mysql --defaults-extra-file=/etc/mkauth-huawei-online/db.cnf mkradius \
  -e "SELECT nome, velup, veldown FROM sis_plano ORDER BY nome"

