<?php
declare(strict_types=1);

/**
 * Script CLI para registrar o actualizar el usuario administrador en MySQL (DBeaver)
 * leyendo las credenciales únicamente desde variables de entorno o entrada interactiva.
 * Uso: php scripts/configurar_admin.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la línea de comandos.
");
}

require_once dirname(__DIR__) . '/config/conexion.php';

echo "=== Configuración de Administrador en MySQL (balmaceda_db) ===
";
$usuario = readline("Usuario administrador: ");
$clave   = readline("Contraseña administrador: ");

$usuario = trim((string) $usuario);
$clave   = (string) $clave;

if ($usuario === '' || $clave === '') {
    exit("Error: usuario y contraseña no pueden estar vacíos.
");
}

try {
    $pdo = obtenerConexionPDO();
    $hash = password_hash($clave, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO admin_usuarios (usuario, password_hash)
         VALUES (:usuario, :hash)
         ON DUPLICATE KEY UPDATE password_hash = :hash_upd'
    );
    $stmt->execute([
        ':usuario'  => $usuario,
        ':hash'     => $hash,
        ':hash_upd' => $hash,
    ]);

    echo "Listo: usuario '{$usuario}' sincronizado con hash seguro en admin_usuarios.
";
} catch (Throwable $e) {
    exit("Error de conexión MySQL: " . $e->getMessage() . "
");
}
