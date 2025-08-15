<?php
// core/bootstrap.php
if (session_status() === PHP_SESSION_NONE) session_start();
$config = require __DIR__.'/config.php';
try {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
    $config['db']['host'],
    $config['db']['name'],
    $config['db']['charset']
  );
  $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  die('Error de conexión: '.$e->getMessage());
}
function q($pdo,$sql,$params=[]){ $st=$pdo->prepare($sql); $st->execute($params); return $st; }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function auth(){ return $_SESSION['user'] ?? null; }
function require_login(){ if(!auth()){ header('Location: login.php'); exit; } }
function is_admin(){ return (auth()['role'] ?? '')==='admin'; }