<?php
$anios=$pdo->query("SELECT id, anio FROM anios_academicos ORDER BY anio DESC")->fetchAll();
$areas=$pdo->query("SELECT id, nombre FROM areas ORDER BY nombre")->fetchAll();
$anio_id = $_GET['anio_id'] ?? ($anios[0]['id'] ?? null);
$area_id = $_GET['area_id'] ?? ($areas[0]['id'] ?? null);

$periodos = q($pdo,"SELECT id, nombre FROM periodos WHERE anio_id=? ORDER BY id",[$anio_id])->fetchAll();
$aulas    = q($pdo,"SELECT id, CONCAT(nombre,' ',seccion,' - ',grado) AS n FROM aulas WHERE anio_id=? ORDER BY id",[$anio_id])->fetchAll();
$competencias = q($pdo,"SELECT id, nombre FROM competencias WHERE area_id=? ORDER BY id",[$area_id])->fetchAll();

$periodo_id = $_GET['periodo_id'] ?? ($periodos[0]['id'] ?? null);
$aula_id    = $_GET['aula_id'] ?? ($aulas[0]['id'] ?? null);
$competencia_id = $_GET['competencia_id'] ?? ($competencias[0]['id'] ?? null);

$estudiantes = $aula_id ? q($pdo,"SELECT id, CONCAT(apellidos,', ',nombres) AS n FROM estudiantes WHERE aula_id=? ORDER BY apellidos, nombres",[$aula_id])->fetchAll() : [];

function nota($pdo,$est,$comp,$per){
  $s=q($pdo,"SELECT nota FROM calificaciones WHERE estudiante_id=? AND competencia_id=? AND periodo_id=? LIMIT 1",[$est,$comp,$per]);
  return $s->fetchColumn() ?: '';
}
?>
<h4>Matriz de Calificaciones</h4>
<div class="card shadow-sm"><div class="card-body">
<form class="row g-2 mb-3" method="get" action="index.php">
  <input type="hidden" name="page" value="grading">
  <div class="col-md-2"><label class="form-label small">Año</label>
    <select name="anio_id" class="form-select" onchange="this.form.submit()"><?php foreach($anios as $a): ?><option value="<?=$a['id']?>" <?=($a['id']==$anio_id)?'selected':''?>><?=$a['anio']?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-3"><label class="form-label small">Período</label>
    <select name="periodo_id" class="form-select" onchange="this.form.submit()"><?php foreach($periodos as $p): ?><option value="<?=$p['id']?>" <?=($p['id']==$periodo_id)?'selected':''?>><?=$p['nombre']?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-3"><label class="form-label small">Aula</label>
    <select name="aula_id" class="form-select" onchange="this.form.submit()"><?php foreach($aulas as $au): ?><option value="<?=$au['id']?>" <?=($au['id']==$aula_id)?'selected':''?>><?=$au['n']?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-2"><label class="form-label small">Área</label>
    <select name="area_id" class="form-select" onchange="this.form.submit()"><?php foreach($areas as $ar): ?><option value="<?=$ar['id']?>" <?=($ar['id']==$area_id)?'selected':''?>><?=$ar['nombre']?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-2"><label class="form-label small">Competencia</label>
    <select name="competencia_id" class="form-select" onchange="this.form.submit()"><?php foreach($competencias as $c): ?><option value="<?=$c['id']?>" <?=($c['id']==$competencia_id)?'selected':''?>><?=$c['nombre']?></option><?php endforeach; ?></select>
  </div>
</form>

<div class="table-responsive">
<table class="table table-bordered align-middle text-center">
  <thead class="table-light"><tr><th class="text-start">Estudiante</th><th>AD</th><th>A</th><th>B</th><th>C</th></tr></thead>
  <tbody>
  <?php foreach($estudiantes as $e): $n=nota($pdo,$e['id'],$competencia_id,$periodo_id); ?>
    <tr data-est="<?=$e['id']?>">
      <td class="text-start"><?=$e['n']?></td>
      <?php foreach(['AD','A','B','C'] as $nota): $act = ($n===$nota)?'active':''; ?>
      <td><button type="button" class="btn btn-light btn-nota <?=$act?>" data-nota="<?=$nota?>" data-est="<?=$e['id']?>"><?=$act?'✓':''?></button></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<div class="d-flex gap-2">
  <button class="btn btn-secondary" id="btn-aplicar-todos" data-nota="A">Aplicar A a todos</button>
  <a class="btn btn-success" id="btn-export" href="public/actions/export.php?periodo_id=<?=$periodo_id?>&aula_id=<?=$aula_id?>&competencia_id=<?=$competencia_id?>">Exportar Excel/CSV/PDF</a>
</div>
</div></div>

<script>
const params = new URLSearchParams(window.location.search);
const periodo_id = params.get('periodo_id');
const competencia_id = params.get('competencia_id');

document.querySelectorAll('.btn-nota').forEach(btn=>{
  btn.addEventListener('click', async () => {
    const est = btn.dataset.est;
    const nota = btn.dataset.nota;
    const row = btn.closest('tr');
    row.querySelectorAll('.btn-nota').forEach(b=>{b.classList.remove('active'); b.innerText='';});
    btn.classList.add('active'); btn.innerText='✓';
    const res = await fetch('public/actions/save_grade.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({estudiante_id: est, competencia_id, periodo_id, nota})
    });
    if(!res.ok){ Swal.fire('Error','No se pudo guardar','error'); }
  });
});

document.getElementById('btn-aplicar-todos').addEventListener('click', async (e)=>{
  const nota = e.target.dataset.nota;
  const rows = document.querySelectorAll('tbody tr');
  for (const r of rows){
    const est = r.dataset.est;
    const btn = r.querySelector(`.btn-nota[data-nota="${nota}"]`);
    r.querySelectorAll('.btn-nota').forEach(b=>{b.classList.remove('active'); b.innerText='';});
    btn.classList.add('active'); btn.innerText='✓';
    await fetch('public/actions/save_grade.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({estudiante_id: est, competencia_id, periodo_id, nota})
    });
  }
  Swal.fire('Listo','Se aplicó la nota a todos','success');
});
</script>
