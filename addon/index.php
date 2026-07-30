<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
?>
<!doctype html>
<html lang="pt-BR" class="has-navbar-fixed-top">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MK-AUTH :: Huawei Online</title>
<link href="../../estilos/mk-auth.css" rel="stylesheet"><link href="../../estilos/font-awesome.css" rel="stylesheet">
<script src="../../scripts/jquery.js"></script><script src="../../scripts/mk-auth.js"></script>
<style>
.hw{padding:16px;max-width:1600px;margin:auto}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.cardx{padding:16px;border:1px solid #ddd;border-radius:10px;background:#fff}.cardx b{font-size:1.5rem;display:block}
.boxx{margin-top:14px;padding:12px;background:#fff;border:1px solid #ddd;border-radius:10px;overflow:auto}
table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap}
th{background:#10233f;color:#fff}.ok{color:#16803c}.bad{color:#c62828}.note{padding:10px;background:#fff7d6;border-left:4px solid #e2a900}
@media(max-width:800px){.cards{grid-template-columns:repeat(2,1fr)}}
</style>
</head><body>
<?php include('../../topo.php'); ?>
<main class="hw"><h1 class="title">Huawei Online</h1>
<div class="note">Interfaces: SNMP em tempo real. Clientes: RADIUS accounting; os contadores individuais dependem de Interim-Update.</div>
<div class="cards">
<div class="cardx">Clientes Huawei<b id="clients">—</b></div>
<div class="cardx">Interfaces UP<b id="ifs">—</b></div>
<div class="cardx">Download agregado<b id="rx">—</b></div>
<div class="cardx">Upload agregado<b id="tx">—</b></div>
</div>
<div id="health" class="note" style="margin-top:14px">Verificando accounting RADIUS…</div>
<section class="boxx"><h2 class="subtitle">Interfaces SNMP</h2><table><thead><tr><th>Interface</th><th>Estado</th><th>RX</th><th>TX</th></tr></thead><tbody id="ifrows"></tbody></table></section>
<section class="boxx"><h2 class="subtitle">Sessões Huawei</h2><table><thead><tr><th>Login / Cliente</th><th>IP</th><th>Plano</th><th>MAC</th><th>Porta</th><th>Início</th><th>Atualização</th></tr></thead><tbody id="sessions"></tbody></table></section>
</main>
<?php include('../../baixo.php'); ?><script src="../../menu.js.hhvm"></script>
<script>
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const rate=n=>n==null?'medindo…':n>=1e9?(n/1e9).toFixed(2)+' Gbps':n>=1e6?(n/1e6).toFixed(2)+' Mbps':(n/1e3).toFixed(2)+' Kbps';
async function refreshInterfaces(){const d=await (await fetch('api_interfaces.php',{cache:'no-store'})).json();if(!d.ok)return;
const active=d.interfaces.filter(i=>i.up&&(i.rx_bps>0||i.tx_bps>0));document.querySelector('#ifs').textContent=d.interfaces.filter(i=>i.up).length;
document.querySelector('#rx').textContent=rate(active.reduce((a,i)=>a+(i.rx_bps||0),0));document.querySelector('#tx').textContent=rate(active.reduce((a,i)=>a+(i.tx_bps||0),0));
document.querySelector('#ifrows').innerHTML=d.interfaces.filter(i=>i.up||i.rx_bps>0||i.tx_bps>0).map(i=>`<tr><td>${esc(i.name)}</td><td class="${i.up?'ok':'bad'}">${i.up?'UP':'DOWN'}</td><td>${rate(i.rx_bps)}</td><td>${rate(i.tx_bps)}</td></tr>`).join('');}
async function refreshSessions(){const d=await (await fetch('api_sessions.php',{cache:'no-store'})).json();if(!d.ok)return;document.querySelector('#clients').textContent=d.sessions.length;
document.querySelector('#sessions').innerHTML=d.sessions.map(s=>`<tr><td><b>${esc(s.login)}</b><br>${esc(s.nome)}</td><td>${esc(s.ip)}</td><td>${esc(s.plano)}</td><td>${esc(s.mac)}</td><td>${esc(s.porta)}</td><td>${esc(s.inicio)}</td><td class="${s.atraso>900?'bad':'ok'}">${esc(s.atualizado)} (${s.atraso}s)</td></tr>`).join('');}
async function refreshHealth(){const d=await (await fetch('api_health.php',{cache:'no-store'})).json();if(!d.ok)return;
document.querySelector('#health').innerHTML=`NAS ${esc(d.nas)} · ${d.online} online · ${d.with_interim}/${d.total} sessões com Interim-Update`+
(d.warnings.length?`<br><b class="bad">${d.warnings.map(esc).join('<br>')}</b>`:'<br><b class="ok">Accounting periódico detectado.</b>');}
refreshInterfaces();refreshSessions();refreshHealth();setInterval(refreshInterfaces,5000);setInterval(refreshSessions,15000);setInterval(refreshHealth,15000);
</script></body></html>
