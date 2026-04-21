<?php
// CIAO SPRITZ - Konfigurace
// ZKOPÍRUJ tento soubor jako config.php a vyplň hodnoty
// config.php je v .gitignore - NIKDY ho necommituj!

define('DB_HOST', 'localhost');
define('DB_NAME', 'u880385154_ciao');     // ← tvoje DB
define('DB_USER', 'u880385154_ciao');     // ← tvůj DB user
define('DB_PASS', 'DOPLŇ_HESLO');        // ← tvoje DB heslo

define('SITE_URL', 'https://ciaospritz.cz');
define('SITE_NAME', 'Ciao Spritz');
define('FREE_SHIPPING_FROM', 2000);
define('SHIPPING_PRICE', 120);

// Automatická detekce BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST']);
define('LANG', $_SESSION['lang'] ?? 'cs');

// DB připojení
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB chyba: ' . $e->getMessage());
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function t($cs, $en) { return LANG === 'en' ? $en : $cs; }
function formatPrice($price) { return number_format((float)$price, 0, ',', ' ') . ' Kč'; }
