<?php
// modules/matriculas.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Control de acceso: Solo Director y Administrador
check_role(['Director', 'Administrador']);

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// --- PROCESAR ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $alumno_id = intval($_POST['alumno_id'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
        $estado = trim($_POST['estado'] ?? 'Activa');

        if ($alumno_id > 0) {
            try {
                $pdo->beginTransaction();

                // 1. Validar datos del alumno (verificar si existe y tiene datos completos)
                $stmtCheckAl = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
                $stmtCheckAl->execute([$alumno_id]);
                $alumno = $stmtCheckAl->fetch();

                if (!$alumno) {
                    throw new Exception("El estudiante seleccionado no existe en el sistema.");
                }

                if (empty($alumno['documento']) || empty($alumno['nombre']) || empty($alumno['apellido'])) {
                    throw new Exception("El expediente del alumno está incompleto (DNI o nombre faltante). Complete sus datos primero.");
                }

                // 2. Validar que no esté ya matriculado de forma activa
                $stmtCheckMat = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE alumno_id = ? AND estado = 'Activa'");
                $stmtCheckMat->execute([$alumno_id]);
                if ($stmtCheckMat->fetchColumn() > 0) {
                    throw new Exception("El estudiante ya cuenta con una matrícula Activa actualmente.");
                }

                // 3. Validar pagos pendientes (Regla de negocio legacy)
                $stmtCheckDeuda = $pdo->prepare("SELECT SUM(monto) FROM pagos WHERE alumno_id = ? AND estado = 'Pendiente'");
                $stmtCheckDeuda->execute([$alumno_id]);
                $deuda = $stmtCheckDeuda->fetchColumn() ?: 0.0;

                if ($deuda > 0) {
                    throw new Exception("No se puede matricular. El alumno tiene una deuda pendiente de S/. " . number_format($deuda, 2) . ". Debe registrar los pagos correspondientes primero.");
                }

                // 4. Registrar matrícula
                $stmtInsertMat = $pdo->prepare("INSERT INTO matriculas (alumno_id, fecha, estado) VALUES (?, ?, ?)");
                $stmtInsertMat->execute([$alumno_id, $fecha, $estado]);
                $matriculaId = $pdo->lastInsertId();

                // 5. Asignar cursos automáticamente (Insertar registros iniciales en calificaciones para que aparezca asignado)
                if ($estado === 'Activa') {
                    $cursos = $pdo->query("SELECT id FROM cursos")->fetchAll();
                    $stmtAsig = $pdo->prepare("INSERT INTO calificaciones (alumno_id, curso_id, nota, fecha, observaciones) VALUES (?, ?, 0.0, ?, 'Matriculado automáticamente')");
                    foreach ($cursos as $c) {
                        // Evitar duplicar si ya existe nota registrada
                        $stmtCheckCal = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE alumno_id = ? AND curso_id = ?");
                        $stmtCheckCal->execute([$alumno_id, $c['id']]);
                        if ($stmtCheckCal->fetchColumn() == 0) {
                            $stmtAsig->execute([$alumno_id, $c['id'], date('Y-m-d')]);
                        }
                    }
                }

                $pdo->commit();
                header("Location: matriculas.php?msg=Matrícula registrada con éxito. Se asignaron todos los cursos disponibles automáticamente.");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Error al matricular: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, seleccione un alumno.";
        }
    }
}

