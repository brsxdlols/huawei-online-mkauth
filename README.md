# Huawei Online para MK-AUTH

Addon isolado para Huawei NetEngine 8000. Não altera dashboard, topo, menu ou arquivos centrais do MK-AUTH.

## Recursos

- clientes Huawei online pelo `radacct`;
- busca por nome, login, IP ou MAC;
- cadastro, alteração e histórico de conexões do cliente;
- gráfico individual em tempo real usando os contadores da sessão no NE8000;
- SNMP somente quando o botão **Testar SNMP** é acionado;
- botão **Radius LOG**;
- patch idempotente de capitalização do MAC para Huawei e MikroTik;
- backup dos triggers e MACs antes de instalar o patch.

## Instalação

Execute como `root`, substituindo os valores:

```bash
export HUAWEI_NAS_IP='10.255.255.200'
export HUAWEI_SNMP_COMMUNITY='SUA_COMUNIDADE'
export HUAWEI_SSH_HOST='IP_DO_HUAWEI'
export HUAWEI_SSH_PORT='22'
export HUAWEI_SSH_USER='USUARIO_SSH'
export HUAWEI_SSH_PASSWORD='SENHA_SSH'
curl -fsSL https://raw.githubusercontent.com/brsxdlols/huawei-online-mkauth/main/install.sh | bash
```

Abra:

```text
http://IP-DO-MKAUTH/admin/addons/huawei_online/
```

Os segredos ficam em `/etc/mkauth-huawei-online/config.php`, fora da pasta pública.

## RADIUS Huawei

Exemplo — adapte IPs e chaves:

```text
radius-server group radius-server-pppoe
 radius-server shared-key-cipher SUA_CHAVE_RADIUS
 radius-server authentication 172.16.88.2 source ip-address 45.170.122.1 1812 weight 0
 radius-server accounting 172.16.88.2 source ip-address 45.170.122.1 1813 weight 0
 radius-server retransmit 5 timeout 20
 radius-server source interface LoopBack1
 radius-server nas-ip-address 45.170.122.1
 radius-server user-name original
 radius-server user-name trust-server-request
 radius-server nas-port-id include interface-description delimiter - pe-vlan

radius local-ip 45.170.122.1
undo radius local-ip all
radius-server authorization 172.16.88.2 destination-port 3799 shared-key-cipher SUA_CHAVE_COA server-group radius-server-pppoe
```

Accounting periódico validado no NE8000:

```text
aaa
 accounting-scheme radius-pppoe
  accounting interim interval 3
  accounting send-update
```

Teste AAA:

```text
test-aaa ne8k 1 radius-group radius-server-pppoe chap
```

O ramal do MK-AUTH deve usar o mesmo NAS-IP configurado no Huawei.

## Tráfego individual

Ao abrir um cliente, o addon consulta sob demanda:

```text
display access-user username LOGIN
display access-user user-id ID
```

O gráfico calcula Mbps pela diferença de `Up bytes number` e `Down bytes number` entre amostras de três segundos. Ao sair da tela, as consultas param.

O SNMP/IF-MIB comum permanece disponível apenas para teste de comunicação, pois contadores de interface podem agregar vários assinantes.

## Patch de MAC

O instalador remove triggers antigos que forçam minúsculas e cria triggers que preservam o formato recebido no último accounting:

- Huawei pode manter `aa:bb:cc:dd:ee:ff`;
- MikroTik pode manter `AA:BB:CC:DD:EE:FF`.

O patch só altera capitalização quando os MACs são equivalentes ignorando maiúsculas/minúsculas. Não instala cron permanente.
