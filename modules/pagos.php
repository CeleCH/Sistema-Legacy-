<?php
// modules/pagos.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Control de acceso: Director, Administrador, Alumno, Padre de familia
check_role(['Director', 'Administrador', 'Alumno', 'Padre de familia']);

$rol = $_SESSION['usuario_rol'];
$userId = $_SESSION['usuario_id'];
$can_modify = in_array($rol, ['Director', 'Administrador']);

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Determinar el alumno_id para filtros de Alumno / Padre de familia
$alumnoId = null;
$alumnoNombre = '';
if (!$can_modify) {
    if ($rol === 'Padre de familia') {
        // Simular asociándolo al primer alumno
        $alumnoId = $pdo->query("SELECT id FROM alumnos LIMIT 1")->fetchColumn();
        $alumnoNombre = $pdo->query("SELECT nombre || ' ' || apellido FROM alumnos LIMIT 1")->fetchColumn();
    } else {
        $stmtAl = $pdo->prepare("SELECT id, nombre || ' ' || apellido as full_name FROM alumnos WHERE usuario_id = ?");
        $stmtAl->execute([$userId]);
        $alData = $stmtAl->fetch();
        $alumnoId = $alData['id'] ?? null;
        $alumnoNombre = $alData['full_name'] ?? '';
    }
}

// --- PROCESAR ACCIONES POST (Solo Admins) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_modify) {
    if ($action === 'create') {
        $alumno_id = intval($_POST['alumno_id'] ?? 0);
        $monto = floatval($_POST['monto'] ?? 0.0);
        $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
        $concepto = trim($_POST['concepto'] ?? '');
        $estado = trim($_POST['estado'] ?? 'Pendiente');

        if ($alumno_id > 0 && $monto > 0 && !empty($concepto)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO pagos (alumno_id, monto, fecha, concepto, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$alumno_id, $monto, $fecha, $concepto, $estado]);
                
                // Si el pago es registrado como pendiente, crear una notificación rápida para el alumno
                if ($estado === 'Pendiente') {
                    $stmtUser = $pdo->prepare("SELECT usuario_id FROM alumnos WHERE id = ?");
                    $stmtUser->execute([$alumno_id]);
                    $alUser = $stmtUser->fetchColumn();
                    if ($alUser) {
                        $stmtNot = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, fecha, leido) VALUES (?, ?, ?, ?, 0)");
                        $stmtNot->execute([$alUser, 'Nuevo Cobro Registrado', "Se ha generado un cobro pendiente de S/. " . number_format($monto, 2) . " por concepto de: $concepto.", date('Y-m-d H:i:s')]);
                    }
                }

                header("Location: pagos.php?msg=Pago registrado con éxito");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar pago: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, complete todos los campos obligatorios y con montos válidos.";
        }
    }
}

// --- PROCESAR ACCIÓN COBRAR / PAGAR (GET - Solo Admins) ---
if ($action === 'pay' && $can_modify) {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE pagos SET estado = 'Pagado' WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: pagos.php?msg=Pago marcado como PAGADO correctamente");
            exit;
        } catch (PDOException $e) {
            header("Location: pagos.php?error=Error al procesar cobro: " . urlencode($e->getMessage()));
            exit;
        }
    }
}

