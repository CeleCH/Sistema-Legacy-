<?php
// includes/auth.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario ha iniciado sesión. Si no, lo redirige al login.
 */
function check_login() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Verifica si el usuario tiene uno de los roles permitidos.
 * Si no, muestra un mensaje de acceso denegado.
 * 
 * @param array $rolesPermitidos
 */
function check_role($rolesPermitidos) {
    check_login();
    if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos)) {
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Acceso Denegado</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body class='bg-light d-flex align-items-center justify-content-center vh-100'>
            <div class='card shadow-lg text-center p-5 border-0' style='max-width: 500px;'>
                <div class='text-danger mb-4'>
                    <svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' fill='currentColor' class='bi bi-exclamation-triangle-fill' viewBox='0 0 16 16'>
                      <path d='M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z'/>
                    </svg>
                </div>
                <h1 class='h3 text-dark fw-bold'>Acceso Denegado</h1>
                <p class='text-muted mt-3'>Lo sentimos, no tienes los permisos necesarios para acceder a este módulo. Tu rol actual es: <strong>" . htmlspecialchars($_SESSION['usuario_rol']) . "</strong>.</p>
                <div class='mt-4'>
                    <a href='../dashboard.php' class='btn btn-primary px-4'>Volver al Dashboard</a>
                    <a href='../logout.php' class='btn btn-outline-secondary px-4 ms-2'>Cerrar Sesión</a>
                </div>
            </div>
        </body>
        </html>";
        exit;
    }
}

/**
 * Retorna true si el usuario actual es de un rol específico.
 */
function has_role($rol) {
    return isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === $rol;
}
