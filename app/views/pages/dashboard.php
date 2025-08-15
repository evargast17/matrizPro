<?php
// app/views/pages/dashboard.php - Dashboard moderno con estadísticas

$total_est = $pdo->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn();
$total_doc = $pdo->query("SELECT COUNT(*) FROM docentes")->fetchColumn();
$total_areas = $pdo->query("SELECT COUNT(*) FROM areas")->fetchColumn();
$total_competencias = $pdo->query("SELECT COUNT(*) FROM competencias")->fetchColumn();
$anio_act = $pdo->query("SELECT anio FROM anios_academicos WHERE activo=1 LIMIT 1")->fetchColumn();

// Estadísticas por nivel
$areas_inicial = $pdo->query("SELECT COUNT(*) FROM areas WHERE nivel='inicial'")->fetchColumn();
$areas_primaria = $pdo->query("SELECT COUNT(*) FROM areas WHERE nivel='primaria'")->fetchColumn();

// Estadísticas de evaluaciones recientes
$evaluaciones_mes = $pdo->query("SELECT COUNT(*) FROM calificaciones WHERE MONTH(updated_at) = MONTH(NOW()) AND YEAR(updated_at) = YEAR(NOW())")->fetchColumn();

// Distribución de notas (último período)
$periodo_actual = $pdo->query("SELECT id FROM periodos WHERE fecha_fin >= NOW() ORDER BY fecha_inicio LIMIT 1")->fetchColumn();
$notas_stats = [];
if($periodo_actual) {
    $notas_stats = [
        'AD' => $pdo->query("SELECT COUNT(*) FROM calificaciones WHERE periodo_id = $periodo_actual AND nota = 'AD'")->fetchColumn(),
        'A' => $pdo->query("SELECT COUNT(*) FROM calificaciones WHERE periodo_id = $periodo_actual AND nota = 'A'")->fetchColumn(),
        'B' => $pdo->query("SELECT COUNT(*) FROM calificaciones WHERE periodo_id = $periodo_actual AND nota = 'B'")->fetchColumn(),
        'C' => $pdo->query("SELECT COUNT(*) FROM calificaciones WHERE periodo_id = $periodo_actual AND nota = 'C'")->fetchColumn()
    ];
}

