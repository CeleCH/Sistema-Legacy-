<?php
// modules/profesores.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Control de acceso: Solo Director
check_role(['Director']);

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// --- PROCESAR ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $especialidad = trim($_POST['especialidad'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($nombre) && !empty($apellido) && !empty($username) && !empty($password)) {
            try {
                $pdo->beginTransaction();

                // 1. Crear usuario asociado con rol Docente
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO usuarios (username, password, nombre, email, rol) VALUES (?, ?, ?, ?, 'Docente')");
                $stmtUser->execute([$username, $passwordHash, "$nombre $apellido", $email]);
                $usuarioId = $pdo->lastInsertId();

                // 2. Crear profesor
                $stmtProf = $pdo->prepare("INSERT INTO profesores (usuario_id, nombre, apellido, especialidad, telefono, email) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtProf->execute([$usuarioId, $nombre, $apellido, $especialidad, $telefono, $email]);

                $pdo->commit();
                header("Location: profesores.php?msg=Docente registrado con éxito");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Error al registrar docente: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, complete todos los campos obligatorios.";
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $especialidad = trim($_POST['especialidad'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($id > 0 && !empty($nombre) && !empty($apellido) && !empty($username)) {
            try {
                $pdo->beginTransaction();

                // Obtener usuario_id
                $stmtGetU = $pdo->prepare("SELECT usuario_id FROM profesores WHERE id = ?");
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

                // Actualizar profesor
                $stmtProf = $pdo->prepare("UPDATE profesores SET nombre = ?, apellido = ?, especialidad = ?, telefono = ?, email = ? WHERE id = ?");
                $stmtProf->execute([$nombre, $apellido, $especialidad, $telefono, $email, $id]);

                $pdo->commit();
                header("Location: profesores.php?msg=Datos del docente actualizados con éxito");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Error al actualizar docente: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, complete todos los campos obligatorios.";
        }
    }
}

// --- PROCESAR ACCIÓN ELIMINAR (GET) ---
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->beginTransaction();

            // Obtener usuario_id antes de borrar
            $stmtGetU = $pdo->prepare("SELECT usuario_id FROM profesores WHERE id = ?");
            $stmtGetU->execute([$id]);
            $usuarioId = $stmtGetU->fetchColumn();

            // Eliminar profesor (desasociará profesor_id en cursos por ON DELETE SET NULL)
            $stmtDelProf = $pdo->prepare("DELETE FROM profesores WHERE id = ?");
            $stmtDelProf->execute([$id]);

            // Eliminar usuario asociado
            if ($usuarioId) {
                $stmtDelUs = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmtDelUs->execute([$usuarioId]);
            }

            $pdo->commit();
            header("Location: profesores.php?msg=Docente eliminado del sistema");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: profesores.php?error=Error al eliminar: " . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Profesores -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-chalkboard-user me-2 text-primary"></i>Gestión de Docentes</h1>
        <p class="text-muted mb-0">Administración de la plana docente del colegio.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="profesores.php?action=new" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Registrar Docente</a>
    <?php else: ?>
        <a href="profesores.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Volver al Listado</a>
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
            SELECT p.*, u.username, 
                   (SELECT COUNT(*) FROM cursos c WHERE c.profesor_id = p.id) as cant_cursos 
            FROM profesores p
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            ORDER BY p.apellido ASC, p.nombre ASC
        ");
        $profesores = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error al cargar docentes: " . $e->getMessage() . "</div>";
        $profesores = [];
    }
    ?>

    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0 fw-bold">Plana Docente</h5>
                </div>
                <div class="col-md-6 mt-2 mt-md-0">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="tableSearch" placeholder="Buscar por nombre, especialidad o usuario...">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Código / Docente</th>
                            <th>Especialidad</th>
                            <th>Usuario / Email</th>
                            <th>Cursos Asignados</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($profesores)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No hay docentes registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($profesores as $pr): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($pr['apellido'] . ', ' . $pr['nombre']); ?></div>
                                        <small class="text-muted">Tel: <?php echo htmlspecialchars($pr['telefono'] ?: '-'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($pr['especialidad'] ?: 'General'); ?></span>
                                    </td>
                                    <td>
                                        <div><code><?php echo htmlspecialchars($pr['username'] ?: ''); ?></code></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($pr['email'] ?: ''); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-white fs-7"><?php echo $pr['cant_cursos']; ?> cursos</span>
                                    </td>
                                    <td class="text-end px-3">
                                        <a href="profesores.php?action=view&id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-info text-white" title="Ver Información"><i class="fa-solid fa-eye"></i></a>
                                        <a href="profesores.php?action=edit&id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="profesores.php?action=delete&id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirmarEliminacion('¿Desea eliminar este docente? Sus cursos quedarán huérfanos temporalmente y se borrará su usuario del sistema.');"><i class="fa-solid fa-trash-can"></i></a>
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
<?php elseif ($action === 'new'): ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Nuevo Registro de Docente</h5>
        </div>
        <div class="card-body">
            <form action="profesores.php?action=create" method="POST" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="col-md-6">
                        <label for="apellido" class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" required>
                    </div>
                    <div class="col-md-6">
                        <label for="especialidad" class="form-label fw-semibold">Especialidad / Área Curricular</label>
                        <input type="text" class="form-control" id="especialidad" name="especialidad" placeholder="Ej. Álgebra, Ciencias Naturales, etc.">
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label fw-semibold">Teléfono de Contacto</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>

                    <div class="border-top my-4"></div>
                    <h5 class="fw-bold text-primary"><i class="fa-solid fa-key me-2"></i>Credenciales del Portal Docente</h5>
                    
                    <div class="col-md-4">
                        <label for="email" class="form-label fw-semibold">Correo Electrónico Institucional</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="profesor@colegio.edu.pe">
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
                        <button type="submit" class="btn btn-primary">Registrar Docente</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<!-- 3. FORMULARIO EDITAR -->
<?php elseif ($action === 'edit'): ?>
    <?php
    $id = intval($_GET['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username, u.email 
            FROM profesores p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $profesor = $stmt->fetch();
        if (!$profesor) {
            echo "<div class='alert alert-danger'>Docente no encontrado.</div>";
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Editar Datos de: <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido']); ?></h5>
        </div>
        <div class="card-body">
            <form action="profesores.php?action=update" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $profesor['id']; ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($profesor['nombre']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="apellido" class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars($profesor['apellido']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="especialidad" class="form-label fw-semibold">Especialidad / Área Curricular</label>
                        <input type="text" class="form-control" id="especialidad" name="especialidad" value="<?php echo htmlspecialchars($profesor['especialidad']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label fw-semibold">Teléfono de Contacto</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($profesor['telefono']); ?>">
                    </div>

                    <div class="border-top my-4"></div>
                    <h5 class="fw-bold text-primary"><i class="fa-solid fa-key me-2"></i>Credenciales del Portal Docente</h5>
                    
                    <div class="col-md-4">
                        <label for="email" class="form-label fw-semibold">Correo Electrónico Institucional</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($profesor['email']); ?>" placeholder="profesor@colegio.edu.pe">
                    </div>
                    <div class="col-md-4">
                        <label for="username" class="form-label fw-semibold">Nombre de Usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($profesor['username']); ?>" required>
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

<!-- 4. VISTA INFORMACIÓN DETALLADA -->
<?php elseif ($action === 'view'): ?>
    <?php
    $id = intval($_GET['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username, u.email 
            FROM profesores p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $profesor = $stmt->fetch();
        
        if (!$profesor) {
            echo "<div class='alert alert-danger'>Docente no encontrado.</div>";
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }

        // Cursos asignados
        $stmtCursos = $pdo->prepare("SELECT * FROM cursos WHERE profesor_id = ? ORDER BY nombre ASC");
        $stmtCursos->execute([$id]);
        $cursos = $stmtCursos->fetchAll();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    ?>

    <div class="row g-4">
        <!-- Ficha de Datos Personales -->
        <div class="col-lg-4">
            <div class="card bg-white shadow-sm border-0 text-center p-4">
                <div class="user-avatar-large mx-auto mb-3 bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    <?php echo strtoupper(substr($profesor['nombre'], 0, 1)); ?>
                </div>
                <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido']); ?></h4>
                <span class="badge bg-secondary px-3 py-2 mb-3"><?php echo htmlspecialchars($profesor['especialidad'] ?: 'General'); ?></span>

                <ul class="list-group list-group-flush text-start fs-7 border-top">
                    <li class="list-group-item py-2"><strong>Usuario:</strong> <code><?php echo htmlspecialchars($profesor['username']); ?></code></li>
                    <li class="list-group-item py-2"><strong>Email:</strong> <?php echo htmlspecialchars($profesor['email'] ?: 'No registrado'); ?></li>
                    <li class="list-group-item py-2"><strong>Teléfono:</strong> <?php echo htmlspecialchars($profesor['telefono'] ?: '-'); ?></li>
                </ul>
            </div>
        </div>

        <!-- Cursos Asignados -->
        <div class="col-lg-8">
            <div class="card bg-white shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-book me-2 text-primary"></i>Cursos Curriculares Asignados</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre del Curso</th>
                                <th>Horario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cursos)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">Este docente no tiene cursos asignados en este ciclo académico.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cursos as $c): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($c['codigo']); ?></code></td>
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($c['nombre']); ?></td>
                                        <td><small class="text-muted"><i class="fa-regular fa-clock me-1"></i> <?php echo htmlspecialchars($c['horario'] ?: 'No programado'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mt-3 text-end">
                <a href="cursos.php" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-2"></i>Asignar / Modificar Cursos</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
