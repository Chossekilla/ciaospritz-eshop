<?php
session_start();
require_once 'includes/config.php';

// Musí být přihlášen
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/prihlaseni.php');
    exit;
}

$orderNumber = $_GET['order'] ?? '';
if (!$orderNumber) { header('Location: ' . BASE_URL . '/muj-ucet.php'); exit; }

// Načti objednávku - ověř že patří přihlášenému zákazníkovi
$stmt = $pdo->prepare("SELECT o.* FROM orders o JOIN users u ON u.email = o.customer_email WHERE o.order_number = ? AND u.id = ?");
$stmt->execute([$orderNumber, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) { header('Location: ' . BASE_URL . '/muj-ucet.php'); exit; }

// Načti položky
$items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$order['id']]);
$items = $items->fetchAll();

$invoiceNumber = 'FAK-' . preg_replace('/[^0-9]/', '', $order['order_number']);
$invoiceDate = date('d. m. Y', strtotime($order['created_at']));
$dueDate = date('d. m. Y', strtotime($order['created_at'] . ' +14 days'));
$vatBase = round($order['total'] / 1.21, 2);
$vatAmount = round($order['total'] - $vatBase, 2);
$shippingNames = ['kuryrem'=>'Kurýr GLS','osobni'=>'Osobní odběr','zasilkovna'=>'Zásilkovna'];
$paymentNames = ['prevod'=>'Bankovní převod','hotovost'=>'Hotovost','dobírka'=>'Dobírka'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Faktura <?= e($invoiceNumber) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Helvetica Neue',Arial,sans-serif;font-size:13px;color:#222;background:#f0f0f0}
.page-wrapper{max-width:800px;margin:32px auto;background:white;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.1);overflow:hidden}
.toolbar{background:#111;padding:14px 32px;display:flex;align-items:center;gap:12px}
.toolbar a,.toolbar button{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
.btn-back{background:rgba(255,255,255,0.1);color:white}
.btn-print{background:#E8631A;color:white}
.invoice{padding:48px}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;padding-bottom:32px;border-bottom:2px solid #f0f0f0}
.logo{font-family:Georgia,serif;font-size:28px;font-weight:900;letter-spacing:2px}.logo span{color:#E8631A}
.logo-sub{font-size:11px;color:#aaa;letter-spacing:3px;text-transform:uppercase;margin-top:4px}
.inv-title h1{font-size:28px;font-weight:900;text-align:right}.inv-num{font-size:14px;color:#E8631A;font-weight:700;text-align:right;margin-top:4px}.inv-date{font-size:12px;color:#888;text-align:right;margin-top:4px}
.addresses{display:grid;grid-template-columns:1fr 1fr 1fr;gap:32px;margin-bottom:40px}
.address-block h3{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#aaa;margin-bottom:10px}
.address-block .company{font-size:14px;font-weight:700;margin-bottom:4px}.address-block p{font-size:12px;color:#555;line-height:1.7}
table{width:100%;border-collapse:collapse;margin-bottom:24px}
thead tr{background:#111;color:white}
thead th{padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;text-align:left}
thead th:not(:first-child){text-align:right}
tbody tr{border-bottom:1px solid #f0f0f0}
tbody td{padding:14px 16px;font-size:13px}
tbody td:not(:first-child){text-align:right}
.totals{margin-left:auto;width:280px}
.total-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0;font-size:13px}
.total-final{background:#111;color:white;border-radius:8px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin-top:8px}
.total-final .amount{font-size:22px;font-weight:900;color:#E8631A}
.payment-box{margin-top:32px;background:#f0f8f1;border:1px solid #c3e6cb;border-radius:10px;padding:20px 24px}
.payment-box h3{font-size:12px;font-weight:700;text-transform:uppercase;color:#2D7A3A;margin-bottom:12px}
.payment-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.pi-label{font-size:10px;color:#888;text-transform:uppercase;margin-bottom:3px}
.pi-value{font-size:14px;font-weight:700}
.footer{margin-top:40px;padding-top:24px;border-top:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#aaa}
.stamp{text-align:center;border:2px solid #e0e0e0;border-radius:8px;padding:12px 20px;min-width:160px;font-size:11px;color:#aaa}
.stamp-label{text-transform:uppercase;letter-spacing:.08em;font-size:10px;margin-bottom:24px}
@media print{body{background:white}.page-wrapper{margin:0;border-radius:0;box-shadow:none}.toolbar{display:none}@page{margin:1cm}}
</style>
</head>
<body>
<div class="toolbar">
    <a href="<?= BASE_URL ?>/muj-ucet.php?tab=orders" class="btn-back">← Moje objednávky</a>
    <button onclick="window.print()" class="btn-print">🖨️ Tisknout / PDF</button>
</div>
<div class="page-wrapper">
<div class="invoice">
    <div class="header">
        <div><div class="logo">CIAO <span>SPRITZ</span></div><div class="logo-sub">aperitivo italiano</div></div>
        <div class="inv-title"><h1>FAKTURA</h1><div class="inv-num"><?= e($invoiceNumber) ?></div><div class="inv-date">Vystaveno: <?= $invoiceDate ?></div><div class="inv-date">Splatnost: <?= $dueDate ?></div></div>
    </div>
    <div class="addresses">
        <div class="address-block"><h3>Dodavatel</h3><div class="company">Ciao Spritz</div><p>Petr Mašek<br><?= SHOP_EMAIL ?><br><?= SHOP_PHONE ?></p></div>
        <div class="address-block"><h3>Odběratel</h3><div class="company"><?= e($order['customer_name']) ?></div><p><?php if($order['address']): ?><?= e($order['address']) ?><br><?= e($order['zip'].' '.$order['city']) ?><br><?php endif; ?><?= e($order['customer_email']) ?><?php if($order['customer_phone']): ?><br><?= e($order['customer_phone']) ?><?php endif; ?></p></div>
        <div class="address-block"><h3>Objednávka</h3><p><strong>Číslo:</strong> <?= e($order['order_number']) ?><br><strong>Doprava:</strong> <?= $shippingNames[$order['shipping_method']] ?? $order['shipping_method'] ?><br><strong>Platba:</strong> <?= $paymentNames[$order['payment_method']] ?? $order['payment_method'] ?></p></div>
    </div>
    <table>
        <thead><tr><th>Položka</th><th>Cena/ks</th><th>Množství</th><th>Celkem</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
        <tr><td><strong><?= e($item['product_name']) ?></strong></td><td><?= number_format($item['price'],0,',',' ') ?> Kč</td><td><?= (int)$item['quantity'] ?> ks</td><td><strong><?= number_format($item['price']*$item['quantity'],0,',',' ') ?> Kč</strong></td></tr>
        <?php endforeach; ?>
        <?php if($order['shipping_price']>0): ?>
        <tr style="color:#666;font-style:italic"><td>Doprava</td><td><?= number_format($order['shipping_price'],0,',',' ') ?> Kč</td><td>1</td><td><?= number_format($order['shipping_price'],0,',',' ') ?> Kč</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div class="totals">
        <div class="total-row"><span style="color:#666">Základ daně (21% DPH)</span><span><?= number_format($vatBase,2,',',' ') ?> Kč</span></div>
        <div class="total-row"><span style="color:#666">DPH 21%</span><span><?= number_format($vatAmount,2,',',' ') ?> Kč</span></div>
        <?php if($order['shipping_price']==0): ?><div class="total-row"><span style="color:#666">Doprava</span><span style="color:#2D7A3A">ZDARMA</span></div><?php endif; ?>
        <div class="total-final"><span style="font-weight:600">Celkem k úhradě</span><span class="amount"><?= number_format($order['total'],0,',',' ') ?> Kč</span></div>
    </div>
    <?php if($order['payment_method']==='prevod'||$order['payment_method']==='bankovní převod'): ?>
    <div class="payment-box"><h3>💳 Platební instrukce</h3>
    <div class="payment-grid">
        <div><div class="pi-label">Číslo účtu</div><div class="pi-value"><?= SHOP_BANK ?></div></div>
        <div><div class="pi-label">Částka</div><div class="pi-value"><?= number_format($order['total'],0,',',' ') ?> Kč</div></div>
        <div><div class="pi-label">Variabilní symbol</div><div class="pi-value"><?= preg_replace('/[^0-9]/','', $order['order_number']) ?></div></div>
        <div><div class="pi-label">Splatnost</div><div class="pi-value"><?= $dueDate ?></div></div>
    </div></div>
    <?php endif; ?>
    <div class="footer">
        <div><strong style="color:#E8631A">Ciao Spritz</strong> · <?= SHOP_EMAIL ?> · <?= SHOP_PHONE ?><br>Děkujeme za Vaši objednávku! 🍊</div>
        <div class="stamp"><div class="stamp-label">Podpis / Razítko</div></div>
    </div>
</div>
</div>
</body>
</html>
