-- ============================================================================
-- Base de datos: Colegio José Manuel Balmaceda · San Javier
-- Compatible con MySQL 8.0+ / MariaDB 10.5+ y DBeaver
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `balmaceda_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `balmaceda_db`;

-- ----------------------------------------------------------------------------
-- Tabla de mensajes enviados desde el formulario "Escríbenos"
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajes_contacto` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `correo` VARCHAR(160) NOT NULL,
  `origen_pagina` VARCHAR(80) NOT NULL DEFAULT 'Inicio',
  `mensaje` TEXT NOT NULL,
  `leido` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_mensajes_creado_en` (`creado_en`),
  INDEX `idx_mensajes_leido` (`leido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tabla de videos / reels recientes de Instagram (@balmaceda403san_javier)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `instagram_videos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(160) NOT NULL,
  `descripcion` VARCHAR(280) DEFAULT NULL,
  `url_instagram` VARCHAR(255) NOT NULL,
  `shortcode` VARCHAR(64) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ig_activo_fecha` (`activo`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tabla de usuarios administradores (almacena únicamente hashes seguros)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nombre_completo` VARCHAR(120) NOT NULL DEFAULT 'Administración Institucional',
  `ultimo_acceso` DATETIME DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Limpieza automática semanal (7 días) para evitar saturación de mensajes
-- Nota: Si tu servidor MySQL tiene el planificador desactivado, ejecuta:
--       SET GLOBAL event_scheduler = ON;
-- ----------------------------------------------------------------------------
DROP EVENT IF EXISTS `ev_eliminar_mensajes_semanales`;

DELIMITER $$
CREATE EVENT `ev_eliminar_mensajes_semanales`
  ON SCHEDULE EVERY 1 DAY
  STARTS CURRENT_TIMESTAMP
  COMMENT 'Elimina automáticamente los mensajes de contacto con más de 7 días de antigüedad'
DO
BEGIN
  DELETE FROM `mensajes_contacto`
  WHERE `creado_en` < DATE_SUB(NOW(), INTERVAL 7 DAY);
END$$
DELIMITER ;
