<?php
// Iniciar sesión
session_start();

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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

$error_message = '';

// Verificar si ya está logueado
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Por favor ingresa email y contraseña';
    } else {
        try {
            // Buscar usuario
            $stmt = $pdo->prepare("
                SELECT u.*, d.nombres as docente_nombres, d.apellidos as docente_apellidos,
                       d.tipo_docente
                FROM users u 
                LEFT JOIN docentes d ON d.id = u.docente_id 
                WHERE u.email = ? AND u.activo = 1 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login exitoso
                session_regenerate_id(true);
                
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'docente_id' => $user['docente_id'],
                    'docente_nombres' => $user['docente_nombres'],
                    'docente_apellidos' => $user['docente_apellidos'],
                    'tipo_docente' => $user['tipo_docente'],
                    'last_activity' => time()
                ];
                
                // Actualizar último acceso
                try {
                    $stmt = $pdo->prepare("UPDATE users SET ultimo_acceso = NOW(), ip_ultimo_acceso = ? WHERE id = ?");
                    $stmt->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
                } catch (Exception $e) {
                    // Ignorar error de actualización
                }
                
                // Redireccionar
                $redirect = $_POST['redirect'] ?? 'index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error_message = 'Email o contraseña incorrectos';
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error_message = 'Error interno. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Acceder - Matriz Escolar PRO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            backdrop-filter: blur(20px);
        }
        
        .login-header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 24px;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
            color: white;
        }
        
        .role-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 4px solid #6366f1;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
        }
        
        .role-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .role-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 3px;
        }
        
        .role-email {
            font-size: 11px;
            color: #64748b;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card">
                    <div class="login-header">
                        <div class="logo-icon">
                            <i class="bi bi-mortarboard"></i>
                        </div>
                        <h3 class="mb-0">Matriz Escolar PRO</h3>
                        <p class="mb-0 opacity-90">Sistema de Gestión Educativa</p>
                    </div>
                    
                    <div class="p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <div><?= htmlspecialchars($error_message) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" id="loginForm">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? 'index.php') ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-envelope me-2"></i>Correo Electrónico
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       name="email" 
                                       id="email"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="tu@colegio.edu.pe"
                                       required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-lock me-2"></i>Contraseña
                                </label>
                                <div class="position-relative">
                                    <input type="password" 
                                           class="form-control" 
                                           name="password" 
                                           id="password"
                                           placeholder="Tu contraseña"
                                           required>
                                    <button type="button" 
                                            class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3"
                                            onclick="togglePassword()"
                                            tabindex="-1">
                                        <i class="bi bi-eye" id="password-toggle"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100 py-3 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Iniciar Sesión
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Credenciales demo -->
                <div class="text-center mt-4">
                    <div class="alert alert-info">
                        <h6 class="fw-semibold mb-3">
                            <i class="bi bi-info-circle me-2"></i>Credenciales de Demostración
                            <br><small class="text-muted">Haz clic en cualquier tarjeta para acceder</small>
                        </h6>
                        
                        <div class="row text-start">
                            <div class="col-md-6">
                                <div class="role-card" onclick="quickLogin('admin@colegio.edu.pe')">
                                    <div class="role-title">👑 Administrador</div>
                                    <div class="role-email">admin@colegio.edu.pe</div>
                                </div>
                                
                                <div class="role-card" onclick="quickLogin('coordinadora@colegio.edu.pe')">
                                    <div class="role-title">👩‍💼 Coordinadora</div>
                                    <div class="role-email">coordinadora@colegio.edu.pe</div>
                                </div>
                                
                                <div class="role-card" onclick="quickLogin('kelly.correa@colegio.edu.pe')">
                                    <div class="role-title">👩‍🏫 Tutor Inicial 3</div>
                                    <div class="role-email">kelly.correa@colegio.edu.pe</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="role-card" onclick="quickLogin('maria.sanchez@colegio.edu.pe')">
                                    <div class="role-title">👨‍🏫 Docente Inglés</div>
                                    <div class="role-email">maria.sanchez@colegio.edu.pe</div>
                                </div>
                                
                                <div class="role-card" onclick="quickLogin('ana.torres@colegio.edu.pe')">
                                    <div class="role-title">🎨 Docente Talleres</div>
                                    <div class="role-email">ana.torres@colegio.edu.pe</div>
                                </div>
                                
                                <div class="role-card" onclick="quickLogin('luz.pasache@colegio.edu.pe')">
                                    <div class="role-title">👩‍🏫 Tutor Primaria</div>
                                    <div class="role-email">luz.pasache@colegio.edu.pe</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3 p-2 bg-light rounded">
                            <small class="text-muted">
                                <strong>Contraseña para todos:</strong> admin123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function quickLogin(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'admin123';
            document.getElementById('loginForm').submit();
        }
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F1') {
                e.preventDefault();
                quickLogin('admin@colegio.edu.pe');
            } else if (e.key === 'F2') {
                e.preventDefault();
                quickLogin('coordinadora@colegio.edu.pe');
            } else if (e.key === 'F3') {
                e.preventDefault();
                quickLogin('kelly.correa@colegio.edu.pe');
            }
        });
        
        // Prevenir envíos múltiples
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';
        });
        
        console.log('🎓 Login Demo - Atajos:');
        console.log('F1: Admin | F2: Coordinadora | F3: Tutor');
        console.log('O haz clic en cualquier tarjeta de rol');
    </script>
</body>
</html>