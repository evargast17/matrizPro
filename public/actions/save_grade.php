<?php
require __DIR__.'/../../core.php';

// Verificar que el usuario esté logueado
if (!auth()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Solo procesar requests POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// Validar datos requeridos
$estudiante_id = (int)($data['estudiante_id'] ?? 0);
$competencia_id = (int)($data['competencia_id'] ?? 0);
$periodo_id = (int)($data['periodo_id'] ?? 0);
$nota = $data['nota'] ?? '';
$observaciones = $data['observaciones'] ?? '';

// Validar que los datos sean válidos
if (!$estudiante_id || !$competencia_id || !$periodo_id || !in_array($nota, ['AD', 'A', 'B', 'C'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos o inválidos']);
    exit;
}

$me = auth();

// Verificar permisos (simplificado)
$puede_editar = false;

try {
    if ($me['role'] === 'admin') {
        $puede_editar = true;
    } elseif ($me['docente_id']) {
        // Verificar que el docente esté asignado a esta aula y área
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM docente_aula_area daa
            JOIN estudiantes e ON e.aula_id = daa.aula_id
            JOIN competencias c ON c.area_id = daa.area_id
            WHERE daa.docente_id = ? AND e.id = ? AND c.id = ? AND daa.activo = 1
        ");
        $stmt->execute([$me['docente_id'], $estudiante_id, $competencia_id]);
        $puede_editar = $stmt->fetchColumn() > 0;
    }
    
    if (!$puede_editar) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes permisos para editar esta calificación']);
        exit;
    }
    
    // Verificar que el estudiante, competencia y período existan
    $stmt = $pdo->prepare("
        SELECT e.id as estudiante_existe, c.id as competencia_existe, p.id as periodo_existe
        FROM estudiantes e, competencias c, periodos p
        WHERE e.id = ? AND c.id = ? AND p.id = ?
    ");
    $stmt->execute([$estudiante_id, $competencia_id, $periodo_id]);
    $verificacion = $stmt->fetch();
    
    if (!$verificacion) {
        http_response_code(400);
        echo json_encode(['error' => 'Estudiante, competencia o período no válidos']);
        exit;
    }
    
    // Insertar o actualizar la calificación
    $stmt = $pdo->prepare("
        INSERT INTO calificaciones (estudiante_id, competencia_id, periodo_id, nota, observaciones, registrado_por, estado)
        VALUES (?, ?, ?, ?, ?, ?, 'registrado')
        ON DUPLICATE KEY UPDATE 
        nota = VALUES(nota),
        observaciones = VALUES(observaciones),
        registrado_por = VALUES(registrado_por),
        estado = 'registrado',
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $result = $stmt->execute([
        $estudiante_id, 
        $competencia_id, 
        $periodo_id, 
        $nota, 
        $observaciones, 
        $me['id']
    ]);
    
    if ($result) {
        // Log de auditoría
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'SAVE_GRADE', 'calificaciones', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $me['id'],
                $estudiante_id . '_' . $competencia_id . '_' . $periodo_id,
                json_encode([
                    'estudiante_id' => $estudiante_id,
                    'competencia_id' => $competencia_id,
                    'periodo_id' => $periodo_id,
                    'nota' => $nota,
                    'observaciones' => $observaciones
                ]),
                $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {
            // Log de auditoría falló, pero no es crítico
            error_log('Audit log failed: ' . $e->getMessage());
        }
        
        echo json_encode([
            'ok' => true,
            'message' => 'Calificación guardada correctamente',
            'data' => [
                'estudiante_id' => $estudiante_id,
                'competencia_id' => $competencia_id,
                'periodo_id' => $periodo_id,
                'nota' => $nota,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('Error al ejecutar la consulta');
    }
    
} catch (Exception $e) {
    error_log('Save grade error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => 'No se pudo guardar la calificación. Intenta nuevamente.'
    ]);
}
?>