<?php
// modules/calificaciones.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Control de acceso: Todos los roles
check_role(['Director', 'Administrador', 'Docente', 'Alumno', 'Padre de familia']);

$rol = $_SESSION['usuario_rol'];
$userId = $_SESSION['usuario_id'];
$can_register = in_array($rol, ['Director', 'Administrador', 'Docente']);

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Filtro de curso para registro
$sel_curso_id = intval($_GET['curso_id'] ?? 0);

// --- PROCESAR ACTUALIZACIÓN DE NOTAS POST (Docentes/Admins) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_register) {
    $curso_id = intval($_POST['curso_id'] ?? 0);
    $notas = $_POST['nota'] ?? []; // Array [alumno_id => nota]
    $observaciones = $_POST['observacion'] ?? []; // Array [alumno_id => obs]
    $fecha = date('Y-m-d');

    if ($curso_id > 0 && !empty($notas)) {
        try {
            $pdo->beginTransaction();

            foreach ($notas as $al_id => $val_nota) {
                $val_nota = floatval($val_nota);
                // Validar rango de nota
                if ($val_nota < 0 || $val_nota > 20) {
                    throw new Exception("La calificación debe estar en el rango de 0 a 20.");
                }

                $obs = trim($observaciones[$al_id] ?? '');

                // Verificar si ya existe registro de nota para este alumno en este curso
                $stmtCheck = $pdo->prepare("SELECT id, nota FROM calificaciones WHERE alumno_id = ? AND curso_id = ?");
                $stmtCheck->execute([$al_id, $curso_id]);
                $calif_existente = $stmtCheck->fetch();

                if ($calif_existente) {
                    // Actualizar nota existente
                    $stmtUpdate = $pdo->prepare("UPDATE calificaciones SET nota = ?, fecha = ?, observaciones = ? WHERE id = ?");
                    $stmtUpdate->execute([$val_nota, $fecha, $obs, $calif_existente['id']]);
                    $old_nota = $calif_existente['nota'];
                } else {
                    // Insertar nueva nota
                    $stmtInsert = $pdo->prepare("INSERT INTO calificaciones (alumno_id, curso_id, nota, fecha, observaciones) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsert->execute([$al_id, $curso_id, $val_nota, $fecha, $obs]);
                    $old_nota = null;
                }

                // Si la nota es reprobatoria (< 11) y cambió o es nueva, enviar notificación
                if ($val_nota < 11 && ($old_nota === null || $old_nota >= 11)) {
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
                            'Alerta Académica: Calificación Baja',
                            "Se ha registrado una calificación baja (" . number_format($val_nota, 1) . ") para " . htmlspecialchars($alData['full_name']) . " en el curso de $cNom.",
                            date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            $pdo->commit();
            header("Location: calificaciones.php?curso_id=$curso_id&msg=Calificaciones guardadas y promedios actualizados.");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al guardar calificaciones: " . $e->getMessage();
        }
    } else {
        $error = "Selección de curso no válida.";
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Calificaciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-square-poll-vertical me-2 text-primary"></i>Calificaciones y Promedios</h1>
        <p class="text-muted mb-0"><?php echo $can_register ? 'Ingreso de calificaciones e informes escolares.' : 'Consulta de libreta de calificaciones y desempeño académico.'; ?></p>
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

<!-- VISTA REGISTRO (Docentes / Administradores) -->
<?php if ($can_register): ?>
    <?php
    // Obtener cursos
    try {
        if ($rol === 'Docente') {
            $stmtProf = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
            $stmtProf->execute([$userId]);
            $profId = $stmtProf->fetchColumn();

            $stmtCursos = $pdo->prepare("SELECT id, nombre, codigo FROM cursos WHERE profesor_id = ? ORDER BY nombre ASC");
            $stmtCursos->execute([$profId]);
            $cursos = $stmtCursos->fetchAll();
        } else {
            $cursos = $pdo->query("SELECT id, nombre, codigo FROM cursos ORDER BY nombre ASC")->fetchAll();
        }
    } catch (PDOException $e) {
        $cursos = [];
    }
    ?>

    <!-- Formulario Filtro Curso -->
    <div class="card bg-white shadow-sm border-0 mb-4">
        <div class="card-body">
            <form action="calificaciones.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label for="curso_id" class="form-label fw-semibold">Seleccionar Curso Asignado</label>
                    <select class="form-select" id="curso_id" name="curso_id" required>
                        <option value="">-- Seleccionar Materia --</option>
                        <?php foreach ($cursos as $cur): ?>
                            <option value="<?php echo $cur['id']; ?>" <?php echo $sel_curso_id == $cur['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cur['nombre'] . ' (' . $cur['codigo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-2"></i>Cargar Estudiantes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado para cargar notas -->
    <?php if ($sel_curso_id > 0): ?>
        <?php
        try {
            // Cargar alumnos matriculados y sus notas existentes en este curso
            $stmtAl = $pdo->prepare("
                SELECT a.id as alumno_id, a.nombre, a.apellido, a.documento, 
                       c.nota, c.observaciones 
                FROM alumnos a 
                JOIN matriculas m ON a.id = m.alumno_id 
                LEFT JOIN calificaciones c ON a.id = c.alumno_id AND c.curso_id = ? 
                WHERE m.estado = 'Activa'
                ORDER BY a.apellido ASC
            ");
            $stmtAl->execute([$sel_curso_id]);
            $alumnos = $stmtAl->fetchAll();
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            $alumnos = [];
        }
        ?>

        <div class="card bg-white shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold">Registro de Notas del Curso</h5>
            </div>
            <div class="card-body">
                <form action="calificaciones.php" method="POST">
                    <input type="hidden" name="curso_id" value="<?php echo $sel_curso_id; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>DNI</th>
                                    <th style="width: 150px;">Nota (0-20)</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alumnos)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No hay estudiantes activos en este curso.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alumnos as $al): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($al['apellido'] . ', ' . $al['nombre']); ?></td>
                                            <td><code><?php echo htmlspecialchars($al['documento']); ?></code></td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="20" class="form-control fw-bold <?php echo ($al['nota'] !== null && $al['nota'] < 11) ? 'text-danger border-danger' : 'text-success'; ?>" name="nota[<?php echo $al['alumno_id']; ?>]" value="<?php echo $al['nota'] !== null ? number_format($al['nota'], 1) : ''; ?>" placeholder="S/N" required>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control text-muted fs-7" name="observacion[<?php echo $al['alumno_id']; ?>]" value="<?php echo htmlspecialchars($al['observaciones'] ?: ''); ?>" placeholder="Ej. Tarea entregada a tiempo, Examen recuperatorio...">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($alumnos)): ?>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-success px-5"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar Notas y Enviar Notificaciones</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endif; ?>

<!-- VISTA CONSULTA (Alumnos / Padres de familia) -->
<?php else: ?>
    <?php
    try {
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
            // Boleta de calificaciones
            $stmtBoleta = $pdo->prepare("
                SELECT c.nombre as curso_nombre, c.codigo as curso_codigo, cal.nota, cal.fecha, cal.observaciones 
                FROM calificaciones cal 
                JOIN cursos c ON cal.curso_id = c.id 
                WHERE cal.alumno_id = ?
                ORDER BY c.nombre ASC
            ");
            $stmtBoleta->execute([$alumnoId]);
            $boleta = $stmtBoleta->fetchAll();

            // Calcular promedio
            $promedio = 0.0;
            if (count($boleta) > 0) {
                $sum = array_sum(array_column($boleta, 'nota'));
                $promedio = $sum / count($boleta);
            }
        } else {
            $boleta = [];
            $promedio = 0.0;
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        $boleta = [];
        $promedio = 0.0;
    }
    ?>

    <!-- Cabecera de impresión oficial (solo visible al imprimir) -->
    <div class="print-header">
        <div class="text-center">
            <h2>Colegio Futuro Digital</h2>
            <p class="text-uppercase tracking-wide fw-bold mb-1" style="font-size: 0.85rem; letter-spacing: 1px;">Libreta Oficial de Calificaciones</p>
            <p class="mb-0">Estudiante: <strong><?php echo htmlspecialchars($alumnoNombre); ?></strong> | Fecha de Emisión: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>

    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold">Reporte Académico de: <span class="text-primary"><?php echo htmlspecialchars($alumnoNombre); ?></span></h5>
            
            <?php
            $pColor = 'bg-success';
            $pEval = 'Aprobado';
            if ($promedio < 11) {
                $pColor = 'bg-danger';
                $pEval = 'Desaprobado';
            } elseif ($promedio < 15) {
                $pColor = 'bg-warning text-dark';
                $pEval = 'Regular';
            }
            ?>
            <div>
                <span class="badge bg-secondary me-2 fs-7">Rendimiento: <strong><?php echo $pEval; ?></strong></span>
                <span class="badge <?php echo $pColor; ?> fs-7">Promedio General: <strong><?php echo number_format($promedio, 2); ?> / 20</strong></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Materia / Curso</th>
                            <th>Calificación</th>
                            <th>Fecha Registro</th>
                            <th>Observaciones del Docente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($boleta)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No se registran notas cargadas para este estudiante.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($boleta as $b): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($b['curso_codigo']); ?></code></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($b['curso_nombre']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $b['nota'] >= 11 ? 'bg-success' : 'bg-danger'; ?> fs-7">
                                            <?php echo number_format($b['nota'], 1); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($b['fecha']); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($b['observaciones'] ?: '-'); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
