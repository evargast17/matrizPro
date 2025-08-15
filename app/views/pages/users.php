<?php if(!is_admin()){ echo '<div class="alert alert-warning">Solo administrador.</div>'; return; }
if($_SERVER['REQUEST_METHOD']==='POST'){
  $email = $_POST['email']; $role = $_POST['role']; $docente_id = $_POST['docente_id'] ?: null;
  $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
  q($pdo,"INSERT INTO users(email,password_hash,role,docente_id) VALUES(?,?,?,?)",[$email,$hash,$role,$docente_id]);
}
$docentes=$pdo->query("SELECT id, CONCAT(apellidos,', ',nombres) AS n FROM docentes ORDER BY apellidos")->fetchAll();
$rows=$pdo->query("SELECT u.*, d.apellidos, d.nombres FROM users u LEFT JOIN docentes d ON d.id=u.docente_id ORDER BY u.id DESC")->fetchAll();
?>
<h4>Usuarios</h4>
<form method="post" class="row g-2 mb-3">
  <div class="col-md-3"><input class="form-control" name="email" placeholder="email@colegio.com" required></div>
  <div class="col-md-2"><input type="password" class="form-control" name="password" placeholder="Contraseña" required></div>
  <div class="col-md-2"><select name="role" class="form-select"><option value="admin">Admin</option><option value="docente">Docente</option></select></div>
  <div class="col-md-3"><select name="docente_id" class="form-select"><option value="">(sin docente)</option><?php foreach($docentes as $d): ?><option value="<?=$d['id']?>"><?=$d['n']?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Crear</button></div>
</form>
<table class="table table-striped data-table"><thead><tr><th>Email</th><th>Rol</th><th>Docente</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['email']?></td><td><?=$r['role']?></td><td><?=($r['apellidos']? $r['apellidos'].', '.$r['nombres'] : '—')?></td></tr><?php endforeach; ?>
</tbody></table>
