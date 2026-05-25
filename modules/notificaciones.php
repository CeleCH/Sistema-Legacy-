<?php
// modules/notificaciones.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Control de acceso: Todos los roles
check_role(['Director', 'Administrador', 'Docente', 'Alumno', 'Padre de familia']);

$rol = $_SESSION['usuario_rol'];
$userId = $_SESSION['usuario_id'];
$can_send = in_array($rol, ['Director', 'Administrador', 'Docente']);

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// --- PROCESAR ACCIONES POST (Enviar notificación) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_send && $action === 'create') {
    $destino = $_POST['destino'] ?? ''; // 'todos' o user_id numérico
    $titulo = trim($_POST['titulo'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $fecha = date('Y-m-d H:i:s');

    if (!empty($titulo) && !empty($mensaje) && !empty($destino)) {
        try {
            $pdo->beginTransaction();

            $stmtNot = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, fecha, leido) VALUES (?, ?, ?, ?, 0)");

            if ($destino === 'todos') {
                // Obtener todos los usuarios
                $usuarios = $pdo->query("SELECT id FROM usuarios")->fetchAll();
                foreach ($usuarios as $u) {
                    $stmtNot->execute([$u['id'], $titulo, $mensaje, $fecha]);
                }
                $notif_msg = "Notificación global enviada a todos los usuarios.";
            } else {
                $dest_user_id = intval($destino);
                $stmtNot->execute([$dest_user_id, $titulo, $mensaje, $fecha]);
                $notif_msg = "Notificación enviada correctamente al usuario seleccionado.";
            }

            $pdo->commit();
            header("Location: notificaciones.php?msg=" . urlencode($notif_msg));
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al enviar notificación: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, complete todos los campos obligatorios.";
    }
}

// --- PROCESAR ACCIÓN MARCAR COMO LEÍDO (GET) ---
if ($action === 'mark_read') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            // Validar que la notificación pertenece al usuario
            $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $userId]);
            header("Location: notificaciones.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error al marcar como leída: " . $e->getMessage();
        }
    }
}

// --- PROCESAR ACCIÓN MARCAR TODAS COMO LEÍDAS (GET) ---
if ($action === 'mark_all_read') {
    try {
        $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ?");
        $stmt->execute([$userId]);
        header("Location: notificaciones.php?msg=Todas las notificaciones fueron marcadas como leídas");
        exit;
    } catch (PDOException $e) {
        $error = "Error al actualizar notificaciones: " . $e->getMessage();
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Notificaciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-bell me-2 text-danger"></i>Centro de Notificaciones</h1>
        <p class="text-muted mb-0">Mensajes del sistema, alertas académicas y anuncios del colegio.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="notificaciones.php?action=mark_all_read" class="btn btn-outline-secondary btn-sm d-flex align-items-center"><i class="fa-solid fa-envelope-open me-2"></i> Marcar todas como leídas</a>
        <?php if ($can_send && $action === 'list'): ?>
            <a href="notificaciones.php?action=new" class="btn btn-primary btn-sm d-flex align-items-center"><i class="fa-solid fa-paper-plane me-2"></i> Redactar Anuncio</a>
        <?php elseif ($action !== 'list'): ?>
            <a href="notificaciones.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center"><i class="fa-solid fa-arrow-left me-2"></i> Volver a la Bandeja</a>
        <?php endif; ?>
    </div>
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

<!-- 1. VISTA BANDEJA DE ENTRADA -->
<?php if ($action === 'list'): ?>
    <?php
    try {
        $stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY fecha DESC");
        $stmt->execute([$userId]);
        $notificaciones = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error al cargar bandeja: " . $e->getMessage() . "</div>";
        $notificaciones = [];
    }
    ?>

    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Mi Bandeja de Alertas</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($notificaciones)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fa-regular fa-bell-slash d-block fs-1 mb-3 text-secondary"></i>
                    <p class="mb-0">No se registran notificaciones o alertas en este momento.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notificaciones as $n): ?>
                        <div class="list-group-item p-3 <?php echo !$n['leido'] ? 'bg-light border-start border-danger border-3' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 fw-bold text-dark <?php echo !$n['leido'] ? 'text-primary' : ''; ?>">
                                    <?php if (!$n['leido']): ?>
                                        <i class="fa-solid fa-circle text-danger me-2 fs-9"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($n['titulo']); ?>
                                </h6>
                                <div class="text-end">
                                    <small class="text-muted fs-8"><i class="fa-regular fa-clock me-1"></i> <?php echo htmlspecialchars($n['fecha']); ?></small>
                                    <?php if (!$n['leido']): ?>
                                        <a href="notificaciones.php?action=mark_read&id=<?php echo $n['id']; ?>" class="btn btn-xs btn-outline-primary ms-3 fs-9 py-0 px-2" title="Marcar como leída">Marcar como leída</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mb-0 text-muted fs-7 mt-1"><?php echo htmlspecialchars($n['mensaje']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- 2. FORMULARIO REDACTAR ANUNCIO / NOTIFICACIÓN (Docentes / Admins) -->
<?php elseif ($action === 'new' && $can_send): ?>
    <?php
    // Obtener lista de usuarios destinatarios
    try {
        $usuarios = $pdo->query("SELECT id, nombre, rol FROM usuarios WHERE id != $userId ORDER BY rol ASC, nombre ASC")->fetchAll();
    } catch (PDOException $e) {
        $usuarios = [];
    }
    ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Simulación de Envío de Notificaciones</h5>
        </div>
        <div class="card-body">
            
            <div class="alert alert-warning border-start border-warning border-3 mb-4" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i> Este panel simula el envío inmediato de alertas del sistema, correos transaccionales e informes automatizados a los paneles correspondientes de los destinatarios.
            </div>

            <form action="notificaciones.php?action=create" method="POST" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="destino" class="form-label fw-semibold">Destinatario del Mensaje <span class="text-danger">*</span></label>
                        <select class="form-select" id="destino" name="destino" required>
                            <option value="">-- Seleccionar Destinatario --</option>
                            <option value="todos">Todos los Usuarios (Mensaje Global)</option>
                            <?php foreach ($usuarios as $us): ?>
                                <option value="<?php echo $us['id']; ?>"><?php echo htmlspecialchars($us['nombre'] . ' [' . $us['rol'] . ']'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="titulo" class="form-label fw-semibold">Asunto / Título de Alerta <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ej. Suspensión de Clases por Feriado, Citación a Reunión..." required>
                    </div>
                    <div class="col-md-12">
                        <label for="mensaje" class="form-label fw-semibold">Mensaje Completo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="mensaje" name="mensaje" rows="5" placeholder="Escriba el detalle del comunicado aquí..." required></textarea>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-paper-plane me-2"></i>Enviar Comunicado</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
