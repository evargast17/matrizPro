<?php
/**
 * CORE SIMPLIFICADO - Matriz Escolar PRO
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zona horaria
date_default_timezone_set('America/Lima');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de la base de datos
$config = [
    'db' => [
        'host' => 'localhost',
        'name' => 'bdsistemaiepin',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ]
];

// Conexión a la base de datos
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['name'],
        $config['db']['charset']
    );
    
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('<div style="padding:20px;background:#fee;border:1px solid #f00;color:#800;font-family:Arial">
         Error de conexión a la base de datos: ' . $e->getMessage() . '
         </div>');
}

// =====================================================
// FUNCIONES BÁSICAS
// =====================================================

/**
 * Ejecutar consulta preparada
 */
function q($pdo, $sql, $params = []) { 
    try {
        $stmt = $pdo->prepare($sql); 
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Query error: ' . $e->getMessage() . ' SQL: ' . $sql);
        throw new Exception('Error en la consulta a la base de datos');
    }
}

/**
 * Escapar datos para HTML
 */
function e($s) { 
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
}

/**
 * Obtener usuario autenticado
 */
function auth() { 
    return $_SESSION['user'] ?? null; 
}

/**
 * Requerir login
 */
function require_login() { 
    if (!auth()) { 
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?redirect=' . $redirect); 
        exit; 
    } 
}

/**
 * Verificar si es admin
 */
function is_admin() { 
    return (auth()['role'] ?? '') === 'admin'; 
}

/**
 * Verificar si es coordinadora
 */
function is_coordinadora() {
    return (auth()['role'] ?? '') === 'coordinadora';
}

/**
 * Verificar si es tutor
 */
function is_tutor() {
    return (auth()['role'] ?? '') === 'tutor';
}

/**
 * Verificar si es docente (cualquier tipo)
 */
function is_docente() {
    $role = auth()['role'] ?? '';
    return in_array($role, ['tutor', 'docente_area', 'docente_taller']);
}

// =====================================================
// FUNCIONES DE PERMISOS SIMPLIFICADAS
// =====================================================

/**
 * Verificar permisos básicos
 */
