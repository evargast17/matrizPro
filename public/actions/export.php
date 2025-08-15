<?php
require __DIR__.'/../../core.php';
require_login();
$periodo_id=(int)($_GET['periodo_id']??0);
$aula_id=(int)($_GET['aula_id']??0);
$competencia_id=(int)($_GET['competencia_id']??0);

$periodo = q($pdo,"SELECT nombre FROM periodos WHERE id=?",[$periodo_id])->fetchColumn();
$comp = q($pdo,"SELECT c.nombre, a.nombre AS area FROM competencias c JOIN areas a ON a.id=c.area_id WHERE c.id=?",[$competencia_id])->fetch();
$aula = q($pdo,"SELECT CONCAT(nombre,' ',seccion,' - ',grado) AS n FROM aulas WHERE id=?",[$aula_id])->fetchColumn();

$rows = q($pdo,"SELECT e.id, CONCAT(e.apellidos, ', ', e.nombres) AS estudiante,
(SELECT nota FROM calificaciones WHERE estudiante_id=e.id AND competencia_id=? AND periodo_id=? LIMIT 1) AS nota
FROM estudiantes e WHERE aula_id=? ORDER BY e.apellidos, e.nombres", [$competencia_id,$periodo_id,$aula_id])->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="calificaciones.csv"');
$out=fopen('php://output','w');
fputcsv($out, ['Aula', $aula]);
fputcsv($out, ['Periodo', $periodo]);
fputcsv($out, ['Área', $comp['area']]);
fputcsv($out, ['Competencia', $comp['nombre']]);
fputcsv($out, []);
fputcsv($out, ['Estudiante','AD','A','B','C']);
foreach($rows as $r){
  $mark = ['','','',''];
  if ($r['nota']==='AD') $mark=[ 'X','','','' ];
  elseif ($r['nota']==='A') $mark=[ '','X','','' ];
  elseif ($r['nota']==='B') $mark=[ '','','X','' ];
  elseif ($r['nota']==='C') $mark=[ '','','','X' ];
  fputcsv($out, array_merge([$r['estudiante']], $mark));
}
fclose($out);