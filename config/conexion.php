<?php
declare(strict_types=1);

/**
 * Carga variables desde el archivo .env local (ignorado en Git) sin exponer credenciales.
 */
function cargarEntornoLocal(string $rutaEnv): void
{
    if (!is_file($rutaEnv) || !is_readable($rutaEnv)) {
        return;
    }

    $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        return;
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }

        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        if (
            (str_starts_with($valor, '"') && str_ends_with($valor, '"')) ||
            (str_starts_with($valor, "'") && str_ends_with($valor, "'"))
        ) {
            $valor = substr($valor, 1, -1);
        }

        if ($clave !== '' && getenv($clave) === false) {
            putenv("{$clave}={$valor}");
            $_ENV[$clave] = $valor;
        }
    }
}

cargarEntornoLocal(dirname(__DIR__) . '/.env');

/**
 * Obtiene una conexión PDO activa hacia MySQL (misma instancia administrada en DBeaver).
 *
 * @throws RuntimeException Si faltan credenciales o falla la conexión.
 */
function obtenerConexionPDO(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db   = getenv('DB_NAME') ?: 'balmaceda_db';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';

    if ($user === '') {
        throw new RuntimeException('Configuración de base de datos incompleta. Define DB_USER y DB_PASS en el archivo .env.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $opciones);
}

/**
 * Elimina mensajes con más de 7 días de antigüedad (respaldo del evento semanal MySQL).
 */
function purgarMensajesVencidos(PDO $pdo): int
{
    $stmt = $pdo->prepare('DELETE FROM mensajes_contacto WHERE creado_en < DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $stmt->execute();
    return $stmt->rowCount();
}

/**
 * Verifica credenciales del modo administrador usando hashes criptográficos (sin claves en texto plano).
 */
function verificarCredencialesAdmin(string $usuario, string $clave, ?PDO $pdo = null): bool
{
    $usuario = trim($usuario);
    if ($usuario === '' || $clave === '') {
        return false;
    }

    // 1. Verificar contra tabla admin_usuarios en MySQL si existe registro
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_usuarios WHERE usuario = :usuario LIMIT 1');
            $stmt->execute([':usuario' => $usuario]);
            $fila = $stmt->fetch();
            if ($fila && isset($fila['password_hash']) && password_verify($clave, $fila['password_hash'])) {
                $upd = $pdo->prepare('UPDATE admin_usuarios SET ultimo_acceso = NOW() WHERE id = :id');
                $upd->execute([':id' => $fila['id']]);
                return true;
            }
        } catch (Throwable $e) {
            // Continúa con validación por entorno/digest
        }
    }

    // 2. Verificar contra variables de entorno (.env) si fueron definidas
    $envUser = getenv('ADMIN_USER') ?: '';
    $envHash = getenv('ADMIN_PASS_HASH') ?: '';
    if ($envUser !== '' && $envHash !== '') {
        return hash_equals($envUser, $usuario) && password_verify($clave, $envHash);
    }

    return false;
}
