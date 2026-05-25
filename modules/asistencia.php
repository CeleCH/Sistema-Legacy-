<?php
// modules/asistencia.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Control de acceso: Todos los roles
check_role(['Director', 'Administrador', 'Docente', 'Alumno', 'Padre de familia']);

$rol = $_SESSION['usuario_rol'];
$userId = $_SESSION['usuario_id'];
$can_register = in_array($rol, ['Director', 'Administrador', 'Docente']);

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Variables de filtro para registro
$sel_curso_id = intval($_GET['curso_id'] ?? 0);
$sel_fecha = $_GET['fecha'] ?? date('Y-m-d');

// --- PROCESAR REGISTRO POST (Solo Docentes/Admins) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_register) {
    $curso_id = intval($_POST['curso_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $asistencias = $_POST['asistencia'] ?? []; // Array de [alumno_id => estado]

    if ($curso_id > 0 && !empty($fecha) && !empty($asistencias)) {
        try {
            $pdo->beginTransaction();

            $stmtAsist = $pdo->prepare("
                INSERT INTO asistencias (alumno_id, curso_id, fecha, estado) 
                VALUES (:alumno_id, :curso_id, :fecha, :estado)
                ON CONFLICT(alumno_id, curso_id, fecha) DO UPDATE SET estado = :estado
            ");

            foreach ($asistencias as $al_id => $est) {
                $stmtAsist->execute([
                    ':alumno_id' => $al_id,
                    ':curso_id' => $curso_id,
                    ':fecha' => $fecha,
                    ':estado' => $est
                ]);

                // Generar alerta de notificación automática si es FALTA
                if ($est === 'Falta') {
                    // Obtener usuario_id del alumno
                    $stmtAlUser = $pdo->prepare("SELECT usuario_id, nombre || ' ' || apellido as full_name FROM alumnos WHERE id = ?");
                    $stmtAlUser->execute([$al_id]);
                    $alData = $stmtAlUser->fetch();
                    
                    if ($alData && $alData['usuario_id']) {
                        // Obtener nombre del curso
                        $stmtCurNom = $pdo->prepare("SELECT nombre FROM cursos WHERE id = ?");
                        $stmtCurNom->execute([$curso_id]);
                        $cNom = $stmtCurNom->fetchColumn();

                        $stmtNot = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, fecha, leido) VALUES (?, ?, ?, ?, 0)");
                        $stmtNot->execute([
                            $alData['usuario_id'],
                            'Inasistencia Registrada',
                            "Se ha registrado una FALTA para " . htmlspecialchars($alData['full_name']) . " en el curso de $cNom el día $fecha.",
                            date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            $pdo->commit();
            header("Location: asistencia.php?curso_id=$curso_id&fecha=$fecha&msg=Asistencia guardada correctamente. Se enviaron notificaciones de inasistencia.");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al registrar asistencia: " . $e->getMessage();
        }
    } else {
        $error = "Debe seleccionar un curso e ingresar registros válidos.";
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Asistencia -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Control de Asistencia</h1>
        <p class="text-muted mb-0"><?php echo $can_register ? 'Registro diario de asistencia por curso.' : 'Consulta de récord y faltas del estudiante.'; ?></p>
    </div>
    <?php if (!$can_register): ?>
        <button onclick="window.print()" class="btn btn-outline-danger shadow-sm no-print">
            <i class="fa-solid fa-file-pdf me-2"></i>Descargar PDF
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- VISTA PARA DOCENTES / ADMINISTRADORES (REGISTRO) -->
<?php if ($can_register): ?>
    <?php
    // Obtener cursos disponibles
    try {
        if ($rol === 'Docente') {
            // Solo sus cursos
            $stmtProf = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
            $stmtProf->execute([$userId]);
            $profId = $stmtProf->fetchColumn();

            $stmtCursos = $pdo->prepare("SELECT id, nombre, codigo FROM cursos WHERE profesor_id = ? ORDER BY nombre ASC");
            $stmtCursos->execute([$profId]);
            $cursos = $stmtCursos->fetchAll();
        } else {
            // Todos los cursos
            $cursos = $pdo->query("SELECT id, nombre, codigo FROM cursos ORDER BY nombre ASC")->fetchAll();
        }
    } catch (PDOException $e) {
        $cursos = [];
    }
    ?>

    <!-- Formulario de Filtro -->
    <div class="card bg-white shadow-sm border-0 mb-4">
        <div class="card-body">
            <form action="asistencia.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="curso_id" class="form-label fw-semibold">Seleccionar Curso</label>
                    <select class="form-select" id="curso_id" name="curso_id" required>
                        <option value="">-- Seleccionar Materia --</option>
                        <?php foreach ($cursos as $cur): ?>
                            <option value="<?php echo $cur['id']; ?>" <?php echo $sel_curso_id == $cur['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cur['nombre'] . ' (' . $cur['codigo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="fecha" class="form-label fw-semibold">Fecha</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo htmlspecialchars($sel_fecha); ?>" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-list-check me-2"></i>Cargar Lista</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Alumnos para Tomar Asistencia -->
    <?php if ($sel_curso_id > 0): ?>
        <?php
        try {
            // Cargar alumnos con matrícula ACTIVA
            $stmtAl = $pdo->prepare("
                SELECT a.id, a.nombre, a.apellido, a.documento, 
                       (SELECT estado FROM asistencias WHERE alumno_id = a.id AND curso_id = ? AND fecha = ?) as estado_hoy
                FROM alumnos a 
                JOIN matriculas m ON a.id = m.alumno_id 
                WHERE m.estado = 'Activa'
                ORDER BY a.apellido ASC
            ");
            $stmtAl->execute([$sel_curso_id, $sel_fecha]);
            $alumnos = $stmtAl->fetchAll();
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            $alumnos = [];
        }
        ?>

        <div class="card bg-white shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold">Tomar Asistencia para la Fecha: <span class="text-primary"><?php echo htmlspecialchars($sel_fecha); ?></span></h5>
            </div>
            <div class="card-body">
                <form action="asistencia.php" method="POST">
                    <input type="hidden" name="curso_id" value="<?php echo $sel_curso_id; ?>">
                    <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($sel_fecha); ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Documento</th>
                                    <th class="text-center">Estado de Asistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alumnos)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No se encuentran alumnos matriculados activos para asignar asistencia.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alumnos as $al): ?>
                                        <?php $est = $al['estado_hoy'] ?: 'Presente'; // Por defecto Presente ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($al['apellido'] . ', ' . $al['nombre']); ?></td>
                                            <td><code><?php echo htmlspecialchars($al['documento']); ?></code></td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-3">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="asistencia[<?php echo $al['id']; ?>]" id="pres_<?php echo $al['id']; ?>" value="Presente" <?php echo $est === 'Presente' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label text-success fw-bold" for="pres_<?php echo $al['id']; ?>">Presente</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="asistencia[<?php echo $al['id']; ?>]" id="tard_<?php echo $al['id']; ?>" value="Tardanza" <?php echo $est === 'Tardanza' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label text-warning fw-bold" for="tard_<?php echo $al['id']; ?>">Tardanza</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="asistencia[<?php echo $al['id']; ?>]" id="falt_<?php echo $al['id']; ?>" value="Falta" <?php echo $est === 'Falta' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label text-danger fw-bold" for="falt_<?php echo $al['id']; ?>">Falta</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="asistencia[<?php echo $al['id']; ?>]" id="just_<?php echo $al['id']; ?>" value="Justificada" <?php echo $est === 'Justificada' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label text-info fw-bold" for="just_<?php echo $al['id']; ?>">Justificada</label>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($alumnos)): ?>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-success px-5"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar Asistencias del Día</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endif; ?>

<!-- VISTA PARA ESTUDIANTES / PADRES (CONSULTA) -->
<?php else: ?>
    <?php
    try {
        // Encontrar alumno
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
            // Cargar récord de asistencia por curso
            $stmtRec = $pdo->prepare("
                SELECT c.nombre as curso_nombre, c.codigo as curso_codigo,
                       SUM(CASE WHEN a.estado = 'Presente' THEN 1 ELSE 0 END) as presentes,
                       SUM(CASE WHEN a.estado = 'Tardanza' THEN 1 ELSE 0 END) as tardanzas,
                       SUM(CASE WHEN a.estado = 'Falta' THEN 1 ELSE 0 END) as faltas,
                       SUM(CASE WHEN a.estado = 'Justificada' THEN 1 ELSE 0 END) as justificadas
                FROM asistencias a
                JOIN cursos c ON a.curso_id = c.id
                WHERE a.alumno_id = ?
                GROUP BY c.id
            ");
            $stmtRec->execute([$alumnoId]);
            $records = $stmtRec->fetchAll();

            // Cargar historial de inasistencias completo
            $stmtHist = $pdo->prepare("
                SELECT c.nombre as curso_nombre, a.fecha, a.estado 
                FROM asistencias a 
                JOIN cursos c ON a.curso_id = c.id 
                WHERE a.alumno_id = ? AND a.estado IN ('Falta', 'Tardanza')
                ORDER BY a.fecha DESC
            ");
            $stmtHist->execute([$alumnoId]);
            $alertas_asis = $stmtHist->fetchAll();
        } else {
            $records = [];
            $alertas_asis = [];
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        $records = [];
        $alertas_asis = [];
    }
    ?>

    <!-- Cabecera de impresión oficial (solo visible al imprimir) -->
    <div class="print-header">
        <div class="text-center">
            <h2>Colegio Futuro Digital</h2>
            <p class="text-uppercase tracking-wide fw-bold mb-1" style="font-size: 0.85rem; letter-spacing: 1px;">Reporte Oficial de Control de Asistencia</p>
            <p class="mb-0">Estudiante: <strong><?php echo htmlspecialchars($alumnoNombre); ?></strong> | Fecha de Emisión: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Récord por curso -->
        <div class="col-lg-8">
            <div class="card bg-white shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold">Récord de Asistencias de: <span class="text-primary"><?php echo htmlspecialchars($alumnoNombre); ?></span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th class="text-center text-success">Presentes</th>
                                    <th class="text-center text-warning">Tardanzas</th>
                                    <th class="text-center text-danger">Faltas</th>
                                    <th class="text-center text-info">Justificadas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($records)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No se registran asistencias en ninguna materia.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($records as $rec): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($rec['curso_nombre']); ?></div>
                                                <small class="text-muted">Código: <?php echo htmlspecialchars($rec['curso_codigo']); ?></small>
                                            </td>
                                            <td class="text-center text-success fw-bold"><?php echo $rec['presentes']; ?></td>
                                            <td class="text-center text-warning fw-bold"><?php echo $rec['tardanzas']; ?></td>
                                            <td class="text-center text-danger fw-bold"><?php echo $rec['faltas']; ?></td>
                                            <td class="text-center text-info fw-bold"><?php echo $rec['justificadas']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas de tardanzas / faltas -->
        <div class="col-lg-4">
            <div class="card bg-white shadow-sm border-0 h-100">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Faltas y Tardanzas</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($alertas_asis)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fa-solid fa-circle-check text-success d-block fs-3 mb-2"></i>
                            ¡Excelente! No tienes inasistencias ni tardanzas registradas.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($alertas_asis as $aa): ?>
                                <div class="list-group-item p-3 border-start border-3 <?php echo $aa['estado'] === 'Falta' ? 'border-danger bg-light-danger' : 'border-warning bg-light-warning'; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($aa['curso_nombre']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($aa['fecha']); ?></small>
                                    </div>
                                    <span class="badge <?php echo $aa['estado'] === 'Falta' ? 'bg-danger' : 'bg-warning text-dark'; ?> fs-9">
                                        <?php echo $aa['estado']; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
