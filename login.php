<?php
// login.php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirigir al dashboard si ya está logueado
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_rol'] = $user['rol'];
                $_SESSION['usuario_username'] = $user['username'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error en el sistema: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor, ingrese todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colegio Futuro Digital - Iniciar Sesión</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom Style -->
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            animation: fadeInUp 0.8s ease-in-out;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo h2 {
            color: #1e3c72;
            font-weight: 700;
            margin-bottom: 0.2rem;
            font-size: 1.8rem;
        }

        .login-logo p {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #1e3c72;
            box-shadow: 0 0 0 0.25rem rgba(30, 60, 114, 0.25);
        }

        .btn-primary {
            background-color: #1e3c72;
            border-color: #1e3c72;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: #122548;
            border-color: #122548;
            transform: translateY(-2px);
        }

        .footer-text {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .alert {
            font-size: 0.9rem;
            border-radius: 8px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Estilos Premium para Accesos Rápidos */
        .quick-access-section {
            margin-top: 1.8rem;
            animation: fadeInUp 1s ease-in-out;
        }

        .quick-access-divider {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            margin-bottom: 1.2rem;
        }
        
        .quick-access-divider::before, .quick-access-divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px dashed #dee2e6;
        }
        .quick-access-divider::before { margin-right: 0.75rem; }
        .quick-access-divider::after { margin-left: 0.75rem; }

        .quick-login-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .quick-login-chip {
            flex: 1 1 calc(33.333% - 10px);
            min-width: 105px;
            max-width: 130px;
            background: #ffffff;
            border: 1.5px solid #f1f3f5;
            border-radius: 12px;
            padding: 10px 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .quick-login-chip:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(30, 60, 114, 0.1);
            background: #ffffff;
        }

        .quick-login-chip .chip-icon {
            font-size: 1.3rem;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .quick-login-chip .chip-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #343a40;
            margin-top: 2px;
        }

        .quick-login-chip .chip-sub {
            font-size: 0.65rem;
            color: #868e96;
            margin-top: -3px;
        }

        /* Colores dinámicos premium */
        /* 1. Director -> Índigo */
        .chip-director:hover { border-color: #6610f2; }
        .chip-director .chip-icon { background: rgba(102, 16, 242, 0.08); color: #6610f2; }
        .chip-director:hover .chip-icon { background: #6610f2; color: #ffffff; }

        /* 2. Admin -> Esmeralda */
        .chip-admin:hover { border-color: #198754; }
        .chip-admin .chip-icon { background: rgba(25, 135, 84, 0.08); color: #198754; }
        .chip-admin:hover .chip-icon { background: #198754; color: #ffffff; }

        /* 3. Docente -> Celeste/Sky */
        .chip-docente:hover { border-color: #0dcaf0; }
        .chip-docente .chip-icon { background: rgba(13, 202, 240, 0.08); color: #0dcaf0; }
        .chip-docente:hover .chip-icon { background: #0dcaf0; color: #ffffff; }

        /* 4. Alumno -> Ámbar/Naranja */
        .chip-alumno:hover { border-color: #fd7e14; }
        .chip-alumno .chip-icon { background: rgba(253, 126, 20, 0.08); color: #fd7e14; }
        .chip-alumno:hover .chip-icon { background: #fd7e14; color: #ffffff; }

        /* 5. Padre -> Teal */
        .chip-padre:hover { border-color: #20c997; }
        .chip-padre .chip-icon { background: rgba(32, 201, 151, 0.08); color: #20c997; }
        .chip-padre:hover .chip-icon { background: #20c997; color: #ffffff; }

        /* Animación para inputs autocompletados */
        @keyframes pulse-fill {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(30, 60, 114, 0.4); border-color: #1e3c72; }
            50% { transform: scale(1.02); box-shadow: 0 0 0 8px rgba(30, 60, 114, 0); border-color: #1e3c72; }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(30, 60, 114, 0); border-color: #1e3c72; }
        }
        
        .autocomplete-pulse {
            animation: pulse-fill 0.5s ease-in-out;
            border-color: #1e3c72 !important;
            background-color: rgba(30, 60, 114, 0.03) !important;
        }

        .login-card {
            max-width: 470px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-logo">
            <h2>Colegio Futuro Digital</h2>
            <p>Portal Académico Legacy</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm">
            <div class="mb-3">
                <label for="username" class="form-label text-dark fw-semibold">Nombre de usuario</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Ingrese su usuario" required autocomplete="username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-dark fw-semibold">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Ingrese su contraseña" required autocomplete="current-password">
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary">Ingresar al Sistema</button>
            </div>

            <div class="quick-access-section">
                <div class="quick-access-divider">
                    <span>Acceso Rápido Demo</span>
                </div>
                
                <div class="quick-login-container">
                    <!-- Director -->
                    <div class="quick-login-chip chip-director" onclick="fillDemo('director', 'Dr. Carlos Mendoza')">
                        <div class="chip-icon">👑</div>
                        <div class="chip-label">Director</div>
                        <div class="chip-sub">director</div>
                    </div>
                    <!-- Admin -->
                    <div class="quick-login-chip chip-admin" onclick="fillDemo('admin', 'Ing. Sofía Reyes')">
                        <div class="chip-icon">💼</div>
                        <div class="chip-label">Admin</div>
                        <div class="chip-sub">admin</div>
                    </div>
                    <!-- Docente -->
                    <div class="quick-login-chip chip-docente" onclick="fillDemo('profesor1', 'Prof. Ana Gómez')">
                        <div class="chip-icon">👩‍🏫</div>
                        <div class="chip-label">Docente</div>
                        <div class="chip-sub">profesor1</div>
                    </div>
                    <!-- Alumno -->
                    <div class="quick-login-chip chip-alumno" onclick="fillDemo('alumno1', 'Diego Castro')">
                        <div class="chip-icon">🧑‍🎓</div>
                        <div class="chip-label">Alumno</div>
                        <div class="chip-sub">alumno1</div>
                    </div>
                    <!-- Padre -->
                    <div class="quick-login-chip chip-padre" onclick="fillDemo('padre1', 'Roberto Castro')">
                        <div class="chip-icon">👨‍👩‍👦</div>
                        <div class="chip-label">Padre</div>
                        <div class="chip-sub">padre1</div>
                    </div>
                </div>
            </div>
        </form>

        <div class="footer-text">
            &copy; 2026 Colegio Futuro Digital. Todos los derechos reservados.
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación básica en el cliente
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const user = document.getElementById('username').value.trim();
            const pass = document.getElementById('password').value.trim();

            if (!user || !pass) {
                e.preventDefault();
                alert('Por favor complete todos los campos.');
            }
        });

        // Función premium de autocompletado y login automático
        function fillDemo(username, displayName) {
            const userInput = document.getElementById('username');
            const passInput = document.getElementById('password');
            const form = document.getElementById('loginForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Auto-rellenar credenciales
            userInput.value = username;
            passInput.value = 'password123';
            
            // Disparar animación de pulso
            userInput.classList.remove('autocomplete-pulse');
            passInput.classList.remove('autocomplete-pulse');
            void userInput.offsetWidth; // Truco de reflow para reiniciar animación CSS
            
            userInput.classList.add('autocomplete-pulse');
            passInput.classList.add('autocomplete-pulse');
            
            // Modificar botón de envío a un estado de carga premium
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Accediendo como ${displayName}...
            `;
            submitBtn.style.backgroundColor = '#122548';
            submitBtn.style.borderColor = '#122548';
            submitBtn.style.transform = 'scale(0.98)';
            
            // Redirigir/Enviar tras una pequeña demora para retroalimentación de pulso
            setTimeout(() => {
                form.submit();
            }, 500);
        }
    </script>
</body>
</html>
