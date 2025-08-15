<?php
/**
 * MATRIZ ESCOLAR PRO - CORE SYSTEM
 * Sistema de Gestión Educativa con Seguridad Empresarial
 * Versión: 2.0
 * Características: Seguridad, Validación, Auditoría, Notificaciones
 */

// Configuración de errores para desarrollo (cambiar en producción)
if ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '.local') !== false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

// Configuración de zona horaria
date_default_timezone_set('America/Lima');

// Configuración de sesión segura
if (session_status() === PHP_SESSION_NONE) {
    // Configuración segura de sesiones
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '7200'); // 2 horas
    
    session_start();
    
    // Regenerar ID de sesión para prevenir session fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['created_at'] = time();
    }
    
    // Verificar timeout de sesión
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Cargar configuración
$config = require __DIR__ . '/config.php';

// =====================================================
// CONEXIÓN A BASE DE DATOS CON MANEJO DE ERRORES
// =====================================================
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['name'],
        $config['db']['charset'] ?? 'utf8mb4'
    );
    
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
    
    // Configurar variables de auditoría
    if (isset($_SESSION['user'])) {
        $pdo->exec("SET @audit_user_id = " . (int)$_SESSION['user']['id']);
        $pdo->exec("SET @audit_ip = '" . $_SERVER['REMOTE_ADDR'] . "'");
    }
    
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    
    // En producción, mostrar mensaje genérico
    if ($_SERVER['SERVER_NAME'] !== 'localhost') {
        die('<div style="padding:20px;background:#fee;border:1px solid #f00;color:#800;font-family:Arial">
             Error de conexión a la base de datos. Contacte al administrador del sistema.
             </div>');
    } else {
        die('Error de conexión: ' . $e->getMessage());
    }
}

// =====================================================
// FUNCIONES BÁSICAS MEJORADAS
// =====================================================

/**
 * Ejecutar consulta preparada con manejo de errores
 */
function q($pdo, $sql, $params = []) { 
    try {
        $stmt = $pdo->prepare($sql); 
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Error en la ejecución de la consulta');
        }
        
        return $stmt;
    } catch (PDOException $e) {
        error_log('Query error: ' . $e->getMessage() . ' SQL: ' . $sql . ' Params: ' . json_encode($params));
        throw new Exception('Error en la consulta a la base de datos');
    }
}

/**
 * Escapar datos para salida HTML
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
 * Verificar si el usuario está logueado
 */
function require_login() { 
    if (!auth()) { 
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?redirect=' . $redirect); 
        exit; 
    } 
}

/**
 * Verificar si el usuario es administrador
 */
function is_admin() { 
    return (auth()['role'] ?? '') === 'admin'; 
}

// =====================================================
// FUNCIONES DE SEGURIDAD CSRF
// =====================================================

/**
 * Generar token CSRF
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token']) || 
        empty($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) {
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generar campo hidden para CSRF
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validar token CSRF
 */
function validate_csrf($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? ''));
    
    if (empty($_SESSION['csrf_token']) || 
        empty($token) || 
        !hash_equals($_SESSION['csrf_token'], $token)) {
        
        http_response_code(419);
        log_security_event('CSRF_TOKEN_INVALID', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown'
        ]);
        
        die(json_encode([
            'error' => 'Token CSRF inválido',
            'message' => 'Por favor recarga la página e intenta nuevamente'
        ]));
    }
    return true;
}

// =====================================================
// FUNCIONES DE RATE LIMITING
// =====================================================

/**
 * Verificar límite de intentos de login
 */
