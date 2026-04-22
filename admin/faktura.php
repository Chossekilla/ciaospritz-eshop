<?php
/**
 * CIAO SPRITZ - Faktura
 * Generuje HTML fakturu optimalizovanou pro tisk/PDF
 * Použití: /admin/faktura.php?id=ORDER_ID nebo /admin/faktura.php?order=CS-2026...
 */
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();

// Načti objednávku
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $stmt->execute([(int)$_GET['id']]);
} elseif (isset($_GET['order'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=?");
    $stmt->execute([$_GET['order']]);
} else {
    die('Chybí ID objednávky.');
}

$order = $stmt->fetch();
if (!$order) die('Objednávka nenalezena.');

// Načti položky
$items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$order['id']]);
$items = $items->fetchAll();

// Číslo faktury = číslo objednávky
$invoiceNumber = 'FAK-' . preg_replace('/[^0-9]/', '', $order['order_number']);
$invoiceDate = date('d. m. Y', strtotime($order['created_at']));
$dueDate = date('d. m. Y', strtotime($order['created_at'] . ' +14 days'));

$subtotal = $order['total'] - $order['shipping_price'];
$vatRate = 21;
$vatBase = round($order['total'] / (1 + $vatRate/100), 2);
$vatAmount = round($order['total'] - $vatBase, 2);

$shippingNames = [
    'kuryrem' => 'Kurýr GLS',
    'osobni' => 'Osobní odběr',
    'zasilkovna' => 'Zásilkovna',
];
$paymentNames = [
    'prevod' => 'Bankovní převod',
    'hotovost' => 'Hotovost',
    'dobírka' => 'Dobírka',
    'karta' => 'Platební karta',
];

$shippingName = $shippingNames[$order['shipping_method']] ?? $order['shipping_method'];
$paymentName = $paymentNames[$order['payment_method']] ?? $order['payment_method'];

