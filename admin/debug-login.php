<?php
session_start();
require_once __DIR__.'/../includes/config.php';

echo "<pre style='font-family:monospace;font-size:13px;padding:20px'>";
echo "=== CIAO SPRITZ - Admin Login Debug ===\n\n";

// 1. DB připojení
try {
    $pdo->query("SELECT 1");
    echo "✅ DB připojení OK\n";
} catch(Exception $e) {
    echo "❌ DB chyba: " . $e->getMessage() . "\n";
    exit;
}

// 2. Tabulka staff
try {
    $count = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
    echo "✅ Tabulka staff OK ($count záznamů)\n";
} catch(Exception $e) {
    echo "❌ Tabulka staff chybí! Spusť SQL:\n";
    echo "CREATE TABLE staff (id int AUTO_INCREMENT PRIMARY KEY, name varchar(255) NOT NULL, email varchar(255) NOT NULL UNIQUE, password varchar(255) NOT NULL, role enum('admin','manager','prodavac') DEFAULT 'prodavac', active tinyint DEFAULT 1, last_login timestamp NULL, created_at timestamp DEFAULT CURRENT_TIMESTAMP) CHARSET=utf8mb4;\n\n";
    
    // Pokus o vytvoření
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff (id int NOT NULL AUTO_INCREMENT, name varchar(255) NOT NULL, email varchar(255) NOT NULL, password varchar(255) NOT NULL, role enum('admin','manager','prodavac') NOT NULL DEFAULT 'prodavac', phone varchar(20) DEFAULT NULL, note text, active tinyint(1) DEFAULT 1, last_login timestamp NULL DEFAULT NULL, created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY email (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_log (id int NOT NULL AUTO_INCREMENT, staff_id int NOT NULL, action varchar(255) NOT NULL, detail text, ip varchar(45) DEFAULT NULL, created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✅ Tabulka staff byla VYTVOŘENA\n";
        $count = 0;
    } catch(Exception $e2) {
        echo "❌ Nelze vytvořit: " . $e2->getMessage() . "\n";
        exit;
    }
}

// 3. Admin účet
$admin = $pdo->query("SELECT * FROM staff WHERE email='rcaffe@email.cz'")->fetch();
if ($admin) {
    echo "✅ Admin účet nalezen (role: {$admin['role']}, aktivní: {$admin['active']})\n";
    $pwOk = password_verify('admin123', $admin['password']);
    echo ($pwOk ? "✅" : "❌") . " Heslo admin123: " . ($pwOk ? "SPRÁVNÉ" : "ŠPATNÉ - resetuji...") . "\n";
    if (!$pwOk) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE staff SET password=?, active=1 WHERE email='rcaffe@email.cz'")->execute([$hash]);
        echo "✅ Heslo bylo resetováno na admin123\n";
    }
    if (!$admin['active']) {
        $pdo->exec("UPDATE staff SET active=1 WHERE email='rcaffe@email.cz'");
        echo "✅ Účet aktivován\n";
    }
} else {
    echo "❌ Admin účet neexistuje - vytvářím...\n";
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO staff (name,email,password,role,active) VALUES ('Hlavní Admin','rcaffe@email.cz',?,'admin',1)")->execute([$hash]);
    echo "✅ Admin vytvořen: rcaffe@email.cz / admin123\n";
}

// 4. BASE_URL
echo "\n📍 BASE_URL: " . BASE_URL . "\n";
echo "📍 Admin URL: " . BASE_URL . "/admin/index.php\n";

echo "\n=== HOTOVO ===\n";
echo "Přihlašovací údaje:\n";
echo "  Email: rcaffe@email.cz\n";
echo "  Heslo: admin123\n";
echo "</pre>";
echo "<a href='" . BASE_URL . "/admin/index.php' style='display:inline-block;margin:20px;padding:14px 28px;background:#2D7A3A;color:white;text-decoration:none;border-radius:8px;font-family:sans-serif;font-weight:600'>→ Přejít na Admin Login</a>";
echo "<br><small style='font-family:sans-serif;margin:20px;display:block;color:#dc3545'>⚠️ Po dokončení SMAŽ tento soubor!</small>";
