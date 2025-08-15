<?php
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(isset($_POST['create'])) q($pdo,"INSERT INTO anios_academicos(anio,activo) VALUES(?,?)",[ $_POST['anio'], isset($_POST['activo'])?1:0 ]);
  if(isset($_POST['active'])){ $pdo->exec("UPDATE anios_academicos SET activo=0"); q($pdo,"UPDATE anios_academicos SET activo=1 WHERE id=?",[$_POST['id']]); }
}
$rows = $pdo->query("SELECT * FROM anios_academicos ORDER BY anio DESC")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Años Académicos</h4>
</div>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-3"><input type="number" name="anio" class="form-control" placeholder="2025" required></div>
  <div class="col-md-2 form-check align-self-center"><input type="checkbox" class="form-check-input" name="activo" id="activo"><label class="form-check-label" for="activo">Activo</label></div>
  <div class="col-md-2"><button class="btn btn-primary" name="create">Agregar</button></div>
</form>
<table class="table table-striped data-table">
  <thead><tr><th>ID</th><th>Año</th><th>Activo</th><th></th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
  <tr><td><?=$r['id']?></td><td><?=$r['anio']?></td><td><?=$r['activo']?'Sí':'No'?></td>
    <td><?php if(!$r['activo']): ?><form method="post">
      <input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-success" name="active">Hacer activo</button>
    </form><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
