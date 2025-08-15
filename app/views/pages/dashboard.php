<?php
$total_est = $pdo->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn();
$total_doc = $pdo->query("SELECT COUNT(*) FROM docentes")->fetchColumn();
$total_area = $pdo->query("SELECT COUNT(*) FROM areas")->fetchColumn();
$anio_act = $pdo->query("SELECT anio FROM anios_academicos WHERE activo=1 LIMIT 1")->fetchColumn();
?>
<div class="row g-3">
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
    <div class="text-muted small">Año activo</div><h3 class="mb-0"><?=$anio_act ?: '—'?></h3>
  </div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
    <div class="text-muted small">Estudiantes</div><h3 class="mb-0"><?=$total_est?></h3>
  </div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
    <div class="text-muted small">Docentes</div><h3 class="mb-0"><?=$total_doc?></h3>
  </div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
    <div class="text-muted small">Áreas</div><h3 class="mb-0"><?=$total_area?></h3>
  </div></div></div>
</div>
<div class="card shadow-sm mt-4"><div class="card-body">
  <h5 class="card-title">Actividad del período</h5>
  <canvas id="chart"></canvas>
</div></div>
<script>
const ctx=document.getElementById('chart');
new Chart(ctx,{type:'bar',data:{labels:['AD','A','B','C'],datasets:[{label:'Notas (demo)',data:[12,28,9,3]}]}});
</script>