// --- PROCESAR ACCIÓN ELIMINAR (GET - Solo Admins) ---
if ($action === 'delete' && $can_modify) {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM pagos WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: pagos.php?msg=Transacción financiera eliminada");
            exit;
        } catch (PDOException $e) {
            header("Location: pagos.php?error=Error al eliminar transacción: " . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Renderizar Vistas
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Módulo Pagos -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-dark fw-bold"><i class="fa-solid fa-credit-card me-2 text-primary"></i>Módulo de Finanzas y Pagos</h1>
        <p class="text-muted mb-0">Control de ingresos de matrículas, pensiones mensuales y estado financiero.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($action === 'list' && $can_modify): ?>
            <a href="pagos.php?action=new" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Registrar Cobro / Pago</a>
        <?php elseif ($action !== 'list'): ?>
            <a href="pagos.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Volver al Listado</a>
        <?php endif; ?>
        
        <?php if (!$can_modify): ?>
            <button onclick="window.print()" class="btn btn-outline-danger shadow-sm no-print">
                <i class="fa-solid fa-file-pdf me-2"></i>Descargar PDF
            </button>
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

<!-- 1. VISTA LISTADO -->
<?php if ($action === 'list'): ?>
    <?php
    try {
        if ($can_modify) {
            // Cargar todos los pagos del colegio
            $stmt = $pdo->query("
                SELECT p.*, a.nombre as al_nombre, a.apellido as al_apellido, a.documento 
                FROM pagos p 
                JOIN alumnos a ON p.alumno_id = a.id 
                ORDER BY p.fecha DESC
            ");
            $pagos = $stmt->fetchAll();
            
            // Estadísticas generales
            $total_pagado = $pdo->query("SELECT SUM(monto) FROM pagos WHERE estado = 'Pagado'")->fetchColumn() ?: 0.0;
            $total_deuda = $pdo->query("SELECT SUM(monto) FROM pagos WHERE estado = 'Pendiente'")->fetchColumn() ?: 0.0;
        } else {
            // Cargar pagos del alumno actual
            $stmt = $pdo->prepare("
                SELECT p.*, a.nombre as al_nombre, a.apellido as al_apellido, a.documento 
                FROM pagos p 
                JOIN alumnos a ON p.alumno_id = a.id 
                WHERE p.alumno_id = ?
                ORDER BY p.fecha DESC
            ");
            $stmt->execute([$alumnoId]);
            $pagos = $stmt->fetchAll();

            // Estadísticas personales
            $stmtP = $pdo->prepare("SELECT SUM(monto) FROM pagos WHERE alumno_id = ? AND estado = 'Pagado'");
            $stmtP->execute([$alumnoId]);
            $total_pagado = $stmtP->fetchColumn() ?: 0.0;

            $stmtD = $pdo->prepare("SELECT SUM(monto) FROM pagos WHERE alumno_id = ? AND estado = 'Pendiente'");
            $stmtD->execute([$alumnoId]);
            $total_deuda = $stmtD->fetchColumn() ?: 0.0;
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error al cargar pagos: " . $e->getMessage() . "</div>";
        $pagos = [];
    }
    ?>

    <!-- Cabecera de impresión oficial (solo visible al imprimir) -->
    <?php if (!$can_modify): ?>
        <div class="print-header">
            <div class="text-center">
                <h2>Colegio Futuro Digital</h2>
                <p class="text-uppercase tracking-wide fw-bold mb-1" style="font-size: 0.85rem; letter-spacing: 1px;">Estado de Cuenta e Historial de Pagos</p>
                <p class="mb-0">Estudiante: <strong><?php echo htmlspecialchars($alumnoNombre); ?></strong> | Fecha de Emisión: <?php echo date('d/m/Y H:i'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cards Estadísticas Rápidas -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-success text-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-uppercase fs-9 tracking-wide mb-1 fw-bold">Recaudado / Pagado</h6>
                        <h3 class="fw-bold mb-0">S/. <?php echo number_format($total_pagado, 2); ?></h3>
                    </div>
                    <div class="fs-1 opacity-75"><i class="fa-solid fa-circle-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-warning text-dark">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-uppercase fs-9 tracking-wide mb-1 fw-bold">Deuda Pendiente</h6>
                        <h3 class="fw-bold mb-0">S/. <?php echo number_format($total_deuda, 2); ?></h3>
                    </div>
                    <div class="fs-1 opacity-75"><i class="fa-solid fa-circle-exclamation"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Pagos -->
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0 fw-bold"><?php echo $can_modify ? 'Historial Financiero General' : 'Mi Estado Financiero'; ?></h5>
                </div>
                <div class="col-md-6 mt-2 mt-md-0">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="tableSearch" placeholder="Buscar por concepto, alumno o estado...">
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
                            <th>Concepto de Pago</th>
                            <?php if ($can_modify): ?>
                                <th>Alumno / DNI</th>
                            <?php endif; ?>
                            <th>Monto</th>
                            <th>Estado</th>
                            <?php if ($can_modify): ?>
                                <th class="text-end">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagos)): ?>
                            <tr>
                                <td colspan="<?php echo $can_modify ? '6' : '4'; ?>" class="text-center py-4 text-muted">No se registran transacciones de pago.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pagos as $p): ?>
                                <tr>
                                    <td><i class="fa-regular fa-calendar-days me-1 text-muted"></i> <?php echo htmlspecialchars($p['fecha']); ?></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['concepto']); ?></td>
                                    <?php if ($can_modify): ?>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($p['al_apellido'] . ', ' . $p['al_nombre']); ?></div>
                                            <small class="text-muted">DNI: <?php echo htmlspecialchars($p['documento']); ?></small>
                                        </td>
                                    <?php endif; ?>
                                    <td class="fw-bold text-primary">S/. <?php echo number_format($p['monto'], 2); ?></td>
                                    <td>
                                        <?php
                                        $badgeColor = 'bg-success';
                                        if ($p['estado'] === 'Pendiente') $badgeColor = 'bg-warning text-dark';
                                        elseif ($p['estado'] === 'Vencido') $badgeColor = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badgeColor; ?>"><?php echo $p['estado']; ?></span>
                                    </td>
                                    <?php if ($can_modify): ?>
                                        <td class="text-end px-3">
                                            <?php if ($p['estado'] !== 'Pagado'): ?>
                                                <a href="pagos.php?action=pay&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-success" title="Registrar Cobro" onclick="return confirm('¿Está seguro de marcar este pago como PAGADO?');"><i class="fa-solid fa-circle-check"></i> Cobrar</a>
                                            <?php endif; ?>
                                            <a href="pagos.php?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Eliminar Registro" onclick="return confirmarEliminacion();"><i class="fa-solid fa-trash-can"></i></a>
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

<!-- 2. FORMULARIO REGISTRAR COBRO -->
<?php elseif ($action === 'new' && $can_modify): ?>
    <?php
    // Obtener todos los alumnos para el dropdown selector
    try {
        $alumnos = $pdo->query("SELECT id, nombre, apellido, documento FROM alumnos ORDER BY apellido ASC")->fetchAll();
    } catch (PDOException $e) {
        $alumnos = [];
    }
    ?>
    <div class="card bg-white shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold">Registrar Nueva Transacción / Cobro</h5>
        </div>
        <div class="card-body">
            <form action="pagos.php?action=create" method="POST" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="alumno_id" class="form-label fw-semibold">Estudiante Beneficiario <span class="text-danger">*</span></label>
                        <select class="form-select" id="alumno_id" name="alumno_id" required>
                            <option value="">-- Seleccionar Alumno --</option>
                            <?php foreach ($alumnos as $al): ?>
                                <option value="<?php echo $al['id']; ?>"><?php echo htmlspecialchars($al['apellido'] . ', ' . $al['nombre'] . ' (DNI: ' . $al['documento'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="monto" class="form-label fw-semibold">Monto del Arancel (S/.) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" class="form-control" id="monto" name="monto" placeholder="Ej. 400.00" required>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha" class="form-label fw-semibold">Fecha de Registro</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label for="concepto" class="form-label fw-semibold">Concepto de Cobro <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="concepto" name="concepto" placeholder="Ej. Pensión de Junio, Taller de Ajedrez, Certificado de Estudios" required>
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label fw-semibold">Estado del Pago</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="Pendiente">Pendiente (Por cobrar)</option>
                            <option value="Pagado">Pagado (Al instante)</option>
                            <option value="Vencido">Vencido (Deuda vencida)</option>
                        </select>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">Generar Transacción</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
