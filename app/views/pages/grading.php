<?php
// app/views/pages/grading.php - Matriz de calificaciones moderna (solo para docentes)

// Verificar que el usuario sea docente o admin
if($me['role'] !== 'docente' && !is_admin()) {
    echo '<div class="alert alert-modern alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Solo los docentes pueden acceder a la matriz de calificaciones.
          </div>';
    return;
}

$anios = $pdo->query("SELECT id, anio FROM anios_academicos ORDER BY anio DESC")->fetchAll();
$anio_id = $_GET['anio_id'] ?? ($anios[0]['id'] ?? null);

// Filtrar por docente si no es admin
$where_docente = '';
$params_docente = [];
if($me['role'] === 'docente' && $me['docente_id']) {
    $where_docente = ' AND EXISTS (SELECT 1 FROM docente_aula_area daa WHERE daa.aula_id = a.id AND daa.docente_id = ?)';
    $params_docente = [$me['docente_id']];
}

$periodos = q($pdo, "SELECT id, nombre FROM periodos WHERE anio_id = ? ORDER BY id", [$anio_id])->fetchAll();
$aulas = q($pdo, "SELECT DISTINCT a.id, CONCAT(a.nombre,' ',a.seccion,' - ',a.grado) AS n 
                  FROM aulas a WHERE a.anio_id = ? $where_docente ORDER BY a.id", 
                  array_merge([$anio_id], $params_docente))->fetchAll();

$periodo_id = $_GET['periodo_id'] ?? ($periodos[0]['id'] ?? null);
$aula_id = $_GET['aula_id'] ?? ($aulas[0]['id'] ?? null);

// Obtener áreas asignadas al docente para esta aula
$areas = [];
if($aula_id) {
    if($me['role'] === 'admin') {
        $areas = $pdo->query("SELECT DISTINCT ar.id, ar.nombre, ar.nivel FROM areas ar ORDER BY ar.nombre")->fetchAll();
    } else {
        $areas = q($pdo, "SELECT DISTINCT ar.id, ar.nombre, ar.nivel 
                          FROM areas ar 
                          JOIN docente_aula_area daa ON daa.area_id = ar.id 
                          WHERE daa.docente_id = ? AND daa.aula_id = ?", 
                          [$me['docente_id'], $aula_id])->fetchAll();
    }
}

$area_id = $_GET['area_id'] ?? ($areas[0]['id'] ?? null);

// Obtener competencias del área seleccionada
$competencias = [];
if($area_id) {
    $competencias = q($pdo, "SELECT id, nombre FROM competencias WHERE area_id = ? ORDER BY id", [$area_id])->fetchAll();
}

