<?php
// login.php - REEMPLAZAR tu archivo actual

require __DIR__.'/core.php';

$error_message = '';
$is_locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    try {
        validate_csrf();
    } catch (Exception $e) {
        $error_message = 'Token de seguridad inválido. Recarga la página.';
    }
    
    if (empty($error_message)) {
        $email = sanitize($_POST['email'] ?? '', 'email');
        $password = $_POST['password'] ?? '';
        
        // Validar datos de entrada
        $validation_rules = [
            'email' => ['required' => true, 'email' => true],
            'password' => ['required' => true, 'min_length' => 6]
        ];
        
        $validation_errors = validate_input($_POST, $validation_rules);
        
        if (empty($validation_errors)) {
            // Verificar rate limiting
            if (!check_rate_limit($pdo, $email)) {
                $is_locked = true;
                $error_message = 'Demasiados intentos fallidos. Espera 15 minutos antes de intentar nuevamente.';
                log_login_attempt($pdo, $email, false);
            } else {
                // Intentar login
                try {
                    $user = q($pdo, "SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1", [$email])->fetch();
                    
                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Login exitoso
                        session_regenerate_id(true); // Prevenir session fixation
                        
                        $_SESSION['user'] = [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'role' => $user['role'],
                            'docente_id' => $user['docente_id'],
                            'last_activity' => time()
                        ];
                        
                        // Registrar login exitoso
                        log_login_attempt($pdo, $email, true);
                        log_action($pdo, 'LOGIN', 'users', $user['id'], ['ip' => $_SERVER['REMOTE_ADDR']]);
                        
                        // Actualizar última actividad
                        q($pdo, "UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?", 
                          [$_SERVER['REMOTE_ADDR'], $user['id']]);
                        
                        // Redireccionar
                        $redirect = $_POST['redirect'] ?? 'index.php';
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        // Login fallido
                        $error_message = 'Email o contraseña incorrectos';
                        log_login_attempt($pdo, $email, false);
                        
                        // Delay para prevenir ataques de fuerza bruta
                        sleep(1);
                    }
                } catch (Exception $e) {
                    error_log('Login error: ' . $e->getMessage());
                    $error_message = 'Error interno. Intenta nuevamente.';
                }
            }
        } else {
            $error_message = 'Por favor corrige los errores en el formulario';
        }
    }
}

// Verificar si ya está logueado
if (auth()) {
    header('Location: index.php');
    exit;
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
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .login-header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s ease;
            font-size: 15px;
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
        
        .alert-modern {
            border-radius: 12px;
            border: none;
            padding: 16px;
        }
        
        .security-features {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 14px;
            color: #64748b;
        }
        
        .feature-item i {
            color: #10b981;
            margin-right: 8px;
        }
        
        .rate-limit-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            color: #92400e;
        }
        
        .logo-container {
            margin-bottom: 1rem;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <div class="logo-container">
                            <div class="logo-icon">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                        </div>
                        <h3 class="mb-0">Matriz Escolar PRO</h3>
                        <p class="mb-0 opacity-90">Sistema de Gestión Educativa</p>
                    </div>
                    
                    <div class="p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-modern <?= $is_locked ? 'rate-limit-warning' : 'alert-danger' ?> mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-<?= $is_locked ? 'shield-exclamation' : 'exclamation-triangle' ?> me-2"></i>
                                    <div>
                                        <strong><?= $is_locked ? 'Cuenta Bloqueada' : 'Error de Acceso' ?></strong>
                                        <div class="mt-1"><?= e($error_message) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" class="needs-validation" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? 'index.php') ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-envelope me-2"></i>Correo Electrónico
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       name="email" 
                                       value="<?= e($_POST['email'] ?? 'admin@demo.com') ?>"
                                       placeholder="tu@email.com"
                                       required 
                                       autocomplete="email"
                                       <?= $is_locked ? 'disabled' : '' ?>>
                                <div class="invalid-feedback">
                                    Por favor ingresa un email válido
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-lock me-2"></i>Contraseña
                                </label>
                                <div class="position-relative">
                                    <input type="password" 
                                           class="form-control" 
                                           name="password" 
                                           value="admin123"
                                           placeholder="Tu contraseña"
                                           required 
                                           minlength="6"
                                           autocomplete="current-password"
                                           id="password"
                                           <?= $is_locked ? 'disabled' : '' ?>>
                                    <button type="button" 
                                            class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3"
                                            onclick="togglePassword()"
                                            tabindex="-1">
                                        <i class="bi bi-eye" id="password-toggle"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    La contraseña debe tener al menos 6 caracteres
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Recordar sesión
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" 
                                    class="btn btn-login w-100 py-3 fw-semibold"
                                    <?= $is_locked ? 'disabled' : '' ?>>
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                <?= $is_locked ? 'Cuenta Bloqueada' : 'Iniciar Sesión' ?>
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="#" class="text-decoration-none" onclick="showRecoveryInfo()">
                                <i class="bi bi-question-circle me-1"></i>
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                        
                        <!-- Características de seguridad -->
                        <div class="security-features">
                            <h6 class="fw-semibold mb-3">
                                <i class="bi bi-shield-check text-success me-2"></i>
                                Sistema Seguro
                            </h6>
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill"></i>
                                Encriptación de datos avanzada
                            </div>
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill"></i>
                                Protección contra ataques de fuerza bruta
                            </div>
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill"></i>
                                Auditoría completa de accesos
                            </div>
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill"></i>
                                Sesiones seguras con timeout automático
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center p-3 border-top">
                        <small class="text-muted">
                            © <?= date('Y') ?> Matriz Escolar PRO. 
                            <a href="#" class="text-decoration-none">Política de Privacidad</a>
                        </small>
                    </div>
                </div>
                
                <!-- Demo credentials info -->
                <div class="text-center mt-4">
                    <div class="alert alert-info alert-modern">
                        <h6 class="fw-semibold mb-2">
                            <i class="bi bi-info-circle me-2"></i>Credenciales de Demostración
                        </h6>
                        <div class="row text-start">
                            <div class="col-6">
                                <strong>Administrador:</strong><br>
                                <small>admin@demo.com<br>admin123</small>
                            </div>
                            <div class="col-6">
                                <strong>Docente:</strong><br>
                                <small>docente@demo.com<br>admin123</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Validación del formulario
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Toggle password visibility
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
        
        // Mostrar información de recuperación
        function showRecoveryInfo() {
            Swal.fire({
                title: 'Recuperación de Contraseña',
                html: `
                    <div class="text-start">
                        <p>Para recuperar tu contraseña, contacta al administrador del sistema con la siguiente información:</p>
                        <ul>
                            <li>Tu nombre completo</li>
                            <li>Email registrado en el sistema</li>
                            <li>Número de DNI</li>
                        </ul>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Contacto:</strong> admin@colegio.edu.pe
                        </div>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#6366f1'
            });
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert:not(.rate-limit-warning)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
        
        // Prevent multiple form submissions
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';
            
            // Re-enable after 3 seconds in case of error
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión';
            }, 3000);
        });
        
        // Security notifications
        <?php if ($is_locked): ?>
        // Show countdown for rate limit
        let remainingTime = 15 * 60; // 15 minutes in seconds
        const countdownTimer = setInterval(() => {
            remainingTime--;
            if (remainingTime <= 0) {
                clearInterval(countdownTimer);
                location.reload();
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>