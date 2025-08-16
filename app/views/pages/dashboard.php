<?php
// app/views/pages/dashboard.php - Dashboard completo por roles
$me = auth();

// Verificar autenticación
if (!$me) {
    header('Location: login.php');
    exit;
}

// =====================================================
// OBTENER DATOS GENERALES
// =====================================================

// Año académico activo
$anio_activo = null;
try {
    $stmt = $pdo->query("SELECT * FROM anios_academicos WHERE activo = 1 LIMIT 1");
    $anio_activo = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error obteniendo año activo: ' . $e->getMessage());
}

// Período actual
$periodo_actual = null;
try {
    $stmt = $pdo->query("
        SELECT * FROM periodos 
        WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin 
        AND activo = 1 
        ORDER BY fecha_inicio 
        LIMIT 1
    ");
    $periodo_actual = $stmt->fetch();
    
    // Si no hay período actual, obtener el más reciente
    if (!$periodo_actual) {
        $stmt = $pdo->query("
            SELECT * FROM periodos 
            WHERE activo = 1 
            ORDER BY fecha_inicio DESC 
            LIMIT 1
        ");
        $periodo_actual = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log('Error obteniendo período actual: ' . $e->getMessage());
}

// Estadísticas generales del sistema
$stats_generales = [
    'total_estudiantes' => 0,
    'total_docentes' => 0,
    'total_aulas' => 0,
    'total_areas' => 0,
    'total_competencias' => 0,
    'evaluaciones_mes' => 0
];

try {
    $stats_generales['total_estudiantes'] = $pdo->query("SELECT COUNT(*) FROM estudiantes WHERE activo = 1")->fetchColumn();
    $stats_generales['total_docentes'] = $pdo->query("SELECT COUNT(*) FROM docentes WHERE activo = 1")->fetchColumn();
    $stats_generales['total_aulas'] = $pdo->query("SELECT COUNT(*) FROM aulas WHERE activo = 1")->fetchColumn();
    $stats_generales['total_areas'] = $pdo->query("SELECT COUNT(*) FROM areas WHERE activo = 1")->fetchColumn();
    $stats_generales['total_competencias'] = $pdo->query("SELECT COUNT(*) FROM competencias WHERE activo = 1")->fetchColumn();
    
    if ($periodo_actual) {
        $stats_generales['evaluaciones_mes'] = $pdo->query("
            SELECT COUNT(*) FROM calificaciones 
            WHERE MONTH(updated_at) = MONTH(NOW()) 
            AND YEAR(updated_at) = YEAR(NOW())
        ")->fetchColumn();
    }
} catch (Exception $e) {
    error_log('Error obteniendo estadísticas generales: ' . $e->getMessage());
}

// =====================================================
// DATOS ESPECÍFICOS POR ROL
// =====================================================

$datos_rol = [
    'mis_aulas' => [],
    'mis_areas' => [],
    'estudiantes_asignados' => 0,
    'evaluaciones_pendientes' => 0,
    'actividades_recientes' => [],
    'estadisticas_notas' => [],
    'progreso_areas' => [],
    'alertas_academicas' => []
];

// DATOS PARA DOCENTES (todos los tipos)
if ($me['docente_id'] && is_docente()) {
    try {
        // Obtener aulas asignadas
        $stmt = $pdo->prepare("
            SELECT DISTINCT a.id, a.nombre, a.seccion, a.grado, a.nivel,
                   CONCAT(a.nombre, ' ', a.seccion, ' - ', a.grado) as aula_completa,
                   daa.es_tutor
            FROM aulas a
            JOIN docente_aula_area daa ON daa.aula_id = a.id
            WHERE daa.docente_id = ? AND daa.activo = 1 AND a.activo = 1
            ORDER BY a.nivel, a.nombre, a.seccion
        ");
        $stmt->execute([$me['docente_id']]);
        $datos_rol['mis_aulas'] = $stmt->fetchAll();
        
        // Obtener áreas asignadas
        $stmt = $pdo->prepare("
            SELECT DISTINCT ar.id, ar.nombre, ar.tipo, ar.va_siagie, ar.color
            FROM areas ar
            JOIN docente_aula_area daa ON daa.area_id = ar.id
            WHERE daa.docente_id = ? AND daa.activo = 1 AND ar.activo = 1
            ORDER BY ar.tipo, ar.orden, ar.nombre
        ");
        $stmt->execute([$me['docente_id']]);
        $datos_rol['mis_areas'] = $stmt->fetchAll();
        
        // Contar estudiantes asignados
        if (!empty($datos_rol['mis_aulas'])) {
            $aulas_ids = array_column($datos_rol['mis_aulas'], 'id');
            $placeholders = str_repeat('?,', count($aulas_ids) - 1) . '?';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM estudiantes WHERE aula_id IN ($placeholders) AND activo = 1");
            $stmt->execute($aulas_ids);
            $datos_rol['estudiantes_asignados'] = $stmt->fetchColumn();
        }
        
        // Evaluaciones pendientes (si hay período actual)
        if ($periodo_actual && !empty($datos_rol['mis_aulas']) && !empty($datos_rol['mis_areas'])) {
            $aulas_ids = array_column($datos_rol['mis_aulas'], 'id');
            $areas_ids = array_column($datos_rol['mis_areas'], 'id');
            
            $aulas_placeholders = str_repeat('?,', count($aulas_ids) - 1) . '?';
            $areas_placeholders = str_repeat('?,', count($areas_ids) - 1) . '?';
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM (
                    SELECT DISTINCT e.id, comp.id
                    FROM estudiantes e
                    JOIN aulas au ON au.id = e.aula_id
                    JOIN areas ar ON (ar.nivel = au.nivel OR ar.nivel = 'todos') 
                        AND ar.id IN ($areas_placeholders) AND ar.activo = 1
                    JOIN competencias comp ON comp.area_id = ar.id AND comp.activo = 1
                    LEFT JOIN calificaciones c ON c.estudiante_id = e.id 
                        AND c.competencia_id = comp.id AND c.periodo_id = ?
                    WHERE e.aula_id IN ($aulas_placeholders) 
                        AND e.activo = 1 AND c.id IS NULL
                ) as pendientes
            ");
            $params = array_merge($areas_ids, [$periodo_actual['id']], $aulas_ids);
            $stmt->execute($params);
            $datos_rol['evaluaciones_pendientes'] = $stmt->fetchColumn();
        }
        
        // Actividades recientes
        if ($periodo_actual) {
            $stmt = $pdo->prepare("
                SELECT c.updated_at, e.nombres, e.apellidos, comp.nombre as competencia, 
                       c.nota, ar.nombre as area, au.nombre as aula_nombre, au.seccion, au.grado,
                       c.observaciones
                FROM calificaciones c
                JOIN estudiantes e ON e.id = c.estudiante_id
                JOIN competencias comp ON comp.id = c.competencia_id
                JOIN areas ar ON ar.id = comp.area_id
                JOIN aulas au ON au.id = e.aula_id
                JOIN docente_aula_area daa ON daa.aula_id = au.id AND daa.area_id = ar.id
                WHERE daa.docente_id = ? AND c.periodo_id = ?
                ORDER BY c.updated_at DESC
                LIMIT 15
            ");
            $stmt->execute([$me['docente_id'], $periodo_actual['id']]);
            $datos_rol['actividades_recientes'] = $stmt->fetchAll();
        }
        
    } catch (Exception $e) {
        error_log('Error obteniendo datos de docente: ' . $e->getMessage());
    }
}

// DATOS PARA ADMIN Y COORDINADORA
if (in_array($me['role'], ['admin', 'coordinadora'])) {
    try {
        // Estadísticas de notas del período actual
        if ($periodo_actual) {
            $stmt = $pdo->prepare("
                SELECT 
                    nota,
                    COUNT(*) as cantidad,
                    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) as porcentaje
                FROM calificaciones 
                WHERE periodo_id = ?
                GROUP BY nota
                ORDER BY FIELD(nota, 'AD', 'A', 'B', 'C')
            ");
            $stmt->execute([$periodo_actual['id']]);
            $datos_rol['estadisticas_notas'] = $stmt->fetchAll();
        }
        
        // Progreso por área curricular
        if ($periodo_actual) {
            $stmt = $pdo->prepare("
                SELECT 
                    ar.id,
                    ar.nombre as area_nombre,
                    ar.tipo,
                    ar.va_siagie,
                    COUNT(DISTINCT e.id) as total_estudiantes,
                    COUNT(DISTINCT comp.id) as total_competencias,
                    COUNT(c.id) as evaluaciones_realizadas,
                    ROUND(
                        CASE 
                            WHEN COUNT(DISTINCT e.id) * COUNT(DISTINCT comp.id) = 0 THEN 0
                            ELSE COUNT(c.id) * 100.0 / (COUNT(DISTINCT e.id) * COUNT(DISTINCT comp.id))
                        END, 1
                    ) as porcentaje_completitud
                FROM areas ar
                JOIN competencias comp ON comp.area_id = ar.id AND comp.activo = 1
                CROSS JOIN estudiantes e
                JOIN aulas au ON au.id = e.aula_id AND (ar.nivel = au.nivel OR ar.nivel = 'todos')
                LEFT JOIN calificaciones c ON c.estudiante_id = e.id 
                    AND c.competencia_id = comp.id AND c.periodo_id = ?
                WHERE ar.activo = 1 AND e.activo = 1 AND au.activo = 1
                GROUP BY ar.id, ar.nombre, ar.tipo, ar.va_siagie
                HAVING total_competencias > 0
                ORDER BY ar.tipo, porcentaje_completitud DESC
            ");
            $stmt->execute([$periodo_actual['id']]);
            $datos_rol['progreso_areas'] = $stmt->fetchAll();
        }
        
        // Alertas académicas (para coordinadora)
        if ($me['role'] === 'coordinadora' && $periodo_actual) {
            // Aulas con baja completitud
            $stmt = $pdo->prepare("
                SELECT 
                    au.nombre, au.seccion, au.grado,
                    ROUND(COUNT(c.id) * 100.0 / (COUNT(DISTINCT e.id) * COUNT(DISTINCT comp.id)), 1) as completitud
                FROM aulas au
                JOIN estudiantes e ON e.aula_id = au.id AND e.activo = 1
                CROSS JOIN areas ar
                JOIN competencias comp ON comp.area_id = ar.id AND comp.activo = 1
                LEFT JOIN calificaciones c ON c.estudiante_id = e.id 
                    AND c.competencia_id = comp.id AND c.periodo_id = ?
                WHERE au.activo = 1 AND ar.activo = 1 
                    AND (ar.nivel = au.nivel OR ar.nivel = 'todos')
                GROUP BY au.id
                HAVING completitud < 70
                ORDER BY completitud ASC
                LIMIT 5
            ");
            $stmt->execute([$periodo_actual['id']]);
            $aulas_baja_completitud = $stmt->fetchAll();
            
            foreach ($aulas_baja_completitud as $aula) {
                $datos_rol['alertas_academicas'][] = [
                    'tipo' => 'warning',
                    'titulo' => 'Baja completitud de evaluaciones',
                    'mensaje' => $aula['nombre'] . ' ' . $aula['seccion'] . ' - ' . $aula['grado'] . ' (' . $aula['completitud'] . '%)',
                    'icono' => 'exclamation-triangle'
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log('Error obteniendo datos de admin/coordinadora: ' . $e->getMessage());
    }
}

// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

function formatear_fecha($fecha) {
    if (!$fecha) return '-';
    return date('d/m/Y', strtotime($fecha));
}

function calcular_progreso_periodo($periodo) {
    if (!$periodo) return 0;
    
    $fecha_inicio = strtotime($periodo['fecha_inicio']);
    $fecha_fin = strtotime($periodo['fecha_fin']);
    $fecha_actual = time();
    
    if ($fecha_actual < $fecha_inicio) return 0;
    if ($fecha_actual > $fecha_fin) return 100;
    
    $total_dias = ($fecha_fin - $fecha_inicio) / (60 * 60 * 24);
    $dias_transcurridos = ($fecha_actual - $fecha_inicio) / (60 * 60 * 24);
    
    return min(100, max(0, ($dias_transcurridos / $total_dias) * 100));
}

function obtener_icono_rol($role) {
    $iconos = [
        'admin' => 'shield-check',
        'coordinadora' => 'clipboard-check',
        'tutor' => 'people-fill',
        'docente_area' => 'book-half',
        'docente_taller' => 'palette'
    ];
    return $iconos[$role] ?? 'person';
}

function obtener_color_nota($nota) {
    $colores = [
        'AD' => '#10b981',
        'A' => '#6366f1',
        'B' => '#f59e0b',
        'C' => '#ef4444'
    ];
    return $colores[$nota] ?? '#9ca3af';
}
?>


<div class="dashboard-container fade-in-up">
    <!-- =====================================================
         HEADER CONTEXTUAL SEGÚN ROL
         ===================================================== -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="welcome-content">
                                <div class="welcome-icon">
                                    <i class="bi bi-<?= obtener_icono_rol($me['role']) ?>"></i>
                                </div>
                                <div class="welcome-text">
                                    <?php if ($me['role'] === 'admin'): ?>
                                        <h3 class="welcome-title">Panel de Administración</h3>
                                        <p class="welcome-subtitle">Gestión completa del sistema educativo</p>
                                    <?php elseif ($me['role'] === 'coordinadora'): ?>
                                        <h3 class="welcome-title">Coordinación Académica</h3>
                                        <p class="welcome-subtitle">Supervisión y validación de registros académicos</p>
                                    <?php elseif ($me['role'] === 'tutor'): ?>
                                        <h3 class="welcome-title">
                                            Bienvenido/a, <?= htmlspecialchars($me['docente_nombres'] ?? 'Tutor') ?>
                                        </h3>
                                        <p class="welcome-subtitle">Gestión integral de estudiantes y evaluaciones</p>
                                    <?php else: ?>
                                        <h3 class="welcome-title">
                                            Bienvenido/a, <?= htmlspecialchars($me['docente_nombres'] ?? 'Docente') ?>
                                        </h3>
                                        <p class="welcome-subtitle">Evaluación de competencias específicas</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="period-info">
                                <div class="current-year">
                                    <i class="bi bi-calendar-event me-2"></i>
                                    <strong>
                                        <?= $anio_activo ? 'Año ' . $anio_activo['anio'] : 'Sin año activo' ?>
                                    </strong>
                                </div>
                                <div class="current-period">
                                    <i class="bi bi-clock me-2"></i>
                                    <?= $periodo_actual ? $periodo_actual['nombre'] : 'Sin período activo' ?>
                                </div>
                                <?php if ($periodo_actual): ?>
                                    <div class="period-progress-container mt-2">
                                        <div class="period-progress">
                                            <?php $progreso = calcular_progreso_periodo($periodo_actual); ?>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-primary" 
                                                     style="width: <?= $progreso ?>%" 
                                                     title="Progreso del período: <?= round($progreso, 1) ?>%">
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= formatear_fecha($periodo_actual['fecha_inicio']) ?> - 
                                                <?= formatear_fecha($periodo_actual['fecha_fin']) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================
         ESTADÍSTICAS PRINCIPALES POR ROL
         ===================================================== -->
    <div class="row g-4 mb-4">
        <?php if ($me['role'] === 'admin'): ?>
            <!-- ESTADÍSTICAS PARA ADMINISTRADOR -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-students">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $stats_generales['total_estudiantes'] ?>">0</h3>
                        <p class="stat-label">Estudiantes Activos</p>
                        <div class="stat-trend">
                            <i class="bi bi-arrow-up text-success"></i>
                            <span class="text-success small">Sistema completo</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-teachers">
                    <div class="stat-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $stats_generales['total_docentes'] ?>">0</h3>
                        <p class="stat-label">Docentes</p>
                        <div class="stat-trend">
                            <i class="bi bi-check-circle text-success"></i>
                            <span class="text-success small">Activos</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-classrooms">
                    <div class="stat-icon">
                        <i class="bi bi-door-open"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $stats_generales['total_aulas'] ?>">0</h3>
                        <p class="stat-label">Aulas Configuradas</p>
                        <div class="stat-trend">
                            <i class="bi bi-gear text-primary"></i>
                            <span class="text-primary small">Sistema</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-competencies">
                    <div class="stat-icon">
                        <i class="bi bi-list-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $stats_generales['total_competencias'] ?>">0</h3>
                        <p class="stat-label">Competencias</p>
                        <div class="stat-trend">
                            <i class="bi bi-book text-info"></i>
                            <span class="text-info small">Curriculares</span>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($me['role'] === 'coordinadora'): ?>
            <!-- ESTADÍSTICAS PARA COORDINADORA -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-evaluations">
                    <div class="stat-icon">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $stats_generales['evaluaciones_mes'] ?>">0</h3>
                        <p class="stat-label">Evaluaciones Este Mes</p>
                        <div class="stat-trend">
                            <i class="bi bi-calendar-check text-success"></i>
                            <span class="text-success small">Registradas</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-students">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $stats_generales['total_estudiantes'] ?>">0</h3>
                        <p class="stat-label">Total Estudiantes</p>
                        <div class="stat-trend">
                            <i class="bi bi-mortarboard text-primary"></i>
                            <span class="text-primary small">Activos</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-classrooms">
                    <div class="stat-icon">
                        <i class="bi bi-door-open"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $stats_generales['total_aulas'] ?>">0</h3>
                        <p class="stat-label">Aulas Supervisadas</p>
                        <div class="stat-trend">
                            <i class="bi bi-eye text-info"></i>
                            <span class="text-info small">Supervisión</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-achievement">
                    <div class="stat-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value">
                            <?php
                            if (!empty($datos_rol['estadisticas_notas'])) {
                                $logros = 0;
                                $total = 0;
                                foreach ($datos_rol['estadisticas_notas'] as $stat) {
                                    $total += $stat['cantidad'];
                                    if (in_array($stat['nota'], ['AD', 'A'])) {
                                        $logros += $stat['cantidad'];
                                    }
                                }
                                $porcentaje_logro = $total > 0 ? round(($logros / $total) * 100, 1) : 0;
                                echo '<span data-count="' . $porcentaje_logro . '">0</span>%';
                            } else {
                                echo '<span data-count="0">0</span>%';
                            }
                            ?>
                        </h3>
                        <p class="stat-label">Nivel de Logro</p>
                        <div class="stat-trend">
                            <i class="bi bi-trophy text-warning"></i>
                            <span class="text-warning small">Académico</span>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- ESTADÍSTICAS PARA DOCENTES -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-classrooms">
                    <div class="stat-icon">
                        <i class="bi bi-door-open"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= count($datos_rol['mis_aulas']) ?>">0</h3>
                        <p class="stat-label">Mis Aulas</p>
                        <div class="stat-trend">
                            <i class="bi bi-house text-primary"></i>
                            <span class="text-primary small">Asignadas</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-students">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $datos_rol['estudiantes_asignados'] ?>">0</h3>
                        <p class="stat-label">Mis Estudiantes</p>
                        <div class="stat-trend">
                            <i class="bi bi-mortarboard text-success"></i>
                            <span class="text-success small">A cargo</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-areas">
                    <div class="stat-icon">
                        <i class="bi bi-book"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= count($datos_rol['mis_areas']) ?>">0</h3>
                        <p class="stat-label">Mis Áreas</p>
                        <div class="stat-trend">
                            <i class="bi bi-bookmark text-info"></i>
                            <span class="text-info small">
                                <?= $me['role'] === 'tutor' ? 'Todas' : 'Específicas' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-pending">
                    <div class="stat-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?= $datos_rol['evaluaciones_pendientes'] ?>">0</h3>
                        <p class="stat-label">Evaluaciones Pendientes</p>
                        <div class="stat-trend">
                            <?php if ($datos_rol['evaluaciones_pendientes'] > 0): ?>
                                <i class="bi bi-clock text-warning"></i>
                                <span class="text-warning small">Por completar</span>
                            <?php else: ?>
                                <i class="bi bi-check-circle text-success"></i>
                                <span class="text-success small">Al día</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- =====================================================
         CONTENIDO PRINCIPAL SEGÚN ROL
         ===================================================== -->
    <div class="row g-4">
        
        <!-- COLUMNA PRINCIPAL -->
        <div class="col-lg-8">
            
            <?php if (in_array($me['role'], ['admin', 'coordinadora'])): ?>
                
                <!-- GRÁFICO DE DISTRIBUCIÓN DE NOTAS (ADMIN/COORDINADORA) -->
                <?php if (!empty($datos_rol['estadisticas_notas'])): ?>
                <div class="dashboard-card chart-card mb-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-bar-chart me-2"></i>Distribución de Calificaciones
                        </div>
                        <div class="card-subtitle">
                            <?= $periodo_actual['nombre'] ?? 'Período actual' ?> - 
                            Total: <?= array_sum(array_column($datos_rol['estadisticas_notas'], 'cantidad')) ?> evaluaciones
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="notasChart" height="100"></canvas>
                        </div>
                        
                        <!-- Resumen de logros -->
                        <div class="chart-summary mt-3">
                            <div class="row text-center">
                                <?php 
                                $total_evaluaciones = array_sum(array_column($datos_rol['estadisticas_notas'], 'cantidad'));
                                $logros = 0;
                                foreach ($datos_rol['estadisticas_notas'] as $stat) {
                                    if (in_array($stat['nota'], ['AD', 'A'])) $logros += $stat['cantidad'];
                                }
                                $porcentaje_logro = $total_evaluaciones > 0 ? round(($logros / $total_evaluaciones) * 100, 1) : 0;
                                ?>
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <div class="summary-number text-success"><?= $logros ?></div>
                                        <div class="summary-label">Logros (AD + A)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <div class="summary-number text-primary"><?= $porcentaje_logro ?>%</div>
                                        <div class="summary-label">Nivel de Logro</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PROGRESO POR ÁREAS (ADMIN/COORDINADORA) -->
                <?php if (!empty($datos_rol['progreso_areas'])): ?>
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-graph-up me-2"></i>Progreso por Área Curricular
                        </div>
                        <div class="card-subtitle">Completitud de evaluaciones por área</div>
                    </div>
                    <div class="card-body">
                        <div class="areas-progress">
                            <?php foreach ($datos_rol['progreso_areas'] as $area): ?>
                                <div class="area-progress-item mb-3">
                                    <div class="area-header">
                                        <div class="area-info">
                                            <h6 class="area-name">
                                                <?= htmlspecialchars($area['area_nombre']) ?>
                                                <span class="area-badge badge bg-<?= $area['tipo'] === 'curricular' ? 'primary' : 'secondary' ?>">
                                                    <?= ucfirst($area['tipo']) ?>
                                                </span>
                                                <?php if ($area['va_siagie']): ?>
                                                    <span class="siagie-badge" title="Va al SIAGIE">📊</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="area-stats text-muted">
                                                <?= $area['total_competencias'] ?> competencias • 
                                                <?= $area['total_estudiantes'] ?> estudiantes
                                            </small>
                                        </div>
                                        <div class="area-percentage">
                                            <span class="percentage-value badge bg-<?= $area['porcentaje_completitud'] >= 80 ? 'success' : ($area['porcentaje_completitud'] >= 60 ? 'warning' : 'danger') ?>">
                                                <?= $area['porcentaje_completitud'] ?>%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="progress-bar-container">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?= $area['porcentaje_completitud'] >= 80 ? 'success' : ($area['porcentaje_completitud'] >= 60 ? 'warning' : 'danger') ?>" 
                                                 style="width: <?= $area['porcentaje_completitud'] ?>%"
                                                 data-bs-toggle="tooltip" 
                                                 title="<?= $area['evaluaciones_realizadas'] ?> de <?= $area['total_estudiantes'] * $area['total_competencias'] ?> evaluaciones">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                
                <!-- MIS AULAS (DOCENTES) -->
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-door-open me-2"></i>Mis Aulas Asignadas
                        </div>
                        <div class="card-subtitle">
                            <?= $me['role'] === 'tutor' ? 'Aulas bajo tu tutoría' : 'Aulas donde enseñas' ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($datos_rol['mis_aulas'])): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-info-circle"></i>
                                </div>
                                <h6 class="empty-title">No tienes aulas asignadas</h6>
                                <p class="empty-message">Contacta al administrador para configurar tus asignaciones</p>
                            </div>
                        <?php else: ?>
                            <div class="classroom-grid">
                                <?php foreach ($datos_rol['mis_aulas'] as $aula): ?>
                                    <div class="classroom-card">
                                        <div class="classroom-header">
                                            <div class="classroom-info">
                                                <h6 class="classroom-name">
                                                    <?= htmlspecialchars($aula['aula_completa']) ?>
                                                    <?php if ($aula['es_tutor']): ?>
                                                        <span class="tutor-badge">👑 Tutor</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="classroom-level">
                                                    Nivel: <?= ucfirst(str_replace('_', ' ', $aula['nivel'])) ?>
                                                </small>
                                            </div>
                                            <div class="classroom-actions">
                                                <a href="index.php?page=grading&aula_id=<?= $aula['id'] ?>" 
                                                   class="btn btn-primary btn-sm" 
                                                   title="Evaluar">
                                                    <i class="bi bi-ui-checks-grid"></i>
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <?php
                                        // Obtener datos del aula
                                        try {
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM estudiantes WHERE aula_id = ? AND activo = 1");
                                            $stmt->execute([$aula['id']]);
                                            $estudiantes_aula = $stmt->fetchColumn();
                                            
                                            // Calcular completitud si hay período actual
                                            $completitud = 0;
                                            if ($periodo_actual) {
                                                $stmt = $pdo->prepare("
                                                    SELECT 
                                                        COUNT(DISTINCT e.id) as total_estudiantes,
                                                        COUNT(DISTINCT comp.id) as total_competencias,
                                                        COUNT(c.id) as evaluaciones_realizadas
                                                    FROM estudiantes e
                                                    CROSS JOIN areas ar
                                                    JOIN competencias comp ON comp.area_id = ar.id AND comp.activo = 1
                                                    JOIN docente_aula_area daa ON daa.area_id = ar.id AND daa.aula_id = e.aula_id AND daa.docente_id = ?
                                                    LEFT JOIN calificaciones c ON c.estudiante_id = e.id 
                                                        AND c.competencia_id = comp.id AND c.periodo_id = ?
                                                    WHERE e.aula_id = ? AND e.activo = 1 AND ar.activo = 1
                                                ");
                                                $stmt->execute([$me['docente_id'], $periodo_actual['id'], $aula['id']]);
                                                $stats_aula = $stmt->fetch();
                                                
                                                if ($stats_aula && $stats_aula['total_estudiantes'] > 0 && $stats_aula['total_competencias'] > 0) {
                                                    $total_posibles = $stats_aula['total_estudiantes'] * $stats_aula['total_competencias'];
                                                    $completitud = round(($stats_aula['evaluaciones_realizadas'] / $total_posibles) * 100, 1);
                                                }
                                            }
                                        } catch (Exception $e) {
                                            $estudiantes_aula = 0;
                                            $completitud = 0;
                                        }
                                        ?>
                                        
                                        <div class="classroom-stats">
                                            <div class="stat-row">
                                                <span class="stat-label">
                                                    <i class="bi bi-people me-1"></i>Estudiantes:
                                                </span>
                                                <span class="stat-value"><?= $estudiantes_aula ?></span>
                                            </div>
                                            <div class="stat-row">
                                                <span class="stat-label">
                                                    <i class="bi bi-graph-up me-1"></i>Completitud:
                                                </span>
                                                <span class="stat-value"><?= $completitud ?>%</span>
                                            </div>
                                        </div>
                                        
                                        <div class="classroom-progress">
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar bg-<?= $completitud >= 80 ? 'success' : ($completitud >= 60 ? 'warning' : 'danger') ?>" 
                                                     style="width: <?= $completitud ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ACTIVIDAD RECIENTE (DOCENTES) -->
                <?php if (!empty($datos_rol['actividades_recientes'])): ?>
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-clock-history me-2"></i>Actividad Reciente
                        </div>
                        <div class="card-subtitle">Últimas evaluaciones registradas</div>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php foreach (array_slice($datos_rol['actividades_recientes'], 0, 8) as $actividad): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker" style="background-color: <?= obtener_color_nota($actividad['nota']) ?>">
                                        <span class="timeline-nota"><?= $actividad['nota'] ?></span>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <h6 class="timeline-student">
                                                <?= htmlspecialchars($actividad['apellidos'] . ', ' . $actividad['nombres']) ?>
                                            </h6>
                                            <span class="timeline-time">
                                                <?= date('d/m H:i', strtotime($actividad['updated_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="timeline-details">
                                            <div class="timeline-subject">
                                                <i class="bi bi-book me-1"></i>
                                                <?= htmlspecialchars($actividad['area']) ?>
                                            </div>
                                            <div class="timeline-competency">
                                                <?= htmlspecialchars($actividad['competencia']) ?>
                                            </div>
                                            <div class="timeline-classroom">
                                                <i class="bi bi-door-open me-1"></i>
                                                <?= htmlspecialchars($actividad['aula_nombre'] . ' ' . $actividad['seccion'] . ' - ' . $actividad['grado']) ?>
                                            </div>
                                            <?php if ($actividad['observaciones']): ?>
                                                <div class="timeline-observations">
                                                    <i class="bi bi-chat-text me-1"></i>
                                                    <?= htmlspecialchars($actividad['observaciones']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($datos_rol['actividades_recientes']) > 8): ?>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary btn-sm" onclick="verMasActividades()">
                                    <i class="bi bi-arrow-down me-2"></i>Ver más actividades
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>

        <!-- COLUMNA LATERAL -->
        <div class="col-lg-4">
            <!-- COLUMNA LATERAL -->
        <div class="col-lg-4">
            
            <?php if ($me['role'] === 'admin'): ?>
                
                <!-- ACCIONES RÁPIDAS ADMIN -->
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-lightning me-2"></i>Acciones Rápidas
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="index.php?page=students" class="action-button">
                                <div class="action-icon bg-primary">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Registrar Estudiante</div>
                                    <div class="action-description">Agregar nuevo estudiante</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="index.php?page=teachers" class="action-button">
                                <div class="action-icon bg-success">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Gestionar Docentes</div>
                                    <div class="action-description">Administrar personal</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="index.php?page=assignments" class="action-button">
                                <div class="action-icon bg-info">
                                    <i class="bi bi-link-45deg"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Asignar Docentes</div>
                                    <div class="action-description">Configurar asignaciones</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="index.php?page=areas" class="action-button">
                                <div class="action-icon bg-warning">
                                    <i class="bi bi-book"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Configurar Áreas</div>
                                    <div class="action-description">Gestionar currículo</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="index.php?page=grading" class="action-button">
                                <div class="action-icon bg-secondary">
                                    <i class="bi bi-ui-checks-grid"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Matriz de Evaluación</div>
                                    <div class="action-description">Supervisar evaluaciones</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ESTADO DEL SISTEMA -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-gear me-2"></i>Estado del Sistema
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="system-status">
                            <div class="status-item">
                                <div class="status-indicator <?= $anio_activo ? 'status-active' : 'status-inactive' ?>"></div>
                                <div class="status-content">
                                    <div class="status-label">Año Académico</div>
                                    <div class="status-value">
                                        <?= $anio_activo ? $anio_activo['anio'] . ' (Activo)' : 'Sin año activo' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="status-item">
                                <div class="status-indicator <?= $periodo_actual ? 'status-active' : 'status-warning' ?>"></div>
                                <div class="status-content">
                                    <div class="status-label">Período Actual</div>
                                    <div class="status-value">
                                        <?= $periodo_actual ? $periodo_actual['nombre'] : 'Sin período' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="status-item">
                                <div class="status-indicator status-info"></div>
                                <div class="status-content">
                                    <div class="status-label">Docentes Activos</div>
                                    <div class="status-value"><?= $stats_generales['total_docentes'] ?></div>
                                </div>
                            </div>
                            
                            <div class="status-item">
                                <div class="status-indicator status-info"></div>
                                <div class="status-content">
                                    <div class="status-label">Estudiantes Activos</div>
                                    <div class="status-value"><?= $stats_generales['total_estudiantes'] ?></div>
                                </div>
                            </div>
                            
                            <div class="status-item">
                                <div class="status-indicator status-info"></div>
                                <div class="status-content">
                                    <div class="status-label">Áreas Configuradas</div>
                                    <div class="status-value"><?= $stats_generales['total_areas'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($me['role'] === 'coordinadora'): ?>
                
                <!-- PANEL DE SUPERVISIÓN -->
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-clipboard-check me-2"></i>Supervisión Académica
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="index.php?page=grading&modo=supervision" class="action-button">
                                <div class="action-icon bg-primary">
                                    <i class="bi bi-eye"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Supervisar Calificaciones</div>
                                    <div class="action-description">Revisar evaluaciones</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="index.php?page=consolidado" class="action-button">
                                <div class="action-icon bg-success">
                                    <i class="bi bi-table"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Consolidado SIAGIE</div>
                                    <div class="action-description">Generar reportes</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="index.php?page=reports" class="action-button">
                                <div class="action-icon bg-info">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Reportes Académicos</div>
                                    <div class="action-description">Análisis detallado</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="index.php?page=boletas" class="action-button">
                                <div class="action-icon bg-warning">
                                    <i class="bi bi-file-check"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Validar Boletas</div>
                                    <div class="action-description">Revisar documentos</div>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ALERTAS ACADÉMICAS -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-bell me-2"></i>Alertas Académicas
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($datos_rol['alertas_academicas'])): ?>
                            <div class="no-alerts">
                                <div class="no-alerts-icon">
                                    <i class="bi bi-check-circle text-success"></i>
                                </div>
                                <div class="no-alerts-message">
                                    No hay alertas académicas pendientes
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alerts-list">
                                <?php foreach ($datos_rol['alertas_academicas'] as $alerta): ?>
                                    <div class="alert-item alert-<?= $alerta['tipo'] ?>">
                                        <div class="alert-icon">
                                            <i class="bi bi-<?= $alerta['icono'] ?>"></i>
                                        </div>
                                        <div class="alert-content">
                                            <div class="alert-title"><?= htmlspecialchars($alerta['titulo']) ?></div>
                                            <div class="alert-message"><?= htmlspecialchars($alerta['mensaje']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Información del período -->
                        <?php if ($periodo_actual): ?>
                            <div class="period-info-card mt-3">
                                <div class="period-header">
                                    <h6 class="period-name"><?= htmlspecialchars($periodo_actual['nombre']) ?></h6>
                                    <small class="period-dates">
                                        <?= formatear_fecha($periodo_actual['fecha_inicio']) ?> - 
                                        <?= formatear_fecha($periodo_actual['fecha_fin']) ?>
                                    </small>
                                </div>
                                
                                <?php $progreso = calcular_progreso_periodo($periodo_actual); ?>
                                <div class="period-progress-info">
                                    <div class="progress mb-2" style="height: 8px;">
                                        <div class="progress-bar bg-info" style="width: <?= $progreso ?>%"></div>
                                    </div>
                                    <small class="text-muted">Progreso del período: <?= round($progreso, 1) ?>%</small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                
                <!-- INFORMACIÓN DEL DOCENTE -->
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-person-circle me-2"></i>Mi Información
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="teacher-profile">
                            <div class="teacher-avatar">
                                <?= strtoupper(substr($me['email'], 0, 2)) ?>
                            </div>
                            <div class="teacher-info">
                                <h6 class="teacher-name">
                                    <?= htmlspecialchars(($me['docente_nombres'] ?? '') . ' ' . ($me['docente_apellidos'] ?? '')) ?>
                                </h6>
                                <div class="teacher-role">
                                    <?php
                                    $role_names = [
                                        'tutor' => 'Tutor de Aula',
                                        'docente_area' => 'Docente de Área',
                                        'docente_taller' => 'Docente de Taller'
                                    ];
                                    echo $role_names[$me['role']] ?? ucfirst($me['role']);
                                    
                                    if ($me['tipo_docente']) {
                                        echo ' • ' . ucfirst($me['tipo_docente']);
                                    }
                                    ?>
                                </div>
                                <div class="teacher-email"><?= htmlspecialchars($me['email']) ?></div>
                            </div>
                        </div>
                        
                        <div class="teacher-stats">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="bi bi-door-open"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-label">Aulas asignadas</div>
                                    <div class="stat-number"><?= count($datos_rol['mis_aulas']) ?></div>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="bi bi-book"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-label">Áreas asignadas</div>
                                    <div class="stat-number"><?= count($datos_rol['mis_areas']) ?></div>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-label">Estudiantes a cargo</div>
                                    <div class="stat-number"><?= $datos_rol['estudiantes_asignados'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ALERTAS ACADÉMICAS DOCENTE (si las tienes) -->
                <?php if (!empty($datos_rol['alertas_academicas'])): ?>
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-bell me-2"></i>Alertas Académicas
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alerts-list">
                            <?php foreach ($datos_rol['alertas_academicas'] as $alerta): ?>
                                <div class="alert-item alert-<?= $alerta['tipo'] ?>">
                                    <div class="alert-icon">
                                        <i class="bi bi-<?= $alerta['icono'] ?>"></i>
                                    </div>
                                    <div class="alert-content">
                                        <div class="alert-title"><?= htmlspecialchars($alerta['titulo']) ?></div>
                                        <div class="alert-message"><?= htmlspecialchars($alerta['mensaje']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Fin del dashboard -->