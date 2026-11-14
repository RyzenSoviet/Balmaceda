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
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'db_conectada' => false, 'videos' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo === 'GET') {
    $stmt = $pdo->query(
        'SELECT id, titulo, descripcion, url_instagram, shortcode, creado_en
         FROM instagram_videos
         WHERE activo = 1
         ORDER BY creado_en DESC, id DESC
         LIMIT 6'
    );
    echo json_encode([
        'ok'           => true,
        'db_conectada' => true,
        'videos'       => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_autenticado'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso restringido al modo administrador.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo === 'POST') {
    $entrada = json_decode((string) file_get_contents('php://input'), true);
    $titulo      = trim((string) ($entrada['titulo'] ?? ''));
    $descripcion = trim((string) ($entrada['descripcion'] ?? ''));
    $url         = trim((string) ($entrada['url_instagram'] ?? ''));

    if ($titulo === '' || $url === '' || !preg_match('#^https://(www\.)?instagram\.com/(reel|p|tv)/([A-Za-z0-9_-]+)#i', $url, $m)) {
        http_response_code(422);
        echo json_encode([
            'ok'      => false,
            'mensaje' => 'Ingresa un título y un enlace válido de Reel o publicación de Instagram (https://www.instagram.com/reel/...).',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tipo      = strtolower($m[2]);
    $shortcode = $m[3];
    $urlLimpia = "https://www.instagram.com/{$tipo}/{$shortcode}/";

    $stmt = $pdo->prepare(
        'INSERT INTO instagram_videos (titulo, descripcion, url_instagram, shortcode)
         VALUES (:titulo, :descripcion, :url, :shortcode)'
    );
    $stmt->execute([
        ':titulo'      => mb_substr($titulo, 0, 160),
        ':descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 280) : null,
        ':url'         => $urlLimpia,
        ':shortcode'   => $shortcode,
    ]);

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
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
        echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM instagram_videos WHERE id = :id');
    $stmt->execute([':id' => $id]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
