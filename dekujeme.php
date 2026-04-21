<?php
$pageTitle = 'Děkujeme za objednávku';
require_once 'includes/header.php';

$lang = LANG;
$orderNumber = e($_GET['order'] ?? '');

if (!$orderNumber) { header('Location: '.BASE_URL.'/'); exit; }

// Načti objednávku
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) { header('Location: '.BASE_URL.'/'); exit; }

// Načti položky
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();
?>

<section class="section">
    <div class="container" style="max-width:680px">

        <!-- SUCCESS ICON -->
        <div style="text-align:center;padding:48px 0 32px">
            <div style="width:96px;height:96px;background:rgba(45,122,58,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:3rem;margin:0 auto 24px">
                ✅
            </div>
            <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;margin-bottom:12px">
                <?= t('Děkujeme za objednávku!', 'Thank you for your order!') ?>
            </h1>
            <p style="font-size:1.05rem;color:var(--gray-dark);margin-bottom:8px">
                <?= t('Potvrzení jsme odeslali na', 'Confirmation was sent to') ?>
                <strong style="color:var(--orange)"><?= e($order['customer_email']) ?></strong>
            </p>
            <p style="font-size:1.1rem;color:var(--gray)">
                <?= t('Číslo objednávky:', 'Order number:') ?>
                <strong style="font-family:var(--font-display);font-size:1.3rem;color:var(--black)"><?= $orderNumber ?></strong>
            </p>
        </div>

        <!-- DETAIL OBJEDNÁVKY -->
        <div style="background:var(--gray-light);border-radius:var(--radius-lg);padding:32px;margin-bottom:32px">
            <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:20px"><?= t('Detail objednávky', 'Order details') ?></h2>

            <!-- Položky -->
            <?php foreach ($items as $item): ?>
            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:14px">
                <span><?= e($item['product_name']) ?> <span style="color:var(--gray)">× <?= $item['quantity'] ?></span></span>
                <span style="font-weight:600"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
            </div>
            <?php endforeach; ?>

            <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:14px">
                <span><?= t('Doprava', 'Shipping') ?></span>
                <span><?= $order['shipping_price'] == 0 ? t('ZDARMA', 'FREE') : formatPrice($order['shipping_price']) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:12px 0 0;font-size:1.1rem;font-weight:700;border-top:2px solid var(--border);margin-top:4px">
                <span><?= t('Celkem', 'Total') ?></span>
                <span style="color:var(--orange)"><?= formatPrice($order['total']) ?></span>
            </div>
        </div>

        <!-- INFO BLOKY -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px">
            <div style="background:white;border:1px solid var(--border);border-radius:var(--radius);padding:20px">
                <div style="font-size:1.3rem;margin-bottom:8px">🚚</div>
                <div style="font-weight:600;margin-bottom:4px"><?= t('Doprava', 'Shipping') ?></div>
                <div style="font-size:13px;color:var(--gray)"><?= $order['shipping_method'] === 'osobni' ? t('Osobní odběr', 'Personal pickup') : t('Kurýr', 'Courier') ?></div>
                <?php if ($order['address']): ?>
                <div style="font-size:13px;color:var(--gray-dark);margin-top:4px"><?= e($order['address']) ?>, <?= e($order['zip']) ?> <?= e($order['city']) ?></div>
                <?php endif; ?>
            </div>
            <div style="background:white;border:1px solid var(--border);border-radius:var(--radius);padding:20px">
                <div style="font-size:1.3rem;margin-bottom:8px">💳</div>
                <div style="font-weight:600;margin-bottom:4px"><?= t('Platba', 'Payment') ?></div>
                <div style="font-size:13px;color:var(--gray)"><?= e($order['payment_method']) ?></div>
                <?php if ($order['payment_method'] === 'prevod'): ?>
                <div style="font-size:12px;color:var(--orange);margin-top:6px;font-weight:500">
                    <?= t('Číslo účtu vám přijde v emailu.', 'Bank account details will be in the email.') ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CTA -->
        <div style="text-align:center;display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/produkty.php" class="btn btn-primary"><?= t('Pokračovat v nákupu', 'Continue shopping') ?></a>
            <a href="<?= BASE_URL ?>/" class="btn btn-secondary"><?= t('Zpět na úvodní stránku', 'Back to homepage') ?></a>
        </div>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
