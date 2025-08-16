<?php
require __DIR__ . '/core.php';

// Verificar que el usuario esté logueado
require_login();

// Obtener la página solicitada
$page = $_GET['page'] ?? 'dashboard';

// Páginas permitidas
$allowed_pages = ['dashboard', 'years', 'periods', 'classrooms', 'students', 'teachers', 'areas', 'competencies', 'assignments', 'grading', 'consolidado', 'boletas', 'reports', 'users'];

// Verificar que la página sea válida
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Verificar permisos básicos por página
$me = auth();
$access_denied = false;

switch ($page) {
    case 'users':
        if ($me['role'] !== 'admin') {
            $access_denied = true;
        }
        break;
    case 'years':
    case 'periods':
    case 'classrooms':
    case 'teachers':
        if (!in_array($me['role'], ['admin', 'coordinadora'])) {
            $access_denied = true;
        }
        break;
    case 'students':
    case 'areas':
    case 'competencies':
    case 'assignments':
        if (!in_array($me['role'], ['admin', 'coordinadora'])) {
            $access_denied = true;
        }
        break;
}

// Si no tiene acceso, redirigir al dashboard
if ($access_denied) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Incluir el layout superior
include __DIR__ . '/app/views/partials/layout_top.php';

// Incluir la página solicitada
$page_file = __DIR__ . "/app/views/pages/{$page}.php";
if (file_exists($page_file)) {
    include $page_file;
} else {
    // Página no encontrada
    echo '<div class="alert alert-warning">
            <h4>Página no encontrada</h4>
            <p>La página solicitada no existe o está en desarrollo.</p>
            <a href="index.php" class="btn btn-primary">Volver al Dashboard</a>
          </div>';
}

// Incluir el layout inferior
include __DIR__ . '/app/views/partials/layout_bottom.php';
?>