<?php
// app/views/pages/competencies.php - Gestión de competencias por área

$areas = $pdo->query("SELECT id, nombre, nivel FROM areas ORDER BY nivel, nombre")->fetchAll();
$area_seleccionada = $_GET['area_id'] ?? ($areas[0]['id'] ?? null);

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['create'])) {
        q($pdo,"INSERT INTO competencias(area_id, nombre, descripcion, capacidades) VALUES(?, ?, ?, ?)", 
          [$_POST['area_id'], $_POST['nombre'], $_POST['descripcion'], $_POST['capacidades']]);
    }
}

// Obtener competencias del área seleccionada
$competencias = [];
$area_info = null;
if($area_seleccionada) {
    $competencias = q($pdo, "SELECT * FROM competencias WHERE area_id = ? ORDER BY id", [$area_seleccionada])->fetchAll();
    $area_info = q($pdo, "SELECT nombre, nivel FROM areas WHERE id = ?", [$area_seleccionada])->fetch();
}
?>

<div class="row">
    <div class="col-12">
        <!-- Selector de área -->
        <div class="modern-card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-1">Gestión de Competencias</h5>
                        <p class="text-muted mb-0">Configura las competencias específicas para cada área curricular</p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Seleccionar Área</label>
                        <select class="form-select" onchange="location.href='index.php?page=competencies&area_id='+this.value">
                            <option value="">Seleccionar área...</option>
                            <?php foreach($areas as $area): ?>
                                <option value="<?= $area['id'] ?>" <?= $area['id'] == $area_seleccionada ? 'selected' : '' ?>>
                                    <?= e($area['nombre']) ?> (<?= ucfirst($area['nivel']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php if($area_info): ?>
        <!-- Información del área seleccionada -->
        <div class="alert alert-modern alert-info mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                <div>
                    <h6 class="mb-1">Área: <?= e($area_info['nombre']) ?></h6>
                    <small>Nivel: <?= ucfirst($area_info['nivel']) ?> | Competencias configuradas: <?= count($competencias) ?></small>
                </div>
            </div>
        </div>

        <!-- Formulario para nueva competencia -->
        <div class="modern-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva Competencia</h5>
                <p class="text-muted mb-0">Agrega una nueva competencia para el área seleccionada</p>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="area_id" value="<?= $area_seleccionada ?>">
                    
                    <div class="col-12">
                        <label class="form-label">Nombre de la Competencia *</label>
                        <input type="text" class="form-control" name="nombre" 
                               placeholder="ej: Resuelve problemas de cantidad, Se comunica oralmente..." required>
                        <div class="form-text">Describe la competencia de manera clara y específica</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="4"
                                  placeholder="Describe los alcances y objetivos de esta competencia..."></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Capacidades</label>
                        <textarea class="form-control" name="capacidades" rows="4"
                                  placeholder="Lista las capacidades específicas separadas por líneas..."></textarea>
                        <div class="form-text">Una capacidad por línea</div>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="create" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Crear Competencia
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de competencias -->
        <div class="modern-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Competencias de <?= e($area_info['nombre']) ?></h5>
                    <p class="text-muted mb-0"><?= count($competencias) ?> competencia(s) configurada(s)</p>
                </div>
                <span class="badge bg-primary fs-6"><?= count($competencias) ?></span>
            </div>
            
            <?php if(empty($competencias)): ?>
                <div class="card-body text-center py-5">
                    <i class="bi bi-list-check display-1 text-muted"></i>
                    <h6 class="mt-3 text-muted">No hay competencias configuradas</h6>
                    <p class="text-muted">Agrega la primera competencia usando el formulario superior</p>
                </div>
            <?php else: ?>
                <div class="card-body p-0">
                    <?php foreach($competencias as $index => $comp): ?>
                        <div class="competencia-item p-4 <?= $index < count($competencias) - 1 ? 'border-bottom' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="competencia-header">
                                    <h6 class="mb-1 text-primary">
                                        <span class="badge bg-primary me-2"><?= $index + 1 ?></span>
                                        <?= e($comp['nombre']) ?>
                                    </h6>
                                    <small class="text-muted">ID: <?= $comp['id'] ?></small>
                                </div>
                                <div class="competencia-actions">
                                    <button class="btn btn-sm btn-outline-secondary me-1" 
                                            onclick="editCompetencia(<?= $comp['id'] ?>, '<?= e($comp['nombre']) ?>', '<?= e($comp['descripcion']) ?>', '<?= e($comp['capacidades']) ?>')"
                                            title="Editar competencia">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="index.php?page=grading&competencia_id=<?= $comp['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary"
                                       title="Evaluar competencia">
                                        <i class="bi bi-ui-checks-grid"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <?php if($comp['descripcion']): ?>
                                <div class="competencia-descripcion mb-3">
                                    <p class="text-muted mb-0"><?= e($comp['descripcion']) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($comp['capacidades']): ?>
                                <div class="competencia-capacidades">
                                    <small class="text-muted fw-semibold">Capacidades:</small>
                                    <ul class="list-unstyled mt-2 mb-0">
                                        <?php foreach(explode("\n", $comp['capacidades']) as $capacidad): ?>
                                            <?php if(trim($capacidad)): ?>
                                                <li class="d-flex align-items-start mb-1">
                                                    <i class="bi bi-check-circle-fill text-success me-2 mt-1" style="font-size: 0.8rem;"></i>
                                                    <small><?= e(trim($capacidad)) ?></small>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Mensaje cuando no hay área seleccionada -->
        <div class="text-center py-5">
            <i class="bi bi-arrow-up-circle display-1 text-muted"></i>
            <h5 class="mt-3 text-muted">Selecciona un área curricular</h5>
            <p class="text-muted">Elige un área del selector superior para gestionar sus competencias</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para editar competencia -->
<div class="modal fade" id="editCompetenciaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Competencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCompetenciaForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="competencia_id" id="edit_competencia_id">
                    <input type="hidden" name="area_id" value="<?= $area_seleccionada ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Competencia</label>
                        <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Capacidades</label>
                        <textarea class="form-control" name="capacidades" id="edit_capacidades" rows="4"></textarea>
                        <div class="form-text">Una capacidad por línea</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.competencia-item {
    transition: background-color 0.2s ease;
}

.competencia-item:hover {
    background-color: #f8fafc;
}

.competencia-header h6 {
    line-height: 1.4;
}

.competencia-actions {
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.competencia-item:hover .competencia-actions {
    opacity: 1;
}
</style>

<script>
function editCompetencia(id, nombre, descripcion, capacidades) {
    document.getElementById('edit_competencia_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_descripcion').value = descripcion;
    document.getElementById('edit_capacidades').value = capacidades;
    new bootstrap.Modal(document.getElementById('editCompetenciaModal')).show();
}
</script>