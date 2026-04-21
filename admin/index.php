<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';

// ODHLÁŠENÍ
if (isset($_GET['logout'])) {
    if (isset($_SESSION['staff_id'])) {
        logAction($pdo, 'Odhlášení');
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

// PŘIHLÁŠENÍ
if (!isset($_SESSION['staff_id'])) {
    $loginError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $staff = $stmt->fetch();

        if ($staff && password_verify($password, $staff['password'])) {
            $_SESSION['staff_id']    = $staff['id'];
            $_SESSION['staff_name']  = $staff['name'];
            $_SESSION['staff_email'] = $staff['email'];
            $_SESSION['staff_role']  = $staff['role'];

            $pdo->prepare("UPDATE staff SET last_login = NOW() WHERE id = ?")->execute([$staff['id']]);
            logAction($pdo, 'Přihlášení', 'Role: ' . $staff['role']);

            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        } else {
            $loginError = 'Nesprávný email nebo heslo.';
        }
    }

    // LOGIN STRÁNKA
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin - Ciao Spritz</title>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'DM Sans', sans-serif; background: linear-gradient(135deg, #fff8f4, #f0f8f1); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .login-box { background: white; border-radius: 20px; padding: 48px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); }
            .logo { text-align: center; font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 900; margin-bottom: 8px; }
            .logo span { color: #E8631A; }
            .subtitle { text-align: center; font-size: 13px; color: #888; margin-bottom: 36px; letter-spacing: 0.1em; text-transform: uppercase; }
            .form-group { margin-bottom: 16px; }
            label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #555; }
            input { width: 100%; padding: 13px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; outline: none; transition: border-color 0.2s; font-family: inherit; }
            input:focus { border-color: #E8631A; }
            .btn { width: 100%; padding: 14px; background: #2D7A3A; color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 8px; transition: background 0.2s; font-family: inherit; }
            .btn:hover { background: #38973f; }
            .error { background: rgba(220,53,69,0.08); color: #dc3545; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; border: 1px solid rgba(220,53,69,0.2); }
            .back { text-align: center; margin-top: 20px; font-size: 13px; }
            .back a { color: #E8631A; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <div class="logo">CIAO <span>SPRITZ</span></div>
            <div class="subtitle">Administrace</div>
            <?php if ($loginError): ?>
                <div class="error">⚠️ <?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required autofocus placeholder="vas@email.cz">
                </div>
                <div class="form-group">
                    <label>Heslo</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" name="login" class="btn">Přihlásit se →</button>
            </form>
            <div class="back"><a href="<?= BASE_URL ?>/">← Zpět na web</a></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ===== PŘIHLÁŠEN - DASHBOARD =====
requireLogin();

$section = $_GET['section'] ?? 'dashboard';

// Zpracování akcí
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_product' && canAccess('products')) {
        $pdo->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$_POST['id']]);
        logAction($pdo, 'Smazán produkt', 'ID: ' . $_POST['id']);
        $message = '✅ Produkt odstraněn.';
    }
    if ($action === 'update_order_status' && canAccess('orders')) {
        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$_POST['status'], $_POST['id']]);
        logAction($pdo, 'Změna stavu objednávky', 'ID: ' . $_POST['id'] . ' → ' . $_POST['status']);
        $message = '✅ Stav objednávky aktualizován.';
    }
    if ($action === 'delete_article' && canAccess('articles')) {
        $pdo->prepare("DELETE FROM articles WHERE id=?")->execute([$_POST['id']]);
        logAction($pdo, 'Smazán článek', 'ID: ' . $_POST['id']);
        $message = '✅ Článek smazán.';
    }
    if ($action === 'toggle_article' && canAccess('articles')) {
        $pdo->prepare("UPDATE articles SET published = NOT published WHERE id=?")->execute([$_POST['id']]);
        $message = '✅ Stav článku změněn.';
    }

    header('Location: ' . BASE_URL . '/admin/index.php?section=' . $section . '&msg=' . urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

// Načti data
$stats = [];
if ($section === 'dashboard') {
    $stats['orders_new']  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='nova'")->fetchColumn();
    $stats['orders_all']  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['products']    = $pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
    $stats['revenue']     = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'zrusena'")->fetchColumn();
    $stats['users']       = $pdo->query("SELECT COUNT(*) FROM users WHERE active=1")->fetchColumn();
    $stats['reservations']= $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='ceka'")->fetchColumn();
    $recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8")->fetchAll();
}

$items = [];
if ($section === 'products')    $items = $pdo->query("SELECT * FROM products ORDER BY category, id")->fetchAll();
if ($section === 'orders')      $items = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();
if ($section === 'articles')    $items = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC")->fetchAll();

$role = $_SESSION['staff_role'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; color: #111; display: flex; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { width: 240px; background: #111; color: white; flex-shrink: 0; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100; }
        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logo .brand { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 900; }
        .sidebar-logo .brand span { color: #E8631A; }
        .sidebar-logo .role-badge { margin-top: 8px; }
        .sidebar-user { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-user .name { font-weight: 600; font-size: 14px; }
        .sidebar-user .email { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 2px; }
        .sidebar nav { padding: 12px; flex: 1; }
        .nav-section { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.3); padding: 12px 8px 6px; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; color: rgba(255,255,255,0.65); font-size: 13px; font-weight: 500; text-decoration: none; transition: all 0.2s; margin-bottom: 2px; }
        .sidebar nav a:hover { background: rgba(255,255,255,0.07); color: white; }
        .sidebar nav a.active { background: rgba(232,99,26,0.2); color: #E8631A; }
        .sidebar nav a.disabled { opacity: 0.3; pointer-events: none; }
        .sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-footer a { display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.45); font-size: 13px; text-decoration: none; padding: 8px 12px; border-radius: 8px; }
        .sidebar-footer a:hover { color: white; background: rgba(255,255,255,0.05); }

        /* MAIN */
        .main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #e0e0e0; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .topbar h1 { font-family: 'Playfair Display', serif; font-size: 1.3rem; font-weight: 900; }
        .topbar-actions { display: flex; gap: 12px; align-items: center; }
        .content { padding: 28px 32px; flex: 1; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px 24px; border: 1px solid #e0e0e0; display: flex; align-items: center; gap: 16px; transition: all 0.2s; }
        .stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .stat-num { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; line-height: 1; }
        .stat-label { font-size: 12px; color: #888; margin-top: 3px; }
        .stat-badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 50px; margin-left: 6px; }

        /* CARDS */
        .card { background: white; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 16px 24px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1rem; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #888; border-bottom: 1px solid #e0e0e0; background: #fafafa; }
        td { padding: 13px 20px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        /* BUTTONS */
        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; white-space: nowrap; }
        .btn-primary { background: #2D7A3A; color: white; }
        .btn-primary:hover { background: #38973f; }
        .btn-orange { background: #E8631A; color: white; }
        .btn-danger { background: rgba(220,53,69,0.1); color: #dc3545; }
        .btn-danger:hover { background: #dc3545; color: white; }
        .btn-outline { background: none; border: 1.5px solid #e0e0e0; color: #555; }
        .btn-outline:hover { border-color: #E8631A; color: #E8631A; }

        /* BADGES */
        .badge { display: inline-block; padding: 3px 9px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .badge-nova       { background: rgba(232,99,26,0.1); color: #E8631A; }
        .badge-zpracovava { background: rgba(0,123,255,0.1); color: #007bff; }
        .badge-odeslana   { background: rgba(111,66,193,0.1); color: #6f42c1; }
        .badge-dorucena   { background: rgba(45,122,58,0.1); color: #2D7A3A; }
        .badge-zrusena    { background: rgba(220,53,69,0.1); color: #dc3545; }
        .badge-info       { background: rgba(0,123,255,0.1); color: #007bff; }

        /* ALERT */
        .alert { padding: 12px 20px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; background: rgba(45,122,58,0.08); color: #2D7A3A; border: 1px solid rgba(45,122,58,0.15); }

        select.status-select { padding: 5px 8px; border: 1.5px solid #e0e0e0; border-radius: 6px; font-size: 12px; }

        /* NOTIFICATIONS */
        .notif { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; background: #dc3545; color: white; border-radius: 50%; font-size: 10px; font-weight: 700; margin-left: auto; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">CIAO <span>SPRITZ</span></div>
        <div class="role-badge"><?= getRoleBadge($role) ?></div>
    </div>
    <div class="sidebar-user">
        <div class="name"><?= htmlspecialchars($_SESSION['staff_name']) ?></div>
        <div class="email"><?= htmlspecialchars($_SESSION['staff_email']) ?></div>
    </div>
    <nav>
        <div class="nav-section">Přehled</div>
        <a href="?section=dashboard" class="<?= $section==='dashboard'?'active':'' ?>">📊 Dashboard</a>

        <div class="nav-section">Prodej</div>
        <a href="?section=orders" class="<?= !canAccess('orders')?'disabled':($section==='orders'?'active':'') ?>">
            🛒 Objednávky
            <?php $newOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='nova'")->fetchColumn(); ?>
            <?php if ($newOrders > 0): ?><span class="notif"><?= $newOrders ?></span><?php endif; ?>
        </a>
        <a href="?section=products" class="<?= !canAccess('products')?'disabled':($section==='products'?'active':'') ?>">🍾 Produkty</a>

        <div class="nav-section">Obsah</div>
        <a href="<?= BASE_URL ?>/admin/media.php" class="<?= !canAccess('articles')?'disabled':'' ?>">📁 Media Manager</a>
        <a href="<?= BASE_URL ?>/admin/hero.php" class="<?= !canAccess('articles')?'disabled':'' ?>">🌅 Hero banner</a>
        <a href="?section=articles" class="<?= !canAccess('articles')?'disabled':($section==='articles'?'active':'') ?>">📰 Články</a>
        <a href="<?= BASE_URL ?>/admin/gallery.php" class="<?= !canAccess('gallery')?'disabled':'' ?>">🖼️ Galerie</a>
        <a href="<?= BASE_URL ?>/admin/reservations.php" class="<?= !canAccess('reservations')?'disabled':'' ?>">
            📅 Rezervace
            <?php $pendingRes = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='ceka'")->fetchColumn(); ?>
            <?php if ($pendingRes > 0): ?><span class="notif"><?= $pendingRes ?></span><?php endif; ?>
        </a>

        <div class="nav-section">Zákazníci</div>
        <a href="<?= BASE_URL ?>/admin/customers.php" class="<?= !canAccess('customers')?'disabled':'' ?>">👥 Zákazníci & zprávy</a>

        <?php if (canAccess('staff')): ?>
        <div class="nav-section">Nástroje</div>
        <a href="<?= BASE_URL ?>/admin/export.php">📤 XML Export</a>
        <a href="<?= BASE_URL ?>/admin/ai-descriptions.php">🤖 AI Popisy</a>
        <div class="nav-section">Systém</div>
        <a href="<?= BASE_URL ?>/admin/staff.php">👨‍💼 Zaměstnanci</a>
        <a href="<?= BASE_URL ?>/admin/log.php">📋 Přístupový log</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/" target="_blank">🌐 Zobrazit web</a>
        <a href="?logout=1">🚪 Odhlásit se</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <h1>
            <?php $titles = ['dashboard'=>'Dashboard','products'=>'Produkty','orders'=>'Objednávky','articles'=>'Články'];
            echo $titles[$section] ?? 'Admin'; ?>
        </h1>
        <div class="topbar-actions">
            <?php if ($section === 'products' && canAccess('products')): ?>
                <a href="<?= BASE_URL ?>/admin/product-edit.php" class="btn btn-primary">+ Přidat produkt</a>
            <?php elseif ($section === 'articles' && canAccess('articles')): ?>
                <a href="<?= BASE_URL ?>/admin/article-edit.php" class="btn btn-primary">+ Přidat článek</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="content">
        <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <?php if ($section === 'dashboard'): ?>
        <!-- ===== DASHBOARD ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(232,99,26,0.1)">🛒</div>
                <div>
                    <div class="stat-num" style="color:#E8631A"><?= $stats['orders_all'] ?>
                        <?php if ($stats['orders_new'] > 0): ?>
                        <span class="stat-badge" style="background:rgba(220,53,69,0.1);color:#dc3545">+<?= $stats['orders_new'] ?> nové</span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">Objednávky celkem</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(45,122,58,0.1)">💰</div>
                <div>
                    <div class="stat-num" style="color:#2D7A3A"><?= number_format($stats['revenue'],0,',',' ') ?></div>
                    <div class="stat-label">Tržby celkem (Kč)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(111,66,193,0.1)">👥</div>
                <div>
                    <div class="stat-num" style="color:#6f42c1"><?= $stats['users'] ?></div>
                    <div class="stat-label">Registrovaní zákazníci</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(0,123,255,0.1)">🍾</div>
                <div>
                    <div class="stat-num" style="color:#007bff"><?= $stats['products'] ?></div>
                    <div class="stat-label">Aktivních produktů</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(232,99,26,0.1)">📅</div>
                <div>
                    <div class="stat-num" style="color:#E8631A"><?= $stats['reservations'] ?></div>
                    <div class="stat-label">Čekající rezervace</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(45,122,58,0.1)">📰</div>
                <div>
                    <div class="stat-num" style="color:#2D7A3A"><?= $pdo->query("SELECT COUNT(*) FROM articles WHERE published=1")->fetchColumn() ?></div>
                    <div class="stat-label">Publikované články</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>🛒 Poslední objednávky</h2>
                <a href="?section=orders" class="btn btn-outline">Zobrazit vše →</a>
            </div>
            <table>
                <thead><tr><th>Číslo</th><th>Zákazník</th><th>Celkem</th><th>Doprava</th><th>Platba</th><th>Stav</th><th>Datum</th></tr></thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#888;padding:32px">Žádné objednávky</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                        <td>
                            <div><?= htmlspecialchars($o['customer_name']) ?></div>
                            <div style="font-size:11px;color:#888"><?= htmlspecialchars($o['customer_email']) ?></div>
                        </td>
                        <td style="font-weight:700"><?= number_format($o['total'],0,',',' ') ?> Kč</td>
                        <td><?= $o['shipping_method'] === 'osobni' ? '🏪 Osobní' : '🚚 Kurýr' ?></td>
                        <td><?= htmlspecialchars($o['payment_method']) ?></td>
                        <td><span class="badge badge-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                        <td style="color:#888;font-size:12px"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($section === 'products' && canAccess('products')): ?>
        <!-- ===== PRODUKTY ===== -->
        <div class="card">
            <div class="card-header"><h2>🍾 Všechny produkty (<?= count($items) ?>)</h2></div>
            <table>
                <thead><tr><th>Název</th><th>Kategorie</th><th>Cena</th><th>Sklad</th><th>Featured</th><th>Akce</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['name_cs']) ?></strong></td>
                    <td><span class="badge badge-info"><?= $item['category'] ?></span></td>
                    <td><?= number_format($item['price'],0,',',' ') ?> Kč</td>
                    <td><?= $item['stock'] ?> ks</td>
                    <td><?= $item['featured'] ? '⭐' : '—' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/product-edit.php?id=<?= $item['id'] ?>" class="btn btn-outline">✏️ Upravit</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Odebrat produkt?')">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($section === 'orders' && canAccess('orders')): ?>
        <!-- ===== OBJEDNÁVKY ===== -->
        <div class="card">
            <div class="card-header"><h2>🛒 Všechny objednávky (<?= count($items) ?>)</h2></div>
            <table>
                <thead><tr><th>Číslo</th><th>Zákazník</th><th>Celkem</th><th>Doprava</th><th>Platba</th><th>Stav</th><th>Datum</th></tr></thead>
                <tbody>
                <?php foreach ($items as $o): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                    <td>
                        <div><?= htmlspecialchars($o['customer_name']) ?></div>
                        <div style="font-size:11px;color:#888"><?= htmlspecialchars($o['customer_email']) ?></div>
                        <?php if ($o['customer_phone']): ?><div style="font-size:11px;color:#888"><?= htmlspecialchars($o['customer_phone']) ?></div><?php endif; ?>
                    </td>
                    <td style="font-weight:700"><?= number_format($o['total'],0,',',' ') ?> Kč</td>
                    <td><?= $o['shipping_method'] === 'osobni' ? '🏪 Osobní' : '🚚 Kurýr' ?></td>
                    <td><?= htmlspecialchars($o['payment_method']) ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_order_status">
                            <input type="hidden" name="id" value="<?= $o['id'] ?>">
                            <select name="status" class="status-select" onchange="this.form.submit()">
                                <?php foreach (['nova','zpracovava','odeslana','dorucena','zrusena'] as $s): ?>
                                <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td style="color:#888;font-size:12px"><?= date('d.m.Y', strtotime($o['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($section === 'articles' && canAccess('articles')): ?>
        <!-- ===== ČLÁNKY ===== -->
        <div class="card">
            <div class="card-header"><h2>📰 Všechny články (<?= count($items) ?>)</h2></div>
            <table>
                <thead><tr><th>Název</th><th>Stav</th><th>Datum</th><th>Akce</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['title_cs']) ?></strong></td>
                    <td><span class="badge <?= $item['published'] ? 'badge-dorucena' : 'badge-info' ?>"><?= $item['published'] ? 'Publikován' : 'Skryt' ?></span></td>
                    <td style="color:#888;font-size:12px"><?= date('d.m.Y', strtotime($item['created_at'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/article-edit.php?id=<?= $item['id'] ?>" class="btn btn-outline">✏️</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_article">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-orange"><?= $item['published'] ? '👁️ Skrýt' : '✅ Pub.' ?></button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Smazat?')">
                            <input type="hidden" name="action" value="delete_article">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
