<?php
// modules/alumnos.php
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
        $apellido = trim($_POST['apellido'] ?? '');
        $documento = trim($_POST['documento'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado_academico = trim($_POST['estado_academico'] ?? 'Regular');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($nombre) && !empty($apellido) && !empty($documento) && !empty($username) && !empty($password)) {
            try {
                $pdo->beginTransaction();

                // 1. Crear usuario asociado
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO usuarios (username, password, nombre, email, rol) VALUES (?, ?, ?, ?, 'Alumno')");
                $stmtUser->execute([$username, $passwordHash, "$nombre $apellido", $email]);
                $usuarioId = $pdo->lastInsertId();

                // 2. Crear alumno
                $stmtAl = $pdo->prepare("INSERT INTO alumnos (usuario_id, nombre, apellido, documento, fecha_nacimiento, direccion, telefono, estado_academico) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtAl->execute([$usuarioId, $nombre, $apellido, $documento, $fecha_nacimiento, $direccion, $telefono, $estado_academico]);

                // 3. Crear pago inicial de matrícula de prueba
                $stmtPago = $pdo->prepare("INSERT INTO pagos (alumno_id, monto, fecha, concepto, estado) VALUES (?, ?, ?, ?, ?)");
                $alumnoId = $pdo->lastInsertId();
                $stmtPago->execute([$alumnoId, 350.00, date('Y-m-d'), 'Matrícula Anual', 'Pendiente']);

                $pdo->commit();
                header("Location: alumnos.php?msg=Alumno registrado con éxito");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Error al registrar alumno: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, complete todos los campos obligatorios.";
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $documento = trim($_POST['documento'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado_academico = trim($_POST['estado_academico'] ?? 'Regular');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($id > 0 && !empty($nombre) && !empty($apellido) && !empty($documento) && !empty($username)) {
            try {
                $pdo->beginTransaction();

                // Obtener usuario_id
                $stmtGetU = $pdo->prepare("SELECT usuario_id FROM alumnos WHERE id = ?");
                $stmtGetU->execute([$id]);
                $usuarioId = $stmtGetU->fetchColumn();

                if ($usuarioId) {
                    // Actualizar usuario
                    if (!empty($password)) {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmtUser = $pdo->prepare("UPDATE usuarios SET username = ?, password = ?, nombre = ?, email = ? WHERE id = ?");
                        $stmtUser->execute([$username, $passwordHash, "$nombre $apellido", $email, $usuarioId]);
                    } else {
                        $stmtUser = $pdo->prepare("UPDATE usuarios SET username = ?, nombre = ?, email = ? WHERE id = ?");
                        $stmtUser->execute([$username, "$nombre $apellido", $email, $usuarioId]);
                    }
                }

                // Actualizar alumno
                $stmtAl = $pdo->prepare("UPDATE alumnos SET nombre = ?, apellido = ?, documento = ?, fecha_nacimiento = ?, direccion = ?, telefono = ?, estado_academico = ? WHERE id = ?");
                $stmtAl->execute([$nombre, $apellido, $documento, $fecha_nacimiento, $direccion, $telefono, $estado_academico, $id]);

                $pdo->commit();
                header("Location: alumnos.php?msg=Datos del alumno actualizados con éxito");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Error al actualizar alumno: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, complete todos los campos obligatorios.";
        }
    }
}

// --- PROCESAR ACCIÓN ELIMINAR (GET) ---
if ($action === 'delete' && $can_modify) {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->beginTransaction();

            // Obtener usuario_id antes de borrar
            $stmtGetU = $pdo->prepare("SELECT usuario_id FROM alumnos WHERE id = ?");
            $stmtGetU->execute([$id]);
            $usuarioId = $stmtGetU->fetchColumn();

            // Eliminar alumno (Cascades to matriculas, pagos, asistencias, calificaciones)
            $stmtDelAl = $pdo->prepare("DELETE FROM alumnos WHERE id = ?");
            $stmtDelAl->execute([$id]);

            // Eliminar usuario asociado
            if ($usuarioId) {
                $stmtDelUs = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmtDelUs->execute([$usuarioId]);
            }

            $pdo->commit();
            header("Location: alumnos.php?msg=Alumno eliminado del sistema");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: alumnos.php?error=Error al eliminar: " . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Alumnos -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Gestión de Alumnos</h1>
        <p class="text-muted mb-0">Listado, registro y expediente académico de estudiantes.</p>
    </div>
    <?php if ($action === 'list' && $can_modify): ?>
        <a href="alumnos.php?action=new" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Registrar Alumno</a>
    <?php elseif ($action !== 'list'): ?>
        <a href="alumnos.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Volver al Listado</a>
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
            SELECT a.*, u.username, u.email 
            FROM alumnos a 
            LEFT JOIN usuarios u ON a.usuario_id = u.id 
            ORDER BY a.apellido ASC, a.nombre ASC
        ");
        $alumnos = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error al cargar alumnos: " . $e->getMessage() . "</div>";
        $alumnos = [];
    }
    ?>

    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0 fw-bold">Estudiantes del Colegio</h5>
                </div>
                <div class="col-md-6 mt-2 mt-md-0">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="tableSearch" placeholder="Buscar por nombre, documento o código...">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Apellidos y Nombres</th>
                            <th>Usuario / Email</th>
                            <th>Estado Académico</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alumnos)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No hay alumnos registrados en el sistema.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alumnos as $al): ?>
                                <tr>
                                    <td class="fw-semibold text-primary"><?php echo htmlspecialchars($al['documento']); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($al['apellido'] . ', ' . $al['nombre']); ?></div>
                                        <small class="text-muted">Tel: <?php echo htmlspecialchars($al['telefono'] ?: '-'); ?></small>
                                    </td>
                                    <td>
                                        <div><code><?php echo htmlspecialchars($al['username'] ?: ''); ?></code></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($al['email'] ?: ''); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeColor = 'bg-success';
                                        if ($al['estado_academico'] === 'Condicional') $badgeColor = 'bg-warning text-dark';
                                        elseif ($al['estado_academico'] === 'Suspendido') $badgeColor = 'bg-danger';
                                        elseif ($al['estado_academico'] === 'Egresado') $badgeColor = 'bg-info';
                                        ?>
                                        <span class="badge <?php echo $badgeColor; ?>"><?php echo $al['estado_academico']; ?></span>
                                    </td>
                                    <td class="text-end px-3">
                                        <a href="alumnos.php?action=view&id=<?php echo $al['id']; ?>" class="btn btn-sm btn-info text-white" title="Ver Expediente"><i class="fa-solid fa-file-invoice"></i></a>
                                        <?php if ($can_modify): ?>
                                            <a href="alumnos.php?action=edit&id=<?php echo $al['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="alumnos.php?action=delete&id=<?php echo $al['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirmarEliminacion('¿Desea eliminar este alumno? Se borrarán sus notas, asistencias, pagos y su usuario del sistema.');"><i class="fa-solid fa-trash-can"></i></a>
                                        <?php endif; ?>
                                    </td>
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
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Nuevo Registro de Estudiante</h5>
        </div>
        <div class="card-body">
            <form action="alumnos.php?action=create" method="POST" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="col-md-6">
                        <label for="apellido" class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" required>
                    </div>
                    <div class="col-md-4">
                        <label for="documento" class="form-label fw-semibold">Documento de Identidad (DNI/RUT) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="documento" name="documento" required>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_nacimiento" class="form-label fw-semibold">Fecha de Nacimiento</label>
                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                    </div>
                    <div class="col-md-4">
                        <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                    <div class="col-md-8">
                        <label for="direccion" class="form-label fw-semibold">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion">
                    </div>
                    <div class="col-md-4">
                        <label for="estado_academico" class="form-label fw-semibold">Estado Académico</label>
                        <select class="form-select" id="estado_academico" name="estado_academico">
                            <option value="Regular">Regular</option>
                            <option value="Condicional">Condicional</option>
                            <option value="Suspendido">Suspendido</option>
                            <option value="Egresado">Egresado</option>
                        </select>
                    </div>

                    <div class="border-top my-4"></div>
                    <h5 class="fw-bold text-primary"><i class="fa-solid fa-key me-2"></i>Credenciales del Portal Estudiantil</h5>
                    
                    <div class="col-md-4">
                        <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="ejemplo@correo.com">
                    </div>
                    <div class="col-md-4">
                        <label for="username" class="form-label fw-semibold">Nombre de Usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="col-md-4">
                        <label for="password" class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <button type="reset" class="btn btn-outline-secondary me-2">Limpiar Formulario</button>
                        <button type="submit" class="btn btn-primary">Registrar Alumno</button>
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
        $stmt = $pdo->prepare("
            SELECT a.*, u.username, u.email 
            FROM alumnos a 
            LEFT JOIN usuarios u ON a.usuario_id = u.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $alumno = $stmt->fetch();
        if (!$alumno) {
            echo "<div class='alert alert-danger'>Alumno no encontrado.</div>";
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Editar Datos de: <?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']); ?></h5>
        </div>
        <div class="card-body">
            <form action="alumnos.php?action=update" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $alumno['id']; ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($alumno['nombre']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="apellido" class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars($alumno['apellido']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="documento" class="form-label fw-semibold">Documento de Identidad (DNI/RUT) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="documento" name="documento" value="<?php echo htmlspecialchars($alumno['documento']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_nacimiento" class="form-label fw-semibold">Fecha de Nacimiento</label>
                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($alumno['fecha_nacimiento']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($alumno['telefono']); ?>">
                    </div>
                    <div class="col-md-8">
                        <label for="direccion" class="form-label fw-semibold">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($alumno['direccion']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="estado_academico" class="form-label fw-semibold">Estado Académico</label>
                        <select class="form-select" id="estado_academico" name="estado_academico">
                            <option value="Regular" <?php echo $alumno['estado_academico'] === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                            <option value="Condicional" <?php echo $alumno['estado_academico'] === 'Condicional' ? 'selected' : ''; ?>>Condicional</option>
                            <option value="Suspendido" <?php echo $alumno['estado_academico'] === 'Suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                            <option value="Egresado" <?php echo $alumno['estado_academico'] === 'Egresado' ? 'selected' : ''; ?>>Egresado</option>
                        </select>
                    </div>

                    <div class="border-top my-4"></div>
                    <h5 class="fw-bold text-primary"><i class="fa-solid fa-key me-2"></i>Credenciales del Portal Estudiantil</h5>
                    
                    <div class="col-md-4">
                        <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($alumno['email']); ?>" placeholder="ejemplo@correo.com">
                    </div>
                    <div class="col-md-4">
                        <label for="username" class="form-label fw-semibold">Nombre de Usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($alumno['username']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="password" class="form-label fw-semibold">Nueva Contraseña <span class="text-muted">(Dejar en blanco para no cambiar)</span></label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <button type="submit" class="btn btn-warning px-4">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<!-- 4. VISTA EXPEDIENTE / VER DETALLES -->
<?php elseif ($action === 'view'): ?>
    <?php
    $id = intval($_GET['id'] ?? 0);
    try {
        // Datos Personales
        $stmt = $pdo->prepare("
            SELECT a.*, u.username, u.email 
            FROM alumnos a 
            LEFT JOIN usuarios u ON a.usuario_id = u.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $alumno = $stmt->fetch();
        
        if (!$alumno) {
            echo "<div class='alert alert-danger'>Estudiante no encontrado.</div>";
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }

        // Calificaciones del alumno
        $stmtCal = $pdo->prepare("
            SELECT c.nombre as curso_nombre, c.codigo as curso_codigo, cal.nota, cal.fecha, cal.observaciones 
            FROM calificaciones cal 
            JOIN cursos c ON cal.curso_id = c.id 
            WHERE cal.alumno_id = ?
            ORDER BY cal.fecha DESC
        ");
        $stmtCal->execute([$id]);
        $notas = $stmtCal->fetchAll();

        // Calcular promedio
        $promedio = 0;
        if (count($notas) > 0) {
            $sum = array_sum(array_column($notas, 'nota'));
            $promedio = $sum / count($notas);
        }

        // Historial de Asistencia
        $stmtAsist = $pdo->prepare("
            SELECT c.nombre as curso_nombre, a.fecha, a.estado 
            FROM asistencias a 
            JOIN cursos c ON a.curso_id = c.id 
            WHERE a.alumno_id = ?
            ORDER BY a.fecha DESC LIMIT 15
        ");
        $stmtAsist->execute([$id]);
        $asistencias = $stmtAsist->fetchAll();

        // Conteo de Asistencias
        $stmtAsisCount = $pdo->prepare("SELECT estado, COUNT(*) as cant FROM asistencias WHERE alumno_id = ? GROUP BY estado");
        $stmtAsisCount->execute([$id]);
        $asistencias_res = $stmtAsisCount->fetchAll();
        $resumen_asis = ['Presente' => 0, 'Falta' => 0, 'Tardanza' => 0, 'Justificada' => 0];
        foreach ($asistencias_res as $ar) {
            $resumen_asis[$ar['estado']] = $ar['cant'];
        }

        // Pagos y Finanzas
        $stmtPagos = $pdo->prepare("
            SELECT * FROM pagos WHERE alumno_id = ? ORDER BY fecha DESC
        ");
        $stmtPagos->execute([$id]);
        $pagos = $stmtPagos->fetchAll();

        $deuda = 0.0;
        foreach ($pagos as $p) {
            if ($p['estado'] === 'Pendiente') {
                $deuda += $p['monto'];
            }
        }

    } catch (PDOException $e) {
        die("Error cargando expediente: " . $e->getMessage());
    }
    ?>

    <div class="row g-4">
        <!-- Ficha de Datos Personales -->
        <div class="col-lg-4">
            <div class="card bg-white shadow-sm border-0 text-center p-4">
                <div class="user-avatar-large mx-auto mb-3 bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    <?php echo strtoupper(substr($alumno['nombre'], 0, 1)); ?>
                </div>
                <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']); ?></h4>
                <p class="text-muted fs-7 mb-2">DNI: <?php echo htmlspecialchars($alumno['documento']); ?></p>
                <div class="mb-3">
                    <?php
                    $badgeColor = 'bg-success';
                    if ($alumno['estado_academico'] === 'Condicional') $badgeColor = 'bg-warning text-dark';
                    elseif ($alumno['estado_academico'] === 'Suspendido') $badgeColor = 'bg-danger';
                    elseif ($alumno['estado_academico'] === 'Egresado') $badgeColor = 'bg-info';
                    ?>
                    <span class="badge <?php echo $badgeColor; ?> px-3 py-2"><?php echo $alumno['estado_academico']; ?></span>
                </div>

                <ul class="list-group list-group-flush text-start fs-7 mt-3 border-top">
                    <li class="list-group-item py-2"><strong>Usuario:</strong> <code><?php echo htmlspecialchars($alumno['username']); ?></code></li>
                    <li class="list-group-item py-2"><strong>Email:</strong> <?php echo htmlspecialchars($alumno['email'] ?: 'No registrado'); ?></li>
                    <li class="list-group-item py-2"><strong>Teléfono:</strong> <?php echo htmlspecialchars($alumno['telefono'] ?: '-'); ?></li>
                    <li class="list-group-item py-2"><strong>Nacimiento:</strong> <?php echo htmlspecialchars($alumno['fecha_nacimiento'] ?: '-'); ?></li>
                    <li class="list-group-item py-2 text-truncate"><strong>Dirección:</strong> <?php echo htmlspecialchars($alumno['direccion'] ?: '-'); ?></li>
                </ul>
            </div>

            <!-- Resumen Financiero -->
            <div class="card bg-white shadow-sm border-0 mt-4 p-3">
                <h6 class="fw-bold text-dark border-bottom pb-2"><i class="fa-solid fa-wallet me-2 text-warning"></i>Estado Financiero</h6>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="text-muted">Deuda Pendiente:</span>
                    <span class="fw-bold text-danger fs-5">S/. <?php echo number_format($deuda, 2); ?></span>
                </div>
                <div class="text-end mt-2">
                    <a href="pagos.php" class="btn btn-xs btn-outline-warning text-dark fs-8 py-1 px-2"><i class="fa-solid fa-dollar-sign"></i> Ir a Pagos</a>
                </div>
            </div>
        </div>

        <!-- Fichas Académicas -->
        <div class="col-lg-8">
            <!-- Pestañas de Navegación -->
            <ul class="nav nav-tabs mb-3 shadow-sm bg-white rounded p-1" id="expedienteTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="notas-tab" data-bs-toggle="tab" data-bs-target="#notas" type="button" role="tab" aria-controls="notas" aria-selected="true"><i class="fa-solid fa-star me-2 text-warning"></i>Calificaciones</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="asis-tab" data-bs-toggle="tab" data-bs-target="#asis" type="button" role="tab" aria-controls="asis" aria-selected="false"><i class="fa-solid fa-calendar-days me-2 text-success"></i>Asistencia</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="finanzas-tab" data-bs-toggle="tab" data-bs-target="#finanzas" type="button" role="tab" aria-controls="finanzas" aria-selected="false"><i class="fa-solid fa-receipt me-2 text-info"></i>Historial de Pagos</button>
                </li>
            </ul>

            <div class="tab-content" id="expedienteTabContent">
                <!-- Pestaña 1: Calificaciones -->
                <div class="tab-pane fade show active" id="notas" role="tabpanel" aria-labelledby="notas-tab">
                    <div class="card bg-white shadow-sm border-0">
                        <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Notas Registradas</h6>
                            <span class="badge bg-primary fs-7">Promedio: <?php echo number_format($promedio, 2); ?> / 20</span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Nota</th>
                                        <th>Fecha</th>
                                        <th>Observación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($notas)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">El estudiante no cuenta con calificaciones registradas.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($notas as $n): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($n['curso_nombre']); ?></div>
                                                    <small class="text-muted">Cod: <?php echo htmlspecialchars($n['curso_codigo']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $n['nota'] >= 11 ? 'bg-success' : 'bg-danger'; ?> fs-7">
                                                        <?php echo number_format($n['nota'], 1); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($n['fecha']); ?></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($n['observaciones'] ?: '-'); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 2: Asistencia -->
                <div class="tab-pane fade" id="asis" role="tabpanel" aria-labelledby="asis-tab">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 text-center bg-white shadow-xs">
                                <small class="text-muted text-uppercase fs-9 fw-bold">Asistencias</small>
                                <h3 class="fw-bold text-success mb-0"><?php echo $resumen_asis['Presente']; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 text-center bg-white shadow-xs">
                                <small class="text-muted text-uppercase fs-9 fw-bold">Faltas</small>
                                <h3 class="fw-bold text-danger mb-0"><?php echo $resumen_asis['Falta']; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 text-center bg-white shadow-xs">
                                <small class="text-muted text-uppercase fs-9 fw-bold">Tardanzas</small>
                                <h3 class="fw-bold text-warning mb-0"><?php echo $resumen_asis['Tardanza']; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 text-center bg-white shadow-xs">
                                <small class="text-muted text-uppercase fs-9 fw-bold">Justificadas</small>
                                <h3 class="fw-bold text-info mb-0"><?php echo $resumen_asis['Justificada']; ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-white shadow-sm border-0">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <h6 class="mb-0 fw-bold">Últimos Registros de Asistencia</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($asistencias)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted">No se registran asistencias para este estudiante.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($asistencias as $a): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($a['curso_nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($a['fecha']); ?></td>
                                                <td>
                                                    <?php
                                                    $bgAs = 'bg-success';
                                                    if ($a['estado'] === 'Falta') $bgAs = 'bg-danger';
                                                    elseif ($a['estado'] === 'Tardanza') $bgAs = 'bg-warning text-dark';
                                                    elseif ($a['estado'] === 'Justificada') $bgAs = 'bg-info';
                                                    ?>
                                                    <span class="badge <?php echo $bgAs; ?>"><?php echo $a['estado']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 3: Historial de Pagos -->
                <div class="tab-pane fade" id="finanzas" role="tabpanel" aria-labelledby="finanzas-tab">
                    <div class="card bg-white shadow-sm border-0">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <h6 class="mb-0 fw-bold">Historial Financiero</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Monto</th>
                                        <th>Fecha de Registro</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pagos)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No se registran transacciones financieras.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pagos as $p): ?>
                                            <tr>
                                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['concepto']); ?></td>
                                                <td>S/. <?php echo number_format($p['monto'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($p['fecha']); ?></td>
                                                <td>
                                                    <?php
                                                    $bgPa = 'bg-success';
                                                    if ($p['estado'] === 'Pendiente') $bgPa = 'bg-warning text-dark';
                                                    elseif ($p['estado'] === 'Vencido') $bgPa = 'bg-danger';
                                                    ?>
                                                    <span class="badge <?php echo $bgPa; ?>"><?php echo $p['estado']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
