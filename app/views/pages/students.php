<?php
$aulas=$pdo->query("SELECT id, CONCAT(nombre,' ',seccion,' - ',grado) AS n FROM aulas ORDER BY id DESC")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
  q($pdo,"INSERT INTO estudiantes(dni,nombres,apellidos,aula_id) VALUES(?,?,?,?)",
    [$_POST['dni'],$_POST['nombres'],$_POST['apellidos'],$_POST['aula_id']]);
}
$rows=$pdo->query("SELECT e.*, CONCAT(a.nombre,' ',a.seccion,' - ',a.grado) AS aula FROM estudiantes e JOIN aulas a ON a.id=e.aula_id ORDER BY e.id DESC")->fetchAll();
?>
<h4>Estudiantes</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-2"><input class="form-control" name="dni" placeholder="DNI"></div>
  <div class="col-md-3"><input class="form-control" name="nombres" placeholder="Nombres" required></div>
  <div class="col-md-3"><input class="form-control" name="apellidos" placeholder="Apellidos" required></div>
  <div class="col-md-3"><select name="aula_id" class="form-select"><?php foreach($aulas as $a): ?><option value="<?=$a['id']?>"><?=$a['n']?></option><?php endforeach; ?></select></div>
  <div class="col-md-1"><button class="btn btn-primary w-100">Agregar</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>DNI</th><th>Estudiante</th><th>Aula</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['dni']?></td><td><?=$r['apellidos'].', '.$r['nombres']?></td><td><?=$r['aula']?></td></tr><?php endforeach; ?>
</tbody></table>
