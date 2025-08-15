<?php
// app/views/pages/areas.php - Gestión de áreas con niveles educativos

$niveles = [
    'inicial' => 'Nivel Inicial (3-5 años)',
    'primaria' => 'Nivel Primaria (6-11 años)'
];

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['create'])) {
        q($pdo,"INSERT INTO areas(nombre, nivel, descripcion) VALUES(?, ?, ?)", 
          [$_POST['nombre'], $_POST['nivel'], $_POST['descripcion']]);
    }
}

$rows = $pdo->query("SELECT * FROM areas ORDER BY nivel, nombre")->fetchAll();
?>

<div class="row">
    <div class="col-12">
        <!-- Formulario de creación -->
        <div class="modern-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva Área Curricular</h5>
                <p class="text-muted mb-0">Configura las áreas curriculares por nivel educativo</p>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nivel Educativo</label>
                        <select name="nivel" class="form-select" required>
                            <option value="">Seleccionar nivel...</option>
                            <?php foreach($niveles as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Nombre del Área</label>
                        <input type="text" class="form-control" name="nombre" 
                               placeholder="ej: Matemática, Comunicación..." required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Descripción (opcional)</label>
                        <input type="text" class="form-control" name="descripcion" 
                               placeholder="Descripción breve del área...">
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="create" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Crear Área
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Áreas por nivel -->
        <?php foreach($niveles as $nivel_key => $nivel_label): ?>
            <?php $areas_nivel = array_filter($rows, fn($r) => $r['nivel'] === $nivel_key); ?>
            
            <div class="modern-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="bi bi-bookmark-fill me-2"></i><?= $nivel_label ?>
                        </h5>
                        <p class="text-muted mb-0">
                            <?= count($areas_nivel) ?> área(s) configurada(s)
                        </p>
                    </div>
                    <span class="badge bg-primary fs-6"><?= count($areas_nivel) ?></span>
                </div>
                
                <?php if(empty($areas_nivel)): ?>
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h6 class="mt-3 text-muted">No hay áreas configuradas para este nivel</h6>
                        <p class="text-muted">Agrega la primera área curricular usando el formulario superior</p>
                    </div>
                <?php else: ?>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0 ps-4">Área</th>
                                        <th class="border-0">Descripción</th>
                                        <th class="border-0">Competencias</th>
                                        <th class="border-0 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($areas_nivel as $area): ?>
                                        <?php 
                                        $competencias_count = q($pdo, "SELECT COUNT(*) FROM competencias WHERE area_id = ?", [$area['id']])->fetchColumn();
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-circle me-3">
                                                        <i class="bi bi-book text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= e($area['nombre']) ?></h6>
                                                        <small class="text-muted">ID: <?= $area['id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?= e($area['descripcion'] ?: 'Sin descripción') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $competencias_count ?> competencia(s)
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="index.php?page=competencies&area_id=<?= $area['id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Ver competencias">
                                                        <i class="bi bi-list-check"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-secondary" 
                                                            title="Editar área"
                                                            onclick="editArea(<?= $area['id'] ?>, '<?= e($area['nombre']) ?>', '<?= e($area['descripcion']) ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal para editar área -->
<div class="modal fade" id="editAreaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Área Curricular</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAreaForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="area_id" id="edit_area_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Área</label>
                        <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3"></textarea>
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
.icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(13, 110, 253, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
function editArea(id, nombre, descripcion) {
    document.getElementById('edit_area_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_descripcion').value = descripcion;
    new bootstrap.Modal(document.getElementById('editAreaModal')).show();
}
</script>