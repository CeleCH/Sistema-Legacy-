<?php
// modules/cursos.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Control de acceso: Director, Administrador, Docente
check_role(['Director', 'Administrador', 'Docente']);

$rol = $_SESSION['usuario_rol'];
$can_modify = in_array($rol, ['Director', 'Administrador']);

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// --- PROCESAR ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_modify) {
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $profesor_id = intval($_POST['profesor_id'] ?? 0);
        $horario = trim($_POST['horario'] ?? '');

        // Validar profesor_id (si es 0, insertar NULL)
        $profVal = $profesor_id > 0 ? $profesor_id : null;

        if (!empty($nombre) && !empty($codigo)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO cursos (nombre, codigo, descripcion, profesor_id, horario) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $codigo, $descripcion, $profVal, $horario]);
                header("Location: cursos.php?msg=Curso registrado con éxito");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar curso: " . $e->getMessage();
            }
        } else {
            $error = "El nombre y el código de curso son obligatorios.";
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $profesor_id = intval($_POST['profesor_id'] ?? 0);
        $horario = trim($_POST['horario'] ?? '');

        $profVal = $profesor_id > 0 ? $profesor_id : null;

        if ($id > 0 && !empty($nombre) && !empty($codigo)) {
            try {
                $stmt = $pdo->prepare("UPDATE cursos SET nombre = ?, codigo = ?, descripcion = ?, profesor_id = ?, horario = ? WHERE id = ?");
                $stmt->execute([$nombre, $codigo, $descripcion, $profVal, $horario, $id]);
                header("Location: cursos.php?msg=Curso actualizado con éxito");
                exit;
            } catch (PDOException $e) {
                $error = "Error al actualizar curso: " . $e->getMessage();
            }
        } else {
            $error = "Por favor complete todos los campos obligatorios.";
        }
    }
}

// --- PROCESAR ACCIÓN ELIMINAR (GET) ---
if ($action === 'delete' && $can_modify) {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: cursos.php?msg=Curso eliminado del sistema");
            exit;
        } catch (PDOException $e) {
            header("Location: cursos.php?error=Error al eliminar curso: " . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Cursos -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-book me-2 text-primary"></i>Gestión de Cursos</h1>
        <p class="text-muted mb-0">Catálogo de materias curriculares y asignación horaria.</p>
    </div>
    <?php if ($action === 'list' && $can_modify): ?>
        <a href="cursos.php?action=new" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Registrar Curso</a>
    <?php elseif ($action !== 'list'): ?>
        <a href="cursos.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Volver al Listado</a>
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
            SELECT c.*, p.nombre as prof_nombre, p.apellido as prof_apellido 
            FROM cursos c 
            LEFT JOIN profesores p ON c.profesor_id = p.id 
            ORDER BY c.nombre ASC
        ");
        $cursos = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error al cargar cursos: " . $e->getMessage() . "</div>";
        $cursos = [];
    }
    ?>

    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0 fw-bold">Listado de Cursos Disponibles</h5>
                </div>
                <div class="col-md-6 mt-2 mt-md-0">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="tableSearch" placeholder="Buscar por código, curso, docente u horario...">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre del Curso</th>
                            <th>Descripción</th>
                            <th>Docente Asignado</th>
                            <th>Horario Programado</th>
                            <?php if ($can_modify): ?>
                                <th class="text-end">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cursos)): ?>
                            <tr>
                                <td colspan="<?php echo $can_modify ? '6' : '5'; ?>" class="text-center py-4 text-muted">No se registran cursos.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cursos as $c): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($c['codigo']); ?></code></td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($c['nombre']); ?></td>
                                    <td><small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?php echo htmlspecialchars($c['descripcion'] ?: '-'); ?></small></td>
                                    <td>
                                        <?php if ($c['profesor_id']): ?>
                                            <div class="fw-semibold"><i class="fa-solid fa-user-tie me-1 text-secondary"></i> <?php echo htmlspecialchars($c['prof_apellido'] . ', ' . $c['prof_nombre']); ?></div>
                                        <?php else: ?>
                                            <span class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Sin docente asignado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> <?php echo htmlspecialchars($c['horario'] ?: 'No programado'); ?></small>
                                    </td>
                                    <?php if ($can_modify): ?>
                                        <td class="text-end px-3">
                                            <a href="cursos.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="cursos.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirmarEliminacion('¿Desea eliminar este curso del sistema? Esta acción desvinculará calificaciones y asistencias relacionadas.');"><i class="fa-solid fa-trash-can"></i></a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- 2. FORMULARIO REGISTRAR -->
<?php elseif ($action === 'new' && $can_modify): ?>
    <?php
    // Obtener docentes para el selector
    $profesores = [];
    try {
        $profesores = $pdo->query("SELECT id, nombre, apellido FROM profesores ORDER BY apellido ASC")->fetchAll();
    } catch (PDOException $e) {
        // Ignore
    }
    ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Nuevo Registro de Curso</h5>
        </div>
        <div class="card-body">
            <form action="cursos.php?action=create" method="POST" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nombre" class="form-label fw-semibold">Nombre del Curso <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej. Biología Celular y Genética" required>
                    </div>
                    <div class="col-md-4">
                        <label for="codigo" class="form-label fw-semibold">Código del Curso <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="codigo" name="codigo" placeholder="Ej. BIO-102" required>
                    </div>
                    <div class="col-md-12">
                        <label for="descripcion" class="form-label fw-semibold">Descripción / Sílabo Corto</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Detalle breve del contenido curricular del curso..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="profesor_id" class="form-label fw-semibold">Asignar Docente</label>
                        <select class="form-select" id="profesor_id" name="profesor_id">
                            <option value="0">-- Seleccionar Docente (Opcional) --</option>
                            <?php foreach ($profesores as $pr): ?>
                                <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['apellido'] . ', ' . $pr['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="horario" class="form-label fw-semibold">Horario Programado</label>
                        <input type="text" class="form-control" id="horario" name="horario" placeholder="Ej. Martes y Jueves 08:00 - 10:00">
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <button type="submit" class="btn btn-primary">Registrar Curso</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<!-- 3. FORMULARIO EDITAR -->
<?php elseif ($action === 'edit' && $can_modify): ?>
    <?php
    $id = intval($_GET['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        $curso = $stmt->fetch();
        if (!$curso) {
            echo "<div class='alert alert-danger'>Curso no encontrado.</div>";
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }

        // Obtener docentes para el selector
        $profesores = $pdo->query("SELECT id, nombre, apellido FROM profesores ORDER BY apellido ASC")->fetchAll();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Editar Curso: <?php echo htmlspecialchars($curso['nombre']); ?></h5>
        </div>
        <div class="card-body">
            <form action="cursos.php?action=update" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $curso['id']; ?>">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nombre" class="form-label fw-semibold">Nombre del Curso <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($curso['nombre']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="codigo" class="form-label fw-semibold">Código del Curso <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($curso['codigo']); ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label for="descripcion" class="form-label fw-semibold">Descripción / Sílabo Corto</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($curso['descripcion']); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="profesor_id" class="form-label fw-semibold">Docente Asignado</label>
                        <select class="form-select" id="profesor_id" name="profesor_id">
                            <option value="0">-- Sin docente asignado --</option>
                            <?php foreach ($profesores as $pr): ?>
                                <option value="<?php echo $pr['id']; ?>" <?php echo $curso['profesor_id'] == $pr['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pr['apellido'] . ', ' . $pr['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="horario" class="form-label fw-semibold">Horario Programado</label>
                        <input type="text" class="form-control" id="horario" name="horario" value="<?php echo htmlspecialchars($curso['horario']); ?>">
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
