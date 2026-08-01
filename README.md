# Huawei Online para MK-AUTH

Addon isolado para Huawei NetEngine 8000. Não altera dashboard, topo, menu ou arquivos centrais do MK-AUTH.

## Recursos

- clientes Huawei online pelo `radacct`;
- busca por nome, login, IP ou MAC;
- cadastro, alteração e histórico de conexões do cliente;
- gráfico individual em tempo real usando os contadores da sessão no NE8000;
- SNMP somente quando o botão **Testar SNMP** é acionado;
- teste de porta, autenticação e permissão pelo botão **Testar SSH**;
- botão **Radius LOG**;
- sincronização automática dos planos MK-AUTH com os atributos Huawei;
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

### Por que o SSH é necessário?

O RADIUS informa sessões e contadores acumulados. O SNMP padrão monitora interfaces,
mas não identifica com segurança o tráfego de cada login PPPoE. Para o gráfico em
tempo real, o addon consulta sob demanda os contadores da sessão com
`display access-user` no Huawei.

Use preferencialmente um usuário SSH exclusivo para o addon, com permissão somente
de leitura para os comandos `screen-length` e `display access-user`. Se o botão
**Desconectar** for habilitado, o usuário também precisa executar `cut access-user`,
que é um comando de nível de gerenciamento. Restrinja o SSH ao IP do MK-AUTH por
ACL/política do equipamento. Configure o IP
interno de gerência do Huawei e a porta SSH interna; evite usar redirecionamento do
IP público a partir do próprio MK-AUTH. O usuário, host e porta são informados nas
variáveis `HUAWEI_SSH_USER`, `HUAWEI_SSH_HOST` e `HUAWEI_SSH_PORT` durante a instalação.

Após instalar, a engrenagem **Configurações** permite alterar IP, SNMP, porta SSH,
usuário e senha sem reinstalar. Na primeira abertura, o assistente é exibido
automaticamente se algum dado obrigatório estiver ausente. O botão **Testar
conectividade** verifica RADIUS, SNMP, porta SSH, autenticação e permissão do comando.

## Patch de planos Huawei

O instalador cria `/root/planos` com os três scripts do pacote original e registra um
único agendamento seguro em `/etc/cron.d/mkauth-huawei-planos`:

```cron
*/1 * * * * root /root/planos/att-planos-huawei.sh
```

A cada minuto, a velocidade de `sis_plano.velup` e `sis_plano.veldown` é convertida
para bps e sincronizada nos atributos:

- `Huawei-Input-Average-Rate`
- `Huawei-Output-Average-Rate`

A sincronização usa `groupname + attribute`, não define IDs manualmente, não cria
novas duplicatas e não remove ou modifica atributos MikroTik. Os três agendamentos
antigos são removidos porque `planos.sh` é apenas uma consulta e `att-planos.sh` é
mantido somente para compatibilidade. Antes da primeira sincronização, o instalador
salva um backup dos atributos Huawei em `/root/planos`. Para sincronizar manualmente:

```bash
/root/planos/att-planos-huawei.sh
```

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
