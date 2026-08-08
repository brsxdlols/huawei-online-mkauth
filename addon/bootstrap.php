<?php
declare(strict_types=1);
require_once __DIR__ . '/addons.class.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_name('mka'); session_start(); }
$authenticatedUser=trim((string)($_SESSION['MKA_Usuario']??$_SESSION['MM_Usuario']??''));
if(empty($_SESSION['MKA_Logado'])&&$authenticatedUser===''){http_response_code(403);exit('Acesso negado. Entre novamente no MK-AUTH.');}
function db():mysqli{$db=new mysqli('127.0.0.1','root','vertrigo','mkradius');if($db->connect_errno)throw new RuntimeException('Falha no banco.');$db->set_charset('utf8mb4');return $db;}
function addon_config():array{$base='/etc/mkauth-huawei-online/config.php';$override=__DIR__.'/data/config.php';$c=is_file($base)?require $base:[];$o=is_file($override)?require $override:[];$merged=array_merge(is_array($c)?$c:[],is_array($o)?$o:[]);try{$d=db();$r=$d->query("SELECT config_json FROM addon_huawei_config WHERE id=1");if($r&&($row=$r->fetch_assoc())){$sql=json_decode((string)$row['config_json'],true);if(is_array($sql))$merged=array_merge($merged,$sql);}$d->close();}catch(Throwable $e){}return$merged;}
function save_addon_config(array $c):void{$d=db();$d->query("CREATE TABLE IF NOT EXISTS addon_huawei_config (id TINYINT UNSIGNED NOT NULL PRIMARY KEY,config_json LONGTEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$json=json_encode($c,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false)throw new RuntimeException('Falha ao preparar configuração.');$st=$d->prepare("INSERT INTO addon_huawei_config(id,config_json) VALUES(1,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json)");$st->bind_param('s',$json);if(!$st->execute())throw new RuntimeException('Falha ao salvar configuração no banco.');$st->close();$d->close();}
function json_response(array $d,int $s=200):void{http_response_code($s);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function valid_login(string $l):string{$l=trim($l);if($l===''||strlen($l)>64||!preg_match('/^[A-Za-z0-9_.@:-]+$/',$l))throw new InvalidArgumentException('Login inválido.');return $l;}
function csrf_token():string{if(empty($_SESSION['huawei_csrf']))$_SESSION['huawei_csrf']=bin2hex(random_bytes(32));return(string)$_SESSION['huawei_csrf'];}
function require_csrf(string $token):void{if(!hash_equals(csrf_token(),$token))throw new RuntimeException('Sessão expirada. Atualize a página.');}
