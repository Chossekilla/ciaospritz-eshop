<?php
// CIAO SPRITZ - Konfigurace
// Změň tyto hodnoty po nahrání na Hostinger!

define('DB_HOST', 'localhost');
define('DB_NAME', 'u880385154_ciao');
define('DB_USER', 'u880385154_ciao');
define('DB_PASS', 'Karkulka55+');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL', 'https://lightcoral-kangaroo-529392.hostingersite.com');
define('SITE_NAME', 'Ciao Spritz');

// Automatická detekce base URL - funguje na jakékoliv doméně
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST']);

define('FREE_SHIPPING_FROM', 2000);
define('SHIPPING_PRICE', 120);

// Jazyk (cs / en)
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'cs';
}
define('LANG', $_SESSION['lang']);

// Připojení k databázi
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}

// Pomocná funkce pro překlad
function t($cs, $en) {
    return LANG === 'en' ? $en : $cs;
}

// Bezpečný výstup
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Formátování ceny
function formatPrice($price) {
    return number_format($price, 0, ',', ' ') . ' Kč';
}
?>
