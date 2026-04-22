<?php
/**
 * CIAO SPRITZ - Emailový systém
 * Centrální funkce pro odesílání HTML emailů
 */

// Načti šablony z DB nebo použij výchozí
function getEmailTemplate(PDO $pdo, string $type): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE type=? LIMIT 1");
        $stmt->execute([$type]);
        $tpl = $stmt->fetch();
        if ($tpl) return $tpl;
    } catch (Exception $e) {}
    return getDefaultTemplate($type);
}

function getDefaultTemplate(string $type): array {
    $templates = [
        'order_confirmation' => [
            'type' => 'order_confirmation',
            'subject_cs' => 'Potvrzení objednávky #{order_number} — Ciao Spritz',
            'subject_en' => 'Order confirmation #{order_number} — Ciao Spritz',
            'body_cs' => '<p>Dobrý den <strong>{customer_name}</strong>,</p>
<p>děkujeme za Vaši objednávku! Přijali jsme ji a brzy ji zpracujeme.</p>
{items_table}
<p>Způsob dopravy: <strong>{shipping_method}</strong><br>
Způsob platby: <strong>{payment_method}</strong></p>
{payment_instructions}
<p>V případě dotazů nás kontaktujte na <a href="mailto:rcaffe@email.cz"><?= SHOP_EMAIL ?></a> nebo na tel. <strong><?= SHOP_PHONE ?></strong>.</p>',
            'body_en' => '<p>Dear <strong>{customer_name}</strong>,</p>
<p>Thank you for your order! We have received it and will process it shortly.</p>
{items_table}
<p>Shipping method: <strong>{shipping_method}</strong><br>
Payment method: <strong>{payment_method}</strong></p>
{payment_instructions}
<p>If you have any questions, contact us at <a href="mailto:rcaffe@email.cz"><?= SHOP_EMAIL ?></a> or call <strong><?= SHOP_PHONE ?></strong>.</p>',
        ],
        'order_shipped' => [
            'type' => 'order_shipped',
            'subject_cs' => 'Vaše objednávka #{order_number} byla odeslána — Ciao Spritz',
            'subject_en' => 'Your order #{order_number} has been shipped — Ciao Spritz',
            'body_cs' => '<p>Dobrý den <strong>{customer_name}</strong>,</p>
<p>Vaše objednávka č. <strong>{order_number}</strong> byla právě odeslána! 🚚</p>
<p>Brzy dorazí na Vaši adresu. Sledování zásilky Vám zašleme zvlášť.</p>
<p>V případě dotazů nás kontaktujte na <a href="mailto:rcaffe@email.cz"><?= SHOP_EMAIL ?></a>.</p>',
            'body_en' => '<p>Dear <strong>{customer_name}</strong>,</p>
<p>Your order <strong>{order_number}</strong> has just been shipped! 🚚</p>
<p>It will arrive at your address soon. We will send tracking information separately.</p>
<p>If you have any questions, contact us at <a href="mailto:rcaffe@email.cz"><?= SHOP_EMAIL ?></a>.</p>',
        ],
        'order_ready' => [
            'type' => 'order_ready',
            'subject_cs' => 'Objednávka #{order_number} je připravena k vyzvednutí — Ciao Spritz',
            'subject_en' => 'Order #{order_number} is ready for pickup — Ciao Spritz',
            'body_cs' => '<p>Dobrý den <strong>{customer_name}</strong>,</p>
<p>Vaše objednávka č. <strong>{order_number}</strong> je připravena k osobnímu vyzvednutí! 🎉</p>
<p>Kde nás najdete: <strong>Praha — po dohodě</strong><br>
Kontakt: <strong><?= SHOP_PHONE ?></strong></p>',
            'body_en' => '<p>Dear <strong>{customer_name}</strong>,</p>
<p>Your order <strong>{order_number}</strong> is ready for pickup! 🎉</p>
<p>Location: <strong>Prague — by appointment</strong><br>
Contact: <strong><?= SHOP_PHONE ?></strong></p>',
        ],
        'order_admin' => [
            'type' => 'order_admin',
            'subject_cs' => '🛒 Nová objednávka #{order_number} — {customer_name}',
            'subject_en' => '🛒 New order #{order_number} — {customer_name}',
            'body_cs' => '<p><strong>Nová objednávka na Ciao Spritz e-shopu!</strong></p>
{items_table}
<p>Zákazník: <strong>{customer_name}</strong><br>
Email: <strong>{customer_email}</strong><br>
Telefon: <strong>{customer_phone}</strong><br>
Adresa: <strong>{customer_address}</strong></p>
<p>Doprava: <strong>{shipping_method}</strong><br>
Platba: <strong>{payment_method}</strong><br>
Poznámka: {note}</p>
<p><a href="{admin_url}">→ Zobrazit v adminu</a></p>',
            'body_en' => '<p><strong>New order on Ciao Spritz e-shop!</strong></p>
{items_table}
<p>Customer: <strong>{customer_name}</strong><br>
Email: <strong>{customer_email}</strong><br>
Phone: <strong>{customer_phone}</strong><br>
Address: <strong>{customer_address}</strong></p>
<p>Shipping: <strong>{shipping_method}</strong><br>
Payment: <strong>{payment_method}</strong></p>
<p><a href="{admin_url}">→ View in admin</a></p>',
        ],
    ];
    return $templates[$type] ?? $templates['order_confirmation'];
}