function has_permission($permission, $context = []) {
    $user = auth();
    if (!$user) return false;
    
    // Admin siempre puede todo
    if ($user['role'] === 'admin') return true;
    
    // Coordinadora puede ver y supervisar
    if ($user['role'] === 'coordinadora' && 
        in_array($permission, ['view_all_grades', 'validate_grades', 'generate_reports'])) {
        return true;
    }
    
    // Docentes pueden editar sus áreas asignadas
    if (is_docente() && $user['docente_id'] && 
        in_array($permission, ['edit_my_grades', 'edit_subject_grades', 'edit_taller_grades'])) {
        
        $aula_id = $context['aula_id'] ?? null;
        $area_id = $context['area_id'] ?? null;
        
        if ($aula_id && $area_id) {
            global $pdo;
            try {
                $stmt = q($pdo, "
                    SELECT COUNT(*) FROM docente_aula_area 
                    WHERE docente_id = ? AND aula_id = ? AND area_id = ? AND activo = 1
                ", [$user['docente_id'], $aula_id, $area_id]);
                return $stmt->fetchColumn() > 0;
            } catch (Exception $e) {
                return false;
            }
        }
    }
    
    return false;
}

/**
 * Obtener aulas asignadas al docente
 */
function get_assigned_classrooms($docente_id) {
    global $pdo;
    
    try {
        $stmt = q($pdo, "
            SELECT DISTINCT a.id, a.nombre, a.seccion, a.grado, a.nivel,
                   CONCAT(a.nombre, ' ', a.seccion, ' - ', a.grado) as aula_completa
            FROM aulas a
            JOIN docente_aula_area daa ON daa.aula_id = a.id
            WHERE daa.docente_id = ? AND daa.activo = 1 AND a.activo = 1
            ORDER BY a.nivel, a.nombre, a.seccion
        ", [$docente_id]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get assigned classrooms error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Obtener áreas asignadas al docente
 */
function get_assigned_areas($docente_id, $aula_id = null) {
    global $pdo;
    
    try {
        $where_aula = $aula_id ? " AND daa.aula_id = ?" : "";
        $params = $aula_id ? [$docente_id, $aula_id] : [$docente_id];
        
        $stmt = q($pdo, "
            SELECT DISTINCT ar.id, ar.nombre, ar.tipo, ar.va_siagie, ar.color
            FROM areas ar
            JOIN docente_aula_area daa ON daa.area_id = ar.id
            WHERE daa.docente_id = ? AND daa.activo = 1 AND ar.activo = 1 $where_aula
            ORDER BY ar.tipo, ar.nombre
        ", $params);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get assigned areas error: ' . $e->getMessage());
        return [];
    }
}

// =====================================================
// FUNCIONES EDUCATIVAS
// =====================================================

/**
 * Obtener año académico activo
 */
function get_active_academic_year() {
    global $pdo;
    
    try {
        $stmt = q($pdo, "SELECT * FROM anios_academicos WHERE activo = 1 LIMIT 1");
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Obtener período actual
 */
function get_current_period() {
    global $pdo;
    
    try {
        $stmt = q($pdo, "
            SELECT * FROM periodos 
            WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin 
            AND activo = 1 
            LIMIT 1
        ");
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Convertir nota a valor numérico
 */
function nota_to_numeric($nota) {
    switch ($nota) {
        case 'AD': return 4;
        case 'A': return 3;
        case 'B': return 2;
        case 'C': return 1;
        default: return 0;
    }
}

/**
 * Obtener color para nota
 */
function get_nota_color($nota) {
    switch ($nota) {
        case 'AD': return '#10b981';
        case 'A': return '#6366f1';
        case 'B': return '#f59e0b';
        case 'C': return '#ef4444';
        default: return '#9ca3af';
    }
}

/**
 * Obtener descripción de nota
 */
function get_nota_description($nota) {
    switch ($nota) {
        case 'AD': return 'Logro Destacado';
        case 'A': return 'Logro Esperado';
        case 'B': return 'En Proceso';
        case 'C': return 'En Inicio';
        default: return 'Sin Evaluar';
    }
}

// =====================================================
// FUNCIONES DE VALIDACIÓN SIMPLES
// =====================================================

/**
 * Validar entrada básica
 */
function validate_input($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $fieldRules) {
        $value = $data[$field] ?? null;
        
        if (isset($fieldRules['required']) && $fieldRules['required'] && empty($value)) {
            $errors[$field][] = "El campo $field es requerido";
        }
        
        if (isset($fieldRules['email']) && $fieldRules['email'] && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field][] = "El campo $field debe ser un email válido";
        }
        
        if (isset($fieldRules['in']) && is_array($fieldRules['in']) && !empty($value) && !in_array($value, $fieldRules['in'])) {
            $errors[$field][] = "El campo $field tiene un valor inválido";
        }
    }
    
    return $errors;
}

/**
 * Sanitizar datos
 */
function sanitize($data, $type = 'string') {
    if (is_null($data)) return null;
    
    switch ($type) {
        case 'string':
            return trim(strip_tags($data));
        case 'email':
            return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
        case 'int':
            return (int) filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'nota':
            return in_array($data, ['AD', 'A', 'B', 'C']) ? $data : '';
        default:
            return $data;
    }
}

// =====================================================
// CONFIGURACIÓN DEL SISTEMA
// =====================================================

/**
 * Obtener configuración
 */
function get_setting($key, $default = null) {
    $settings = [
        'institution_name' => 'Institución Educativa Privada',
        'academic_year' => '2025',
        'institution_phone' => '(01) 123-4567'
    ];
    
    return $settings[$key] ?? $default;
}

// =====================================================
// CONSTANTES
// =====================================================

define('MATRIX_VERSION', '3.0.0');
define('MATRIX_NAME', 'Matriz Escolar PRO');
define('NOTAS_VALIDAS', ['AD', 'A', 'B', 'C']);
define('NIVELES_EDUCATIVOS', ['inicial_3', 'inicial_4', 'inicial_5', 'primaria']);
define('ROLES_SISTEMA', ['admin', 'coordinadora', 'tutor', 'docente_area', 'docente_taller']);

// =====================================================
// DEBUG
// =====================================================

if ($_SERVER['SERVER_NAME'] === 'localhost') {
    function dd($var) {
        echo '<pre style="background:#f8f9fa;border:1px solid #dee2e6;padding:15px;margin:10px;border-radius:5px;">';
        var_dump($var);
        echo '</pre>';
        die();
    }
}

// Log de carga exitosa
error_log('Core simplificado cargado - ' . date('Y-m-d H:i:s'));
?>