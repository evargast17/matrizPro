<?php
if($_SERVER['REQUEST_METHOD']==='POST'){
  q($pdo,"INSERT INTO areas(nombre) VALUES(?)",[ $_POST['nombre'] ]);
}
$rows=$pdo->query("SELECT * FROM areas ORDER BY id DESC")->fetchAll();
?>
<h4>Áreas</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-8"><input class="form-control" name="nombre" placeholder="Matemática" required></div>
  <div class="col-md-4"><button class="btn btn-primary w-100">Agregar</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>Nombre</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['nombre']?></td></tr><?php endforeach; ?>
</tbody></table>
