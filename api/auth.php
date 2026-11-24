<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

require_once dirname(__DIR__) . '/config/conexion.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $dbConectada = false;
    try {
        obtenerConexionPDO();
        $dbConectada = true;
    } catch (Throwable $e) {
        $dbConectada = false;
    }

    echo json_encode([
        'ok'           => true,
        'autenticado'  => !empty($_SESSION['admin_autenticado']),
        'usuario'      => $_SESSION['admin_usuario'] ?? null,
        'db_conectada' => $dbConectada,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo === 'POST') {
    $entrada = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($entrada)) {
        $entrada = $_POST;
    }

    $accion = (string) ($entrada['accion'] ?? 'login');

    if ($accion === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        echo json_encode(['ok' => true, 'autenticado' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $usuario = trim((string) ($entrada['usuario'] ?? ''));
    $clave   = (string) ($entrada['clave'] ?? '');

    $pdo = null;
    $dbConectada = false;
    try {
        $pdo = obtenerConexionPDO();
        $dbConectada = true;
    } catch (Throwable $e) {
        $pdo = null;
    }

    if (verificarCredencialesAdmin($usuario, $clave, $pdo)) {
        session_regenerate_id(true);
        $_SESSION['admin_autenticado'] = true;
        $_SESSION['admin_usuario']     = $usuario;

        echo json_encode([
            'ok'           => true,
            'autenticado'  => true,
            'usuario'      => $usuario,
            'db_conectada' => $dbConectada,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(401);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Credenciales inválidas. Verifica usuario y contraseña.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
