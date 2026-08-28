<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de la Base de Datos PDO MySQL
$host     = 'localhost';
$dbname   = 'barberia';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");
    // Auto-migraciones silenciosas (se ejecutan solo una vez para evitar overhead DDL en cada petición)
    $migrationLockFile = __DIR__ . '/.migrated.lock';
    if (!file_exists($migrationLockFile)) {
        try {
            $pdo->exec("UPDATE shops SET name = 'Brotherhood Barbershop'");
        } catch (Exception $ex) {}

        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'client_phone'");
            if ($checkCol->rowCount() === 0) {
                $pdo->exec("ALTER TABLE appointments ADD COLUMN client_phone VARCHAR(50) NULL AFTER client_name");
            }
        } catch (Exception $ex) {}

        try {
            $checkPhotoCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo'");
            if ($checkPhotoCol->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL AFTER email");
                $pdo->exec("UPDATE users SET photo = 'uploads/barbers/carlos.png' WHERE id = 1 AND (photo IS NULL OR photo = '')");
                $pdo->exec("UPDATE users SET photo = 'uploads/barbers/franco.png' WHERE id = 2 AND (photo IS NULL OR photo = '')");
            }
        } catch (Exception $ex) {}

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `expenses` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `shop_id` INT NOT NULL,
                  `barber_id` INT NULL,
                  `date` DATE NOT NULL,
                  `category` ENUM('insumos', 'alquiler', 'servicios', 'publicidad', 'sueldos', 'impuestos', 'mantenimiento', 'otros') NOT NULL DEFAULT 'otros',
                  `description` VARCHAR(255) NOT NULL,
                  `amount` DECIMAL(10,2) NOT NULL,
                  `payment_method` ENUM('efectivo', 'transferencia', 'mercado_pago', 'tarjeta') NOT NULL DEFAULT 'efectivo',
                  `observations` TEXT NULL,
                  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `barber_payouts` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `shop_id` INT NOT NULL,
                  `barber_id` INT NOT NULL,
                  `date` DATE NOT NULL,
                  `type` ENUM('pago', 'adelanto', 'bonificacion', 'descuento') NOT NULL DEFAULT 'pago',
                  `amount` DECIMAL(10,2) NOT NULL,
                  `payment_method` ENUM('efectivo', 'transferencia', 'mercado_pago', 'tarjeta') NOT NULL DEFAULT 'efectivo',
                  `description` VARCHAR(255) NULL,
                  `receipt_number` VARCHAR(50) NULL,
                  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                  FOREIGN KEY (`barber_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `cash_registers` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `shop_id` INT NOT NULL,
                  `opened_by` INT NOT NULL,
                  `closed_by` INT NULL,
                  `open_date` DATETIME NOT NULL,
                  `close_date` DATETIME NULL,
                  `initial_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `cash_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `transfer_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `mp_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `card_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `cash_expenses` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `expected_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `real_cash` DECIMAL(10,2) NULL,
                  `difference` DECIMAL(10,2) NULL,
                  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
                  `observations` TEXT NULL,
                  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                  FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `audit_logs` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `shop_id` INT NOT NULL,
                  `user_id` INT NULL,
                  `user_name` VARCHAR(150) NULL,
                  `action` VARCHAR(50) NOT NULL,
                  `entity_type` VARCHAR(50) NOT NULL,
                  `entity_id` INT NULL,
                  `details` TEXT NULL,
                  `ip_address` VARCHAR(45) NULL,
                  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                  FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
            ");
        } catch (Exception $ex) {}

        try {
            $checkPriceCol = $pdo->query("SHOW COLUMNS FROM barber_commissions LIKE 'price'");
            if ($checkPriceCol->rowCount() === 0) {
                $pdo->exec("ALTER TABLE barber_commissions ADD COLUMN price DECIMAL(10,2) NULL DEFAULT NULL AFTER commission_percent");
            }
        } catch (Exception $ex) {}

        // Migraciones RBAC / Super Admin & SaaS Plans
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `saas_plans` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `name` VARCHAR(100) NOT NULL,
                  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  `max_barbers` INT NOT NULL DEFAULT 5,
                  `features` TEXT NULL,
                  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Insertar planes por defecto si la tabla está vacía
            $planCount = $pdo->query("SELECT COUNT(*) FROM saas_plans")->fetchColumn();
            if ($planCount == 0) {
                $pdo->exec("
                    INSERT INTO `saas_plans` (`id`, `name`, `price`, `max_barbers`, `features`, `is_active`) VALUES
                    (1, 'Plan Básico SaaS', 15000.00, 3, 'Turnos, Caja, Catálogo', 1),
                    (2, 'Plan Pro Multi-Barbero', 28000.00, 10, 'Turnos, Caja, Exportación Excel, Múltiples Barberos', 1),
                    (3, 'Plan Enterprise La Mansión', 45000.00, 99, 'Ilimitado, Finanzas Avanzadas, Soporte VIP', 1);
                ");
            }

            // Actualizar columna shop_id NULLable en usuarios
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `shop_id` INT NULL");
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'admin_barberia'");
            
            // Actualizar audit_logs shop_id NULLable
            $pdo->exec("ALTER TABLE `audit_logs` MODIFY COLUMN `shop_id` INT NULL");

            // Columnas en shops
            $checkShopActive = $pdo->query("SHOW COLUMNS FROM shops LIKE 'is_active'");
            if ($checkShopActive->rowCount() === 0) {
                $pdo->exec("ALTER TABLE shops ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER owner_id");
            }
            $checkShopPlan = $pdo->query("SHOW COLUMNS FROM shops LIKE 'plan_id'");
            if ($checkShopPlan->rowCount() === 0) {
                $pdo->exec("ALTER TABLE shops ADD COLUMN plan_id INT NULL AFTER owner_id");
                $pdo->exec("UPDATE shops SET plan_id = 2 WHERE plan_id IS NULL");
            }

            // Insertar Super Admin si no existe
            $checkSuperAdmin = $pdo->query("SELECT id FROM users WHERE email = 'admin@saasbarberia.com'");
            if ($checkSuperAdmin->rowCount() === 0) {
                $hash = '$2y$10$dLmvFK25Ngz2jUW6Lp7jAOUZk/YNq6LKy9CJ2ccVODXhA28RMQrbe'; // 123456
                $pdo->exec("INSERT INTO users (shop_id, name, email, photo, password, role, is_active) VALUES (NULL, 'Super Admin SaaS', 'admin@saasbarberia.com', 'uploads/barbers/carlos.png', '$hash', 'super_admin', 1)");
            }

            // Actualizar roles existentes para estandarizarlos
            $pdo->exec("UPDATE users SET role = 'admin_barberia' WHERE role = 'owner'");
            $pdo->exec("UPDATE users SET role = 'barbero' WHERE role = 'barber'");

        } catch (Exception $ex) {}

        @touch($migrationLockFile);
    }
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage() . "<br><br>Por favor verifica que MySQL esté iniciado y hayas ejecutado el archivo <code>database.sql</code>.");
}
