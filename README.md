# Huawei Online para MK-AUTH

Painel independente para monitorar um BNG Huawei NetEngine 8000 integrado ao
MK-AUTH.

## Recursos

- clientes Huawei online a partir de `radacct`;
- tráfego de interfaces em tempo real via SNMP/IF-MIB;
- estado e tempo da última atualização RADIUS;
- instalação isolada, sem alterar o JavaScript do menu principal;
- guia de configuração RADIUS, accounting, CoA e teste AAA.

## Instalação

Execute como `root` no MK-AUTH:

```bash
export HUAWEI_SNMP_COMMUNITY='SUA_COMUNIDADE'
curl -fsSL -H 'Accept: application/vnd.github.raw' \
  https://api.github.com/repos/brsxdlols/huawei-online-mkauth/contents/install.sh |
  bash
```

O painel ficará disponível em:

```text
/admin/addons/huawei_online/
```

## Configuração usada no ambiente validado

```text
MK-AUTH/RADIUS: 172.31.200.5
NAS Huawei:     10.255.255.200
Modelo:         NetEngine 8000 M8
Versão:         V800R023C10SPC500
Grupo RADIUS:   radius-server-pppoe
Domínio:        pppoe
```

Segredos não são versionados. O instalador grava a comunidade SNMP em
`/etc/mkauth-huawei-online/config.php`, fora do diretório público.

## Exemplo de configuração RADIUS no Huawei

Adapte endereços, interfaces e chaves ao ambiente:

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

Crie também os esquemas e o domínio:

```text
aaa
 authentication-scheme radius-pppoe
  authentication-mode radius local
 accounting-scheme radius-pppoe
 domain pppoe
  authentication-scheme radius-pppoe
  accounting-scheme radius-pppoe
  radius-server group radius-server-pppoe
```

### Accounting periódico

Sem `Interim-Update`, o MK-AUTH registra o início da sessão, mas não atualiza
os contadores do cliente durante a conexão:

```text
aaa
 accounting-scheme radius-pppoe
  accounting realtime 3
```

O valor é em minutos e só passa a valer para novos logins. Use um intervalo
compatível com a quantidade de usuários e a capacidade do RADIUS.

Para 500 a 999 usuários, a recomendação de dimensionamento publicada pela
Huawei é 12 minutos. Um intervalo menor deixa o painel mais atual, mas aumenta
proporcionalmente os pacotes e as gravações no banco. O atributo
`Acct-Interim-Interval`, quando enviado pelo RADIUS, prevalece sobre o valor do
esquema.

## O que controla Online, Offline e histórico no MK-AUTH

O painel padrão usa `radacct.acctstoptime IS NULL` para contar os clientes
online. Não depende dos atributos de velocidade Huawei. Para o alinhamento
completo, o accounting precisa conter:

- `Acct-Status-Type` (`Start`, `Interim-Update` e `Stop`);
- `Acct-Session-Id` estável durante toda a sessão;
- `User-Name`, `NAS-IP-Address`, `Framed-IP-Address` e `Calling-Station-Id`;
- `Acct-Session-Time`;
- `Acct-Input-Octets` e `Acct-Output-Octets`;
- `Acct-Input-Gigawords` e `Acct-Output-Gigawords` para tráfego acima de 4 GiB;
- `Acct-Terminate-Cause` no encerramento.

No ambiente validado, `Start` e `Stop` já chegam e o histórico é criado. O
item ausente era o `Interim-Update`: sem ele, `acctupdatetime`, tempo e bytes
não mudam enquanto o cliente está conectado.

Se aparecer no log `invalid Request Authenticator`, confirme que o
`shared-key` do Huawei é exatamente o mesmo do ramal/NAS no MK-AUTH. Considere
também o IP de origem realmente enxergado pelo servidor quando houver
roteamento, túnel ou NAT; ele pode ser diferente do `nas-ip-address` enviado
dentro do pacote.

## Teste de autenticação no próprio Huawei

Primeiro crie no MK-AUTH o cliente de teste no ramal do Huawei:

- login: `ne8k`;
- senha: `1`;
- plano com atributos `Huawei-Input-Average-Rate` e
  `Huawei-Output-Average-Rate`;
- ramal/NAS: IP informado em `radius-server nas-ip-address`.

Depois execute:

```text
test-aaa ne8k 1 radius-group radius-server-pppoe chap
```

O resultado esperado é autenticação bem-sucedida. O teste envia credenciais
ao RADIUS; não cria uma sessão PPPoE real.

## Tráfego em tempo real

O SNMP lê `ifHCInOctets` e `ifHCOutOctets` do IF-MIB e calcula bit/s entre
duas amostras. Isso fornece tráfego em tempo real por interface.

O tráfego por cliente vem do RADIUS accounting e depende de
`Interim-Update`. SNMP padrão não expõe obrigatoriamente contadores por sessão
PPPoE como interfaces individuais.