// --- PROCESAR CAMBIO DE ESTADO (GET) ---
if ($action === 'change_status') {
    $id = intval($_GET['id'] ?? 0);
    $new_status = $_GET['status'] ?? '';
    
    if ($id > 0 && in_array($new_status, ['Activa', 'Inactiva', 'Pendiente'])) {
        try {
            $stmt = $pdo->prepare("UPDATE matriculas SET estado = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            header("Location: matriculas.php?msg=Estado de matrícula actualizado a " . $new_status);
            exit;
        } catch (PDOException $e) {
            $error = "Error al actualizar estado: " . $e->getMessage();
        }
    }
}

// --- PROCESAR ACCIÓN ELIMINAR (GET) ---
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM matriculas WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: matriculas.php?msg=Matrícula eliminada del sistema");
            exit;
        } catch (PDOException $e) {
            header("Location: matriculas.php?error=Error al eliminar matrícula: " . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Matrículas -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-file-signature me-2 text-primary"></i>Gestión de Matrículas</h1>
        <p class="text-muted mb-0">Control de admisiones, validaciones académicas y deudas financieras.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="matriculas.php?action=new" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Registrar Matrícula</a>
    <?php else: ?>
        <a href="matriculas.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Volver al Listado</a>
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

<!-- 1. VISTA LISTADO -->
<?php if ($action === 'list'): ?>
    <?php
    try {
        $stmt = $pdo->query("
            SELECT m.*, a.nombre, a.apellido, a.documento 
            FROM matriculas m 
            JOIN alumnos a ON m.alumno_id = a.id 
            ORDER BY m.fecha DESC
        ");
        $matriculas = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error al cargar matrículas: " . $e->getMessage() . "</div>";
        $matriculas = [];
    }
    ?>

    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0 fw-bold">Registro General de Matrículas</h5>
                </div>
                <div class="col-md-6 mt-2 mt-md-0">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="tableSearch" placeholder="Buscar por alumno, DNI o fecha...">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Alumno / DNI</th>
                            <th>Estado de Matrícula</th>
                            <th class="text-end">Acciones / Cambiar Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($matriculas)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No se registran matrículas procesadas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($matriculas as $mat): ?>
                                <tr>
                                    <td><i class="fa-regular fa-calendar-days me-1 text-muted"></i> <?php echo htmlspecialchars($mat['fecha']); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($mat['apellido'] . ', ' . $mat['nombre']); ?></div>
                                        <small class="text-muted">DNI: <?php echo htmlspecialchars($mat['documento']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeColor = 'bg-success';
                                        if ($mat['estado'] === 'Pendiente') $badgeColor = 'bg-warning text-dark';
                                        elseif ($mat['estado'] === 'Inactiva') $badgeColor = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badgeColor; ?>"><?php echo $mat['estado']; ?></span>
                                    </td>
                                    <td class="text-end px-3">
                                        <!-- Botones para cambiar estado rápidamente -->
                                        <div class="btn-group btn-group-sm me-2" role="group">
                                            <a href="matriculas.php?action=change_status&id=<?php echo $mat['id']; ?>&status=Activa" class="btn btn-outline-success <?php echo $mat['estado'] == 'Activa' ? 'active' : ''; ?>" title="Activar">Activa</a>
                                            <a href="matriculas.php?action=change_status&id=<?php echo $mat['id']; ?>&status=Pendiente" class="btn btn-outline-warning text-dark <?php echo $mat['estado'] == 'Pendiente' ? 'active' : ''; ?>" title="Pendiente">Pendiente</a>
                                            <a href="matriculas.php?action=change_status&id=<?php echo $mat['id']; ?>&status=Inactiva" class="btn btn-outline-danger <?php echo $mat['estado'] == 'Inactiva' ? 'active' : ''; ?>" title="Inactivar">Inactiva</a>
                                        </div>
                                        
                                        <!-- Borrar -->
                                        <a href="matriculas.php?action=delete&id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar Matrícula" onclick="return confirmarEliminacion('¿Desea eliminar este registro de matrícula? Los cursos asignados inicialmente no se borrarán pero el alumno perderá su estatus de matriculado.');"><i class="fa-solid fa-trash-can"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- 2. FORMULARIO MATRICULAR -->
<?php elseif ($action === 'new'): ?>
    <?php
    // Obtener alumnos no matriculados de forma activa para listarlos en el selector
    try {
        $stmtAl = $pdo->query("
            SELECT id, nombre, apellido, documento 
            FROM alumnos 
            WHERE id NOT IN (SELECT alumno_id FROM matriculas WHERE estado = 'Activa')
            ORDER BY apellido ASC
        ");
        $alumnos = $stmtAl->fetchAll();
    } catch (PDOException $e) {
        $alumnos = [];
    }
    ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Procesar Nueva Matrícula Académica</h5>
        </div>
        <div class="card-body">
            
            <div class="alert alert-info border-start border-info border-3 mb-4" role="alert">
                <h6 class="fw-bold"><i class="fa-solid fa-circle-info me-2"></i>Reglas de Negocio Aplicadas:</h6>
                <ul class="mb-0 fs-7">
                    <li>Se validará que el alumno tenga nombres, apellidos y DNI correctos.</li>
                    <li><strong>Control de Deudas:</strong> El sistema rechazará la matrícula si el alumno registra deudas (pagos pendientes de pensión o matrícula).</li>
                    <li>Al matricular, se asignarán automáticamente todos los cursos del catálogo institucional, inicializando su boletín de calificaciones con nota 0.0.</li>
                </ul>
            </div>

            <form action="matriculas.php?action=create" method="POST" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="alumno_id" class="form-label fw-semibold">Seleccionar Estudiante <span class="text-danger">*</span></label>
                        <select class="form-select" id="alumno_id" name="alumno_id" required>
                            <option value="">-- Seleccionar Alumno --</option>
                            <?php foreach ($alumnos as $al): ?>
                                <option value="<?php echo $al['id']; ?>"><?php echo htmlspecialchars($al['apellido'] . ', ' . $al['nombre'] . ' (DNI: ' . $al['documento'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha" class="form-label fw-semibold">Fecha de Matrícula</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="estado" class="form-label fw-semibold">Estado Inicial</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="Activa">Activa</option>
                            <option value="Pendiente">Pendiente</option>
                        </select>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">Procesar Matrícula</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
