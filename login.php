<?php
require __DIR__.'/core.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $email = $_POST['email'] ?? '';
  $pass  = $_POST['password'] ?? '';
  $u = q($pdo,"SELECT * FROM users WHERE email=? LIMIT 1",[$email])->fetch();
  if($u && password_verify($pass, $u['password_hash'])){
    $_SESSION['user'] = ['id'=>$u['id'],'email'=>$u['email'],'role'=>$u['role'],'docente_id'=>$u['docente_id']];
    header('Location: index.php'); exit;
  } else {
    $err = "Credenciales inválidas";
  }
}
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Acceder - Matriz Escolar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="public/assets/css/auth.css" rel="stylesheet">
</head><body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4">
          <h3 class="mb-3 text-center">Matriz Escolar</h3>
          <p class="text-muted text-center mb-4">Ingresa tus credenciales</p>
          <?php if(!empty($err)): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>
          <form method="post" class="needs-validation" novalidate>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" value="admin@demo.com" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Contraseña</label>
              <input type="password" class="form-control" name="password" value="admin123" required>
            </div>
            <button class="btn btn-primary w-100 py-2">Ingresar</button>
          </form>
        </div>
        <div class="card-footer text-center small text-muted">© <?=date('Y')?> Colegio</div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>