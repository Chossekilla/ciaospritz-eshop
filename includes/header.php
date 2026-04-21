<?php
session_start();
require_once __DIR__ . '/config.php';

// Přepínání jazyka
if (isset($_GET['lang']) && in_array($_GET['lang'], ['cs', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Košík - počet položek
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="<?= LANG ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#E8631A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?><?= SITE_NAME ?></title>
    <meta name="description" content="<?= isset($pageDesc) ? e($pageDesc) : 'Ciao Spritz - Italský aperitiv, který si zamilujete.' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/images/favicon.png">
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <div class="container">
        <span>📞 <a href="tel:602556323">602 556 323</a></span>
        <span>✉️ <a href="mailto:rcaffe@email.cz">rcaffe@email.cz</a></span>
        <div class="lang-switch">
            <a href="?lang=cs" class="<?= LANG === 'cs' ? 'active' : '' ?>">CZ</a>
            <span>|</span>
            <a href="?lang=en" class="<?= LANG === 'en' ? 'active' : '' ?>">EN</a>
        </div>
    </div>
</div>

<!-- NAVIGACE -->
<header class="header" id="header">
    <div class="container">
        <a href="<?= BASE_URL ?>/" class="logo">
            <img src="<?= BASE_URL ?>/images/logo.png" alt="Ciao Spritz" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
            <span class="logo-text" style="display:none">CIAO <span>SPRITZ</span></span>
        </a>

        <nav class="nav" id="nav">
            <a href="<?= BASE_URL ?>/" class="<?= $currentPage === 'index' ? 'active' : '' ?>"><?= t('Domů', 'Home') ?></a>
            <a href="<?= BASE_URL ?>/produkty.php" class="<?= $currentPage === 'produkty' ? 'active' : '' ?>"><?= t('Produkty', 'Products') ?></a>
            <a href="<?= BASE_URL ?>/novinky.php" class="<?= $currentPage === 'novinky' ? 'active' : '' ?>"><?= t('Novinky', 'News') ?></a>
            <a href="<?= BASE_URL ?>/stanek.php" class="<?= $currentPage === 'stanek' ? 'active' : '' ?>"><?= t('Zapůjčení stánku', 'Rent a stand') ?></a>
            <a href="<?= BASE_URL ?>/galerie.php" class="<?= $currentPage === 'galerie' ? 'active' : '' ?>"><?= t('Galerie', 'Gallery') ?></a>
            <a href="<?= BASE_URL ?>/kontakt.php" class="<?= $currentPage === 'kontakt' ? 'active' : '' ?>"><?= t('Kontakt', 'Contact') ?></a>
        </nav>

        <div class="header-actions">
            <a href="<?= BASE_URL ?>/kosik.php" class="cart-btn">
                🛒
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/prihlaseni.php" class="btn-icon">👤</a>
            <button class="hamburger" id="hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<main>