// Progreso por área (solo para el rol actual)
$areas_progreso = [];
if($me['role'] === 'docente' && $me['docente_id']) {
    $areas_progreso = q($pdo, "
        SELECT a.nombre, a.nivel, COUNT(DISTINCT c.id) as total_competencias,
               COUNT(DISTINCT cal.id) as evaluaciones_realizadas
        FROM areas a 
        JOIN competencias c ON c.area_id = a.id
        JOIN docente_aula_area daa ON daa.area_id = a.id AND daa.docente_id = ?
        LEFT JOIN calificaciones cal ON cal.competencia_id = c.id 
        GROUP BY a.id, a.nombre, a.nivel
        ORDER BY a.nivel, a.nombre
    ", [$me['docente_id']])->fetchAll();
} else {
    $areas_progreso = $pdo->query("
        SELECT a.nombre, a.nivel, COUNT(DISTINCT c.id) as total_competencias,
               COUNT(DISTINCT cal.id) as evaluaciones_realizadas
        FROM areas a 
        LEFT JOIN competencias c ON c.area_id = a.id
        LEFT JOIN calificaciones cal ON cal.competencia_id = c.id 
        GROUP BY a.id, a.nombre, a.nivel
        ORDER BY a.nivel, a.nombre
    ")->fetchAll();
}

// Aulas del docente o todas si es admin
$mis_aulas = [];
if($me['role'] === 'docente' && $me['docente_id']) {
    $mis_aulas = q($pdo, "
        SELECT DISTINCT au.id, CONCAT(au.nombre, ' ', au.seccion, ' - ', au.grado) as aula,
               COUNT(DISTINCT e.id) as total_estudiantes
        FROM aulas au
        JOIN docente_aula_area daa ON daa.aula_id = au.id AND daa.docente_id = ?
        JOIN estudiantes e ON e.aula_id = au.id
        GROUP BY au.id
        ORDER BY au.nombre
    ", [$me['docente_id']])->fetchAll();
}

$recent_activities = [];
if($me['role'] === 'docente' && $me['docente_id']) {
    $recent_activities = q($pdo, "
        SELECT cal.updated_at, e.nombres, e.apellidos, c.nombre as competencia, cal.nota,
               a.nombre as area, au.nombre as aula
        FROM calificaciones cal
        JOIN estudiantes e ON e.id = cal.estudiante_id
        JOIN competencias c ON c.id = cal.competencia_id
        JOIN areas a ON a.id = c.area_id
        JOIN aulas au ON au.id = e.aula_id
        JOIN docente_aula_area daa ON daa.aula_id = au.id AND daa.area_id = a.id AND daa.docente_id = ?
        ORDER BY cal.updated_at DESC
        LIMIT 10
    ", [$me['docente_id']])->fetchAll();
}
?>

<div class="fade-in-up">
    <!-- Estadísticas principales -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h3 class="stat-value"><?= $anio_act ?: '—' ?></h3>
                <p class="stat-label">Año Académico Activo</p>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="stat-value"><?= $total_est ?></h3>
                <p class="stat-label">Estudiantes</p>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="bi bi-person-badge"></i>
                </div>
                <h3 class="stat-value"><?= $total_doc ?></h3>
                <p class="stat-label">Docentes</p>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                    <i class="bi bi-list-check"></i>
                </div>
                <h3 class="stat-value"><?= $total_competencias ?></h3>
                <p class="stat-label">Competencias</p>
            </div>
        </div>
    </div>

    <!-- Estadísticas adicionales -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="modern-card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-3">Distribución por Nivel</h6>
                    <div class="row">
                        <div class="col-6">
                            <div class="display-6 text-primary"><?= $areas_inicial ?></div>
                            <small class="text-muted">Nivel Inicial</small>
                        </div>
                        <div class="col-6">
                            <div class="display-6 text-success"><?= $areas_primaria ?></div>
                            <small class="text-muted">Nivel Primaria</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="modern-card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-3">Evaluaciones del Mes</h6>
                    <div class="display-4 text-info"><?= $evaluaciones_mes ?></div>
                    <small class="text-muted">Calificaciones registradas</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="modern-card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-3">Áreas Curriculares</h6>
                    <div class="display-4 text-warning"><?= $total_areas ?></div>
                    <small class="text-muted">Configuradas</small>
                </div>
            </div>
        </div>
    </div>

    <?php if(!empty($notas_stats) && array_sum($notas_stats) > 0): ?>
    <!-- Gráfico de distribución de notas -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="modern-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Distribución de Calificaciones</h5>
                    <p class="text-muted mb-0">Período actual - Total: <?= array_sum($notas_stats) ?> evaluaciones</p>
                </div>
                <div class="card-body">
                    <canvas id="notasChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="modern-card">
                <div class="card-header">
                    <h6 class="mb-0">Resumen de Logros</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $total_evaluaciones = array_sum($notas_stats);
                    $logros = $notas_stats['AD'] + $notas_stats['A'];
                    $porcentaje_logro = $total_evaluaciones > 0 ? round(($logros / $total_evaluaciones) * 100, 1) : 0;
                    ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted">Nivel de Logro</span>
                            <span class="fw-bold"><?= $porcentaje_logro ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= $porcentaje_logro ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="text-success fw-bold"><?= $logros ?></div>
                            <small class="text-muted">Logrado</small>
                        </div>
                        <div class="col-6">
                            <div class="text-warning fw-bold"><?= $total_evaluaciones - $logros ?></div>
                            <small class="text-muted">En proceso</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Información específica del rol -->
    <div class="row g-4">
        <?php if($me['role'] === 'docente'): ?>
        <!-- Vista para docentes -->
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-door-open me-2"></i>Mis Aulas</h5>
                    <p class="text-muted mb-0">Aulas asignadas para evaluación</p>
                </div>
                <div class="card-body">
                    <?php if(empty($mis_aulas)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-info-circle text-muted"></i>
                            <p class="text-muted mb-0">No tienes aulas asignadas</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($mis_aulas as $aula): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <h6 class="mb-1"><?= e($aula['aula']) ?></h6>
                                        <small class="text-muted"><?= $aula['total_estudiantes'] ?> estudiantes</small>
                                    </div>
                                    <a href="index.php?page=grading&aula_id=<?= $aula['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-ui-checks-grid"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Actividad Reciente</h5>
                    <p class="text-muted mb-0">Últimas evaluaciones registradas</p>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_activities)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-clipboard-data text-muted"></i>
                            <p class="text-muted mb-0">No hay evaluaciones recientes</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach(array_slice($recent_activities, 0, 5) as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?= e($activity['apellidos'] . ', ' . $activity['nombres']) ?></h6>
                                        <p class="mb-1 text-muted small">
                                            <?= e($activity['competencia']) ?> - <?= e($activity['area']) ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?= $activity['nota'] === 'AD' ? 'success' : ($activity['nota'] === 'A' ? 'primary' : ($activity['nota'] === 'B' ? 'warning' : 'danger')) ?>">
                                                <?= $activity['nota'] ?>
                                            </span>
                                            <small class="text-muted">
                                                <?= date('d/m H:i', strtotime($activity['updated_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Vista para administradores -->
        <div class="col-md-8">
            <div class="modern-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Progreso por Área Curricular</h5>
                    <p class="text-muted mb-0">Estado de evaluación de competencias</p>
                </div>
                <div class="card-body">
                    <?php if(empty($areas_progreso)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-info-circle text-muted"></i>
                            <p class="text-muted mb-0">No hay datos de progreso disponibles</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($areas_progreso as $area): ?>
                            <?php 
                            $porcentaje = $area['total_competencias'] > 0 ? 
                                         round(($area['evaluaciones_realizadas'] / ($area['total_competencias'] * $total_est)) * 100, 1) : 0;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-0"><?= e($area['nombre']) ?></h6>
                                        <small class="text-muted">
                                            Nivel: <?= ucfirst($area['nivel']) ?> | 
                                            <?= $area['total_competencias'] ?> competencias
                                        </small>
                                    </div>
                                    <span class="badge bg-primary"><?= $porcentaje ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" 
                                         style="width: <?= $porcentaje ?>%; background: linear-gradient(90deg, #6366f1, #8b5cf6);">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="modern-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Acciones Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="index.php?page=students" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle me-2"></i>Registrar Estudiante
                        </a>
                        <a href="index.php?page=areas" class="btn btn-outline-success">
                            <i class="bi bi-book me-2"></i>Gestionar Áreas
                        </a>
                        <a href="index.php?page=competencies" class="btn btn-outline-info">
                            <i class="bi bi-list-check me-2"></i>Configurar Competencias
                        </a>
                        <a href="index.php?page=assignments" class="btn btn-outline-warning">
                            <i class="bi bi-link-45deg me-2"></i>Asignar Docentes
                        </a>
                        <a href="index.php?page=users" class="btn btn-outline-secondary">
                            <i class="bi bi-shield-lock me-2"></i>Gestionar Usuarios
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Estilos adicionales -->
<style>
.timeline {
    position: relative;
    padding-left: 20px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-left: 25px;
}

.timeline-marker {
    position: absolute;
    left: -25px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -21px;
    top: 15px;
    width: 2px;
    height: calc(100% + 5px);
    background: #e2e8f0;
}

.timeline-content {
    background: #f8fafc;
    padding: 12px;
    border-radius: 8px;
    border-left: 3px solid var(--primary-color);
}
</style>

<!-- JavaScript para gráficos -->
<script>
<?php if(!empty($notas_stats) && array_sum($notas_stats) > 0): ?>
// Gráfico de distribución de notas
const ctx = document.getElementById('notasChart');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['AD - Destacado', 'A - Logrado', 'B - En Proceso', 'C - En Inicio'],
        datasets: [{
            data: [<?= $notas_stats['AD'] ?>, <?= $notas_stats['A'] ?>, <?= $notas_stats['B'] ?>, <?= $notas_stats['C'] ?>],
            backgroundColor: [
                '#10b981', // Verde para AD
                '#6366f1', // Azul para A  
                '#f59e0b', // Amarillo para B
                '#ef4444'  // Rojo para C
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            }
        }
    }
});
<?php endif; ?>

// Animación de números
document.addEventListener('DOMContentLoaded', function() {
    const statValues = document.querySelectorAll('.stat-value');
    
    const animateNumber = (element) => {
        const targetValue = parseInt(element.textContent);
        const increment = targetValue / 50;
        let currentValue = 0;
        
        const timer = setInterval(() => {
            currentValue += increment;
            element.textContent = Math.floor(currentValue);
            
            if (currentValue >= targetValue) {
                element.textContent = targetValue;
                clearInterval(timer);
            }
        }, 30);
    };
    
    // Iniciar animación cuando el elemento sea visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateNumber(entry.target);
                observer.unobserve(entry.target);
            }
        });
    });
    
    statValues.forEach(stat => observer.observe(stat));
});
</script>