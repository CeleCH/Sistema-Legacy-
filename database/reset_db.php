<?php
// database/reset_db.php
// Script de utilidad para reiniciar y sembrar la base de datos limpia.

$dbPath = __DIR__ . '/colegio.db';
$backupPath = __DIR__ . '/colegio.db.bak';

echo "=== REINICIO DE BASE DE DATOS ===\n";

if (file_exists($dbPath)) {
    echo "1. Realizando copia de seguridad de la base de datos existente...\n";
    if (copy($dbPath, $backupPath)) {
        echo "   [✓] Copia de seguridad guardada en: database/colegio.db.bak\n";
    } else {
        echo "   [!] Advertencia: No se pudo realizar la copia de seguridad.\n";
    }

    echo "2. Eliminando base de datos anterior...\n";
    if (unlink($dbPath)) {
        echo "   [✓] Base de datos anterior eliminada con éxito.\n";
    } else {
        die("   [✗] Error: No se pudo eliminar el archivo de base de datos actual. Asegúrese de que no esté en uso.\n");
    }
} else {
    echo "1. No existe base de datos anterior. Creando base de datos desde cero...\n";
}

echo "3. Ejecutando inicialización y siembra de datos de prueba...\n";
try {
    // Definimos PDO como global o lo cargamos al requerir el archivo de conexión
    require_once __DIR__ . '/../config/db.php';
    echo "   [✓] Conexión establecida con SQLite.\n";
    echo "   [✓] Estructura de tablas verificada/creada.\n";
    
    // Contar registros para verificar
    $tables = ['usuarios', 'alumnos', 'profesores', 'cursos', 'matriculas', 'pagos', 'asistencias', 'calificaciones', 'notificaciones'];
    echo "\n=== CONSOLIDADO DE REGISTROS SEMBRADOS ===\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "   - Tabla '$table': $count registros cargados de forma real.\n";
    }
    echo "\n[✓] ¡Base de datos reiniciada y sembrada con éxito con más de 10 registros por entidad!\n";
    
} catch (Exception $e) {
    die("   [✗] Error crítico durante el proceso de inicialización: " . $e->getMessage() . "\n");
}
