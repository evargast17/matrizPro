<?php
$docentes=$pdo->query("SELECT id, CONCAT(apellidos,', ',nombres) AS n FROM docentes ORDER BY apellidos")->fetchAll();
$aulas=$pdo->query("SELECT id, CONCAT(nombre,' ',seccion,' - ',grado) AS n FROM aulas ORDER BY id DESC")->fetchAll();
$areas=$pdo->query("SELECT id, nombre FROM areas ORDER BY nombre")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
  q($pdo,"INSERT IGNORE INTO docente_aula_area(docente_id,aula_id,area_id) VALUES(?,?,?)",
    [$_POST['docente_id'],$_POST['aula_id'],$_POST['area_id']]);
}
$rows=$pdo->query("SELECT daa.id, d.apellidos, d.nombres, au.nombre as aula, au.seccion, au.grado, ar.nombre as area
FROM docente_aula_area daa
JOIN docentes d ON d.id=daa.docente_id
JOIN aulas au ON au.id=daa.aula_id
JOIN areas ar ON ar.id=daa.area_id
ORDER BY daa.id DESC")->fetchAll();
?>
<h4>Asignación Docente → Aula → Área</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-4"><select name="docente_id" class="form-select"><?php foreach($docentes as $d): ?><option value="<?=$d['id']?>"><?=$d['n']?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><select name="aula_id" class="form-select"><?php foreach($aulas as $a): ?><option value="<?=$a['id']?>"><?=$a['n']?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="area_id" class="form-select"><?php foreach($areas as $a): ?><option value="<?=$a['id']?>"><?=$a['nombre']?></option><?php endforeach; ?></select></div>
  <div class="col-md-1"><button class="btn btn-primary w-100">Agregar</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>Docente</th><th>Aula</th><th>Área</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['apellidos'].', '.$r['nombres']?></td><td><?=$r['aula'].' '.$r['seccion'].' - '.$r['grado']?></td><td><?=$r['area']?></td></tr><?php endforeach; ?>
</tbody></table>
