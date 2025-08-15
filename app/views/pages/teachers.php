<?php
if($_SERVER['REQUEST_METHOD']==='POST'){
  q($pdo,"INSERT INTO docentes(dni,nombres,apellidos) VALUES(?,?,?)",[ $_POST['dni'],$_POST['nombres'],$_POST['apellidos'] ]);
}
$rows=$pdo->query("SELECT * FROM docentes ORDER BY id DESC")->fetchAll();
?>
<h4>Docentes</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-2"><input class="form-control" name="dni" placeholder="DNI"></div>
  <div class="col-md-4"><input class="form-control" name="nombres" placeholder="Nombres" required></div>
  <div class="col-md-4"><input class="form-control" name="apellidos" placeholder="Apellidos" required></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Agregar</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>DNI</th><th>Docente</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['dni']?></td><td><?=$r['apellidos'].', '.$r['nombres']?></td></tr><?php endforeach; ?>
</tbody></table>
