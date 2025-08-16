<?php 
$me = auth(); 
if (!$me) {
    header('Location: login.php');
    exit;
}

$user_initials = strtoupper(substr($me['email'], 0, 2));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matriz Escolar PRO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/dt-2.0.7/b-3.0.2/fh-4.0.1/r-3.0.3/datatables.min.css" rel="stylesheet"/>
    <link href="public/assets/css/app.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <h4><i class="bi bi-mortarboard me-2"></i>Matriz Escolar PRO</h4>
            </div>
            
            <nav class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">
                    <i class="bi bi-speedometer2"></i>Dashboard
                </a>
            </nav>

            <?php if (in_array($me['role'], ['admin', 'coordinadora'])): ?>
            <nav class="nav-section">
                <div class="nav-section-title">Configuración</div>
                <a class="nav-link <?= $page === 'years' ? 'active' : '' ?>" href="index.php?page=years">
                    <i class="bi bi-calendar3"></i>Años Académicos
                </a>
                <a class="nav-link <?= $page === 'periods' ? 'active' : '' ?>" href="index.php?page=periods">
                    <i class="bi bi-hourglass-split"></i>Períodos
                </a>
                <a class="nav-link <?= $page === 'classrooms' ? 'active' : '' ?>" href="index.php?page=classrooms">
                    <i class="bi bi-door-open"></i>Aulas
                </a>
            </nav>
            <?php endif; ?>

            <?php if (in_array($me['role'], ['admin', 'coordinadora'])): ?>
            <nav class="nav-section">
                <div class="nav-section-title">Personas</div>
                <a class="nav-link <?= $page === 'students' ? 'active' : '' ?>" href="index.php?page=students">
                    <i class="bi bi-people"></i>Estudiantes
                </a>
                <a class="nav-link <?= $page === 'teachers' ? 'active' : '' ?>" href="index.php?page=teachers">
                    <i class="bi bi-person-badge"></i>Docentes
                </a>
            </nav>
            <?php endif; ?>

            <?php if (in_array($me['role'], ['admin', 'coordinadora'])): ?>
            <nav class="nav-section">
                <div class="nav-section-title">Currículo</div>
                <a class="nav-link <?= $page === 'areas' ? 'active' : '' ?>" href="index.php?page=areas">
                    <i class="bi bi-book"></i>Áreas Curriculares
                </a>
                <a class="nav-link <?= $page === 'competencies' ? 'active' : '' ?>" href="index.php?page=competencies">
                    <i class="bi bi-list-check"></i>Competencias
                </a>
                <a class="nav-link <?= $page === 'assignments' ? 'active' : '' ?>" href="index.php?page=assignments">
                    <i class="bi bi-link-45deg"></i>Asignaciones
                </a>
            </nav>
            <?php endif; ?>

            <nav class="nav-section">
                <div class="nav-section-title">Evaluación</div>
                <a class="nav-link <?= $page === 'grading' ? 'active' : '' ?>" href="index.php?page=grading">
                    <i class="bi bi-ui-checks-grid"></i>Matriz de Calificaciones
                </a>
                <?php if (in_array($me['role'], ['admin', 'coordinadora'])): ?>
                <a class="nav-link <?= $page === 'consolidado' ? 'active' : '' ?>" href="index.php?page=consolidado">
                    <i class="bi bi-table"></i>Consolidado SIAGIE
                </a>
                <a class="nav-link <?= $page === 'boletas' ? 'active' : '' ?>" href="index.php?page=boletas">
                    <i class="bi bi-file-pdf"></i>Generar Boletas
                </a>
                <?php endif; ?>
                <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="index.php?page=reports">
                    <i class="bi bi-graph-up"></i>Reportes
                </a>
            </nav>

            <?php if ($me['role'] === 'admin'): ?>
            <nav class="nav-section">
                <div class="nav-section-title">Administración</div>
                <a class="nav-link <?= $page === 'users' ? 'active' : '' ?>" href="index.php?page=users">
                    <i class="bi bi-shield-lock"></i>Usuarios
                </a>
            </nav>
            <?php endif; ?>
        </aside>

        <!-- Contenido principal -->
        <main class="flex-grow-1 main-content">
            <!-- Header -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white main-header sticky-top">
                <div class="container-fluid px-4">
                    <!-- Botón menú móvil -->
                    <button class="btn btn-link d-lg-none p-0 me-3" type="button" onclick="toggleSidebar()">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    
                    <!-- Título de página -->
                    <div class="page-title">
                        <h5 class="mb-0 fw-bold text-dark">
                            <?php
                            $titles = [
                                'dashboard' => 'Dashboard',
                                'years' => 'Años Académicos',
                                'periods' => 'Períodos',
                                'classrooms' => 'Aulas',
                                'students' => 'Estudiantes',
                                'teachers' => 'Docentes',
                                'areas' => 'Áreas Curriculares',
                                'competencies' => 'Competencias',
                                'assignments' => 'Asignaciones',
                                'grading' => 'Matriz de Calificaciones',
                                'consolidado' => 'Consolidado SIAGIE',
                                'boletas' => 'Generar Boletas',
                                'reports' => 'Reportes',
                                'users' => 'Gestión de Usuarios'
                            ];
                            echo $titles[$page] ?? 'Sistema Educativo';
                            ?>
                        </h5>
                    </div>

                    <!-- Info del usuario -->
                    <div class="ms-auto">
                        <div class="user-info">
                            <div class="user-avatar"><?= $user_initials ?></div>
                            <div class="user-details d-none d-md-block">
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($me['docente_nombres'] ? 
                                        $me['docente_nombres'] . ' ' . $me['docente_apellidos'] : 
                                        $me['email']) ?>
                                </div>
                                <div class="text-muted small">
                                    <?php
                                    $role_names = [
                                        'admin' => 'Administrador',
                                        'coordinadora' => 'Coordinadora Académica',
                                        'tutor' => 'Tutor de Aula',
                                        'docente_area' => 'Docente de Área',
                                        'docente_taller' => 'Docente de Taller'
                                    ];
                                    echo $role_names[$me['role']] ?? ucfirst($me['role']);
                                    ?>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-dark p-1" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h6 class="dropdown-header">Mi Cuenta</h6></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Configuración</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Contenido de la página -->
            <div class="container-fluid p-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="index.php" class="text-decoration-none">
                                <i class="bi bi-house me-1"></i>Inicio
                            </a>
                        </li>
                        <li class="breadcrumb-item active">
                            <?= $titles[$page] ?? 'Página' ?>
                        </li>
                    </ol>
                </nav>

                <!-- Mensajes flash (si los hay) -->
                <?php if (isset($_SESSION['flash_messages'])): ?>
                    <?php foreach ($_SESSION['flash_messages'] as $flash): ?>
                        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($flash['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['flash_messages']); ?>
                <?php endif; ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

// Cerrar sidebar al hacer click fuera (móvil)
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.querySelector('[onclick="toggleSidebar()"]');
    
    if (window.innerWidth <= 768 && 
        sidebar && sidebarToggle &&
        !sidebar.contains(e.target) && 
        !sidebarToggle.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

// Auto-ocultar alertas después de 5 segundos
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>