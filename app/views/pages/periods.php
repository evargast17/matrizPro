<?php
$anios=$pdo->query("SELECT id, anio FROM anios_academicos ORDER BY anio DESC")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
  q($pdo,"INSERT INTO periodos(nombre,fecha_inicio,fecha_fin,anio_id) VALUES(?,?,?,?)",
    [$_POST['nombre'],$_POST['inicio'],$_POST['fin'],$_POST['anio_id']]);
}
$rows=$pdo->query("SELECT p.*, a.anio FROM periodos p JOIN anios_academicos a ON a.id=p.anio_id ORDER BY p.id DESC")->fetchAll();
?>
<h4>Períodos</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-3"><input class="form-control" name="nombre" placeholder="Bimestre 1" required></div>
  <div class="col-md-2"><input type="date" class="form-control" name="inicio" required></div>
  <div class="col-md-2"><input type="date" class="form-control" name="fin" required></div>
  <div class="col-md-3"><select name="anio_id" class="form-select"><?php foreach($anios as $a): ?><option value="<?=$a['id']?>"><?=$a['anio']?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><button class="btn btn-primary">Agregar</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>Nombre</th><th>Inicio</th><th>Fin</th><th>Año</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['nombre']?></td><td><?=$r['fecha_inicio']?></td><td><?=$r['fecha_fin']?></td><td><?=$r['anio']?></td></tr><?php endforeach; ?>
</tbody></table>