// Tisk = přidej ?print=1
$autoPrint = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktura <?= htmlspecialchars($invoiceNumber) ?> — Ciao Spritz</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 13px;
            color: #222;
            background: #f0f0f0;
            line-height: 1.5;
        }

        .page-wrapper {
            max-width: 800px;
            margin: 32px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Toolbar - skrytý při tisku */
        .toolbar {
            background: #111;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .toolbar a, .toolbar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            font-family: inherit;
        }
        .btn-back { background: rgba(255,255,255,0.1); color: white; }
        .btn-print { background: #E8631A; color: white; }
        .btn-pdf { background: #2D7A3A; color: white; }
        .toolbar-title { color: rgba(255,255,255,0.6); font-size: 13px; margin-left: auto; }

        /* Faktura */
        .invoice {
            padding: 48px;
        }

        /* Header */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 32px;
            border-bottom: 2px solid #f0f0f0;
        }

        .logo {
            font-family: Georgia, serif;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #111;
        }
        .logo span { color: #E8631A; }
        .logo-sub {
            font-size: 11px;
            color: #aaa;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            font-size: 28px;
            font-weight: 900;
            color: #111;
            letter-spacing: -0.5px;
        }
        .invoice-title .invoice-number {
            font-size: 14px;
            color: #E8631A;
            font-weight: 700;
            margin-top: 4px;
        }
        .invoice-title .invoice-date {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }

        /* Adresy */
        .addresses {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 32px;
            margin-bottom: 40px;
        }

        .address-block h3 {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #aaa;
            margin-bottom: 10px;
        }
        .address-block .company {
            font-size: 14px;
            font-weight: 700;
            color: #111;
            margin-bottom: 4px;
        }
        .address-block p {
            font-size: 12px;
            color: #555;
            line-height: 1.7;
        }

        /* Info řádky */
        .invoice-meta {
            display: flex;
            gap: 24px;
            background: #f9f9f9;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .meta-item {
            flex: 1;
            min-width: 120px;
        }
        .meta-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #aaa;
            margin-bottom: 4px;
        }
        .meta-value {
            font-size: 13px;
            font-weight: 600;
            color: #111;
        }
        .meta-value.highlight {
            color: #E8631A;
        }

        /* Tabulka položek */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .items-table thead tr {
            background: #111;
            color: white;
        }
        .items-table thead th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            text-align: left;
        }
        .items-table thead th:last-child,
        .items-table thead th:nth-child(3),
        .items-table thead th:nth-child(2) {
            text-align: right;
        }
        .items-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
        }
        .items-table tbody tr:hover {
            background: #fafafa;
        }
        .items-table tbody td {
            padding: 14px 16px;
            font-size: 13px;
            vertical-align: middle;
        }
        .items-table tbody td:nth-child(2),
        .items-table tbody td:nth-child(3),
        .items-table tbody td:last-child {
            text-align: right;
        }
        .item-name {
            font-weight: 600;
            color: #111;
        }

        /* Doprava */
        .shipping-row td {
            color: #666;
            font-style: italic;
        }

        /* Souhrn */
        .totals {
            margin-left: auto;
            width: 280px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .total-row:last-child {
            border: none;
        }
        .total-label { color: #666; }
        .total-value { font-weight: 600; }
        .total-final {
            background: #111;
            color: white;
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        .total-final .label {
            font-size: 13px;
            font-weight: 600;
        }
        .total-final .amount {
            font-size: 22px;
            font-weight: 900;
            color: #E8631A;
        }

        /* Platební instrukce */
        .payment-box {
            margin-top: 32px;
            background: #f0f8f1;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 20px 24px;
        }
        .payment-box h3 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #2D7A3A;
            margin-bottom: 12px;
        }
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .payment-item .label {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 3px;
        }
        .payment-item .value {
            font-size: 14px;
            font-weight: 700;
            color: #111;
        }

        /* Patička */
        .invoice-footer {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #aaa;
        }
        .invoice-footer strong {
            color: #E8631A;
        }

        /* Razítko */
        .stamp {
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 20px;
            min-width: 160px;
            font-size: 11px;
            color: #aaa;
        }
        .stamp .stamp-label {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 10px;
            margin-bottom: 24px;
        }

        /* TISK */
        @media print {
            body { background: white; }
            .page-wrapper { margin: 0; border-radius: 0; box-shadow: none; }
            .toolbar { display: none !important; }
            .invoice { padding: 32px; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>

<!-- Toolbar (skrytý při tisku) -->
<div class="toolbar">
    <a href="<?= BASE_URL ?>/admin/index.php?section=orders" class="btn-back">← Zpět na objednávky</a>
    <button onclick="window.print()" class="btn-print">🖨️ Tisknout / PDF</button>
    <span class="toolbar-title">Faktura <?= htmlspecialchars($invoiceNumber) ?> · <?= htmlspecialchars($order['customer_name']) ?></span>
</div>

<div class="page-wrapper">
<div class="invoice">

    <!-- HLAVIČKA -->
    <div class="invoice-header">
        <div>
            <div class="logo">CIAO <span>SPRITZ</span></div>
            <div class="logo-sub">aperitivo italiano</div>
        </div>
        <div class="invoice-title">
            <h1>FAKTURA</h1>
            <div class="invoice-number"><?= htmlspecialchars($invoiceNumber) ?></div>
            <div class="invoice-date">Datum vystavení: <?= $invoiceDate ?></div>
            <div class="invoice-date">Datum splatnosti: <?= $dueDate ?></div>
        </div>
    </div>

    <!-- ADRESY -->
    <div class="addresses">
        <div class="address-block">
            <h3>Dodavatel</h3>
            <div class="company">Ciao Spritz</div>
            <p>
                Petr Mašek<br>
                IČO: doplňte<br>
                DIČ: doplňte<br>
                <?= SHOP_EMAIL ?><br>
                <?= SHOP_PHONE ?>
            </p>
        </div>
        <div class="address-block">
            <h3>Odběratel</h3>
            <div class="company"><?= htmlspecialchars($order['customer_name']) ?></div>
            <p>
                <?php if ($order['address']): ?>
                <?= htmlspecialchars($order['address']) ?><br>
                <?= htmlspecialchars($order['zip'] . ' ' . $order['city']) ?><br>
                <?php endif; ?>
                <?= htmlspecialchars($order['customer_email']) ?><br>
                <?php if ($order['customer_phone']): ?>
                <?= htmlspecialchars($order['customer_phone']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="address-block">
            <h3>Objednávka</h3>
            <p>
                <strong>Číslo:</strong> <?= htmlspecialchars($order['order_number']) ?><br>
                <strong>Doprava:</strong> <?= $shippingName ?><br>
                <strong>Platba:</strong> <?= $paymentName ?><br>
                <?php if ($order['note']): ?>
                <strong>Poznámka:</strong> <?= htmlspecialchars($order['note']) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- POLOŽKY -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Položka</th>
                <th>Cena/ks</th>
                <th>Množství</th>
                <th>Celkem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="item-name"><?= htmlspecialchars($item['product_name']) ?></td>
                <td><?= number_format($item['price'], 0, ',', ' ') ?> Kč</td>
                <td><?= (int)$item['quantity'] ?> ks</td>
                <td><strong><?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> Kč</strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($order['shipping_price'] > 0): ?>
            <tr class="shipping-row">
                <td>Doprava — <?= $shippingName ?></td>
                <td><?= number_format($order['shipping_price'], 0, ',', ' ') ?> Kč</td>
                <td>1</td>
                <td><?= number_format($order['shipping_price'], 0, ',', ' ') ?> Kč</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- SOUHRN -->
    <div class="totals">
        <div class="total-row">
            <span class="total-label">Základ daně (<?= $vatRate ?>% DPH)</span>
            <span class="total-value"><?= number_format($vatBase, 2, ',', ' ') ?> Kč</span>
        </div>
        <div class="total-row">
            <span class="total-label">DPH <?= $vatRate ?>%</span>
            <span class="total-value"><?= number_format($vatAmount, 2, ',', ' ') ?> Kč</span>
        </div>
        <?php if ($order['shipping_price'] == 0): ?>
        <div class="total-row">
            <span class="total-label">Doprava</span>
            <span class="total-value" style="color:#2D7A3A">ZDARMA</span>
        </div>
        <?php endif; ?>
        <div class="total-final">
            <span class="label">Celkem k úhradě</span>
            <span class="amount"><?= number_format($order['total'], 0, ',', ' ') ?> Kč</span>
        </div>
    </div>

    <!-- PLATEBNÍ INSTRUKCE -->
    <?php if ($order['payment_method'] === 'prevod' || $order['payment_method'] === 'bankovní převod'): ?>
    <div class="payment-box">
        <h3>💳 Platební instrukce — bankovní převod</h3>
        <div class="payment-grid">
            <div class="payment-item">
                <div class="label">Číslo účtu</div>
                <div class="value"><?= SHOP_BANK ?></div>
            </div>
            <div class="payment-item">
                <div class="label">Částka</div>
                <div class="value"><?= number_format($order['total'], 0, ',', ' ') ?> Kč</div>
            </div>
            <div class="payment-item">
                <div class="label">Variabilní symbol</div>
                <div class="value"><?= preg_replace('/[^0-9]/', '', $order['order_number']) ?></div>
            </div>
            <div class="payment-item">
                <div class="label">Splatnost</div>
                <div class="value"><?= $dueDate ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PATIČKA -->
    <div class="invoice-footer">
        <div>
            <strong>Ciao Spritz</strong> · <?= SHOP_EMAIL ?> · <?= SHOP_PHONE ?><br>
            Děkujeme za Vaši objednávku! 🍊
        </div>
        <div class="stamp">
            <div class="stamp-label">Podpis / Razítko</div>
        </div>
    </div>

</div>
</div>

<?php if ($autoPrint): ?>
<script>window.onload = function() { window.print(); }</script>
<?php endif; ?>

</body>
</html>
