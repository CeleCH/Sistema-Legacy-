<?php
// dashboard.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$rol = $_SESSION['usuario_rol'];
$userId = $_SESSION['usuario_id'];

// Inicializar variables de estadísticas
$stats = [];
$recent_notifications = [];

// Obtener notificaciones recientes comunes
try {
    $stmtNotif = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 5");
    $stmtNotif->execute([$userId]);
    $recent_notifications = $stmtNotif->fetchAll();
} catch (PDOException $e) {
    // Silently ignore
}

// Estadísticas por rol
if (in_array($rol, ['Director', 'Administrador'])) {
    try {
        // Alumnos totales
        $stats['total_alumnos'] = $pdo->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
        // Docentes totales
        $stats['total_profesores'] = $pdo->query("SELECT COUNT(*) FROM profesores")->fetchColumn();
        // Cursos totales
        $stats['total_cursos'] = $pdo->query("SELECT COUNT(*) FROM cursos")->fetchColumn();
        // Matrículas activas
        $stats['matriculas_activas'] = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Activa'")->fetchColumn();
        // Pagos recaudados
        $stats['pagos_recaudados'] = $pdo->query("SELECT SUM(monto) FROM pagos WHERE estado = 'Pagado'")->fetchColumn() ?: 0.0;
        // Pagos pendientes (deudas)
        $stats['pagos_pendientes'] = $pdo->query("SELECT SUM(monto) FROM pagos WHERE estado = 'Pendiente'")->fetchColumn() ?: 0.0;
        // Promedio general
        $stats['promedio_general'] = $pdo->query("SELECT AVG(nota) FROM calificaciones")->fetchColumn() ?: 0.0;

        // Datos para Gráfico 1: Aprobados vs Reprobados
        $stats['aprobados'] = $pdo->query("SELECT COUNT(*) FROM calificaciones WHERE nota >= 11")->fetchColumn();
        $stats['reprobados'] = $pdo->query("SELECT COUNT(*) FROM calificaciones WHERE nota < 11")->fetchColumn();

        // Datos para Gráfico 2: Asistencia General
        $asistencias_count = $pdo->query("SELECT estado, COUNT(*) as cant FROM asistencias GROUP BY estado")->fetchAll();
        $stats['asistencias'] = ['Presente' => 0, 'Falta' => 0, 'Tardanza' => 0, 'Justificada' => 0];
        foreach ($asistencias_count as $ac) {
            $stats['asistencias'][$ac['estado']] = $ac['cant'];
        }
    } catch (PDOException $e) {
        $error_stats = "Error al calcular estadísticas: " . $e->getMessage();
    }
} elseif ($rol === 'Docente') {
    try {
        // Obtener ID del profesor
        $stmtProf = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
        $stmtProf->execute([$userId]);
        $profId = $stmtProf->fetchColumn();

        if ($profId) {
            // Mis Cursos
            $stmtCursos = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE profesor_id = ?");
            $stmtCursos->execute([$profId]);
            $stats['mis_cursos'] = $stmtCursos->fetchColumn();

            // Calificaciones registradas por mí
            $stmtCalifs = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE curso_id IN (SELECT id FROM cursos WHERE profesor_id = ?)");
            $stmtCalifs->execute([$profId]);
            $stats['calificaciones_registradas'] = $stmtCalifs->fetchColumn();

            // Alumnos a mi cargo (alumnos con notas o asistencia en mis cursos)
            $stmtAlumCargo = $pdo->prepare("
                SELECT COUNT(DISTINCT alumno_id) FROM (
                    SELECT alumno_id FROM calificaciones WHERE curso_id IN (SELECT id FROM cursos WHERE profesor_id = ?)
                    UNION
                    SELECT alumno_id FROM asistencias WHERE curso_id IN (SELECT id FROM cursos WHERE profesor_id = ?)
                )
            ");
            $stmtAlumCargo->execute([$profId, $profId]);
            $stats['alumnos_a_cargo'] = $stmtAlumCargo->fetchColumn();
            
            // Promedio de mis cursos
            $stmtPromMy = $pdo->prepare("SELECT AVG(nota) FROM calificaciones WHERE curso_id IN (SELECT id FROM cursos WHERE profesor_id = ?)");
            $stmtPromMy->execute([$profId]);
            $stats['promedio_cursos'] = $stmtPromMy->fetchColumn() ?: 0.0;
        } else {
            $stats['mis_cursos'] = 0;
            $stats['calificaciones_registradas'] = 0;
            $stats['alumnos_a_cargo'] = 0;
            $stats['promedio_cursos'] = 0.0;
        }
    } catch (PDOException $e) {
        $error_stats = "Error al calcular estadísticas de docente: " . $e->getMessage();
    }
} else {
    // Alumno o Padre de familia
    try {
        // Si es Padre de familia, asociarlo al primer alumno en la base de datos para la simulación
        if ($rol === 'Padre de familia') {
            $alumnoId = $pdo->query("SELECT id FROM alumnos LIMIT 1")->fetchColumn();
            $alumnoNombre = $pdo->query("SELECT nombre || ' ' || apellido FROM alumnos LIMIT 1")->fetchColumn();
        } else {
            $stmtAl = $pdo->prepare("SELECT id, nombre || ' ' || apellido as full_name FROM alumnos WHERE usuario_id = ?");
            $stmtAl->execute([$userId]);
            $alData = $stmtAl->fetch();
            $alumnoId = $alData['id'] ?? null;
            $alumnoNombre = $alData['full_name'] ?? '';
        }

        if ($alumnoId) {
            $stats['alumno_nombre'] = $alumnoNombre;
            // Promedio personal
            $stmtProm = $pdo->prepare("SELECT AVG(nota) FROM calificaciones WHERE alumno_id = ?");
            $stmtProm->execute([$alumnoId]);
            $stats['promedio_personal'] = $stmtProm->fetchColumn() ?: 0.0;

            // Faltas totales
            $stmtFaltas = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE alumno_id = ? AND estado = 'Falta'");
            $stmtFaltas->execute([$alumnoId]);
            $stats['faltas_totales'] = $stmtFaltas->fetchColumn();

            // Deuda pendiente
            $stmtDeuda = $pdo->prepare("SELECT SUM(monto) FROM pagos WHERE alumno_id = ? AND estado = 'Pendiente'");
            $stmtDeuda->execute([$alumnoId]);
            $stats['deuda_pendiente'] = $stmtDeuda->fetchColumn() ?: 0.0;

            // Historial de asistencias
            $stmtAsisAl = $pdo->prepare("SELECT estado, COUNT(*) as cant FROM asistencias WHERE alumno_id = ? GROUP BY estado");
            $stmtAsisAl->execute([$alumnoId]);
            $asis_res = $stmtAsisAl->fetchAll();
            
            $stats['asistencias'] = ['Presente' => 0, 'Falta' => 0, 'Tardanza' => 0, 'Justificada' => 0];
            foreach ($asis_res as $ar) {
                $stats['asistencias'][$ar['estado']] = $ar['cant'];
            }
        }
    } catch (PDOException $e) {
        $error_stats = "Error al calcular datos del alumno: " . $e->getMessage();
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded shadow-sm border-start border-primary border-4">
            <div>
                <h1 class="h3 mb-1 text-dark fw-bold">¡Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>!</h1>
                <p class="text-muted mb-0">Panel Académico del Colegio Futuro Digital. Hoy es <?php echo date('d/m/Y'); ?>.</p>
            </div>
            <div class="text-end d-none d-md-block">
                <span class="badge bg-primary text-uppercase px-3 py-2 fs-7"><?php echo htmlspecialchars($rol); ?></span>
            </div>
        </div>
    </div>
</div>

<?php if (isset($error_stats)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error_stats; ?>
    </div>
<?php endif; ?>

<!-- DASHBOARD ADM / DIR -->
<?php if (in_array($rol, ['Director', 'Administrador'])): ?>
    <div class="row g-4 mb-4">
        <!-- Card 1: Alumnos -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Alumnos Registrados</h6>
                        <h2 class="fw-bold mb-0 text-primary-dark"><?php echo $stats['total_alumnos']; ?></h2>
                    </div>
                    <div class="stat-icon text-primary">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 2: Docentes -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Docentes Activos</h6>
                        <h2 class="fw-bold mb-0 text-success"><?php echo $stats['total_profesores']; ?></h2>
                    </div>
                    <div class="stat-icon text-success">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 3: Cursos -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Cursos Ofrecidos</h6>
                        <h2 class="fw-bold mb-0 text-info"><?php echo $stats['total_cursos']; ?></h2>
                    </div>
                    <div class="stat-icon text-info">
                        <i class="fa-solid fa-book"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 4: Recaudación -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Ingresos Registrados</h6>
                        <h2 class="fw-bold mb-0 text-warning">S/. <?php echo number_format($stats['pagos_recaudados'], 2); ?></h2>
                    </div>
                    <div class="stat-icon text-warning">
                        <i class="fa-solid fa-circle-dollar-to-slot"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos y Reportes Rápidos -->
    <div class="row g-4 mb-4">
        <!-- Gráfico Aprobados vs Reprobados -->
        <div class="col-lg-6">
            <div class="card bg-white shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Rendimiento Académico General</h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center" style="position: relative; height: 300px;">
                    <canvas id="chartRendimiento"></canvas>
                </div>
            </div>
        </div>
        <!-- Gráfico Asistencia -->
        <div class="col-lg-6">
            <div class="card bg-white shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-chart-column me-2 text-success"></i>Control de Asistencia General</h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center" style="position: relative; height: 300px;">
                    <canvas id="chartAsistencia"></canvas>
                </div>
            </div>
        </div>
    </div>

<!-- DASHBOARD DOCENTE -->
<?php elseif ($rol === 'Docente'): ?>
    <div class="row g-4 mb-4">
        <!-- Card 1: Mis Cursos -->
        <div class="col-md-4">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Mis Cursos Asignados</h6>
                        <h2 class="fw-bold mb-0 text-primary"><?php echo $stats['mis_cursos']; ?></h2>
                    </div>
                    <div class="stat-icon text-primary">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 2: Alumnos a Cargo -->
        <div class="col-md-4">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Alumnos a mi Cargo</h6>
                        <h2 class="fw-bold mb-0 text-success"><?php echo $stats['alumnos_a_cargo']; ?></h2>
                    </div>
                    <div class="stat-icon text-success">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 3: Promedio de mis Cursos -->
        <div class="col-md-4">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Promedio de Notas</h6>
                        <h2 class="fw-bold mb-0 text-warning"><?php echo number_format($stats['promedio_cursos'], 2); ?> / 20</h2>
                    </div>
                    <div class="stat-icon text-warning">
                        <i class="fa-solid fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enlaces directos para el docente -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-white shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-wand-magic-sparkles me-2 text-primary"></i>Acciones Rápidas de Docente</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <a href="modules/calificaciones.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fa-solid fa-square-poll-vertical d-block fs-3 mb-2"></i>
                                Registrar Calificaciones
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <a href="modules/asistencia.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fa-solid fa-calendar-check d-block fs-3 mb-2"></i>
                                Tomar Asistencia
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <a href="modules/cursos.php" class="btn btn-outline-info w-100 py-3">
                                <i class="fa-solid fa-book d-block fs-3 mb-2"></i>
                                Ver Horarios de Curso
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <a href="modules/notificaciones.php" class="btn btn-outline-warning w-100 py-3">
                                <i class="fa-solid fa-bell d-block fs-3 mb-2"></i>
                                Enviar Alerta / Mensaje
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- DASHBOARD ALUMNO / PADRE -->
<?php else: ?>
    <div class="row g-4 mb-4">
        <!-- Card 1: Nombre de Alumno -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100 border-start border-primary border-4">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1"><?php echo $rol === 'Padre de familia' ? 'Hijo Monitoreado' : 'Estudiante'; ?></h6>
                    <h5 class="fw-bold text-dark mb-0 text-truncate"><?php echo htmlspecialchars($stats['alumno_nombre'] ?? 'No Asignado'); ?></h5>
                </div>
            </div>
        </div>
        <!-- Card 2: Promedio -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Promedio Académico</h6>
                        <h2 class="fw-bold mb-0 text-primary"><?php echo number_format($stats['promedio_personal'], 2); ?></h2>
                    </div>
                    <div class="stat-icon text-primary">
                        <i class="fa-solid fa-award"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 3: Faltas -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Inasistencias (Faltas)</h6>
                        <h2 class="fw-bold mb-0 text-danger"><?php echo $stats['faltas_totales']; ?></h2>
                    </div>
                    <div class="stat-icon text-danger">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 4: Deuda -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats bg-white shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fs-8 fw-bold mb-1">Deuda Pendiente</h6>
                        <h2 class="fw-bold mb-0 text-warning">S/. <?php echo number_format($stats['deuda_pendiente'], 2); ?></h2>
                    </div>
                    <div class="stat-icon text-warning">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Asistencia Personal -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card bg-white shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Resumen de Asistencia</h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center" style="position: relative; height: 300px;">
                    <canvas id="chartAsistencia"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card bg-white shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-star me-2 text-warning"></i>Accesos Rápidos</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-around">
                    <p class="text-muted">Consulta la información académica actual en tiempo real:</p>
                    <div class="d-grid gap-3">
                        <a href="modules/calificaciones.php" class="btn btn-outline-primary py-2 text-start">
                            <i class="fa-solid fa-square-poll-vertical me-2"></i> Ver Mis Calificaciones y Boleta
                        </a>
                        <a href="modules/asistencia.php" class="btn btn-outline-success py-2 text-start">
                            <i class="fa-solid fa-calendar-check me-2"></i> Reporte Detallado de Asistencia
                        </a>
                        <a href="modules/pagos.php" class="btn btn-outline-warning py-2 text-start">
                            <i class="fa-solid fa-credit-card me-2"></i> Consultar Historial de Pagos y Pensiones
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Notificaciones y Alertas Comunes -->
<div class="row">
    <div class="col-12">
        <div class="card bg-white shadow-sm">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-bell me-2 text-danger"></i>Notificaciones Recientes</h5>
                <a href="modules/notificaciones.php" class="btn btn-sm btn-outline-secondary">Ver Todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_notifications)) { ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fa-regular fa-bell-slash d-block fs-3 mb-2"></i>
                        No tienes notificaciones o alertas en este momento.
                    </div>
                <?php } else { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_notifications as $notif) { ?>
                            <div class="list-group-item list-group-item-action p-3 <?php echo !$notif['leido'] ? 'bg-light border-start border-danger border-3' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($notif['titulo']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($notif['fecha']); ?></small>
                                </div>
                                <p class="mb-1 text-muted fs-7"><?php echo htmlspecialchars($notif['mensaje']); ?></p>
                                <?php if (!$notif['leido']) { ?>
                                    <span class="badge bg-danger fs-9">Nuevo</span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Script del lado del cliente para gráficos Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (in_array($rol, ['Director', 'Administrador'])): ?>
        // Gráfico de Rendimiento (Aprobados vs Reprobados)
        const ctxRendimiento = document.getElementById('chartRendimiento').getContext('2d');
        new Chart(ctxRendimiento, {
            type: 'doughnut',
            data: {
                labels: ['Aprobados (>= 11)', 'Reprobados (< 11)'],
                datasets: [{
                    data: [<?php echo $stats['aprobados']; ?>, <?php echo $stats['reprobados']; ?>],
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Asistencias Generales
        const ctxAsistencia = document.getElementById('chartAsistencia').getContext('2d');
        new Chart(ctxAsistencia, {
            type: 'bar',
            data: {
                labels: ['Presentes', 'Tardanzas', 'Faltas', 'Justificadas'],
                datasets: [{
                    label: 'Registros de Asistencia',
                    data: [
                        <?php echo $stats['asistencias']['Presente']; ?>,
                        <?php echo $stats['asistencias']['Tardanza']; ?>,
                        <?php echo $stats['asistencias']['Falta']; ?>,
                        <?php echo $stats['asistencias']['Justificada']; ?>
                    ],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#17a2b8'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    <?php elseif ($rol === 'Alumno' || $rol === 'Padre de familia'): ?>
        // Gráfico de Asistencia Personal
        const ctxAsistencia = document.getElementById('chartAsistencia').getContext('2d');
        new Chart(ctxAsistencia, {
            type: 'pie',
            data: {
                labels: ['Presentes', 'Tardanzas', 'Faltas', 'Justificadas'],
                datasets: [{
                    data: [
                        <?php echo $stats['asistencias']['Presente'] ?? 0; ?>,
                        <?php echo $stats['asistencias']['Tardanza'] ?? 0; ?>,
                        <?php echo $stats['asistencias']['Falta'] ?? 0; ?>,
                        <?php echo $stats['asistencias']['Justificada'] ?? 0; ?>
                    ],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#17a2b8'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    <?php endif; ?>
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
