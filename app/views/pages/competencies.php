<?php
$areas=$pdo->query("SELECT id, nombre FROM areas ORDER BY nombre")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
  q($pdo,"INSERT INTO competencias(area_id,nombre) VALUES(?,?)",[ $_POST['area_id'], $_POST['nombre'] ]);
}
$rows=$pdo->query("SELECT c.*, a.nombre AS area FROM competencias c JOIN areas a ON a.id=c.area_id ORDER BY c.id DESC")->fetchAll();
?>
<h4>Competencias</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-5"><select name="area_id" class="form-select"><?php foreach($areas as $a): ?><option value="<?=$a['id']?>"><?=$a['nombre']?></option><?php endforeach; ?></select></div>
  <div class="col-md-5"><input class="form-control" name="nombre" placeholder="Resuelve problemas..." required></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Agregar</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>Área</th><th>Competencia</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['area']?></td><td><?=$r['nombre']?></td></tr><?php endforeach; ?>
</tbody></table>