// Wrapper HTML pro emaily
function emailWrapper(string $content, string $title = 'Ciao Spritz'): string {
    return '<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f5f0eb;font-family:\'Helvetica Neue\',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0eb;padding:40px 0">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">

  <!-- HEADER -->
  <tr><td style="background:#111;border-radius:12px 12px 0 0;padding:28px 40px;text-align:center">
    <div style="font-family:Georgia,serif;font-size:28px;font-weight:900;color:white;letter-spacing:2px">
      CIAO <span style="color:#E8631A">SPRITZ</span>
    </div>
    <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-top:4px;letter-spacing:3px;text-transform:uppercase">aperitivo italiano</div>
  </td></tr>

  <!-- OBSAH -->
  <tr><td style="background:white;padding:40px;font-size:15px;line-height:1.7;color:#333">
    ' . $content . '
  </td></tr>

  <!-- FOOTER -->
  <tr><td style="background:#f9f9f9;border-top:1px solid #eee;border-radius:0 0 12px 12px;padding:24px 40px;text-align:center">
    <div style="font-size:13px;color:#888">
      <strong style="color:#E8631A">Ciao Spritz</strong> &nbsp;·&nbsp; 
      <a href="mailto:rcaffe@email.cz" style="color:#888;text-decoration:none"><?= SHOP_EMAIL ?></a> &nbsp;·&nbsp; 
      <?= SHOP_PHONE ?>
    </div>
    <div style="font-size:11px;color:#aaa;margin-top:8px">
      © ' . date('Y') . ' Ciao Spritz — italský aperitiv
    </div>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

// Tabulka produktů pro email
function emailItemsTable(array $items, float $subtotal, float $shippingPrice, float $total): string {
    $rows = '';
    foreach ($items as $item) {
        $itemTotal = number_format($item['price'] * $item['quantity'], 0, ',', ' ');
        $rows .= '<tr>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0">' . htmlspecialchars($item['product_name'] ?? $item['name'] ?? '') . '</td>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:center;color:#888">' . (int)$item['quantity'] . 'x</td>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:600">' . number_format($item['price'], 0, ',', ' ') . ' Kč</td>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:600;color:#E8631A">' . $itemTotal . ' Kč</td>
        </tr>';
    }
    $shippingRow = $shippingPrice > 0
        ? '<tr><td colspan="3" style="padding:8px 12px;text-align:right;color:#888">Doprava:</td><td style="padding:8px 12px;text-align:right">' . number_format($shippingPrice, 0, ',', ' ') . ' Kč</td></tr>'
        : '<tr><td colspan="3" style="padding:8px 12px;text-align:right;color:#888">Doprava:</td><td style="padding:8px 12px;text-align:right;color:#2D7A3A;font-weight:600">ZDARMA</td></tr>';

    return '<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;font-size:14px">
        <thead>
        <tr style="background:#f9f9f9">
            <th style="padding:10px 12px;text-align:left;font-size:12px;color:#888;font-weight:600;text-transform:uppercase">Produkt</th>
            <th style="padding:10px 12px;text-align:center;font-size:12px;color:#888;font-weight:600;text-transform:uppercase">Ks</th>
            <th style="padding:10px 12px;text-align:right;font-size:12px;color:#888;font-weight:600;text-transform:uppercase">Cena/ks</th>
            <th style="padding:10px 12px;text-align:right;font-size:12px;color:#888;font-weight:600;text-transform:uppercase">Celkem</th>
        </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
        <tfoot>
        ' . $shippingRow . '
        <tr style="background:#f9f9f9">
            <td colspan="3" style="padding:12px;text-align:right;font-weight:700;font-size:16px">Celkem k úhradě:</td>
            <td style="padding:12px;text-align:right;font-weight:900;font-size:18px;color:#E8631A">' . number_format($total, 0, ',', ' ') . ' Kč</td>
        </tr>
        </tfoot>
    </table>';
}

// Instrukce k platbě
function paymentInstructions(string $method, float $total, string $orderNumber): string {
    if ($method === 'prevod' || $method === 'bankovní převod') {
        return '<div style="background:#f0f8f1;border:1px solid #c3e6cb;border-radius:8px;padding:20px;margin:20px 0">
            <div style="font-weight:700;color:#2D7A3A;margin-bottom:12px">💳 Platební instrukce — bankovní převod</div>
            <table style="font-size:14px;line-height:2">
                <tr><td style="color:#888;padding-right:16px">Číslo účtu:</td><td><strong>2502996691/2010</strong></td></tr>
                <tr><td style="color:#888;padding-right:16px">Částka:</td><td><strong>' . number_format($total, 0, ',', ' ') . ' Kč</strong></td></tr>
                <tr><td style="color:#888;padding-right:16px">VS:</td><td><strong>' . preg_replace('/[^0-9]/', '', $orderNumber) . '</strong></td></tr>
                <tr><td style="color:#888;padding-right:16px">Zpráva:</td><td><strong>' . $orderNumber . '</strong></td></tr>
            </table>
            <div style="font-size:12px;color:#888;margin-top:8px">Objednávku zpracujeme po přijetí platby.</div>
        </div>';
    }
    if ($method === 'hotovost' || $method === 'dobírka') {
        return '<div style="background:#fff8f0;border:1px solid #ffd0a8;border-radius:8px;padding:16px;margin:20px 0;font-size:14px">
            💵 Platba <strong>' . htmlspecialchars($method) . '</strong> — částku ' . number_format($total, 0, ',', ' ') . ' Kč uhradíte při ' . ($method === 'dobírka' ? 'převzetí zásilky' : 'osobním odběru') . '.
        </div>';
    }
    return '';
}

// Hlavní funkce: odeslání emailu
function sendEmail(string $to, string $subject, string $htmlBody, string $from = 'Ciao Spritz <rcaffe@email.cz>'): bool {
    $boundary = md5(time());
    $headers  = "From: $from\r\n";
    $headers .= "Reply-To: rcaffe@email.cz\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Ciao Spritz Mailer\r\n";

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
}

// Odeslat potvrzení objednávky zákazníkovi
function sendOrderConfirmation(PDO $pdo, array $order, array $items): void {
    $lang = 'cs';
    $tpl = getEmailTemplate($pdo, 'order_confirmation');

    $shippingNames = ['kuryrem' => 'Kurýr GLS', 'osobni' => 'Osobní odběr', 'zasilkovna' => 'Zásilkovna'];
    $paymentNames = ['prevod' => 'Bankovní převod', 'hotovost' => 'Hotovost', 'dobírka' => 'Dobírka'];

    $itemsTable = emailItemsTable($items, $order['total'] - $order['shipping_price'], $order['shipping_price'], $order['total']);
    $paymentInstr = paymentInstructions($order['payment_method'], $order['total'], $order['order_number']);

    $body = $tpl['body_cs'];
    $body = str_replace(
        ['{customer_name}', '{order_number}', '{items_table}', '{shipping_method}', '{payment_method}', '{payment_instructions}'],
        [htmlspecialchars($order['customer_name']), $order['order_number'], $itemsTable, $shippingNames[$order['shipping_method']] ?? $order['shipping_method'], $paymentNames[$order['payment_method']] ?? $order['payment_method'], $paymentInstr],
        $body
    );

    $subject = str_replace('{order_number}', $order['order_number'], $tpl['subject_cs']);
    sendEmail($order['customer_email'], $subject, emailWrapper($body));
}

// Odeslat oznámení adminovi
function sendOrderAdmin(PDO $pdo, array $order, array $items): void {
    $tpl = getEmailTemplate($pdo, 'order_admin');

    $itemsTable = emailItemsTable($items, $order['total'] - $order['shipping_price'], $order['shipping_price'], $order['total']);
    $adminUrl = BASE_URL . '/admin/index.php?section=orders';

    $body = $tpl['body_cs'];
    $body = str_replace(
        ['{customer_name}', '{customer_email}', '{customer_phone}', '{customer_address}', '{order_number}', '{items_table}', '{shipping_method}', '{payment_method}', '{note}', '{admin_url}'],
        [htmlspecialchars($order['customer_name']), htmlspecialchars($order['customer_email']), htmlspecialchars($order['customer_phone'] ?? ''), htmlspecialchars(($order['address'] ?? '') . ', ' . ($order['zip'] ?? '') . ' ' . ($order['city'] ?? '')), $order['order_number'], $itemsTable, $order['shipping_method'], $order['payment_method'], htmlspecialchars($order['note'] ?? '—'), $adminUrl],
        $body
    );

    $subject = str_replace(['{order_number}', '{customer_name}'], [$order['order_number'], $order['customer_name']], $tpl['subject_cs']);
    sendEmail('<?= SHOP_EMAIL ?>', $subject, emailWrapper($body));
}

// Odeslat při změně stavu objednávky
function sendOrderStatusEmail(PDO $pdo, array $order, string $status): void {
    $statusMap = [
        'odeslana'   => 'order_shipped',
        'pripravena' => 'order_ready',
    ];
    $tplType = $statusMap[$status] ?? null;
    if (!$tplType) return;

    $tpl = getEmailTemplate($pdo, $tplType);
    $body = str_replace(
        ['{customer_name}', '{order_number}'],
        [htmlspecialchars($order['customer_name']), $order['order_number']],
        $tpl['body_cs']
    );
    $subject = str_replace('{order_number}', $order['order_number'], $tpl['subject_cs']);
    sendEmail($order['customer_email'], $subject, emailWrapper($body));
}
