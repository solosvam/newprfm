<?php
// PHP 5.6+. Place beside the legacy config.php and run from the terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the terminal.');
}
require __DIR__ . '/config.php';
$limit = isset($argv[1]) ? (int) $argv[1] : 1000;
if ($limit < 1 || $limit > 10000) exit("Limit must be 1..10000.\n");
$pdo = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' . DB_DATABASE . ';charset=utf8mb4', DB_USERNAME, DB_PASSWORD, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
));
$source = DB_PREFIX . 'customer';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $source)) exit("Invalid table prefix.\n");
if (!$pdo->query("SELECT GET_LOCK('legacy_local_customer_migration', 0)")->fetchColumn()) exit("Another migration is running.\n");
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(30) NOT NULL,
        surname VARCHAR(30) NOT NULL,
        gender INT DEFAULT NULL,
        email VARCHAR(50) DEFAULT NULL,
        mobile VARCHAR(12) NOT NULL,
        password VARCHAR(255) DEFAULT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        remember_token VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        bonus_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        registration_otp_hash VARCHAR(255) DEFAULT NULL,
        registration_otp_expires_at TIMESTAMP NULL DEFAULT NULL,
        old_customer_id BIGINT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY customers_old_customer_id_unique (old_customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Keep city_id/order_id columns and indexes, without references to absent new-system tables.
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_addresses (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        title VARCHAR(50) DEFAULT NULL,
        city_id BIGINT UNSIGNED DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        district VARCHAR(100) DEFAULT NULL,
        address VARCHAR(500) NOT NULL,
        building VARCHAR(50) DEFAULT NULL,
        entrance VARCHAR(50) DEFAULT NULL,
        floor VARCHAR(30) DEFAULT NULL,
        apartment VARCHAR(30) DEFAULT NULL,
        note TEXT,
        latitude DECIMAL(10,7) DEFAULT NULL,
        longitude DECIMAL(10,7) DEFAULT NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        KEY customer_addresses_city_id_foreign (city_id),
        KEY customer_addresses_customer_id_foreign (customer_id),
        CONSTRAINT customer_addresses_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_bonus_transactions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        order_id BIGINT UNSIGNED DEFAULT NULL,
        type ENUM('earn','spend','register','adjustment') NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        created_by BIGINT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        KEY customer_bonus_transactions_order_id_foreign (order_id),
        KEY customer_bonus_transactions_customer_id_foreign (customer_id),
        CONSTRAINT customer_bonus_transactions_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Compatible with the current model; base credit-profile DDL is not present locally.
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_credit_profiles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        father_name VARCHAR(100) DEFAULT NULL,
        fin VARCHAR(7) DEFAULT NULL,
        id_card_series VARCHAR(5) DEFAULT NULL,
        id_card_number VARCHAR(8) DEFAULT NULL,
        relative_1_name VARCHAR(100) DEFAULT NULL,
        relative_1_phone VARCHAR(20) DEFAULT NULL,
        relative_2_name VARCHAR(100) DEFAULT NULL,
        relative_2_phone VARCHAR(20) DEFAULT NULL,
        id_card_front VARCHAR(255) DEFAULT NULL,
        id_card_back VARCHAR(255) DEFAULT NULL,
        workplace_name VARCHAR(255) DEFAULT NULL,
        salary DECIMAL(10,2) DEFAULT NULL,
        position VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY customer_credit_profiles_customer_id_unique (customer_id),
        CONSTRAINT customer_credit_profiles_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $column = $pdo->query("SHOW COLUMNS FROM `$source` LIKE 'checked'")->fetch();
    if (!$column) $pdo->exec("ALTER TABLE `$source` ADD checked TINYINT(1) NOT NULL DEFAULT 0");
    $rows = $pdo->query("SELECT customer_id, firstname, lastname, email, telephone, sex,
        CASE WHEN bonus IS NULL OR bonus < 1 THEN 0 ELSE bonus END AS bonus, date_added
        FROM `$source` WHERE (checked IS NULL OR checked = 0)
        AND CHAR_LENGTH(telephone) = 12 AND telephone REGEXP '^994[0-9]{9}$'
        ORDER BY customer_id ASC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    $exists = $pdo->prepare('SELECT id FROM customers WHERE old_customer_id = ?');
    $conflict = $pdo->prepare('SELECT id FROM customers WHERE mobile = ? OR (? IS NOT NULL AND email = ?) LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO customers (old_customer_id, name, surname, email, mobile, gender, bonus_balance, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $mark = $pdo->prepare("UPDATE `$source` SET checked = 1 WHERE customer_id = ?");
    $counts = array('created' => 0, 'already_copied' => 0, 'conflict' => 0, 'invalid' => 0);
    foreach ($rows as $row) {
        $id = (int) $row['customer_id'];
        $pdo->beginTransaction();
        try {
            $exists->execute(array($id));
            if ($exists->fetchColumn()) {
                $mark->execute(array($id));
                $pdo->commit();
                $counts['already_copied']++;
                continue;
            }
            $name = trim($row['firstname']);
            $surname = trim($row['lastname']);
            $email = trim($row['email']);
            $email = $email === '' ? null : $email;
            $date = DateTime::createFromFormat('!Y-m-d H:i:s', $row['date_added']);
            if ($name === '' || $surname === '' || mb_strlen($name, 'UTF-8') > 30 || mb_strlen($surname, 'UTF-8') > 30
                || ($email !== null && (strlen($email) > 50 || !filter_var($email, FILTER_VALIDATE_EMAIL)))
                || !in_array((string) $row['sex'], array('1', '2'), true)
                || !$date || $date->format('Y-m-d H:i:s') !== $row['date_added']) {
                $pdo->rollBack();
                $counts['invalid']++;
                echo "invalid: old_customer_id=$id\n";
                continue;
            }
            $conflict->execute(array($row['telephone'], $email, $email));
            if ($conflict->fetchColumn()) {
                $pdo->rollBack();
                $counts['conflict']++;
                echo "conflict: old_customer_id=$id\n";
                continue;
            }
            $insert->execute(array($id, $name, $surname, $email, $row['telephone'], (string) $row['sex'] === '1' ? 1 : 0, $row['bonus'], $row['date_added']));
            $mark->execute(array($id));
            $pdo->commit();
            $counts['created']++;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    foreach ($counts as $label => $count) echo "$label: $count\n";
} catch (Exception $e) {
    fwrite(STDERR, "Migration stopped: " . $e->getMessage() . "\n");
    $pdo->query("SELECT RELEASE_LOCK('legacy_local_customer_migration')");
    exit(1);
}
$pdo->query("SELECT RELEASE_LOCK('legacy_local_customer_migration')");
