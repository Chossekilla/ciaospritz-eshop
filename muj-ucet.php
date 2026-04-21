<?php
$pageTitle = 'Můj účet';
require_once 'includes/header.php';

$lang = LANG;

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/prihlaseni.php');
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
    header('Location: ' . BASE_URL . '/');
    exit;
}

$userId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'dashboard';
$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Věrnostní body
$stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type IN ('earned','bonus') THEN points ELSE -points END), 0) as balance FROM loyalty_points WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
$stmt->execute([$userId]);
$loyaltyBalance = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_email = ?");
$stmt->execute([$user['email']]);
$orderCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_email = ? AND status != 'zrusena'");
$stmt->execute([$user['email']]);
$totalSpent = (float)$stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $pdo->prepare("UPDATE users SET name=?,phone=?,address=?,city=?,zip=? WHERE id=?")->execute([$name,$phone,$address,$city,$zip,$userId]);
        $_SESSION['user_name'] = $name;
        $success = t('Profil uložen.', 'Profile saved.');
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) {
            $error = t('Současné heslo není správné.', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            $error = t('Heslo musí mít alespoň 6 znaků.', 'Password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            $error = t('Hesla se neshodují.', 'Passwords do not match.');
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            $success = t('Heslo změněno.', 'Password changed.');
        }
    }
    if (isset($_POST['redeem_points'])) {
        $points = (int)($_POST['redeem_points_amount'] ?? 0);
        if ($points < 50) {
            $error = t('Minimální počet bodů je 50.', 'Minimum points is 50.');
        } elseif ($points > $loyaltyBalance) {
            $error = t('Nemáte dostatek bodů.', 'Not enough points.');
        } else {
            $discount = $points * 0.5;
            $code = 'BODY-' . strtoupper(substr(uniqid(), -6));
            $pdo->prepare("INSERT INTO coupons (code,type,value,user_id,expires_at) VALUES (?,'fixed',?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))")->execute([$code,$discount,$userId]);
            $pdo->prepare("INSERT INTO loyalty_points (user_id,points,type,description) VALUES (?,?,'spent',?)")->execute([$userId,$points,"Kupón $code"]);
            $loyaltyBalance -= $points;
            $success = t("Kupón $code vytvořen! Sleva ".formatPrice($discount).". Platí 30 dní.", "Coupon $code created! ".formatPrice($discount)." discount. Valid 30 days.");
        }
    }
    header('Location: ' . BASE_URL . '/muj-ucet.php?tab=' . $tab);
    exit;
}

$orders = $loyaltyHistory = $coupons = $messages = [];

