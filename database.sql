-- ============================================================
-- SCRIPT DE BASE DE DATOS PARA SISTEMA DE GESTION DE BARBERIA
-- Base de Datos: `barberia`
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- ============================================================

DROP DATABASE IF EXISTS `barberia`;
CREATE DATABASE `barberia` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barberia`;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- --------------------------------------------------------
-- Tabla: `saas_plans` (Planes de Suscripción SaaS)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saas_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_barbers` INT NOT NULL DEFAULT 5,
  `features` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: `shops` (Barberías)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shops` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `owner_id` INT NULL,
  `plan_id` INT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`plan_id`) REFERENCES `saas_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: `users` (Usuarios / Super Admin / Admin Barbería / Barberos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `photo` VARCHAR(255) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin_barberia',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actualizamos clave foránea de shops a owner_id tras crear users
ALTER TABLE `shops` ADD CONSTRAINT `fk_shop_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------
-- Tabla: `catalog` (Servicios, Productos, Promos y Color)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `catalog` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `type` ENUM('service', 'product', 'promo', 'color') NOT NULL DEFAULT 'service',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: `appointments` (Turnos y Ventas de Mostrador)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NOT NULL,
  `barber_id` INT NOT NULL,
  `client_name` VARCHAR(150) NOT NULL,
  `client_phone` VARCHAR(50) NULL,
  `item_id` INT NOT NULL,
  `appointment_datetime` DATETIME NOT NULL,
  `payment_method` ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
  `observation` TEXT NULL,
  `status` ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
  `is_direct_sale` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_shop_barber_datetime` (`shop_id`, `barber_id`, `appointment_datetime`, `status`),
  KEY `idx_appointment_datetime` (`appointment_datetime`),
  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barber_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: `sales` (Ventas Consolidadas)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NOT NULL,
  `barber_id` INT NOT NULL,
  `appointment_id` INT NULL,
  `sale_date` DATETIME NOT NULL,
  `base_amount` DECIMAL(10,2) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `total_commission` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('efectivo', 'transferencia') NOT NULL DEFAULT 'efectivo',
  `is_direct_sale` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barber_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: `daily_closures` (Cierres de Caja Diarios)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_closures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NOT NULL,
  `barber_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `total_efectivo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_transferencia` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_ingresos` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_comisiones` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `neto_barberia` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_closure_per_barber_date` (`shop_id`, `barber_id`, `date`),
  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barber_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: `audit_logs` (Registro de Auditoría)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NULL,
  `user_id` INT NULL,
  `user_name` VARCHAR(150) NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: `barber_commissions` (Precios y Comisiones Personalizados por Barbero)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `barber_commissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NOT NULL,
  `barber_id` INT NOT NULL,
  `catalog_id` INT NOT NULL,
  `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `price` DECIMAL(10,2) NULL DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_barber_catalog` (`shop_id`, `barber_id`, `catalog_id`),
  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barber_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- DATOS SEMILLA DE PRUEBA (SEED DATA)
-- --------------------------------------------------------
-- Contraseña por defecto para todas las cuentas de prueba: 123456
-- Hash bcrypt generado para '123456': $2y$10$dLmvFK25Ngz2jUW6Lp7jAOUZk/YNq6LKy9CJ2ccVODXhA28RMQrbe

INSERT INTO `saas_plans` (`id`, `name`, `price`, `max_barbers`, `features`, `is_active`) VALUES
(1, 'Plan Básico SaaS', 15000.00, 3, 'Turnos, Caja, Catálogo', 1),
(2, 'Plan Pro Multi-Barbero', 28000.00, 10, 'Turnos, Caja, Exportación Excel, Múltiples Barberos', 1),
(3, 'Plan Enterprise La Mansión', 45000.00, 99, 'Ilimitado, Finanzas Avanzadas, Soporte VIP', 1);

INSERT INTO `shops` (`id`, `name`, `owner_id`, `plan_id`, `is_active`, `created_at`) VALUES
(1, 'Brotherhood Barbershop', NULL, 2, 1, NOW());

INSERT INTO `users` (`id`, `shop_id`, `name`, `email`, `photo`, `password`, `role`, `is_active`, `created_at`) VALUES
(1, NULL, 'Super Admin SaaS', 'admin@saasbarberia.com', 'uploads/barbers/carlos.png', '$2y$10$dLmvFK25Ngz2jUW6Lp7jAOUZk/YNq6LKy9CJ2ccVODXhA28RMQrbe', 'super_admin', 1, NOW()),
(2, 1, 'Carlos Admin Barbería', 'owner@barberia.com', 'uploads/barbers/carlos.png', '$2y$10$dLmvFK25Ngz2jUW6Lp7jAOUZk/YNq6LKy9CJ2ccVODXhA28RMQrbe', 'admin_barberia', 1, NOW()),
(3, 1, 'Franco Barber', 'franco@barberia.com', 'uploads/barbers/franco.png', '$2y$10$dLmvFK25Ngz2jUW6Lp7jAOUZk/YNq6LKy9CJ2ccVODXhA28RMQrbe', 'barbero', 1, NOW());

UPDATE `shops` SET `owner_id` = 2 WHERE `id` = 1;

INSERT INTO `catalog` (`shop_id`, `name`, `type`, `price`, `commission_percent`) VALUES
(1, 'Corte de Pelo Clásico', 'service', 5000.00, 50.00),
(1, 'Barba Completa', 'service', 3000.00, 40.00),
(1, 'Combo Corte + Barba', 'promo', 7000.00, 45.00),
(1, 'Pomada Modeladora Matificadora', 'product', 2500.00, 20.00),
(1, 'Tintura / Coloración Platinum', 'color', 8500.00, 50.00);

-- Credenciales demo guardadas:
-- Super Admin: admin@saasbarberia.com / 123456
-- Admin Shop:  owner@barberia.com     / 123456
-- Barbero:     franco@barberia.com    / 123456

