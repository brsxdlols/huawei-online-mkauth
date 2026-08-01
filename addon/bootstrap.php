<?php
declare(strict_types=1);
require_once __DIR__ . '/addons.class.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_name('mka'); session_start(); }
$authenticatedUser=trim((string)($_SESSION['MKA_Usuario']??$_SESSION['MM_Usuario']??''));
if(empty($_SESSION['MKA_Logado'])&&$authenticatedUser===''){http_response_code(403);exit('Acesso negado. Entre novamente no MK-AUTH.');}
function db():mysqli{$db=new mysqli('127.0.0.1','root','vertrigo','mkradius');if($db->connect_errno)throw new RuntimeException('Falha no banco.');$db->set_charset('utf8mb4');return $db;}
function addon_config():array{$base='/etc/mkauth-huawei-online/config.php';$override=__DIR__.'/data/config.php';$c=is_file($base)?require $base:[];$o=is_file($override)?require $override:[];return array_merge(is_array($c)?$c:[],is_array($o)?$o:[]);}
function json_response(array $d,int $s=200):void{http_response_code($s);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function valid_login(string $l):string{$l=trim($l);if($l===''||strlen($l)>64||!preg_match('/^[A-Za-z0-9_.@:-]+$/',$l))throw new InvalidArgumentException('Login inválido.');return $l;}
function csrf_token():string{if(empty($_SESSION['huawei_csrf']))$_SESSION['huawei_csrf']=bin2hex(random_bytes(32));return(string)$_SESSION['huawei_csrf'];}
function require_csrf(string $token):void{if(!hash_equals(csrf_token(),$token))throw new RuntimeException('Sessão expirada. Atualize a página.');}
