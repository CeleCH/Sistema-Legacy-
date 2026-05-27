<?php
// includes/sidebar.php

// Detección de página activa
$current_page = basename($_SERVER['SCRIPT_NAME']);
$is_module_dir = (strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false);
$base_path = $is_module_dir ? '../' : './';

$rol = $_SESSION['usuario_rol'];
?>
<!-- Sidebar -->
<aside class="sidebar bg-dark text-white border-end shadow-sm">
    <div class="sidebar-header py-4 px-3 text-center border-bottom border-secondary">
        <div class="user-avatar-large mx-auto mb-3 bg-primary text-white d-flex align-items-center justify-content-center fw-bold">
            <?php echo strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)); ?>
        </div>
        <h6 class="mb-1 text-truncate px-2 fw-semibold"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h6>
        <span class="badge bg-outline-light text-uppercase tracking-wider fs-9 px-2 py-1"><?php echo htmlspecialchars($rol); ?></span>
    </div>
    
    <div class="sidebar-menu py-3">
        <div class="menu-label px-3 fs-9 text-uppercase text-muted fw-bold mb-2">Menú Principal</div>
        
        <ul class="nav flex-column">
            <!-- Dashboard (Todos los roles tienen acceso a su vista del Dashboard) -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>dashboard.php">
                    <i class="fa-solid fa-chart-line me-3 fs-5"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Alumnos (Director, Administrador, Docente) -->
            <?php if (in_array($rol, ['Director', 'Administrador', 'Docente'])): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'alumnos.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/alumnos.php">
                        <i class="fa-solid fa-user-graduate me-3 fs-5"></i>
                        <span>Alumnos</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Profesores (Solo Director) -->
            <?php if ($rol === 'Director'): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'profesores.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/profesores.php">
                        <i class="fa-solid fa-chalkboard-user me-3 fs-5"></i>
                        <span>Docentes</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Cursos (Director, Administrador, Docente) -->
            <?php if (in_array($rol, ['Director', 'Administrador', 'Docente'])): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'cursos.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/cursos.php">
                        <i class="fa-solid fa-book me-3 fs-5"></i>
                        <span>Cursos</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Matrículas (Director, Administrador) -->
            <?php if (in_array($rol, ['Director', 'Administrador'])): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'matriculas.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/matriculas.php">
                        <i class="fa-solid fa-file-signature me-3 fs-5"></i>
                        <span>Matrículas</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Asistencia (Director, Docente, Alumno, Padre de familia) -->
            <?php if (in_array($rol, ['Director', 'Docente', 'Alumno', 'Padre de familia'])): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'asistencia.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/asistencia.php">
                        <i class="fa-solid fa-calendar-check me-3 fs-5"></i>
                        <span>Asistencia</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Calificaciones (Director, Docente, Alumno, Padre de familia) -->
            <?php if (in_array($rol, ['Director', 'Docente', 'Alumno', 'Padre de familia'])): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'calificaciones.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/calificaciones.php">
                        <i class="fa-solid fa-square-poll-vertical me-3 fs-5"></i>
                        <span>Calificaciones</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Pagos (Director, Administrador, Alumno, Padre de familia) -->
            <?php if (in_array($rol, ['Director', 'Administrador', 'Alumno', 'Padre de familia'])): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'pagos.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/pagos.php">
                        <i class="fa-solid fa-credit-card me-3 fs-5"></i>
                        <span>Pagos e Ingresos</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Notificaciones (Todos los roles) -->
            <li class="nav-item mt-2">
                <a class="nav-link d-flex align-items-center <?php echo $current_page == 'notificaciones.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>modules/notificaciones.php">
                    <i class="fa-solid fa-bell me-3 fs-5"></i>
                    <span>Notificaciones</span>
                </a>
            </li>
        </ul>
        
        <div class="menu-label px-3 fs-9 text-uppercase text-muted fw-bold mt-4 mb-2">Sistema</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center text-danger" href="<?php echo $base_path; ?>logout.php">
                    <i class="fa-solid fa-right-from-bracket me-3 fs-5"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </div>
</aside>

<!-- Contenido de la Página (Se cierra en footer.php) -->
<main class="content-body">
    <div class="container-fluid p-4">