// Obtener estudiantes del aula
$estudiantes = [];
if($aula_id) {
    $estudiantes = q($pdo, "SELECT id, CONCAT(apellidos,', ',nombres) AS nombre 
                           FROM estudiantes WHERE aula_id = ? ORDER BY apellidos, nombres", [$aula_id])->fetchAll();
}

// Obtener información contextual
$aula_info = $aula_id ? q($pdo, "SELECT * FROM aulas WHERE id = ?", [$aula_id])->fetch() : null;
$area_info = $area_id ? q($pdo, "SELECT * FROM areas WHERE id = ?", [$area_id])->fetch() : null;
$periodo_info = $periodo_id ? q($pdo, "SELECT * FROM periodos WHERE id = ?", [$periodo_id])->fetch() : null;

function obtenerNota($pdo, $estudiante_id, $competencia_id, $periodo_id) {
    $stmt = q($pdo, "SELECT nota FROM calificaciones WHERE estudiante_id = ? AND competencia_id = ? AND periodo_id = ? LIMIT 1", 
              [$estudiante_id, $competencia_id, $periodo_id]);
    return $stmt->fetchColumn() ?: '';
}
?>

<div class="row">
    <div class="col-12">
        <!-- Filtros de selección -->
        <div class="matrix-filters">
            <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Filtros de Evaluación</h5>
            
            <form method="get" action="index.php" class="row g-3">
                <input type="hidden" name="page" value="grading">
                
                <div class="col-md-2">
                    <div class="filter-section">
                        <label class="filter-label">Año Académico</label>
                        <select name="anio_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach($anios as $anio): ?>
                                <option value="<?= $anio['id'] ?>" <?= $anio['id'] == $anio_id ? 'selected' : '' ?>>
                                    <?= $anio['anio'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="filter-section">
                        <label class="filter-label">Período</label>
                        <select name="periodo_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach($periodos as $periodo): ?>
                                <option value="<?= $periodo['id'] ?>" <?= $periodo['id'] == $periodo_id ? 'selected' : '' ?>>
                                    <?= e($periodo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="filter-section">
                        <label class="filter-label">Aula</label>
                        <select name="aula_id" class="form-select" onchange="this.form.submit()">
                            <?php if(empty($aulas)): ?>
                                <option value="">No hay aulas asignadas</option>
                            <?php else: ?>
                                <?php foreach($aulas as $aula): ?>
                                    <option value="<?= $aula['id'] ?>" <?= $aula['id'] == $aula_id ? 'selected' : '' ?>>
                                        <?= e($aula['n']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="filter-section">
                        <label class="filter-label">Área Curricular</label>
                        <select name="area_id" class="form-select" onchange="this.form.submit()">
                            <?php if(empty($areas)): ?>
                                <option value="">No hay áreas asignadas</option>
                            <?php else: ?>
                                <?php foreach($areas as $area): ?>
                                    <option value="<?= $area['id'] ?>" <?= $area['id'] == $area_id ? 'selected' : '' ?>>
                                        <?= e($area['nombre']) ?> (<?= ucfirst($area['nivel']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <?php if($aula_info && $area_info && $periodo_info && !empty($competencias) && !empty($estudiantes)): ?>
        
        <!-- Información del contexto -->
        <div class="grading-matrix">
            <div class="grading-header">
                <h4 class="mb-0">Matriz de Evaluación</h4>
                <div class="grading-info">
                    <div class="grading-info-item">
                        <div class="grading-info-label">Aula</div>
                        <div class="grading-info-value"><?= e($aula_info['nombre'] . ' ' . $aula_info['seccion'] . ' - ' . $aula_info['grado']) ?></div>
                    </div>
                    <div class="grading-info-item">
                        <div class="grading-info-label">Período</div>
                        <div class="grading-info-value"><?= e($periodo_info['nombre']) ?></div>
                    </div>
                    <div class="grading-info-item">
                        <div class="grading-info-label">Área</div>
                        <div class="grading-info-value"><?= e($area_info['nombre']) ?></div>
                    </div>
                    <div class="grading-info-item">
                        <div class="grading-info-label">Estudiantes</div>
                        <div class="grading-info-value"><?= count($estudiantes) ?></div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de competencias -->
            <div class="card-body">
                <ul class="nav nav-pills mb-4" role="tablist">
                    <?php foreach($competencias as $index => $comp): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#competencia-<?= $comp['id'] ?>" 
                                    type="button" role="tab">
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
                             
                            <div class="competencia-header-info mb-4 p-3 bg-light rounded">
                                <h6 class="text-primary mb-2"><?= e($comp['nombre']) ?></h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Califica a cada estudiante según su desempeño</small>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                onclick="aplicarATodos('<?= $comp['id'] ?>', 'AD')">
                                            Aplicar AD a todos
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="aplicarATodos('<?= $comp['id'] ?>', 'A')">
                                            Aplicar A a todos
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de calificaciones -->
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start" style="min-width: 250px;">Estudiante</th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong>AD</strong>
                                                    <small class="text-muted">Destacado</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong>A</strong>
                                                    <small class="text-muted">Logrado</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong>B</strong>
                                                    <small class="text-muted">Proceso</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 80px;">
                                                <div class="d-flex flex-column align-items-center">
                                                    <strong>C</strong>
                                                    <small class="text-muted">Inicio</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width: 100px;">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($estudiantes as $estudiante): ?>
                                            <?php $nota_actual = obtenerNota($pdo, $estudiante['id'], $comp['id'], $periodo_id); ?>
                                            <tr data-estudiante="<?= $estudiante['id'] ?>" data-competencia="<?= $comp['id'] ?>">
                                                <td class="text-start">
                                                    <div class="d-flex align-items-center">
                                                        <div class="student-avatar me-3">
                                                            <?= strtoupper(substr($estudiante['nombre'], 0, 2)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?= e($estudiante['nombre']) ?></div>
                                                            <small class="text-muted">ID: <?= $estudiante['id'] ?></small>
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
                                                                onclick="seleccionarNota(this)">
                                                            <?= $nota ?>
                                                        </button>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <td class="text-center">
                                                    <span class="badge bg-<?= $nota_actual ? 'success' : 'secondary' ?>">
                                                        <?= $nota_actual ? 'Evaluado' : 'Pendiente' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Resumen de la competencia -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <div class="row text-center">
                                    <div class="col-3">
                                        <div class="stat-number text-success" id="count-ad-<?= $comp['id'] ?>">
                                            <?php
                                            $count_ad = q($pdo, "SELECT COUNT(*) FROM calificaciones WHERE competencia_id = ? AND periodo_id = ? AND nota = 'AD'", [$comp['id'], $periodo_id])->fetchColumn();
                                            echo $count_ad;
                                            ?>
                                        </div>
                                        <div class="stat-label">AD</div>
                                    </div>
                                    <div class="col-3">
                                        <div class="stat-number text-primary" id="count-a-<?= $comp['id'] ?>">
                                            <?php
                                            $count_a = q($pdo, "SELECT COUNT(*) FROM calificaciones WHERE competencia_id = ? AND periodo_id = ? AND nota = 'A'", [$comp['id'], $periodo_id])->fetchColumn();
                                            echo $count_a;
                                            ?>
                                        </div>
                                        <div class="stat-label">A</div>
                                    </div>
                                    <div class="col-3">
                                        <div class="stat-number text-warning" id="count-b-<?= $comp['id'] ?>">
                                            <?php
                                            $count_b = q($pdo, "SELECT COUNT(*) FROM calificaciones WHERE competencia_id = ? AND periodo_id = ? AND nota = 'B'", [$comp['id'], $periodo_id])->fetchColumn();
                                            echo $count_b;
                                            ?>
                                        </div>
                                        <div class="stat-label">B</div>
                                    </div>
                                    <div class="col-3">
                                        <div class="stat-number text-danger" id="count-c-<?= $comp['id'] ?>">
                                            <?php
                                            $count_c = q($pdo, "SELECT COUNT(*) FROM calificaciones WHERE competencia_id = ? AND periodo_id = ? AND nota = 'C'", [$comp['id'], $periodo_id])->fetchColumn();
                                            echo $count_c;
                                            ?>
                                        </div>
                                        <div class="stat-label">C</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones de la competencia -->
                            <div class="mt-3 d-flex gap-2">
                                <a href="public/actions/export.php?periodo_id=<?= $periodo_id ?>&aula_id=<?= $aula_id ?>&competencia_id=<?= $comp['id'] ?>" 
                                   class="btn btn-success">
                                    <i class="bi bi-download me-2"></i>Exportar
                                </a>
                                <button type="button" class="btn btn-info" onclick="verEstadisticas('<?= $comp['id'] ?>')">
                                    <i class="bi bi-graph-up me-2"></i>Estadísticas
                                </button>
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
                <p class="text-muted">
                    <?php if(empty($aulas)): ?>
                        No tienes aulas asignadas para evaluar.
                    <?php elseif(empty($areas)): ?>
                        No tienes áreas curriculares asignadas para esta aula.
                    <?php elseif(empty($competencias)): ?>
                        El área seleccionada no tiene competencias configuradas.
                    <?php elseif(empty($estudiantes)): ?>
                        No hay estudiantes registrados en esta aula.
                    <?php else: ?>
                        Selecciona todos los filtros para comenzar a evaluar.
                    <?php endif; ?>
                </p>
                <?php if(is_admin()): ?>
                    <a href="index.php?page=assignments" class="btn btn-primary">
                        <i class="bi bi-gear me-2"></i>Configurar Asignaciones
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Estilos adicionales -->
<style>
.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
}

.nav-pills .nav-link {
    border-radius: 0.75rem;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.nav-pills .nav-link.active {
    background: var(--primary-color);
}

.btn-grade:not(.active):hover {
    transform: scale(1.1);
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-spinner {
    background: white;
    padding: 2rem;
    border-radius: 1rem;
    text-align: center;
}
</style>

<!-- JavaScript para la funcionalidad -->
<script>
const periodoId = <?= $periodo_id ?: 'null' ?>;

async function seleccionarNota(btn) {
    if (!periodoId) return;
    
    const estudiante = btn.dataset.estudiante;
    const competencia = btn.dataset.competencia;
    const nota = btn.dataset.nota;
    const fila = btn.closest('tr');
    
    // Mostrar loading
    btn.innerHTML = '<div class="spinner"></div>';
    btn.disabled = true;
    
    try {
        const response = await fetch('public/actions/save_grade.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                estudiante_id: estudiante,
                competencia_id: competencia,
                periodo_id: periodoId,
                nota: nota
            })
        });
        
        if (response.ok) {
            // Actualizar UI
            fila.querySelectorAll('.btn-grade').forEach(b => {
                b.classList.remove('active');
                b.innerHTML = b.dataset.nota;
            });
            
            btn.classList.add('active');
            btn.innerHTML = '✓';
            
            // Actualizar badge de estado
            const badge = fila.querySelector('.badge');
            badge.className = 'badge bg-success';
            badge.textContent = 'Evaluado';
            
            // Actualizar contadores
            actualizarContadores(competencia);
            
            // Mostrar notificación
            Swal.fire({
                icon: 'success',
                title: 'Calificación guardada',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
            
        } else {
            throw new Error('Error al guardar');
        }
    } catch (error) {
        Swal.fire('Error', 'No se pudo guardar la calificación', 'error');
        btn.innerHTML = nota;
    } finally {
        btn.disabled = false;
    }
}

async function aplicarATodos(competenciaId, nota) {
    const result = await Swal.fire({
        title: '¿Confirmar acción?',
        text: `¿Aplicar la calificación "${nota}" a todos los estudiantes?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aplicar',
        cancelButtonText: 'Cancelar'
    });
    
    if (!result.isConfirmed) return;
    
    const filas = document.querySelectorAll(`tr[data-competencia="${competenciaId}"]`);
    
    // Mostrar overlay de carga
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner mb-3"></div>
            <div>Aplicando calificaciones...</div>
        </div>
    `;
    document.body.appendChild(overlay);
    
    try {
        for (const fila of filas) {
            const estudiante = fila.dataset.estudiante;
            const btn = fila.querySelector(`[data-nota="${nota}"]`);
            
            await fetch('public/actions/save_grade.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    estudiante_id: estudiante,
                    competencia_id: competenciaId,
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
            badge.className = 'badge bg-success';
            badge.textContent = 'Evaluado';
        }
        
        // Actualizar contadores
        actualizarContadores(competenciaId);
        
        Swal.fire('Completado', 'Se aplicó la calificación a todos los estudiantes', 'success');
        
    } catch (error) {
        Swal.fire('Error', 'Hubo un problema al aplicar las calificaciones', 'error');
    } finally {
        document.body.removeChild(overlay);
    }
}

function actualizarContadores(competenciaId) {
    // Aquí podrías hacer una llamada AJAX para obtener los contadores actualizados
    // Por simplicidad, vamos a contar los elementos en la página
    const filas = document.querySelectorAll(`tr[data-competencia="${competenciaId}"]`);
    
    let counts = { AD: 0, A: 0, B: 0, C: 0 };
    
    filas.forEach(fila => {
        const btnActivo = fila.querySelector('.btn-grade.active');
        if (btnActivo) {
            const nota = btnActivo.dataset.nota;
            counts[nota]++;
        }
    });
    
    Object.keys(counts).forEach(nota => {
        const elemento = document.getElementById(`count-${nota.toLowerCase()}-${competenciaId}`);
        if (elemento) {
            elemento.textContent = counts[nota];
        }
    });
}

function verEstadisticas(competenciaId) {
    // Implementar vista de estadísticas detalladas
    Swal.fire({
        title: 'Estadísticas de la Competencia',
        html: 'Función en desarrollo...',
        icon: 'info'
    });
}
</script>