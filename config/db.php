<?php
// config/db.php

$dbPath = __DIR__ . '/../database/colegio.db';
$dbDir = dirname($dbPath);

if (!file_exists($dbDir)) {
    mkdir($dbDir, 0777, true);
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Habilitar claves foráneas
    $pdo->exec("PRAGMA foreign_keys = ON;");
    
    // Crear tablas si no existen
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            nombre TEXT NOT NULL,
            email TEXT,
            rol TEXT NOT NULL CHECK(rol IN ('Director', 'Administrador', 'Docente', 'Alumno', 'Padre de familia'))
        );

        CREATE TABLE IF NOT EXISTS alumnos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER UNIQUE,
            nombre TEXT NOT NULL,
            apellido TEXT NOT NULL,
            documento TEXT UNIQUE NOT NULL,
            fecha_nacimiento TEXT,
            direccion TEXT,
            telefono TEXT,
            estado_academico TEXT DEFAULT 'Regular' CHECK(estado_academico IN ('Regular', 'Condicional', 'Suspendido', 'Egresado')),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS profesores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER UNIQUE,
            nombre TEXT NOT NULL,
            apellido TEXT NOT NULL,
            especialidad TEXT,
            telefono TEXT,
            email TEXT,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            codigo TEXT UNIQUE NOT NULL,
            descripcion TEXT,
            profesor_id INTEGER,
            horario TEXT,
            FOREIGN KEY (profesor_id) REFERENCES profesores(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS matriculas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            alumno_id INTEGER NOT NULL,
            fecha TEXT NOT NULL,
            estado TEXT DEFAULT 'Pendiente' CHECK(estado IN ('Activa', 'Inactiva', 'Pendiente')),
            FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS pagos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            alumno_id INTEGER NOT NULL,
            monto REAL NOT NULL,
            fecha TEXT NOT NULL,
            concepto TEXT NOT NULL,
            estado TEXT DEFAULT 'Pendiente' CHECK(estado IN ('Pagado', 'Pendiente', 'Vencido')),
            FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS asistencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            alumno_id INTEGER NOT NULL,
            curso_id INTEGER NOT NULL,
            fecha TEXT NOT NULL,
            estado TEXT NOT NULL CHECK(estado IN ('Presente', 'Falta', 'Tardanza', 'Justificada')),
            FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
            FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
            UNIQUE(alumno_id, curso_id, fecha)
        );

        CREATE TABLE IF NOT EXISTS calificaciones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            alumno_id INTEGER NOT NULL,
            curso_id INTEGER NOT NULL,
            nota REAL NOT NULL CHECK(nota >= 0 AND nota <= 20),
            fecha TEXT NOT NULL,
            observaciones TEXT,
            FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
            FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS notificaciones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            titulo TEXT NOT NULL,
            mensaje TEXT NOT NULL,
            fecha TEXT NOT NULL,
            leido INTEGER DEFAULT 0 CHECK(leido IN (0, 1)),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        );
    ");

    // Verificar si la base de datos está vacía para sembrar datos
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $userCount = $stmt->fetchColumn();

    if ($userCount == 0) {
        // Encriptar contraseñas
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);

        // Insertar Usuarios Semilla (Total: 28 usuarios reales y consistentes)
        $usuarios = [
            ['username' => 'director', 'password' => $passwordHash, 'nombre' => 'Dr. Carlos Mendoza', 'email' => 'carlos.mendoza@colegio.edu.pe', 'rol' => 'Director'],
            ['username' => 'admin', 'password' => $passwordHash, 'nombre' => 'Ing. Sofía Reyes', 'email' => 'sofia.reyes@colegio.edu.pe', 'rol' => 'Administrador'],
            ['username' => 'admin2', 'password' => $passwordHash, 'nombre' => 'Srta. Beatriz Solís', 'email' => 'beatriz.solis@colegio.edu.pe', 'rol' => 'Administrador'],
            
            // 10 Docentes
            ['username' => 'profesor1', 'password' => $passwordHash, 'nombre' => 'Prof. Ana María Gómez', 'email' => 'ana.gomez@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor2', 'password' => $passwordHash, 'nombre' => 'Prof. Luis Alberto Flores', 'email' => 'luis.flores@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor3', 'password' => $passwordHash, 'nombre' => 'Prof. Carlos Manuel Torres', 'email' => 'carlos.torres@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor4', 'password' => $passwordHash, 'nombre' => 'Prof. María Elena Rojas', 'email' => 'maria.rojas@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor5', 'password' => $passwordHash, 'nombre' => 'Prof. Patricia Luján Silva', 'email' => 'patricia.silva@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor6', 'password' => $passwordHash, 'nombre' => 'Prof. Jorge Eduardo Díaz', 'email' => 'jorge.diaz@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor7', 'password' => $passwordHash, 'nombre' => 'Prof. Carmen Rosa Vargas', 'email' => 'carmen.vargas@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor8', 'password' => $passwordHash, 'nombre' => 'Prof. Roberto Carlos Mendoza', 'email' => 'roberto.mendoza@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor9', 'password' => $passwordHash, 'nombre' => 'Prof. Teresa de Jesús Herrera', 'email' => 'teresa.herrera@colegio.edu.pe', 'rol' => 'Docente'],
            ['username' => 'profesor10', 'password' => $passwordHash, 'nombre' => 'Prof. Gabriel Francisco Ruiz', 'email' => 'gabriel.ruiz@colegio.edu.pe', 'rol' => 'Docente'],
            
            // 10 Alumnos
            ['username' => 'alumno1', 'password' => $passwordHash, 'nombre' => 'Diego Alejandro Castro', 'email' => 'diego.castro@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno2', 'password' => $passwordHash, 'nombre' => 'Sofia Valentina Rojas', 'email' => 'sofia.rojas@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno3', 'password' => $passwordHash, 'nombre' => 'Mateo Nicolas Silva', 'email' => 'mateo.silva@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno4', 'password' => $passwordHash, 'nombre' => 'Valeria Alejandra Mendoza', 'email' => 'valeria.mendoza@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno5', 'password' => $passwordHash, 'nombre' => 'Lucas Daniel Peralta', 'email' => 'lucas.peralta@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno6', 'password' => $passwordHash, 'nombre' => 'Camila Isabel Herrera', 'email' => 'camila.herrera@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno7', 'password' => $passwordHash, 'nombre' => 'Benjamín Ignacio Castro', 'email' => 'benjamin.castro@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno8', 'password' => $passwordHash, 'nombre' => 'Isabella Sofia Romero', 'email' => 'isabella.romero@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno9', 'password' => $passwordHash, 'nombre' => 'Thiago Alonso Díaz', 'email' => 'thiago.diaz@colegio.edu.pe', 'rol' => 'Alumno'],
            ['username' => 'alumno10', 'password' => $passwordHash, 'nombre' => 'Emma Valentina Ruiz', 'email' => 'emma.ruiz@colegio.edu.pe', 'rol' => 'Alumno'],
            
            // 5 Padres de Familia
            ['username' => 'padre1', 'password' => $passwordHash, 'nombre' => 'Roberto Castro Díaz', 'email' => 'roberto.castro@mail.com', 'rol' => 'Padre de familia'],
            ['username' => 'padre2', 'password' => $passwordHash, 'nombre' => 'Elena Silva Paredes', 'email' => 'elena.silva@mail.com', 'rol' => 'Padre de familia'],
            ['username' => 'padre3', 'password' => $passwordHash, 'nombre' => 'Alejandro Herrera Ruiz', 'email' => 'alejandro.herrera@mail.com', 'rol' => 'Padre de familia'],
            ['username' => 'padre4', 'password' => $passwordHash, 'nombre' => 'Carmen Romero Paz', 'email' => 'carmen.romero@mail.com', 'rol' => 'Padre de familia'],
            ['username' => 'padre5', 'password' => $passwordHash, 'nombre' => 'Miguel Díaz Salazar', 'email' => 'miguel.diaz@mail.com', 'rol' => 'Padre de familia'],
        ];

        $stmtUser = $pdo->prepare("INSERT INTO usuarios (username, password, nombre, email, rol) VALUES (:username, :password, :nombre, :email, :rol)");
        foreach ($usuarios as $u) {
            $stmtUser->execute($u);
        }

        // Obtener ids de usuarios de manera dinámica para referenciar relaciones
        $stmtId = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");

        // 1. Insertar Profesores (Exactamente 10)
        $profesoresData = [
            ['username' => 'profesor1', 'nombre' => 'Ana Maria', 'apellido' => 'Gomez', 'especialidad' => 'Matemáticas y Física', 'telefono' => '987654321', 'email' => 'ana.gomez@colegio.edu.pe'],
            ['username' => 'profesor2', 'nombre' => 'Luis Alberto', 'apellido' => 'Flores', 'especialidad' => 'Comunicación e Idiomas', 'telefono' => '912345678', 'email' => 'luis.flores@colegio.edu.pe'],
            ['username' => 'profesor3', 'nombre' => 'Carlos Manuel', 'apellido' => 'Torres', 'especialidad' => 'Ciencias Naturales', 'telefono' => '922111333', 'email' => 'carlos.torres@colegio.edu.pe'],
            ['username' => 'profesor4', 'nombre' => 'María Elena', 'apellido' => 'Rojas', 'especialidad' => 'Historia y Geografía', 'telefono' => '933222444', 'email' => 'maria.rojas@colegio.edu.pe'],
            ['username' => 'profesor5', 'nombre' => 'Patricia Luján', 'apellido' => 'Silva', 'especialidad' => 'Arte y Cultura', 'telefono' => '944333555', 'email' => 'patricia.silva@colegio.edu.pe'],
            ['username' => 'profesor6', 'nombre' => 'Jorge Eduardo', 'apellido' => 'Díaz', 'especialidad' => 'Educación Física', 'telefono' => '955444666', 'email' => 'jorge.diaz@colegio.edu.pe'],
            ['username' => 'profesor7', 'nombre' => 'Carmen Rosa', 'apellido' => 'Vargas', 'especialidad' => 'Computación e Informática', 'telefono' => '966555777', 'email' => 'carmen.vargas@colegio.edu.pe'],
            ['username' => 'profesor8', 'nombre' => 'Roberto Carlos', 'apellido' => 'Mendoza', 'especialidad' => 'Inglés', 'telefono' => '977666888', 'email' => 'roberto.mendoza@colegio.edu.pe'],
            ['username' => 'profesor9', 'nombre' => 'Teresa de Jesús', 'apellido' => 'Herrera', 'especialidad' => 'Educación Cívica', 'telefono' => '988777999', 'email' => 'teresa.herrera@colegio.edu.pe'],
            ['username' => 'profesor10', 'nombre' => 'Gabriel Francisco', 'apellido' => 'Ruiz', 'especialidad' => 'Tutoría y Orientación', 'telefono' => '999888000', 'email' => 'gabriel.ruiz@colegio.edu.pe'],
        ];

        $stmtProf = $pdo->prepare("INSERT INTO profesores (usuario_id, nombre, apellido, especialidad, telefono, email) VALUES (?, ?, ?, ?, ?, ?)");
        $profIds = []; // mapa de username -> profesor_id
        foreach ($profesoresData as $p) {
            $stmtId->execute([$p['username']]);
            $u_id = $stmtId->fetchColumn();
            
            $stmtProf->execute([$u_id, $p['nombre'], $p['apellido'], $p['especialidad'], $p['telefono'], $p['email']]);
            $profIds[$p['username']] = $pdo->lastInsertId();
        }

        // 2. Insertar Alumnos (Exactamente 10)
        $alumnosData = [
            ['username' => 'alumno1', 'nombre' => 'Diego Alejandro', 'apellido' => 'Castro', 'documento' => '72134567', 'fecha_nacimiento' => '2010-04-15', 'direccion' => 'Av. Larco 456, Miraflores', 'telefono' => '999888777', 'estado_academico' => 'Regular'],
            ['username' => 'alumno2', 'nombre' => 'Sofia Valentina', 'apellido' => 'Rojas', 'documento' => '78945612', 'fecha_nacimiento' => '2011-08-22', 'direccion' => 'Calle Los Pinos 123, San Isidro', 'telefono' => '944555666', 'estado_academico' => 'Regular'],
            ['username' => 'alumno3', 'nombre' => 'Mateo Nicolas', 'apellido' => 'Silva', 'documento' => '71234568', 'fecha_nacimiento' => '2010-11-05', 'direccion' => 'Av. Javier Prado 2040, San Borja', 'telefono' => '933222111', 'estado_academico' => 'Regular'],
            ['username' => 'alumno4', 'nombre' => 'Valeria Alejandra', 'apellido' => 'Mendoza', 'documento' => '73456789', 'fecha_nacimiento' => '2012-01-30', 'direccion' => 'Calle Cantuarias 482, Miraflores', 'telefono' => '955666777', 'estado_academico' => 'Regular'],
            ['username' => 'alumno5', 'nombre' => 'Lucas Daniel', 'apellido' => 'Peralta', 'documento' => '74567890', 'fecha_nacimiento' => '2011-05-14', 'direccion' => 'Av. Brasil 1230, Jesús María', 'telefono' => '966777888', 'estado_academico' => 'Condicional'],
            ['username' => 'alumno6', 'nombre' => 'Camila Isabel', 'apellido' => 'Herrera', 'documento' => '75678901', 'fecha_nacimiento' => '2010-09-09', 'direccion' => 'Jr. Carabaya 831, Cercado de Lima', 'telefono' => '977888999', 'estado_academico' => 'Regular'],
            ['username' => 'alumno7', 'nombre' => 'Benjamín Ignacio', 'apellido' => 'Castro', 'documento' => '76789012', 'fecha_nacimiento' => '2012-07-19', 'direccion' => 'Av. Arequipa 3420, San Isidro', 'telefono' => '988999000', 'estado_academico' => 'Regular'],
            ['username' => 'alumno8', 'nombre' => 'Isabella Sofia', 'apellido' => 'Romero', 'documento' => '77890123', 'fecha_nacimiento' => '2011-03-25', 'direccion' => 'Calle Tarapacá 140, Magdalena', 'telefono' => '911222333', 'estado_academico' => 'Regular'],
            ['username' => 'alumno9', 'nombre' => 'Thiago Alonso', 'apellido' => 'Díaz', 'documento' => '78901234', 'fecha_nacimiento' => '2010-12-12', 'direccion' => 'Jr. Bolognesi 560, San Miguel', 'telefono' => '922333444', 'estado_academico' => 'Regular'],
            ['username' => 'alumno10', 'nombre' => 'Emma Valentina', 'apellido' => 'Ruiz', 'documento' => '79012345', 'fecha_nacimiento' => '2012-06-03', 'direccion' => 'Av. La Marina 2890, San Miguel', 'telefono' => '933444555', 'estado_academico' => 'Suspendido'],
        ];

        $stmtAlum = $pdo->prepare("INSERT INTO alumnos (usuario_id, nombre, apellido, documento, fecha_nacimiento, direccion, telefono, estado_academico) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $alumnoIds = []; // mapa de username -> alumno_id
        foreach ($alumnosData as $a) {
            $stmtId->execute([$a['username']]);
            $u_id = $stmtId->fetchColumn();
            
            $stmtAlum->execute([$u_id, $a['nombre'], $a['apellido'], $a['documento'], $a['fecha_nacimiento'], $a['direccion'], $a['telefono'], $a['estado_academico']]);
            $alumnoIds[$a['username']] = $pdo->lastInsertId();
        }

        // 3. Insertar Cursos (Exactamente 10)
        $cursosData = [
            ['nombre' => 'Álgebra y Aritmética', 'codigo' => 'MAT-101', 'descripcion' => 'Curso de matemáticas avanzadas para secundaria', 'prof_username' => 'profesor1', 'horario' => 'Lunes y Miércoles 08:00 - 10:00'],
            ['nombre' => 'Física Elemental', 'codigo' => 'FIS-201', 'descripcion' => 'Introducción a las leyes físicas y cinemática', 'prof_username' => 'profesor1', 'horario' => 'Martes y Jueves 10:30 - 12:30'],
            ['nombre' => 'Lenguaje y Literatura', 'codigo' => 'LEN-101', 'descripcion' => 'Comprensión lectora, gramática y redacción', 'prof_username' => 'profesor2', 'horario' => 'Lunes y Viernes 10:30 - 12:30'],
            ['nombre' => 'Química Orgánica', 'codigo' => 'QUI-301', 'descripcion' => 'Estudio de los compuestos del carbono y laboratorio', 'prof_username' => 'profesor3', 'horario' => 'Miércoles y Viernes 10:30 - 12:30'],
            ['nombre' => 'Anatomía Humana', 'codigo' => 'BIO-201', 'descripcion' => 'Estructura, órganos y funcionamiento del cuerpo', 'prof_username' => 'profesor3', 'horario' => 'Martes 08:00 - 10:00'],
            ['nombre' => 'Historia del Perú', 'codigo' => 'HIS-101', 'descripcion' => 'Desde las culturas pre-incas hasta la época republicana', 'prof_username' => 'profesor4', 'horario' => 'Jueves 08:00 - 10:00'],
            ['nombre' => 'Geografía Mundial', 'codigo' => 'GEO-101', 'descripcion' => 'Estudio de los continentes, climas y ecosistemas', 'prof_username' => 'profesor4', 'horario' => 'Viernes 08:00 - 10:00'],
            ['nombre' => 'Dibujo y Pintura', 'codigo' => 'ART-101', 'descripcion' => 'Técnicas artísticas clásicas, teoría del color y dibujo', 'prof_username' => 'profesor5', 'horario' => 'Miércoles 14:00 - 16:00'],
            ['nombre' => 'Entrenamiento Deportivo', 'codigo' => 'EF-101', 'descripcion' => 'Desarrollo de capacidades físicas y deportes colectivos', 'prof_username' => 'profesor6', 'horario' => 'Jueves 14:00 - 16:00'],
            ['nombre' => 'Programación Básica', 'codigo' => 'COMP-101', 'descripcion' => 'Introducción a la lógica de programación en Python', 'prof_username' => 'profesor7', 'horario' => 'Lunes 14:00 - 16:00'],
        ];

        $stmtCurso = $pdo->prepare("INSERT INTO cursos (nombre, codigo, descripcion, profesor_id, horario) VALUES (?, ?, ?, ?, ?)");
        $cursoIds = []; // mapa de codigo -> curso_id
        foreach ($cursosData as $c) {
            $prof_id = $profIds[$c['prof_username']] ?? null;
            $stmtCurso->execute([$c['nombre'], $c['codigo'], $c['descripcion'], $prof_id, $c['horario']]);
            $cursoIds[$c['codigo']] = $pdo->lastInsertId();
        }

        // 4. Insertar Matrículas (Exactamente 10)
        $stmtMat = $pdo->prepare("INSERT INTO matriculas (alumno_id, fecha, estado) VALUES (?, ?, ?)");
        $matriculasData = [
            ['alumno' => 'alumno1', 'estado' => 'Activa'],
            ['alumno' => 'alumno2', 'estado' => 'Activa'],
            ['alumno' => 'alumno3', 'estado' => 'Activa'],
            ['alumno' => 'alumno4', 'estado' => 'Activa'],
            ['alumno' => 'alumno5', 'estado' => 'Activa'],
            ['alumno' => 'alumno6', 'estado' => 'Activa'],
            ['alumno' => 'alumno7', 'estado' => 'Activa'],
            ['alumno' => 'alumno8', 'estado' => 'Activa'],
            ['alumno' => 'alumno9', 'estado' => 'Pendiente'],
            ['alumno' => 'alumno10', 'estado' => 'Inactiva'],
        ];
        foreach ($matriculasData as $m) {
            $al_id = $alumnoIds[$m['alumno']];
            $stmtMat->execute([$al_id, date('Y-m-d', strtotime('-15 days')), $m['estado']]);
        }

        // 5. Insertar Pagos (Total: 15 pagos realistas)
        $stmtPago = $pdo->prepare("INSERT INTO pagos (alumno_id, monto, fecha, concepto, estado) VALUES (?, ?, ?, ?, ?)");
        $pagosData = [
            ['alumno' => 'alumno1', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno1', 'monto' => 400.00, 'fecha' => date('Y-m-d'), 'concepto' => 'Pensión Mayo 2026', 'estado' => 'Pendiente'],
            ['alumno' => 'alumno2', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno2', 'monto' => 400.00, 'fecha' => date('Y-m-d', strtotime('-1 days')), 'concepto' => 'Pensión Mayo 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno3', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno3', 'monto' => 400.00, 'fecha' => date('Y-m-d'), 'concepto' => 'Pensión Mayo 2026', 'estado' => 'Pendiente'],
            ['alumno' => 'alumno4', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno4', 'monto' => 400.00, 'fecha' => date('Y-m-d', strtotime('-10 days')), 'concepto' => 'Pensión Mayo 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno5', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno5', 'monto' => 400.00, 'fecha' => date('Y-m-d', strtotime('-20 days')), 'concepto' => 'Pensión Mayo 2026', 'estado' => 'Vencido'],
            ['alumno' => 'alumno6', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno6', 'monto' => 400.00, 'fecha' => date('Y-m-d'), 'concepto' => 'Pensión Mayo 2026', 'estado' => 'Pendiente'],
            ['alumno' => 'alumno7', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno8', 'monto' => 350.00, 'fecha' => date('Y-m-d', strtotime('-15 days')), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pagado'],
            ['alumno' => 'alumno9', 'monto' => 350.00, 'fecha' => date('Y-m-d'), 'concepto' => 'Matrícula Anual 2026', 'estado' => 'Pendiente'],
        ];
        foreach ($pagosData as $p) {
            $al_id = $alumnoIds[$p['alumno']];
            $stmtPago->execute([$al_id, $p['monto'], $p['fecha'], $p['concepto'], $p['estado']]);
        }

        // 6. Insertar Asistencias (Total: 88 registros históricos para nutrir el gráfico)
        $stmtAsist = $pdo->prepare("INSERT INTO asistencias (alumno_id, curso_id, fecha, estado) VALUES (?, ?, ?, ?)");
        $fechas_asistencia = [
            date('Y-m-d', strtotime('-3 days')),
            date('Y-m-d', strtotime('-2 days')),
            date('Y-m-d', strtotime('-1 days')),
        ];
        $cursos_asis = ['MAT-101', 'FIS-201', 'LEN-101'];
        $alumnos_asis = ['alumno1', 'alumno2', 'alumno3', 'alumno4', 'alumno5', 'alumno6', 'alumno7', 'alumno8'];

        $estados_posibles = ['Presente', 'Presente', 'Presente', 'Presente', 'Tardanza', 'Falta', 'Justificada'];

        foreach ($fechas_asistencia as $f) {
            foreach ($cursos_asis as $cod) {
                $c_id = $cursoIds[$cod];
                foreach ($alumnos_asis as $a_uname) {
                    $al_id = $alumnoIds[$a_uname];
                    $estado = $estados_posibles[array_rand($estados_posibles)];
                    try {
                        $stmtAsist->execute([$al_id, $c_id, $f, $estado]);
                    } catch (PDOException $e) {
                        // Ignorar duplicados silenciosamente
                    }
                }
            }
        }
        // Registrar asistencia del día de hoy
        foreach ($alumnos_asis as $a_uname) {
            try {
                $stmtAsist->execute([$alumnoIds[$a_uname], $cursoIds['MAT-101'], date('Y-m-d'), 'Presente']);
            } catch (PDOException $e) {}
            try {
                $stmtAsist->execute([$alumnoIds[$a_uname], $cursoIds['LEN-101'], date('Y-m-d'), $a_uname === 'alumno1' ? 'Falta' : 'Presente']);
            } catch (PDOException $e) {}
        }

        // 7. Insertar Calificaciones (Total: 16 calificaciones realistas)
        $stmtCal = $pdo->prepare("INSERT INTO calificaciones (alumno_id, curso_id, nota, fecha, observaciones) VALUES (?, ?, ?, ?, ?)");
        $califsData = [
            ['alumno' => 'alumno1', 'curso' => 'MAT-101', 'nota' => 16.5, 'obs' => 'Excelente participación en clase'],
            ['alumno' => 'alumno1', 'curso' => 'FIS-201', 'nota' => 14.0, 'obs' => 'Buen examen parcial de cinemática'],
            ['alumno' => 'alumno1', 'curso' => 'LEN-101', 'nota' => 12.0, 'obs' => 'Debe mejorar en ortografía'],
            ['alumno' => 'alumno2', 'curso' => 'MAT-101', 'nota' => 18.0, 'obs' => 'Trabajo impecable y muy ordenado'],
            ['alumno' => 'alumno2', 'curso' => 'FIS-201', 'nota' => 11.5, 'obs' => 'Recomiendo repasar ejercicios de vectores'],
            ['alumno' => 'alumno2', 'curso' => 'LEN-101', 'nota' => 17.5, 'obs' => 'Excelente comprensión lectora y redacción'],
            ['alumno' => 'alumno3', 'curso' => 'MAT-101', 'nota' => 13.0, 'obs' => 'Esfuerzo constante, buen progreso'],
            ['alumno' => 'alumno3', 'curso' => 'QUI-301', 'nota' => 15.5, 'obs' => 'Muy buen desempeño en el laboratorio de química'],
            ['alumno' => 'alumno4', 'curso' => 'MAT-101', 'nota' => 19.5, 'obs' => 'Brillante resolución de problemas complejos'],
            ['alumno' => 'alumno4', 'curso' => 'HIS-101', 'nota' => 16.0, 'obs' => 'Buen análisis histórico de la independencia'],
            ['alumno' => 'alumno5', 'curso' => 'QUI-301', 'nota' => 10.5, 'obs' => 'Debe repasar formulación inorgánica básica'],
            ['alumno' => 'alumno5', 'curso' => 'BIO-201', 'nota' => 11.0, 'obs' => 'Examen aprobado con el puntaje mínimo'],
            ['alumno' => 'alumno6', 'curso' => 'LEN-101', 'nota' => 15.0, 'obs' => 'Buen dominio del vocabulario y gramática'],
            ['alumno' => 'alumno6', 'curso' => 'HIS-101', 'nota' => 14.5, 'obs' => 'Participación activa en el debate grupal'],
            ['alumno' => 'alumno7', 'curso' => 'MAT-101', 'nota' => 12.5, 'obs' => 'Demuestra empeño, seguir practicando'],
            ['alumno' => 'alumno8', 'curso' => 'LEN-101', 'nota' => 16.0, 'obs' => 'Muy buena ortografía y fluidez al exponer'],
        ];
        foreach ($califsData as $c) {
            $al_id = $alumnoIds[$c['alumno']];
            $c_id = $cursoIds[$c['curso']];
            $stmtCal->execute([$al_id, $c_id, $c['nota'], date('Y-m-d', strtotime('-5 days')), $c['obs']]);
        }

        // 8. Insertar Notificaciones (Total: 15 notificaciones para todos los roles)
        $stmtNotif = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, fecha, leido) VALUES (?, ?, ?, ?, ?)");
        $notificacionesData = [
            ['username' => 'alumno1', 'titulo' => 'Inasistencia Registrada', 'mensaje' => 'Se ha registrado una falta en el curso de Lenguaje y Literatura el día de hoy.', 'leido' => 0],
            ['username' => 'alumno1', 'titulo' => 'Pago Pendiente de Mayo', 'mensaje' => 'Recuerde que tiene una pensión pendiente de pago por un monto de S/. 400.00.', 'leido' => 0],
            ['username' => 'alumno2', 'titulo' => 'Felicitaciones Académicas', 'mensaje' => 'Has obtenido una excelente calificación de 18.0 en Álgebra y Aritmética. ¡Sigue así!', 'leido' => 0],
            ['username' => 'alumno3', 'titulo' => 'Examen Bimestral Próximo', 'mensaje' => 'El examen bimestral de Álgebra y Aritmética se realizará el próximo lunes presencialmente.', 'leido' => 1],
            ['username' => 'alumno4', 'titulo' => 'Felicitaciones Académicas', 'mensaje' => 'Tu nota en Álgebra (19.5) califica como la más alta de la sección. ¡Excelente!', 'leido' => 0],
            ['username' => 'alumno5', 'titulo' => 'Pensión Vencida - Alerta', 'mensaje' => 'Tiene una pensión vencida de S/. 400.00 correspondiente al mes anterior. Por favor, regularizar.', 'leido' => 0],
            ['username' => 'alumno5', 'titulo' => 'Citación a Tutoría', 'mensaje' => 'Por favor, acercase a la oficina de tutoría con el Prof. Gabriel Ruiz mañana a las 11:00 am.', 'leido' => 0],
            ['username' => 'profesor1', 'titulo' => 'Reunión de Docentes', 'mensaje' => 'Reunión general de coordinación pedagógica este viernes a las 3:00 pm en la sala de juntas.', 'leido' => 0],
            ['username' => 'profesor2', 'titulo' => 'Entrega de Registros', 'mensaje' => 'Se recuerda que el plazo máximo para la entrega de notas bimestrales vence este viernes.', 'leido' => 0],
            ['username' => 'director', 'titulo' => 'Reporte Mensual de Caja', 'mensaje' => 'El reporte consolidado de ingresos y pensiones del mes de mayo ya está disponible.', 'leido' => 0],
            ['username' => 'director', 'titulo' => 'Solicitud de Licencia', 'mensaje' => 'La profesora Ana María Gómez ha registrado una solicitud de licencia médica para el próximo mes.', 'leido' => 1],
            ['username' => 'admin', 'titulo' => 'Respaldo del Sistema', 'mensaje' => 'El respaldo semanal de la base de datos SQLite se ha completado con éxito y se encuentra en la nube.', 'leido' => 1],
            ['username' => 'padre1', 'titulo' => 'Boleta de Notas Disponible', 'mensaje' => 'La boleta del primer bimestre de su hijo Diego Alejandro Castro ya se encuentra disponible en el portal académico.', 'leido' => 0],
            ['username' => 'padre2', 'titulo' => 'Reporte de Asistencia', 'mensaje' => 'Su hija Sofía Valentina Rojas registró una tardanza el día de ayer en Física Elemental.', 'leido' => 0],
            ['username' => 'padre3', 'titulo' => 'Pago Recibido', 'mensaje' => 'Hemos registrado con éxito su pago de S/. 350.00 por concepto de matrícula anual 2026.', 'leido' => 1],
        ];

        foreach ($notificacionesData as $n) {
            $stmtId->execute([$n['username']]);
            $u_id = $stmtId->fetchColumn();
            if ($u_id) {
                $stmtNotif->execute([$u_id, $n['titulo'], $n['mensaje'], date('Y-m-d H:i:s'), $n['leido']]);
            }
        }
    }

} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
