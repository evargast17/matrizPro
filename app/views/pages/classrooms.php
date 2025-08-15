<?php
$anios = $pdo->query("SELECT id, anio FROM anios_academicos ORDER BY anio DESC")->fetchAll();
$anio_act = $pdo->query("SELECT id FROM anios_academicos WHERE activo=1 LIMIT 1")->fetchColumn();
if($_SERVER['REQUEST_METHOD']==='POST'){
  q($pdo,"INSERT INTO aulas(nombre,seccion,grado,anio_id) VALUES(?,?,?,?)",
    [$_POST['nombre'],$_POST['seccion'],$_POST['grado'],$_POST['anio_id']]);
}
$rows=$pdo->query("SELECT a.*, aa.anio FROM aulas a JOIN anios_academicos aa ON aa.id=a.anio_id ORDER BY a.id DESC")->fetchAll();
?>
<h4>Aulas</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-3"><input class="form-control" name="nombre" placeholder="Primaria 2" required></div>
  <div class="col-md-2"><input class="form-control" name="seccion" placeholder="A"></div>
  <div class="col-md-2"><input class="form-control" name="grado" placeholder="2°"></div>
  <div class="col-md-3"><select name="anio_id" class="form-select"><?php foreach($anios as $a): ?><option value="<?=$a['id']?>" <?=($a['id']==$anio_act)?'selected':''?>><?=$a['anio']?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><button class="btn btn-primary">Agregar</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>Nombre</th><th>Sección</th><th>Grado</th><th>Año</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['nombre']?></td><td><?=$r['seccion']?></td><td><?=$r['grado']?></td><td><?=$r['anio']?></td></tr><?php endforeach; ?>
</tbody></table>