if (in_array($tab, ['dashboard','orders'])) {
    $limit = $tab === 'dashboard' ? 'LIMIT 3' : '';
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_email = ? ORDER BY created_at DESC $limit");
    $stmt->execute([$user['email']]);
    $orders = $stmt->fetchAll();
}
if ($tab === 'loyalty') {
    $stmt = $pdo->prepare("SELECT * FROM loyalty_points WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$userId]);
    $loyaltyHistory = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE user_id = ? AND active = 1 ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $coupons = $stmt->fetchAll();
}
if ($tab === 'messages') {
    $stmt = $pdo->prepare("SELECT om.*, o.order_number FROM order_messages om JOIN orders o ON o.id = om.order_id WHERE o.customer_email = ? ORDER BY om.created_at DESC");
    $stmt->execute([$user['email']]);
    $messages = $stmt->fetchAll();
}

$statusColors = ['nova'=>'#E8631A','zpracovava'=>'#007bff','odeslana'=>'#6f42c1','dorucena'=>'#2D7A3A','zrusena'=>'#dc3545'];
$statusLabels = ['nova'=>['cs'=>'🆕 Nová','en'=>'🆕 New'],'zpracovava'=>['cs'=>'⚙️ Zpracovává','en'=>'⚙️ Processing'],'odeslana'=>['cs'=>'🚚 Odeslaná','en'=>'🚚 Shipped'],'dorucena'=>['cs'=>'✅ Doručená','en'=>'✅ Delivered'],'zrusena'=>['cs'=>'❌ Zrušená','en'=>'❌ Cancelled']];

function getLoyaltyLevel($spent) {
    if ($spent >= 10000) return ['name'=>'Platinum','icon'=>'💎','color'=>'#6f42c1','next'=>null,'next_at'=>null];
    if ($spent >= 5000)  return ['name'=>'Gold',    'icon'=>'🥇','color'=>'#E8631A','next'=>'Platinum','next_at'=>10000];
    if ($spent >= 2000)  return ['name'=>'Silver',  'icon'=>'🥈','color'=>'#888',   'next'=>'Gold','next_at'=>5000];
    return                      ['name'=>'Bronze',  'icon'=>'🥉','color'=>'#cd7f32','next'=>'Silver','next_at'=>2000];
}
$level = getLoyaltyLevel($totalSpent);

$navItems = [
    ['tab'=>'dashboard','icon'=>'📊','cs'=>'Přehled',       'en'=>'Overview'],
    ['tab'=>'orders',   'icon'=>'📦','cs'=>'Objednávky',    'en'=>'Orders'],
    ['tab'=>'loyalty',  'icon'=>'🎁','cs'=>'Body & kupóny', 'en'=>'Points'],
    ['tab'=>'messages', 'icon'=>'💬','cs'=>'Zprávy',        'en'=>'Messages'],
    ['tab'=>'profile',  'icon'=>'👤','cs'=>'Profil',        'en'=>'Profile'],
    ['tab'=>'password', 'icon'=>'🔒','cs'=>'Heslo',         'en'=>'Password'],
];
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů','Home') ?></a> <span>›</span>
            <span><?= t('Můj účet','My account') ?></span>
        </div>
        <h1><?= t('Dobrý den, <span class="accent">'.e($user['name']).'</span>!','Hello, <span class="accent">'.e($user['name']).'</span>!') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- MOBILNÍ NAVIGACE (horizontální scroll) -->
        <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:8px;margin-bottom:24px;-webkit-overflow-scrolling:touch">
            <?php foreach ($navItems as $item): ?>
            <a href="<?= BASE_URL ?>/muj-ucet.php?tab=<?= $item['tab'] ?>"
               style="flex-shrink:0;display:flex;align-items:center;gap:6px;padding:10px 16px;border-radius:50px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;border:2px solid <?= $tab===$item['tab'] ? 'var(--orange)' : 'var(--border)' ?>;background:<?= $tab===$item['tab'] ? 'rgba(232,99,26,0.08)' : 'white' ?>;color:<?= $tab===$item['tab'] ? 'var(--orange)' : 'var(--gray-dark)' ?>">
                <?= $item['icon'] ?> <?= $lang==='en' ? $item['en'] : $item['cs'] ?>
            </a>
            <?php endforeach; ?>
            <a href="<?= BASE_URL ?>/muj-ucet.php?logout=1"
               style="flex-shrink:0;display:flex;align-items:center;gap:6px;padding:10px 16px;border-radius:50px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;border:2px solid var(--border);color:#dc3545">
                🚪 <?= t('Odhlásit','Logout') ?>
            </a>
        </div>

        <?php if ($success): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error">⚠️ <?= e($error) ?></div><?php endif; ?>

        <!-- DASHBOARD -->
        <?php if ($tab === 'dashboard'): ?>

        <!-- Profil karta -->
        <div style="background:linear-gradient(135deg,var(--orange),var(--orange-dark));border-radius:var(--radius-lg);padding:24px;color:white;margin-bottom:24px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
            <div style="width:64px;height:64px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($user['name'],0,1)) ?>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-family:var(--font-display);font-size:1.3rem;font-weight:900"><?= e($user['name']) ?></div>
                <div style="opacity:0.75;font-size:13px"><?= e($user['email']) ?></div>
                <div style="margin-top:6px;display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:50px;font-size:12px;font-weight:700">
                    <?= $level['icon'] ?> <?= $level['name'] ?>
                </div>
            </div>
            <div style="text-align:center">
                <div style="font-size:11px;opacity:0.7"><?= t('Body','Points') ?></div>
                <div style="font-family:var(--font-display);font-size:2rem;font-weight:900"><?= $loyaltyBalance ?></div>
                <div style="font-size:11px;opacity:0.7"><?= formatPrice($loyaltyBalance * 0.5) ?> <?= t('sleva','discount') ?></div>
            </div>
        </div>

        <!-- Statistiky -->
        <div class="account-stats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px">
            <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;text-align:center">
                <div style="font-size:1.5rem;margin-bottom:6px">📦</div>
                <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:900;color:var(--orange)"><?= $orderCount ?></div>
                <div style="font-size:12px;color:var(--gray)"><?= t('Objednávky','Orders') ?></div>
            </div>
            <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;text-align:center">
                <div style="font-size:1.5rem;margin-bottom:6px">💰</div>
                <div style="font-family:var(--font-display);font-size:1.2rem;font-weight:900;color:var(--green)"><?= formatPrice($totalSpent) ?></div>
                <div style="font-size:12px;color:var(--gray)"><?= t('Celkem útrata','Total spent') ?></div>
            </div>
            <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;text-align:center">
                <div style="font-size:1.5rem;margin-bottom:6px">⭐</div>
                <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:900;color:var(--green)"><?= $loyaltyBalance ?></div>
                <div style="font-size:12px;color:var(--gray)"><?= t('Body','Points') ?></div>
            </div>
        </div>

        <!-- Progress na další level -->
        <?php if ($level['next']): ?>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;margin-bottom:24px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                <span><?= $level['icon'] ?> <?= $level['name'] ?></span>
                <span style="color:var(--gray)"><?= t('do','to') ?> <?= $level['icon'] == '🥉' ? '🥈' : ($level['icon'] == '🥈' ? '🥇' : '💎') ?> <?= $level['next'] ?>: <?= formatPrice($level['next_at'] - $totalSpent) ?></span>
            </div>
            <div style="background:var(--gray-light);border-radius:50px;height:8px;overflow:hidden">
                <div style="width:<?= min(100,($totalSpent/($level['next_at']??1))*100) ?>%;height:100%;background:linear-gradient(90deg,var(--orange),var(--orange-light));border-radius:50px;transition:width 0.5s"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Poslední objednávky -->
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:24px">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
                <h3 style="font-family:var(--font-display);font-size:1rem;font-weight:700"><?= t('Poslední objednávky','Recent orders') ?></h3>
                <a href="?tab=orders" style="font-size:13px;color:var(--orange)"><?= t('Vše →','All →') ?></a>
            </div>
            <?php if (empty($orders)): ?>
                <div style="padding:32px;text-align:center;color:var(--gray);font-size:14px">
                    <?= t('Zatím žádné objednávky.','No orders yet.') ?>
                    <br><a href="<?= BASE_URL ?>/produkty.php" class="btn btn-primary" style="margin-top:12px"><?= t('Nakoupit','Shop') ?></a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $o):
                    $s = $statusLabels[$o['status']] ?? ['cs'=>$o['status'],'en'=>$o['status']];
                    $c = $statusColors[$o['status']] ?? '#888';
                ?>
                <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <div>
                        <div style="font-weight:600;font-size:14px"><?= e($o['order_number']) ?></div>
                        <div style="font-size:12px;color:var(--gray)"><?= date('d. m. Y', strtotime($o['created_at'])) ?></div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-weight:700"><?= formatPrice($o['total']) ?></div>
                        <div style="font-size:12px;color:<?= $c ?>;font-weight:600"><?= $lang==='en' ? $s['en'] : $s['cs'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Benefity -->
        <div style="background:rgba(232,99,26,0.04);border:1px solid rgba(232,99,26,0.15);border-radius:var(--radius-lg);padding:20px">
            <h3 style="font-size:14px;font-weight:700;color:var(--orange);margin-bottom:16px">🎁 <?= t('Věrnostní program','Loyalty program') ?></h3>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
                <?php foreach ([
                    ['🥉','Bronze','Věrnostní body za nákupy','Loyalty points',true],
                    ['🥈','Silver (2 000 Kč)','5% sleva na vše','5% discount',$totalSpent>=2000],
                    ['🥇','Gold (5 000 Kč)','10% sleva','10% discount',$totalSpent>=5000],
                    ['💎','Platinum (10 000 Kč)','15% sleva + VIP','15% + VIP',$totalSpent>=10000],
                ] as [$icon,$name,$desc_cs,$desc_en,$active]): ?>
                <div style="padding:12px;border-radius:var(--radius);border:1px solid <?= $active ? 'var(--green)' : 'var(--border)' ?>;background:<?= $active ? 'rgba(45,122,58,0.05)' : 'white' ?>;opacity:<?= $active ? '1' : '0.5' ?>">
                    <div style="font-size:1.1rem;margin-bottom:4px"><?= $icon ?> <?= $active ? '✅' : '🔒' ?></div>
                    <div style="font-weight:600;font-size:12px"><?= $name ?></div>
                    <div style="font-size:11px;color:var(--gray)"><?= $lang==='en' ? $desc_en : $desc_cs ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- OBJEDNÁVKY -->
        <?php elseif ($tab === 'orders'): ?>
        <?php if (empty($orders)): ?>
            <div style="text-align:center;padding:48px 0">
                <div style="font-size:3rem;margin-bottom:16px">📦</div>
                <p style="color:var(--gray);margin-bottom:16px"><?= t('Zatím žádné objednávky.','No orders yet.') ?></p>
                <a href="<?= BASE_URL ?>/produkty.php" class="btn btn-primary"><?= t('Nakoupit','Shop now') ?></a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o):
                $s = $statusLabels[$o['status']] ?? ['cs'=>$o['status'],'en'=>$o['status']];
                $c = $statusColors[$o['status']] ?? '#888';
                $stmt2 = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $stmt2->execute([$o['id']]);
                $items = $stmt2->fetchAll();
            ?>
            <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:16px">
                <div style="padding:16px 20px;background:var(--gray-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                    <div>
                        <div style="font-weight:700"><?= e($o['order_number']) ?></div>
                        <div style="font-size:12px;color:var(--gray)"><?= date('d. m. Y H:i', strtotime($o['created_at'])) ?></div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-family:var(--font-display);font-size:1.2rem;font-weight:900"><?= formatPrice($o['total']) ?></div>
                        <div style="font-size:13px;color:<?= $c ?>;font-weight:600"><?= $lang==='en' ? $s['en'] : $s['cs'] ?></div>
                    </div>
                </div>
                <div style="padding:16px 20px">
                    <?php foreach ($items as $item): ?>
                    <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--border)">
                        <span><?= e($item['product_name']) ?> ×<?= $item['quantity'] ?></span>
                        <span style="font-weight:600"><?= formatPrice($item['price']*$item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                        <span style="font-size:12px;padding:4px 10px;background:var(--gray-light);border-radius:4px">🚚 <?= $o['shipping_method']==='osobni' ? t('Osobní odběr','Pickup') : t('Kurýr','Courier') ?></span>
                        <span style="font-size:12px;padding:4px 10px;background:var(--gray-light);border-radius:4px">💳 <?= e($o['payment_method']) ?></span>
                        <a href="<?= BASE_URL ?>/faktura-zakaznik.php?order=<?= e($o['order_number']) ?>" target="_blank" style="font-size:12px;padding:4px 10px;background:rgba(45,122,58,0.1);color:var(--green);border-radius:4px;font-weight:600;text-decoration:none">🧾 <?= t('Faktura','Invoice') ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- BODY & KUPÓNY -->
        <?php elseif ($tab === 'loyalty'): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
            <div style="background:linear-gradient(135deg,var(--green),#1a5c26);border-radius:var(--radius-lg);padding:24px;color:white;text-align:center">
                <div style="font-size:12px;opacity:0.7;margin-bottom:6px"><?= t('Zůstatek bodů','Balance') ?></div>
                <div style="font-family:var(--font-display);font-size:2.5rem;font-weight:900"><?= $loyaltyBalance ?></div>
                <div style="font-size:12px;opacity:0.7"><?= formatPrice($loyaltyBalance*0.5) ?> <?= t('sleva','discount') ?></div>
            </div>
            <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:14px">💳 <?= t('Uplatnit body','Redeem') ?></h3>
                <?php if ($loyaltyBalance >= 50): ?>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><?= t('Bodů (min. 50)','Points (min. 50)') ?></label>
                        <input type="number" name="redeem_points_amount" class="form-control" min="50" max="<?= $loyaltyBalance ?>" value="50" step="50">
                    </div>
                    <button type="submit" name="redeem_points" class="btn btn-primary" style="width:100%">🎫 <?= t('Vytvořit kupón','Create coupon') ?></button>
                </form>
                <?php else: ?>
                <p style="font-size:13px;color:var(--gray)"><?= t('Potřebujete min. 50 bodů.','You need at least 50 points.') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jak fungují body -->
        <div style="background:rgba(232,99,26,0.05);border:1px solid rgba(232,99,26,0.15);border-radius:var(--radius);padding:16px;margin-bottom:20px;font-size:13px">
            ℹ️ <?= t('Za každých 100 Kč = 1 bod · 100 bodů = 50 Kč sleva · Platnost 1 rok','100 CZK = 1 point · 100 points = 50 CZK off · Valid 1 year') ?>
        </div>

        <?php if (!empty($coupons)): ?>
        <h3 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;margin-bottom:12px"><?= t('Vaše kupóny','Your coupons') ?></h3>
        <?php foreach ($coupons as $c): ?>
        <div style="background:rgba(45,122,58,0.05);border:1px dashed var(--green);border-radius:var(--radius);padding:14px 16px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <div>
                <div style="font-family:monospace;font-size:1rem;font-weight:700;letter-spacing:0.1em;color:var(--green)"><?= e($c['code']) ?></div>
                <div style="font-size:12px;color:var(--gray)"><?= t('Sleva','Discount') ?>: <?= formatPrice($c['value']) ?><?= $c['expires_at'] ? ' · '.t('Do','Until').' '.date('d.m.Y',strtotime($c['expires_at'])) : '' ?></div>
            </div>
            <div style="font-family:var(--font-display);font-size:1.2rem;font-weight:900;color:var(--green)">-<?= formatPrice($c['value']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($loyaltyHistory)): ?>
        <h3 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;margin:20px 0 12px"><?= t('Historie bodů','Points history') ?></h3>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
            <?php foreach ($loyaltyHistory as $lp): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px">
                <div>
                    <div><?= e($lp['description']) ?></div>
                    <div style="font-size:11px;color:var(--gray)"><?= date('d.m.Y',strtotime($lp['created_at'])) ?></div>
                </div>
                <div style="font-weight:700;color:<?= in_array($lp['type'],['earned','bonus']) ? 'var(--green)' : '#dc3545' ?>">
                    <?= in_array($lp['type'],['earned','bonus']) ? '+' : '-' ?><?= $lp['points'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ZPRÁVY -->
        <?php elseif ($tab === 'messages'): ?>
        <?php if (empty($messages)): ?>
            <div style="text-align:center;padding:48px 0;color:var(--gray)"><div style="font-size:3rem;margin-bottom:12px">💬</div><?= t('Žádné zprávy.','No messages.') ?></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($messages as $msg): ?>
            <div style="background:<?= $msg['sender']==='admin' ? 'var(--gray-light)' : 'rgba(45,122,58,0.06)' ?>;border-radius:var(--radius);padding:14px 16px;border-left:3px solid <?= $msg['sender']==='admin' ? 'var(--orange)' : 'var(--green)' ?>">
                <div style="font-size:11px;color:var(--gray);margin-bottom:6px">
                    <?= $msg['sender']==='admin' ? 'Ciao Spritz' : e($user['name']) ?> · <?= e($msg['order_number']) ?> · <?= date('d.m. H:i',strtotime($msg['created_at'])) ?>
                </div>
                <div style="font-size:14px;line-height:1.6"><?= nl2br(e($msg['message'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- PROFIL -->
        <?php elseif ($tab === 'profile'): ?>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;max-width:600px">
            <h2 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;margin-bottom:20px"><?= t('Profil a adresa','Profile & address') ?></h2>
            <form method="POST">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label"><?= t('Jméno a příjmení','Full name') ?></label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled style="background:#f5f5f5;color:#888">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= t('Telefon','Phone') ?></label>
                        <input type="tel" name="phone" class="form-control" value="<?= e($user['phone']??'') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label"><?= t('Ulice a číslo','Street') ?></label>
                        <input type="text" name="address" class="form-control" value="<?= e($user['address']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= t('Město','City') ?></label>
                        <input type="text" name="city" class="form-control" value="<?= e($user['city']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PSČ</label>
                        <input type="text" name="zip" class="form-control" value="<?= e($user['zip']??'') ?>">
                    </div>
                </div>
                <button type="submit" name="save_profile" class="btn btn-primary"><?= t('Uložit','Save') ?></button>
            </form>
        </div>

        <!-- HESLO -->
        <?php elseif ($tab === 'password'): ?>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;max-width:480px">
            <h2 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;margin-bottom:20px"><?= t('Změna hesla','Change password') ?></h2>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label"><?= t('Současné heslo','Current password') ?></label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= t('Nové heslo','New password') ?></label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= t('Nové heslo znovu','Repeat') ?></label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary"><?= t('Změnit heslo','Change password') ?></button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
