<?php
require __DIR__ . '/core.php';

require_login();

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard','years','periods','classrooms','students','teachers','areas','competencies','assignments','grading','users'];
if(!in_array($page,$allowed)) $page='dashboard';

include __DIR__.'/app/views/partials/layout_top.php';
include __DIR__."/app/views/pages/{$page}.php";
include __DIR__.'/app/views/partials/layout_bottom.php';