function check_rate_limit($pdo, $email, $max_attempts = 5, $window_minutes = 15) {
    try {
        $stmt = q($pdo, "
            SELECT COUNT(*) FROM login_attempts 
            WHERE email = ? AND success = 0 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ", [$email, $window_minutes]);
        
        $attempts = $stmt->fetchColumn();
        return $attempts < $max_attempts;
        
    } catch (Exception $e) {
        error_log('Rate limit check error: ' . $e->getMessage());
        return true; // En caso de error, permitir el acceso
    }
}

/**
 * Registrar intento de login
 */
function log_login_attempt($pdo, $email, $success = false, $failure_reason = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        q($pdo, "
            INSERT INTO login_attempts (email, ip_address, user_agent, success, failure_reason, attempted_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ", [$email, $ip, $user_agent, $success ? 1 : 0, $failure_reason]);
        
        // Limpiar intentos antiguos ocasionalmente (1% de probabilidad)
        if (rand(1, 100) === 1) {
            $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        }
        
    } catch (Exception $e) {
        error_log('Login attempt logging error: ' . $e->getMessage());
    }
}

/**
 * Verificar si un usuario está bloqueado
 */
function is_user_locked($pdo, $email) {
    try {
        $stmt = q($pdo, "
            SELECT bloqueado_hasta FROM users 
            WHERE email = ? AND bloqueado_hasta > NOW()
        ", [$email]);
        
        return $stmt->fetchColumn() !== false;
        
    } catch (Exception $e) {
        error_log('User lock check error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Bloquear usuario temporalmente
 */
function lock_user($pdo, $email, $minutes = 15) {
    try {
        q($pdo, "
            UPDATE users 
            SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                intentos_login = intentos_login + 1
            WHERE email = ?
        ", [$minutes, $email]);
        
        log_security_event('USER_LOCKED', ['email' => $email, 'duration_minutes' => $minutes]);
        
    } catch (Exception $e) {
        error_log('User locking error: ' . $e->getMessage());
    }
}

// =====================================================
// FUNCIONES DE VALIDACIÓN AVANZADA
// =====================================================

/**
 * Validar datos según reglas
 */
function validate_input($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $fieldRules) {
        $value = $data[$field] ?? null;
        
        foreach ($fieldRules as $rule => $ruleValue) {
            switch ($rule) {
                case 'required':
                    if ($ruleValue && (is_null($value) || $value === '' || $value === [])) {
                        $errors[$field][] = "El campo $field es requerido";
                    }
                    break;
                    
                case 'min_length':
                    if (!empty($value) && strlen($value) < $ruleValue) {
                        $errors[$field][] = "El campo $field debe tener al menos $ruleValue caracteres";
                    }
                    break;
                    
                case 'max_length':
                    if (!empty($value) && strlen($value) > $ruleValue) {
                        $errors[$field][] = "El campo $field no debe exceder $ruleValue caracteres";
                    }
                    break;
                    
                case 'email':
                    if ($ruleValue && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "El campo $field debe ser un email válido";
                    }
                    break;
                    
                case 'numeric':
                    if ($ruleValue && !empty($value) && !is_numeric($value)) {
                        $errors[$field][] = "El campo $field debe ser numérico";
                    }
                    break;
                    
                case 'integer':
                    if ($ruleValue && !empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$field][] = "El campo $field debe ser un número entero";
                    }
                    break;
                    
                case 'in':
                    if (is_array($ruleValue) && !empty($value) && !in_array($value, $ruleValue)) {
                        $errors[$field][] = "El campo $field debe ser uno de: " . implode(', ', $ruleValue);
                    }
                    break;
                    
                case 'dni':
                    if ($ruleValue && !empty($value) && !preg_match('/^\d{8}$/', $value)) {
                        $errors[$field][] = "El campo $field debe tener 8 dígitos";
                    }
                    break;
                    
                case 'phone':
                    if ($ruleValue && !empty($value) && !preg_match('/^[0-9\+\-\(\)\s]{7,15}$/', $value)) {
                        $errors[$field][] = "El campo $field debe ser un teléfono válido";
                    }
                    break;
                    
                case 'date':
                    if ($ruleValue && !empty($value)) {
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if (!$date || $date->format('Y-m-d') !== $value) {
                            $errors[$field][] = "El campo $field debe ser una fecha válida (YYYY-MM-DD)";
                        }
                    }
                    break;
                    
                case 'unique':
                    if ($ruleValue && !empty($value)) {
                        global $pdo;
                        $table = $ruleValue['table'];
                        $column = $ruleValue['column'];
                        $exclude_id = $ruleValue['exclude_id'] ?? null;
                        
                        $sql = "SELECT COUNT(*) FROM $table WHERE $column = ?";
                        $params = [$value];
                        
                        if ($exclude_id) {
                            $sql .= " AND id != ?";
                            $params[] = $exclude_id;
                        }
                        
                        $count = q($pdo, $sql, $params)->fetchColumn();
                        if ($count > 0) {
                            $errors[$field][] = "El $field ya está en uso";
                        }
                    }
                    break;
                    
                case 'min_value':
                    if (!empty($value) && is_numeric($value) && $value < $ruleValue) {
                        $errors[$field][] = "El campo $field debe ser mayor o igual a $ruleValue";
                    }
                    break;
                    
                case 'max_value':
                    if (!empty($value) && is_numeric($value) && $value > $ruleValue) {
                        $errors[$field][] = "El campo $field debe ser menor o igual a $ruleValue";
                    }
                    break;
                    
                case 'regex':
                    if (!empty($value) && !preg_match($ruleValue, $value)) {
                        $errors[$field][] = "El formato del campo $field no es válido";
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

/**
 * Sanitizar datos según tipo
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
        case 'float':
            return (float) filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'dni':
            return preg_replace('/[^0-9]/', '', $data);
        case 'phone':
            return preg_replace('/[^0-9\+\-\(\)\s]/', '', $data);
        case 'text':
            return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
        case 'html':
            // Permitir HTML básico pero escapar scripts
            return strip_tags($data, '<p><br><strong><em><ul><ol><li><a>');
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        default:
            return $data;
    }
}

// =====================================================
// FUNCIONES DE AUDITORÍA Y LOGGING
// =====================================================

/**
 * Registrar acción en log de auditoría
 */
function log_action($pdo, $action, $table = null, $record_id = null, $details = null, $old_values = null) {
    try {
        $user = auth();
        if (!$user) return false;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $session_id = session_id();
        
        q($pdo, "
            INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, 
                                 ip_address, user_agent, session_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $user['id'], 
            $action, 
            $table, 
            $record_id, 
            $old_values ? json_encode($old_values) : null,
            $details ? json_encode($details) : null,
            $ip,
            $user_agent,
            $session_id
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log('Audit logging error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Registrar evento de seguridad
 */
function log_security_event($event_type, $details = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event_type,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'user_id' => auth()['id'] ?? null,
        'details' => $details
    ];
    
    $log_file = __DIR__ . '/logs/security.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

// =====================================================
// FUNCIONES DE PERMISOS
// =====================================================

/**
 * Verificar permisos granulares
 */
function has_permission($action, $resource = null) {
    $user = auth();
    if (!$user) return false;
    
    // Los administradores tienen todos los permisos
    if ($user['role'] === 'admin') return true;
    
    // Permisos específicos para docentes
    if ($user['role'] === 'docente') {
        switch ($action) {
            case 'view_dashboard':
            case 'view_notifications':
            case 'view_reports':
                return true;
                
            case 'view_grading':
            case 'edit_grades':
                // Verificar si el docente está asignado al aula
                if ($resource && isset($resource['aula_id']) && $user['docente_id']) {
                    global $pdo;
                    try {
                        $stmt = q($pdo, "
                            SELECT COUNT(*) FROM docente_aula_area 
                            WHERE docente_id = ? AND aula_id = ? AND activo = 1
                        ", [$user['docente_id'], $resource['aula_id']]);
                        return $stmt->fetchColumn() > 0;
                    } catch (Exception $e) {
                        error_log('Permission check error: ' . $e->getMessage());
                        return false;
                    }
                }
                return false;
                
            case 'view_students':
                // Puede ver estudiantes de sus aulas asignadas
                if ($resource && isset($resource['aula_id']) && $user['docente_id']) {
                    global $pdo;
                    try {
                        $stmt = q($pdo, "
                            SELECT COUNT(*) FROM docente_aula_area 
                            WHERE docente_id = ? AND aula_id = ? AND activo = 1
                        ", [$user['docente_id'], $resource['aula_id']]);
                        return $stmt->fetchColumn() > 0;
                    } catch (Exception $e) {
                        return false;
                    }
                }
                return false;
                
            case 'manage_areas':
            case 'manage_competencies':
            case 'manage_users':
            case 'manage_system':
                return false; // Solo admin
                
            default:
                return false;
        }
    }
    
    // Otros roles...
    return false;
}

/**
 * Requerir permisos específicos
 */
function require_permission($action, $resource = null) {
    if (!has_permission($action, $resource)) {
        http_response_code(403);
        
        log_security_event('PERMISSION_DENIED', [
            'action' => $action,
            'resource' => $resource,
            'user_id' => auth()['id'] ?? null
        ]);
        
        if (file_exists('app/views/errors/403.php')) {
            include 'app/views/errors/403.php';
        } else {
            echo '<div style="padding:20px;text-align:center;"><h1>403 - Acceso Denegado</h1><p>No tienes permisos para realizar esta acción.</p></div>';
        }
        exit;
    }
}

/**
 * Middleware de autenticación mejorado
 */
function require_auth($role = null) {
    if (!auth()) {
        require_login();
    }
    
    if ($role && auth()['role'] !== $role && auth()['role'] !== 'admin') {
        http_response_code(403);
        
        log_security_event('ROLE_ACCESS_DENIED', [
            'required_role' => $role,
            'user_role' => auth()['role']
        ]);
        
        if (file_exists('app/views/errors/403.php')) {
            include 'app/views/errors/403.php';
        } else {
            echo '<div style="padding:20px;text-align:center;"><h1>403 - Acceso Denegado</h1><p>No tienes el rol necesario para acceder a esta página.</p></div>';
        }
        exit;
    }
}

// =====================================================
// FUNCIONES DE VALIDACIÓN DE ARCHIVOS
// =====================================================

/**
 * Validar archivos subidos
 */
function validate_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
    $errors = [];
    
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $errors; // No hay archivo, no es error si no es requerido
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_TMP_DIR => 'No se encontró la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Subida de archivo detenida por extensión'
        ];
        
        $errors[] = $upload_errors[$file['error']] ?? 'Error desconocido al subir archivo';
        return $errors;
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = 'El archivo es demasiado grande (máximo ' . number_format($max_size / 1024 / 1024, 1) . 'MB)';
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        $errors[] = 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', $allowed_types);
    }
    
    // Verificar tipo MIME real del archivo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg', 
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        if (isset($allowed_mimes[$extension]) && $mime_type !== $allowed_mimes[$extension]) {
            $errors[] = 'El archivo no coincide con su extensión declarada';
        }
    }
    
    return $errors;
}

// =====================================================
// FUNCIONES DE UTILIDAD
// =====================================================

/**
 * Generar nombre único para archivo
 */
function generate_unique_filename($original_name, $prefix = '') {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $name = pathinfo($original_name, PATHINFO_FILENAME);
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    
    return $prefix . date('Y-m-d_H-i-s') . '_' . $safe_name . '.' . $extension;
}

/**
 * Formatear bytes a tamaño legible
 */
function format_bytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Obtener configuración del sistema
 */
function get_setting($key, $default = null) {
    global $pdo;
    static $settings_cache = [];
    
    if (isset($settings_cache[$key])) {
        return $settings_cache[$key];
    }
    
    try {
        $stmt = q($pdo, "SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?", [$key]);
        $setting = $stmt->fetch();
        
        if ($setting) {
            $value = $setting['setting_value'];
            
            // Convertir según tipo
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = in_array(strtolower($value), ['true', '1', 'yes', 'on']);
                    break;
                case 'number':
                    $value = is_numeric($value) ? (float)$value : $default;
                    break;
                case 'json':
                    $value = json_decode($value, true) ?? $default;
                    break;
            }
            
            $settings_cache[$key] = $value;
            return $value;
        }
        
    } catch (Exception $e) {
        error_log('Settings error: ' . $e->getMessage());
    }
    
    return $default;
}

/**
 * Establecer configuración del sistema
 */
function set_setting($key, $value, $type = 'string') {
    global $pdo;
    
    try {
        $user_id = auth()['id'] ?? null;
        
        if ($type === 'json') {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = $value ? 'true' : 'false';
        }
        
        q($pdo, "
            INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
        ", [$key, $value, $type, $user_id]);
        
        // Limpiar cache
        unset($GLOBALS['settings_cache'][$key]);
        
        return true;
        
    } catch (Exception $e) {
        error_log('Set setting error: ' . $e->getMessage());
        return false;
    }
}

// =====================================================
// HEADERS DE SEGURIDAD HTTP
// =====================================================

/**
 * Configurar headers de seguridad
 */
function set_security_headers() {
    // Prevenir ataques XSS
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Política de referrer
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Política de contenido (CSP)
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; " .
           "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net; " .
           "img-src 'self' data: blob:; " .
           "connect-src 'self'; " .
           "frame-src 'none'; " .
           "object-src 'none'; " .
           "base-uri 'self'";
    
    header('Content-Security-Policy: ' . $csp);
    
    // Habilitar HSTS en HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Política de permisos
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// =====================================================
// FUNCIONES DE NOTIFICACIONES
// =====================================================

/**
 * Clase para gestión de notificaciones
 */
class NotificationManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Crear notificación
     */
    public function create($user_id, $title, $message, $type = 'info', $priority = 'normal', $data = null, $expires_at = null) {
        try {
            return q($this->pdo, "
                INSERT INTO notifications (user_id, title, message, type, priority, data, expires_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ", [$user_id, $title, $message, $type, $priority, $data ? json_encode($data) : null, $expires_at]);
        } catch (Exception $e) {
            error_log('Notification creation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear notificación para múltiples usuarios
     */
    public function createForUsers($user_ids, $title, $message, $type = 'info', $priority = 'normal', $data = null) {
        foreach ($user_ids as $user_id) {
            $this->create($user_id, $title, $message, $type, $priority, $data);
        }
    }
    
    /**
     * Crear notificación por rol
     */
    public function createForRole($role, $title, $message, $type = 'info', $priority = 'normal', $data = null) {
        try {
            $users = q($this->pdo, "SELECT id FROM users WHERE role = ? AND activo = 1", [$role])->fetchAll();
            foreach ($users as $user) {
                $this->create($user['id'], $title, $message, $type, $priority, $data);
            }
        } catch (Exception $e) {
            error_log('Role notification error: ' . $e->getMessage());
        }
    }
    
    /**
     * Marcar notificación como leída
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            return q($this->pdo, "
                UPDATE notifications 
                SET read_at = NOW() 
                WHERE id = ? AND user_id = ? AND read_at IS NULL
            ", [$notification_id, $user_id]);
        } catch (Exception $e) {
            error_log('Mark notification read error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead($user_id) {
        try {
            return q($this->pdo, "
                UPDATE notifications 
                SET read_at = NOW() 
                WHERE user_id = ? AND read_at IS NULL
            ", [$user_id]);
        } catch (Exception $e) {
            error_log('Mark all notifications read error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener notificaciones del usuario
     */
    public function getForUser($user_id, $limit = 50, $unread_only = false) {
        try {
            $where = $unread_only ? "AND read_at IS NULL" : "";
            $sql = "
                SELECT * FROM notifications 
                WHERE user_id = ? $where 
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY created_at DESC 
                LIMIT $limit
            ";
            return q($this->pdo, $sql, [$user_id])->fetchAll();
        } catch (Exception $e) {
            error_log('Get notifications error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Contar notificaciones no leídas
     */
    public function getUnreadCount($user_id) {
        try {
            return q($this->pdo, "
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND read_at IS NULL 
                AND (expires_at IS NULL OR expires_at > NOW())
            ", [$user_id])->fetchColumn();
        } catch (Exception $e) {
            error_log('Get unread count error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Limpiar notificaciones expiradas
     */
    public function cleanupExpired() {
        try {
            return q($this->pdo, "
                DELETE FROM notifications 
                WHERE expires_at IS NOT NULL AND expires_at < NOW()
            ");
        } catch (Exception $e) {
            error_log('Cleanup notifications error: ' . $e->getMessage());
            return false;
        }
    }
}

// =====================================================
// FUNCIONES DE FLASH MESSAGES
// =====================================================

/**
 * Establecer mensaje flash
 */
function set_flash($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Obtener y limpiar mensajes flash
 */
function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Verificar si hay mensajes flash
 */
function has_flash_messages() {
    return !empty($_SESSION['flash_messages']);
}

// =====================================================
// FUNCIONES DE CACHE SIMPLE
// =====================================================

/**
 * Obtener valor del cache
 */
function cache_get($key) {
    $cache_file = __DIR__ . '/cache/' . md5($key) . '.cache';
    
    if (file_exists($cache_file)) {
        $data = unserialize(file_get_contents($cache_file));
        
        if ($data['expires'] > time()) {
            return $data['value'];
        } else {
            unlink($cache_file);
        }
    }
    
    return false;
}

/**
 * Establecer valor en cache
 */
function cache_set($key, $value, $ttl = 3600) {
    $cache_dir = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_file = $cache_dir . '/' . md5($key) . '.cache';
    $data = [
        'value' => $value,
        'expires' => time() + $ttl
    ];
    
    return file_put_contents($cache_file, serialize($data), LOCK_EX) !== false;
}

/**
 * Limpiar cache
 */
function cache_clear($pattern = null) {
    $cache_dir = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) return;
    
    $files = glob($cache_dir . '/*.cache');
    foreach ($files as $file) {
        if ($pattern === null || strpos(basename($file), $pattern) !== false) {
            unlink($file);
        }
    }
}

// =====================================================
// FUNCIONES DE UTILIDAD EDUCATIVA
// =====================================================

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
 * Convertir valor numérico a nota
 */
function numeric_to_nota($valor) {
    if ($valor >= 3.5) return 'AD';
    if ($valor >= 2.5) return 'A';
    if ($valor >= 1.5) return 'B';
    return 'C';
}

/**
 * Obtener color para nota
 */
function get_nota_color($nota) {
    switch ($nota) {
        case 'AD': return '#10b981'; // Verde
        case 'A': return '#6366f1';  // Azul
        case 'B': return '#f59e0b';  // Amarillo
        case 'C': return '#ef4444';  // Rojo
        default: return '#9ca3af'; // Gris
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

/**
 * Calcular promedio de calificaciones
 */
function calcular_promedio_estudiante($pdo, $estudiante_id, $periodo_id = null) {
    try {
        $where = $periodo_id ? "AND periodo_id = ?" : "";
        $params = $periodo_id ? [$estudiante_id, $periodo_id] : [$estudiante_id];
        
        $stmt = q($pdo, "
            SELECT AVG(CASE 
                WHEN nota = 'AD' THEN 4
                WHEN nota = 'A' THEN 3
                WHEN nota = 'B' THEN 2
                WHEN nota = 'C' THEN 1
                ELSE 0
            END) as promedio
            FROM calificaciones 
            WHERE estudiante_id = ? $where
        ", $params);
        
        $result = $stmt->fetch();
        return round($result['promedio'] ?? 0, 2);
        
    } catch (Exception $e) {
        error_log('Calculate average error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Obtener estadísticas de competencia
 */
function get_competencia_stats($pdo, $competencia_id, $periodo_id = null) {
    try {
        $where = $periodo_id ? "AND periodo_id = ?" : "";
        $params = $periodo_id ? [$competencia_id, $periodo_id] : [$competencia_id];
        
        $stmt = q($pdo, "
            SELECT 
                nota,
                COUNT(*) as cantidad,
                ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) as porcentaje
            FROM calificaciones 
            WHERE competencia_id = ? $where
            GROUP BY nota
        ", $params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Get competencia stats error: ' . $e->getMessage());
        return [];
    }
}

// =====================================================
// FUNCIONES DE EXPORTACIÓN
// =====================================================

/**
 * Generar CSV desde array
 */
function array_to_csv($data, $headers = null, $filename = null) {
    if ($filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
    
    $output = fopen($filename ? 'php://output' : 'php://temp', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    if ($headers) {
        fputcsv($output, $headers);
    } elseif (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Datos
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    if (!$filename) {
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }
    
    fclose($output);
}

// =====================================================
// INICIALIZACIÓN Y CONFIGURACIÓN FINAL
// =====================================================

// Configurar headers de seguridad
set_security_headers();

// Inicializar token CSRF
csrf_token();

// Inicializar manager de notificaciones
$notification_manager = new NotificationManager($pdo);

// Limpiar cache expirado ocasionalmente (1% de probabilidad)
if (rand(1, 100) === 1) {
    $cache_dir = __DIR__ . '/cache';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*.cache');
        foreach ($files as $file) {
            if (filemtime($file) < time() - 3600) { // 1 hora
                unlink($file);
            }
        }
    }
}

// Función de cierre para limpiar
register_shutdown_function(function() {
    // Actualizar última actividad del usuario
    if (auth()) {
        global $pdo;
        try {
            q($pdo, "UPDATE users SET ultimo_acceso = NOW(), ip_ultimo_acceso = ? WHERE id = ?", 
              [$_SERVER['REMOTE_ADDR'], auth()['id']]);
        } catch (Exception $e) {
            // Silenciosamente fallar
        }
    }
});

// =====================================================
// CONSTANTES DEL SISTEMA
// =====================================================

define('MATRIX_VERSION', '2.0.0');
define('MATRIX_NAME', 'Matriz Escolar PRO');
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('SESSION_TIMEOUT', 7200); // 2 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configuración de notas
define('NOTAS_PERMITIDAS', ['AD', 'A', 'B', 'C']);
define('NOTA_MINIMA_APROBACION', 'A');

// =====================================================
// VARIABLES GLOBALES DEL SISTEMA
// =====================================================

// Información del sistema
$GLOBALS['system_info'] = [
    'name' => MATRIX_NAME,
    'version' => MATRIX_VERSION,
    'institution' => get_setting('institution_name', 'Institución Educativa'),
    'academic_year' => get_setting('academic_year', date('Y')),
    'server_time' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'database_version' => $pdo->query('SELECT VERSION()')->fetchColumn()
];

// =====================================================
// FUNCIONES DE DEBUG (SOLO DESARROLLO)
// =====================================================

if ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '.local') !== false) {
    
    /**
     * Debug helper - volcar variable
     */
    function dd($var) {
        echo '<pre style="background:#f8f9fa;border:1px solid #dee2e6;padding:15px;margin:10px;border-radius:5px;">';
        var_dump($var);
        echo '</pre>';
        die();
    }
    
    /**
     * Debug helper - log to browser console
     */
    function console_log($data) {
        echo '<script>console.log(' . json_encode($data) . ');</script>';
    }
    
    /**
     * Mostrar información de debug
     */
    function show_debug_info() {
        if (auth() && auth()['role'] === 'admin' && isset($_GET['debug'])) {
            global $pdo;
            
            echo '<div style="position:fixed;bottom:10px;right:10px;background:#343a40;color:#fff;padding:10px;border-radius:5px;font-size:12px;z-index:9999;">';
            echo '<strong>Debug Info:</strong><br>';
            echo 'Usuario: ' . auth()['email'] . '<br>';
            echo 'Memoria: ' . number_format(memory_get_usage() / 1024 / 1024, 2) . ' MB<br>';
            echo 'Consultas: ' . ($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) ? 'Conectado' : 'Desconectado') . '<br>';
            echo 'Tiempo: ' . number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) . 's';
            echo '</div>';
        }
    }
    
    register_shutdown_function('show_debug_info');
}

// =====================================================
// MENSAJE DE INICIO EXITOSO
// =====================================================

if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_log('Matriz Escolar PRO v' . MATRIX_VERSION . ' - Core loaded successfully');
}

/*
===============================================================
MATRIZ ESCOLAR PRO - CORE SYSTEM LOADED
===============================================================
✅ Conexión a base de datos establecida
✅ Funciones de seguridad cargadas
✅ Sistema de validación activo
✅ Auditoría y logging configurados
✅ Sistema de notificaciones inicializado
✅ Headers de seguridad establecidos
✅ Cache y utilidades disponibles
✅ Permisos y autenticación listos

Sistema listo para uso en producción.
===============================================================
*/
?>