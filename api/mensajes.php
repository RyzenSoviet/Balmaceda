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

try {
    $pdo = obtenerConexionPDO();
    $purgados = purgarMensajesVencidos($pdo);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'ok'           => false,
        'db_conectada' => false,
        'mensaje'      => 'No fue posible conectar con MySQL. Verifica tu archivo .env y el servicio MySQL en DBeaver.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: Guardar nuevo mensaje desde cualquier formulario "Escríbenos"
if ($metodo === 'POST') {
    $entrada = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($entrada)) {
        $entrada = $_POST;
    }

    $nombre  = trim((string) ($entrada['nombre'] ?? ''));
    $correo  = trim((string) ($entrada['correo'] ?? ''));
    $origen  = trim((string) ($entrada['origen_pagina'] ?? 'Inicio'));
    $mensaje = trim((string) ($entrada['mensaje'] ?? ''));

    if ($nombre === '' || mb_strlen($nombre) > 120) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'mensaje' => 'Ingresa un nombre válido (máximo 120 caracteres).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 160) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'mensaje' => 'Ingresa un correo electrónico válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($mensaje === '' || mb_strlen($mensaje) > 4000) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'mensaje' => 'El mensaje no puede estar vacío (máximo 4000 caracteres).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $origen = mb_substr($origen !== '' ? $origen : 'Inicio', 0, 80);

    $stmt = $pdo->prepare(
        'INSERT INTO mensajes_contacto (nombre, correo, origen_pagina, mensaje)
         VALUES (:nombre, :correo, :origen, :mensaje)'
    );
    $stmt->execute([
        ':nombre'  => $nombre,
        ':correo'  => $correo,
        ':origen'  => $origen,
        ':mensaje' => $mensaje,
    ]);

    echo json_encode([
        'ok'           => true,
        'db_conectada' => true,
        'id'           => (int) $pdo->lastInsertId(),
        'mensaje'      => 'Tu mensaje fue enviado y registrado correctamente en la base de datos del colegio.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// A partir de aquí se requiere sesión de administrador
if (empty($_SESSION['admin_autenticado'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso restringido al modo administrador.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo === 'GET') {
    $stmt = $pdo->query(
        'SELECT
            id,
            nombre,
            correo,
            origen_pagina,
            mensaje,
            leido,
            creado_en,
            DATE_ADD(creado_en, INTERVAL 7 DAY) AS expira_en,
            GREATEST(0, TIMESTAMPDIFF(HOUR, NOW(), DATE_ADD(creado_en, INTERVAL 7 DAY))) AS horas_restantes
         FROM mensajes_contacto
         ORDER BY creado_en DESC, id DESC'
    );

    echo json_encode([
        'ok'           => true,
        'db_conectada' => true,
        'purgados'     => $purgados,
        'mensajes'     => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo === 'PATCH') {
    $entrada = json_decode((string) file_get_contents('php://input'), true);
    $id    = (int) ($entrada['id'] ?? 0);
    $leido = !empty($entrada['leido']) ? 1 : 0;

    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'mensaje' => 'ID de mensaje inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE mensajes_contacto SET leido = :leido WHERE id = :id');
    $stmt->execute([':leido' => $leido, ':id' => $id]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        $entrada = json_decode((string) file_get_contents('php://input'), true);
        $id = (int) ($entrada['id'] ?? 0);
    }

    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'mensaje' => 'ID de mensaje inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM mensajes_contacto WHERE id = :id');
    $stmt->execute([':id' => $id]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
