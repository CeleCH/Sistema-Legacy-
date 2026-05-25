<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
check_login();

// Determinar el path base según la ubicación del script
$is_module = (strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false);
$base_path = $is_module ? '../' : './';

// Obtener notificaciones no leídas para el badge del header
require_once __DIR__ . '/../config/db.php';
$notif_count = 0;
try {
    $stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leido = 0");
    $stmtNotif->execute([$_SESSION['usuario_id']]);
    $notif_count = $stmtNotif->fetchColumn();
} catch (PDOException $e) {
    // Silently ignore
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colegio Futuro Digital - Sistema Académico</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts (Outfit & Roboto) -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom Style -->
    <link href="<?php echo $base_path; ?>assets/css/style.css" rel="stylesheet">
</head>
<body>

    <!-- Navbar Superior -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary-dark fixed-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $base_path; ?>dashboard.php">
                <i class="fa-solid fa-graduation-cap me-2 fs-4"></i>
                <span class="fw-bold tracking-wide">COLEGIO FUTURO DIGITAL</span>
                <span class="badge bg-secondary ms-2 text-uppercase fs-9">Legacy V1.0</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Notificaciones Quick Link -->
                    <li class="nav-item me-3 position-relative">
                        <a class="nav-link text-white d-flex align-items-center" href="<?php echo $base_path; ?>modules/notificaciones.php" title="Notificaciones del sistema">
                            <i class="fa-solid fa-bell fs-5"></i>
                            <?php if ($notif_count > 0): ?>
                                <span class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                    <?php echo $notif_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Usuario Conectado -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-small me-2 bg-light text-primary d-flex align-items-center justify-content-center fw-bold">
                                <?php echo strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)); ?>
                            </div>
                            <div class="d-none d-sm-block text-start">
                                <div class="fw-semibold lh-1 fs-7"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></div>
                                <span class="text-white-50 fs-9"><?php echo htmlspecialchars($_SESSION['usuario_rol']); ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userDropdown">
                            <li><span class="dropdown-item-text text-muted fs-8">Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario_username']); ?></strong></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>modules/notificaciones.php">
                                    <i class="fa-solid fa-bell me-2 text-muted"></i> Notificaciones
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo $base_path; ?>logout.php">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenedor Principal (Flexbox para Sidebar + Contenido) -->
    <div class="wrapper">
