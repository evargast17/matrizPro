<?php $me = auth(); ?>
<!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Matriz Escolar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/v/bs5/dt-2.0.7/b-3.0.2/fh-4.0.1/r-3.0.3/datatables.min.css" rel="stylesheet"/>
<link href="public/assets/css/app.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head><body>
<div class="d-flex">
  <aside class="sidebar bg-dark text-white p-3">
    <div class="fs-5 fw-bold mb-4"><i class="bi bi-grid"></i> Matriz</div>
    <ul class="nav nav-pills flex-column gap-1">
      <li class="nav-item"><a class="nav-link text-white" href="index.php?page=dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
      <li class="text-uppercase small text-secondary mt-3">Académico</li>
      <li><a class="nav-link text-white" href="index.php?page=years"><i class="bi bi-calendar3 me-2"></i>Años</a></li>
      <li><a class="nav-link text-white" href="index.php?page=periods"><i class="bi bi-hourglass-split me-2"></i>Períodos</a></li>
      <li><a class="nav-link text-white" href="index.php?page=classrooms"><i class="bi bi-door-open me-2"></i>Aulas</a></li>
      <li class="text-uppercase small text-secondary mt-3">Personas</li>
      <li><a class="nav-link text-white" href="index.php?page=students"><i class="bi bi-people me-2"></i>Estudiantes</a></li>
      <li><a class="nav-link text-white" href="index.php?page=teachers"><i class="bi bi-person-badge me-2"></i>Docentes</a></li>
      <li class="text-uppercase small text-secondary mt-3">Currículo</li>
      <li><a class="nav-link text-white" href="index.php?page=areas"><i class="bi bi-book me-2"></i>Áreas</a></li>
      <li><a class="nav-link text-white" href="index.php?page=competencies"><i class="bi bi-list-check me-2"></i>Competencias</a></li>
      <li><a class="nav-link text-white" href="index.php?page=assignments"><i class="bi bi-link-45deg me-2"></i>Asignaciones</a></li>
      <li class="text-uppercase small text-secondary mt-3">Evaluación</li>
      <li><a class="nav-link text-white" href="index.php?page=grading"><i class="bi bi-ui-checks-grid me-2"></i>Matriz</a></li>
      <?php if(is_admin()): ?>
      <li class="text-uppercase small text-secondary mt-3">Sistema</li>
      <li><a class="nav-link text-white" href="index.php?page=users"><i class="bi bi-shield-lock me-2"></i>Usuarios</a></li>
      <?php endif; ?>
    </ul>
  </aside>
  <main class="flex-grow-1">
    <nav class="navbar navbar-light bg-white border-bottom px-3 sticky-top">
      <div class="ms-auto d-flex align-items-center gap-3">
        <span class="text-muted small"><i class="bi bi-person-circle me-1"></i><?=e($me['email'])?></span>
        <a class="btn btn-outline-danger btn-sm" href="logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>
      </div>
    </nav>
    <div class="container-fluid p-4">