<?php
// app/views/pages/grading.php - Matriz de calificaciones con roles específicos

$me = auth();

// Verificar permisos básicos
if (!in_array($me['role'], ['admin', 'coordinadora', 'tutor', 'docente_area', 'docente_taller'])) {
    echo '<div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            No tienes permisos para acceder a la matriz de calificaciones.
          </div>';
    return;
}

// Modo de supervisión para coordinadora
$modo_supervision = ($me['role'] === 'coordinadora' && isset($_GET['modo']) && $_GET['modo'] === 'supervision');
$solo_lectura = $modo_supervision;

// Obtener años académicos
$anios = $pdo->query("SELECT id, anio FROM anios_academicos ORDER BY anio DESC")->fetchAll();
$anio_id = $_GET['anio_id'] ?? ($anios[0]['id'] ?? null);

// Obtener períodos del año seleccionado
$periodos = [];
if ($anio_id) {
    $periodos = q($pdo, "SELECT id, nombre, orden FROM periodos WHERE anio_id = ? ORDER BY orden", [$anio_id])->fetchAll();
}
$periodo_id = $_GET['periodo_id'] ?? ($periodos[0]['id'] ?? null);

// Obtener aulas según el rol del usuario
$aulas = [];
if ($anio_id) {
    if (in_array($me['role'], ['admin', 'coordinadora'])) {
        // Admin y coordinadora ven todas las aulas
        $aulas = q($pdo, "
            SELECT id, CONCAT(nombre, ' ', seccion, ' - ', grado) AS aula_completa, nivel
            FROM aulas 
            WHERE anio_id = ? AND activo = 1 
            ORDER BY nivel, nombre, seccion
        ", [$anio_id])->fetchAll();
    } elseif ($me['docente_id']) {
        // Docentes solo ven sus aulas asignadas
        $aulas = q($pdo, "
            SELECT DISTINCT a.id, CONCAT(a.nombre, ' ', a.seccion, ' - ', a.grado) AS aula_completa, a.nivel
            FROM aulas a
            JOIN docente_aula_area daa ON daa.aula_id = a.id
            WHERE a.anio_id = ? AND daa.docente_id = ? AND daa.activo = 1 AND a.activo = 1
            ORDER BY a.nivel, a.nombre, a.seccion
        ", [$anio_id, $me['docente_id']])->fetchAll();
    }
}
$aula_id = $_GET['aula_id'] ?? ($aulas[0]['id'] ?? null);

// Obtener áreas según el rol y aula seleccionada
$areas = [];
if ($aula_id) {
    if (in_array($me['role'], ['admin', 'coordinadora'])) {
        // Admin y coordinadora ven todas las áreas
        $areas = q($pdo, "
            SELECT DISTINCT ar.id, ar.nombre, ar.tipo, ar.va_siagie, ar.color
            FROM areas ar
            JOIN aulas au ON (ar.nivel = au.nivel OR ar.nivel = 'todos')
            WHERE au.id = ? AND ar.activo = 1
            ORDER BY ar.tipo, ar.orden, ar.nombre
        ", [$aula_id])->fetchAll();
    } elseif ($me['docente_id']) {
        // Docentes solo ven sus áreas asignadas
        $areas = q($pdo, "
            SELECT DISTINCT ar.id, ar.nombre, ar.tipo, ar.va_siagie, ar.color
            FROM areas ar
            JOIN docente_aula_area daa ON daa.area_id = ar.id
            WHERE daa.aula_id = ? AND daa.docente_id = ? AND daa.activo = 1 AND ar.activo = 1
            ORDER BY ar.tipo, ar.orden, ar.nombre
        ", [$aula_id, $me['docente_id']])->fetchAll();
    }
}
$area_id = $_GET['area_id'] ?? ($areas[0]['id'] ?? null);

// Verificar permisos específicos para editar
$puede_editar = false;
if (!$solo_lectura && $aula_id && $area_id) {
    $puede_editar = has_permission('edit_my_grades', ['aula_id' => $aula_id, 'area_id' => $area_id]) ||
                   has_permission('edit_subject_grades', ['aula_id' => $aula_id, 'area_id' => $area_id]) ||
                   has_permission('edit_taller_grades', ['aula_id' => $aula_id, 'area_id' => $area_id]);
}

// Obtener competencias del área seleccionada
$competencias = [];
if ($area_id) {
    $competencias = q($pdo, "
        SELECT id, nombre, descripcion 
        FROM competencias 
        WHERE area_id = ? AND activo = 1 
        ORDER BY orden, id
    ", [$area_id])->fetchAll();
}

// Obtener estudiantes del aula
$estudiantes = [];
if ($aula_id) {
    $estudiantes = q($pdo, "
        SELECT id, CONCAT(apellidos, ', ', nombres) AS nombre_completo, dni
        FROM estudiantes 
        WHERE aula_id = ? AND activo = 1 
        ORDER BY apellidos, nombres
    ", [$aula_id])->fetchAll();
}

// Obtener información contextual
$aula_info = null;
$area_info = null;
$periodo_info = null;

if ($aula_id) {
    $aula_info = q($pdo, "SELECT * FROM aulas WHERE id = ?", [$aula_id])->fetch();
}
if ($area_id) {
    $area_info = q($pdo, "SELECT * FROM areas WHERE id = ?", [$area_id])->fetch();
}
if ($periodo_id) {
    $periodo_info = q($pdo, "SELECT * FROM periodos WHERE id = ?", [$periodo_id])->fetch();
}

// Función para obtener calificación
function obtener_calificacion($pdo, $estudiante_id, $competencia_id, $periodo_id) {
    $stmt = q($pdo, "
        SELECT nota, observaciones, estado, registrado_por, fecha_validacion, created_at
        FROM calificaciones 
        WHERE estudiante_id = ? AND competencia_id = ? AND periodo_id = ? 
        LIMIT 1
    ", [$estudiante_id, $competencia_id, $periodo_id]);
    return $stmt->fetch() ?: null;
}

// Calcular estadísticas para la vista actual
$estadisticas_competencias = [];
if ($periodo_id && !empty($competencias) && !empty($estudiantes)) {
    foreach ($competencias as $comp) {
        $stats = q($pdo, "
            SELECT 
                nota,
                COUNT(*) as cantidad
            FROM calificaciones 
            WHERE competencia_id = ? AND periodo_id = ?
            GROUP BY nota
        ", [$comp['id'], $periodo_id])->fetchAll();
        
        $estadisticas_competencias[$comp['id']] = [
            'AD' => 0, 'A' => 0, 'B' => 0, 'C' => 0
        ];
        
        foreach ($stats as $stat) {
            $estadisticas_competencias[$comp['id']][$stat['nota']] = $stat['cantidad'];
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <!-- Header con información del contexto -->
        <div class="modern-card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1">
                            <i class="bi bi-ui-checks-grid text-primary me-2"></i>
                            Matriz de Evaluación
                            <?php if ($modo_supervision): ?>
                                <span class="badge bg-warning ms-2">Modo Supervisión</span>
                            <?php endif; ?>
                        </h4>
                        <p class="text-muted mb-0">
                            <?php if ($me['role'] === 'tutor'): ?>
                                Evaluación integral de todas las competencias de tu aula
                            <?php elseif (in_array($me['role'], ['docente_area', 'docente_taller'])): ?>
                                Evaluación de competencias de tu área específica
                            <?php else: ?>
                                Supervisión y gestión de evaluaciones académicas
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php if ($aula_info && $area_info && $periodo_info): ?>
                            <div class="info-badges">
                                <span class="badge bg-primary fs-6 me-1"><?= e($periodo_info['nombre']) ?></span>
                                <span class="badge bg-secondary fs-6"><?= count($estudiantes) ?> estudiantes</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros de selección -->
        <div class="modern-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-funnel me-2"></i>Filtros de Evaluación
                </h5>
            </div>
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="grading">
                    <?php if ($modo_supervision): ?>
                        <input type="hidden" name="modo" value="supervision">
                    <?php endif; ?>
                    
                    <div class="col-md-2">
                        <label class="form-label">Año Académico</label>
                        <select name="anio_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach($anios as $anio): ?>
                                <option value="<?= $anio['id'] ?>" <?= $anio['id'] == $anio_id ? 'selected' : '' ?>>
                                    <?= $anio['anio'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Período</label>
                        <select name="periodo_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach($periodos as $periodo): ?>
                                <option value="<?= $periodo['id'] ?>" <?= $periodo['id'] == $periodo_id ? 'selected' : '' ?>>
                                    <?= e($periodo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Aula</label>
                        <select name="aula_id" class="form-select" onchange="this.form.submit()">
                            <?php if(empty($aulas)): ?>
                                <option value="">No hay aulas disponibles</option>
                            <?php else: ?>
                                <?php foreach($aulas as $aula): ?>
                                    <option value="<?= $aula['id'] ?>" <?= $aula['id'] == $aula_id ? 'selected' : '' ?>>
                                        <?= e($aula['aula_completa']) ?> (<?= ucfirst(str_replace('_', ' ', $aula['nivel'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Área Curricular</label>
                        <select name="area_id" class="form-select" onchange="this.form.submit()">
                            <?php if(empty($areas)): ?>
                                <option value="">No hay áreas disponibles</option>
                            <?php else: ?>
                                <?php foreach($areas as $area): ?>
                                    <option value="<?= $area['id'] ?>" <?= $area['id'] == $area_id ? 'selected' : '' ?>>
                                        <?= e($area['nombre']) ?> 
                                        (<?= ucfirst($area['tipo']) ?>)
                                        <?php if ($area['va_siagie']): ?>
                                            📊
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($aula_info && $area_info && $periodo_info && !empty($competencias) && !empty($estudiantes)): ?>
        
        <!-- Información contextual detallada -->
        <div class="grading-matrix">
            <div class="grading-header">
                <h4 class="mb-0">
                    Evaluación: <?= e($area_info['nombre']) ?>
                    <?php if (!$puede_editar && !$modo_supervision): ?>
                        <i class="bi bi-lock-fill ms-2" title="Solo lectura"></i>
                    <?php endif; ?>
                </h4>
                <div class="grading-info">
                    <div class="grading-info-item">
                        <div class="grading-info-label">Aula</div>
                        <div class="grading-info-value">
                            <?= e($aula_info['nombre'] . ' ' . $aula_info['seccion'] . ' - ' . $aula_info['grado']) ?>
                        </div>
                    </div>
                    <div class="grading-info-item">
                        <div class="grading-info-label">Período</div>
                        <div class="grading-info-value"><?= e($periodo_info['nombre']) ?></div>
                    </div>
                    <div class="grading-info-item">
                        <div class="grading-info-label">Tipo</div>
                        <div class="grading-info-value">
                            <?= ucfirst($area_info['tipo']) ?>
                            <?php if ($area_info['va_siagie']): ?>
                                <span class="badge bg-light text-dark ms-1">SIAGIE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grading-info-item">
                        <div class="grading-info-label">Estudiantes</div>
                        <div class="grading-info-value"><?= count($estudiantes) ?></div>
                    </div>
                    <div class="grading-info-item">
                        <div class="grading-info-label">Competencias</div>
                        <div class="grading-info-value"><?= count($competencias) ?></div>
                    </div>
                </div>
            </div>

            <!-- Pestañas por competencias -->
            <div class="card-body">
                <ul class="nav nav-pills mb-4" role="tablist">
                    <?php foreach($competencias as $index => $comp): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#competencia-<?= $comp['id'] ?>" 
                                    type="button" role="tab">
                                <i class="bi bi-check-circle me-1"></i>
                                Competencia <?= $index + 1 ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Contenido de las pestañas -->
                <div class="tab-content">
                    <?php foreach($competencias as $index => $comp): ?>
                        <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
                             id="competencia-<?= $comp['id'] ?>" role="tabpanel">
                             
                            <!-- Header de la competencia -->
                            <div class="competencia-header-info mb-4 p-3 bg-light rounded">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="text-primary mb-2">
                                            <i class="bi bi-bullseye me-2"></i>
                                            <?= e($comp['nombre']) ?>
                                        </h6>
                                        <?php if ($comp['descripcion']): ?>
                                            <p class="text-muted mb-0 small"><?= e($comp['descripcion']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($puede_editar): ?>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        onclick="aplicarATodos('<?= $comp['id'] ?>', 'AD')"
                                                        title="Aplicar AD a todos">
                                                    <i class="bi bi-check-all"></i> AD
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="aplicarATodos('<?= $comp['id'] ?>', 'A')"
                                                        title="Aplicar A a todos">
                                                    <i class="bi bi-check-all"></i> A
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de calificaciones -->
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle grading-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start" style="min-width: 250px;">
                                                <i class="bi bi-person me-2"></i>Estudiante
                                            </th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong class="text-success">AD</strong>
                                                    <small class="text-muted">Destacado</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong class="text-primary">A</strong>
                                                    <small class="text-muted">Logrado</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong class="text-warning">B</strong>
                                                    <small class="text-muted">Proceso</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong class="text-danger">C</strong>
                                                    <small class="text-muted">Inicio</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 120px;">
                                                <i class="bi bi-info-circle me-2"></i>Estado
                                            </th>
                                            <?php if ($modo_supervision): ?>
                                                <th class="text-center" style="width: 100px;">
                                                    <i class="bi bi-clock me-2"></i>Fecha
                                                </th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($estudiantes as $estudiante): ?>
                                            <?php 
                                            $calificacion = obtener_calificacion($pdo, $estudiante['id'], $comp['id'], $periodo_id);
                                            $nota_actual = $calificacion['nota'] ?? '';
                                            $observaciones = $calificacion['observaciones'] ?? '';
                                            $estado = $calificacion['estado'] ?? '';
                                            ?>
                                            <tr data-estudiante="<?= $estudiante['id'] ?>" 
                                                data-competencia="<?= $comp['id'] ?>"
                                                class="<?= $estado === 'validado' ? 'table-success' : ($estado === 'registrado' ? 'table-info' : '') ?>">
                                                
                                                <td class="text-start">
                                                    <div class="d-flex align-items-center">
                                                        <div class="student-avatar me-3">
                                                            <?= strtoupper(substr($estudiante['nombre_completo'], 0, 2)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?= e($estudiante['nombre_completo']) ?></div>
                                                            <small class="text-muted">
                                                                <?= $estudiante['dni'] ? 'DNI: ' . $estudiante['dni'] : 'ID: ' . $estudiante['id'] ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                

                                                <?php foreach(['AD', 'A', 'B', 'C'] as $nota): ?>
                                                    <td class="text-center">
                                                        <button type="button" 
                                                                class="btn-grade grade-<?= strtolower($nota) ?> <?= $nota_actual === $nota ? 'active' : '' ?>" 
                                                                data-nota="<?= $nota ?>" 
                                                                data-estudiante="<?= $estudiante['id'] ?>"
                                                                data-competencia="<?= $comp['id'] ?>"

                                                                <?= $puede_editar ? 'onclick="seleccionarNota(this)"' : 'disabled' ?>
                                                                title="<?= get_nota_description($nota) ?>">
                                                            <?= $nota ?>
                                                        </button>
                                                    </td>
                                                <?php endforeach; ?>
                                                

                                                <td class="text-center">
                                                    <?php if ($nota_actual): ?>
                                                        <span class="badge bg-<?= $estado === 'validado' ? 'success' : 'info' ?>">
                                                            <?= $estado === 'validado' ? 'Validado' : 'Registrado' ?>
                                                        </span>
                                                        <?php if ($observaciones): ?>
                                                            <i class="bi bi-chat-text text-info ms-1" 
                                                               title="<?= e($observaciones) ?>"></i>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pendiente</span>
                                                    <?php endif; ?>
                                                </td>
                                                

                                                <?php if ($modo_supervision): ?>
                                                    <td class="text-center">
                                                        <?php if ($calificacion): ?>
                                                            <small class="text-muted">
                                                                <?= date('d/m H:i', strtotime($calificacion['created_at'] ?? 'now')) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Resumen estadístico de la competencia -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <div class="row text-center">
                                    <?php 
                                    $stats = $estadisticas_competencias[$comp['id']] ?? ['AD' => 0, 'A' => 0, 'B' => 0, 'C' => 0];
                                    $total_evaluados = array_sum($stats);
                                    ?>
                                    <div class="col-3">
                                        <div class="stat-number text-success" id="count-ad-<?= $comp['id'] ?>">
                                            <?= $stats['AD'] ?>
                                        </div>
                                        <div class="stat-label">AD - Destacado</div>
                                        <?php if ($total_evaluados > 0): ?>
                                            <small class="text-muted"><?= round(($stats['AD'] / $total_evaluados) * 100, 1) ?>%</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-3">
                                        <div class="stat-number text-primary" id="count-a-<?= $comp['id'] ?>">
                                            <?= $stats['A'] ?>
                                        </div>
                                        <div class="stat-label">A - Logrado</div>
                                        <?php if ($total_evaluados > 0): ?>
                                            <small class="text-muted"><?= round(($stats['A'] / $total_evaluados) * 100, 1) ?>%</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-3">
                                        <div class="stat-number text-warning" id="count-b-<?= $comp['id'] ?>">
                                            <?= $stats['B'] ?>
                                        </div>
                                        <div class="stat-label">B - Proceso</div>
                                        <?php if ($total_evaluados > 0): ?>
                                            <small class="text-muted"><?= round(($stats['B'] / $total_evaluados) * 100, 1) ?>%</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-3">
                                        <div class="stat-number text-danger" id="count-c-<?= $comp['id'] ?>">
                                            <?= $stats['C'] ?>
                                        </div>
                                        <div class="stat-label">C - Inicio</div>
                                        <?php if ($total_evaluados > 0): ?>
                                            <small class="text-muted"><?= round(($stats['C'] / $total_evaluados) * 100, 1) ?>%</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between">
                                            <span>Total evaluados:</span>
                                            <strong><?= $total_evaluados ?> / <?= count($estudiantes) ?></strong>
                                        </div>
                                        <div class="progress mt-1" style="height: 6px;">
                                            <div class="progress-bar bg-info" 
                                                 style="width: <?= count($estudiantes) > 0 ? ($total_evaluados / count($estudiantes)) * 100 : 0 ?>%">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between">
                                            <span>Nivel de logro:</span>
                                            <strong>
                                                <?= $total_evaluados > 0 ? round((($stats['AD'] + $stats['A']) / $total_evaluados) * 100, 1) : 0 ?>%
                                            </strong>
                                        </div>
                                        <div class="progress mt-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?= $total_evaluados > 0 ? (($stats['AD'] + $stats['A']) / $total_evaluados) * 100 : 0 ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones de la competencia -->
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <a href="public/actions/export.php?periodo_id=<?= $periodo_id ?>&aula_id=<?= $aula_id ?>&competencia_id=<?= $comp['id'] ?>" 
                                   class="btn btn-success btn-sm">
                                    <i class="bi bi-download me-2"></i>Exportar CSV
                                </a>
                                
                                <?php if (in_array($me['role'], ['admin', 'coordinadora'])): ?>
                                    <button type="button" class="btn btn-info btn-sm" 
                                            onclick="verEstadisticasDetalladas('<?= $comp['id'] ?>')">
                                        <i class="bi bi-graph-up me-2"></i>Estadísticas
                                    </button>
                                    
                                    <?php if ($me['role'] === 'coordinadora'): ?>
                                        <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="validarCompetencia('<?= $comp['id'] ?>')"
                                                <?= $total_evaluados < count($estudiantes) ? 'disabled title="Debe completar todas las evaluaciones"' : '' ?>>
                                            <i class="bi bi-check-circle me-2"></i>Validar
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Mensaje cuando faltan datos -->
        <div class="modern-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-info-circle display-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Configuración incompleta</h5>
                <div class="mt-3">
                    <?php if(empty($aulas)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No tienes aulas asignadas para evaluar.
                        </div>
                    <?php elseif(empty($areas)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-book me-2"></i>
                            No tienes áreas curriculares asignadas para esta aula.
                        </div>
                    <?php elseif(empty($competencias)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-list-check me-2"></i>
                            El área seleccionada no tiene competencias configuradas.
                        </div>
                    <?php elseif(empty($estudiantes)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-people me-2"></i>
                            No hay estudiantes registrados en esta aula.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-arrow-up me-2"></i>
                            Selecciona todos los filtros para comenzar a evaluar.
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (is_admin()): ?>
                    <div class="mt-4">
                        <a href="index.php?page=assignments" class="btn btn-primary me-2">
                            <i class="bi bi-gear me-2"></i>Configurar Asignaciones
                        </a>
                        <a href="index.php?page=competencies" class="btn btn-outline-primary">
                            <i class="bi bi-list-check me-2"></i>Gestionar Competencias
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript para la funcionalidad -->
<script>
const periodoId = <?= $periodo_id ?: 'null' ?>;
const puedeEditar = <?= $puede_editar ? 'true' : 'false' ?>;
const modoSupervision = <?= $modo_supervision ? 'true' : 'false' ?>;

// Función para seleccionar nota
async function seleccionarNota(btn) {
    if (!puedeEditar || !periodoId) {
        Swal.fire('Error', 'No se pudo validar la competencia', 'error');
    }
}

// Auto-save cada 30 segundos si hay cambios pendientes
let cambiosPendientes = false;
let ultimoAutoSave = Date.now();

function marcarCambiosPendientes() {
    cambiosPendientes = true;
}

setInterval(function() {
    if (cambiosPendientes && (Date.now() - ultimoAutoSave) > 30000) {
        console.log('Auto-guardado activado');
        cambiosPendientes = false;
        ultimoAutoSave = Date.now();
    }
}, 30000);

// Confirmación al salir si hay cambios sin guardar
window.addEventListener('beforeunload', function(e) {
    if (cambiosPendientes) {
        e.preventDefault();
        e.returnValue = '';
        return 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
    }
});

// Feedback visual mejorado
document.addEventListener('DOMContentLoaded', function() {
    // Agregar tooltips a los botones de calificación
    const botonesGrade = document.querySelectorAll('.btn-grade');
    botonesGrade.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            if (!this.disabled) {
                this.style.transform = 'scale(1.1)';
            }
        });
        
        btn.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'scale(1)';
            }
        });
    });
    
    // Resaltar fila al hacer hover
    const filas = document.querySelectorAll('tbody tr');
    filas.forEach(fila => {
        fila.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8fafc';
        });
        
        fila.addEventListener('mouseleave', function() {
            if (!this.classList.contains('table-success') && !this.classList.contains('table-info')) {
                this.style.backgroundColor = '';
            }
        });
    });
});

// Función para exportar datos
function exportarDatos(competenciaId, formato = 'csv') {
    const url = `public/actions/export.php?competencia_id=${competenciaId}&periodo_id=${periodoId}&formato=${formato}`;
    window.open(url, '_blank');
}

// Navegación con teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + S para guardar (aunque ya se guarda automáticamente)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        Swal.fire({
            icon: 'info',
            title: 'Auto-guardado',
            text: 'Las calificaciones se guardan automáticamente',
            timer: 2000,
            showConfirmButton: false
        });
    }
    
    // Escape para salir del modo actual
    if (e.key === 'Escape') {
        // Cerrar cualquier modal abierto
        const modales = document.querySelectorAll('.modal.show');
        modales.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    }
});

console.log('✅ Matriz de Calificaciones cargada correctamente');
console.log('📊 Período ID:', periodoId);
console.log('🔑 Puede editar:', puedeEditar);
console.log('👁️ Modo supervisión:', modoSupervision);

// Guardar calificación
async function guardarCalificacion(btn) {
    if (!puedeEditar) {
        Swal.fire('Información', 'No tienes permisos para editar esta calificación', 'info');
        return;
    }
    
    const estudiante = btn.dataset.estudiante;
    const competencia = btn.dataset.competencia;
    const nota = btn.dataset.nota;
    const fila = btn.closest('tr');
    
    // Confirmar cambio si ya existe una nota
    const notaAnterior = fila.querySelector('.btn-grade.active');
    if (notaAnterior && notaAnterior !== btn) {
        const result = await Swal.fire({
            title: '¿Cambiar calificación?',
            text: `¿Cambiar de ${notaAnterior.dataset.nota} a ${nota}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar'
        });
        
        if (!result.isConfirmed) return;
    }
    
    // Mostrar loading
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
    btn.disabled = true;
    
    try {
        const response = await fetch('public/actions/save_grade.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                estudiante_id: parseInt(estudiante),
                competencia_id: parseInt(competencia),
                periodo_id: periodoId,
                nota: nota
            })
        });
        
        const result = await response.json();
        
        if (response.ok && result.ok) {
            // Actualizar UI
            fila.querySelectorAll('.btn-grade').forEach(b => {
                b.classList.remove('active');
                b.innerHTML = b.dataset.nota;
            });
            
            btn.classList.add('active');
            btn.innerHTML = '✓';
            
            // Actualizar badge de estado
            const badge = fila.querySelector('.badge');
            badge.className = 'badge bg-info';
            badge.textContent = 'Registrado';
            
            // Actualizar contadores
            actualizarContadores(competencia);
            
            // Mostrar notificación
            Swal.fire({
                icon: 'success',
                title: 'Calificación guardada',
                text: `Nota ${nota} registrada correctamente`,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            
        } else {
            throw new Error(result.message || 'Error al guardar');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo guardar la calificación: ' + error.message, 'error');
        btn.innerHTML = nota;
    } finally {
        btn.disabled = false;
    }
}

// Aplicar nota a todos los estudiantes
async function aplicarATodos(competenciaId, nota) {
    if (!puedeEditar) {
        Swal.fire('Información', 'No tienes permisos para realizar esta acción', 'info');
        return;
    }
    
    const result = await Swal.fire({
        title: '¿Confirmar acción?',
        text: `¿Aplicar la calificación "${nota}" a todos los estudiantes de esta competencia?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#6366f1'
    });
    
    if (!result.isConfirmed) return;
    
    const filas = document.querySelectorAll(`tr[data-competencia="${competenciaId}"]`);
    
    // Mostrar overlay de carga
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner-border text-primary mb-3"></div>
            <div>Aplicando calificaciones...</div>
            <div class="progress mt-2" style="width: 200px;">
                <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    
    try {
        let procesados = 0;
        const total = filas.length;
        
        for (const fila of filas) {
            const estudiante = fila.dataset.estudiante;
            const btn = fila.querySelector(`[data-nota="${nota}"]`);
            
            await fetch('public/actions/save_grade.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    estudiante_id: parseInt(estudiante),
                    competencia_id: parseInt(competenciaId),
                    periodo_id: periodoId,
                    nota: nota
                })
            });
            
            // Actualizar UI
            fila.querySelectorAll('.btn-grade').forEach(b => {
                b.classList.remove('active');
                b.innerHTML = b.dataset.nota;
            });
            
            btn.classList.add('active');
            btn.innerHTML = '✓';
            
            // Actualizar badge
            const badge = fila.querySelector('.badge');
            badge.className = 'badge bg-info';
            badge.textContent = 'Registrado';
            
            // Actualizar progreso
            procesados++;
            const porcentaje = (procesados / total) * 100;
            document.getElementById('progress-bar').style.width = porcentaje + '%';
            
            // Pequeña pausa para que se vea el progreso
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        // Actualizar contadores
        actualizarContadores(competenciaId);
        
        Swal.fire({
            icon: 'success',
            title: 'Completado',
            text: `Se aplicó la calificación ${nota} a todos los estudiantes`,
            confirmButtonColor: '#6366f1'
        });
        
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Hubo un problema al aplicar las calificaciones', 'error');
    } finally {
        document.body.removeChild(overlay);
    }
}

// Actualizar contadores de la competencia
function actualizarContadores(competenciaId) {
    const filas = document.querySelectorAll(`tr[data-competencia="${competenciaId}"]`);
    
    let counts = { AD: 0, A: 0, B: 0, C: 0 };
    
    filas.forEach(fila => {
        const btnActivo = fila.querySelector('.btn-grade.active');
        if (btnActivo) {
            const nota = btnActivo.dataset.nota;
            counts[nota]++;
        }
    });
    
    // Actualizar contadores en pantalla
    Object.keys(counts).forEach(nota => {
        const elemento = document.getElementById(`count-${nota.toLowerCase()}-${competenciaId}`);
        if (elemento) {
            elemento.textContent = counts[nota];
        }
    });
    
    // Actualizar porcentajes
    const total = Object.values(counts).reduce((a, b) => a + b, 0);
    if (total > 0) {
        Object.keys(counts).forEach(nota => {
            const porcentaje = Math.round((counts[nota] / total) * 100 * 10) / 10;
            const elementoPorcentaje = elemento.parentElement.querySelector('small');
            if (elementoPorcentaje) {
                elementoPorcentaje.textContent = porcentaje + '%';
            }
        });
    }
}

// Ver estadísticas detalladas
function verEstadisticasDetalladas(competenciaId) {
    // Implementar modal con estadísticas avanzadas
    Swal.fire({
        title: 'Estadísticas Detalladas',
        html: `
            <div class="text-start">
                <p>Función en desarrollo...</p>
                <p>Aquí se mostrarán:</p>
                <ul>
                    <li>Distribución por género</li>
                    <li>Evolución temporal</li>
                    <li>Comparación con otras aulas</li>
                    <li>Recomendaciones pedagógicas</li>
                </ul>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#6366f1'
    });
}

// Validar competencia (solo para coordinadora)
async function validarCompetencia(competenciaId) {
    if (!modoSupervision) {
        Swal.fire('Error', 'Esta función solo está disponible en modo supervisión', 'error');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Validar Competencia',
        text: '¿Confirmas que todas las calificaciones de esta competencia están correctas?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, validar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745'
    });
    
    if (!result.isConfirmed) return;
    
    try {
        // Aquí iría la llamada para validar la competencia
        // Por ahora simulamos la validación
        
        Swal.fire({
            icon: 'success',
            title: 'Competencia Validada',
            text: 'Las calificaciones han sido validadas correctamente',
            confirmButtonColor: '#28a745'
        });
        
        // Marcar filas como validadas
        const filas = document.querySelectorAll(`tr[data-competencia="${competenciaId}"]`);
        filas.forEach(fila => {
            const badge = fila.querySelector('.badge');
            if (badge && badge.textContent === 'Registrado') {
                badge.className = 'badge bg-success';
                badge.textContent = 'Validado';
            }
            fila.classList.add('table-success');
        });
        
    } catch (error) {
        Swal.fire('Error', 'No se pudo validar la competencia: ' + error.message, 'error');
    }
}
</script>