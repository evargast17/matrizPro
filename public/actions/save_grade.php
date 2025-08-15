<?php
require __DIR__.'/../../core.php';
require_login();
$data = json_decode(file_get_contents('php://input'), true);
$est=(int)($data['estudiante_id']??0);
$comp=(int)($data['competencia_id']??0);
$per=(int)($data['periodo_id']??0);
$nota=$data['nota']??null;
if(!$est||!$comp||!$per||!in_array($nota,['AD','A','B','C'])){ http_response_code(400); exit; }
q($pdo,"INSERT INTO calificaciones(estudiante_id,competencia_id,periodo_id,nota) VALUES(?,?,?,?)
ON DUPLICATE KEY UPDATE nota=VALUES(nota)",[$est,$comp,$per,$nota]);
echo json_encode(['ok'=>true]);