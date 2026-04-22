<?php
$pageTitle = 'Pokladna';
require_once 'includes/header.php';
require_once 'includes/mailer.php';

$lang = LANG;
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) { header('Location: '.BASE_URL.'/kosik.php'); exit; }

// Výpočet
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $zip     = trim($_POST['zip'] ?? '');
    $shipping = $_POST['shipping'] ?? 'kuryrem';
    $payment  = $_POST['payment'] ?? 'prevod';
    $note    = trim($_POST['note'] ?? '');

    // Validace
    if (!$name) $errors[] = t('Vyplňte jméno.', 'Enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('Vyplňte platný email.', 'Enter a valid email.');
    if ($shipping === 'kuryrem') {
        if (!$address) $errors[] = t('Vyplňte adresu.', 'Enter address.');
        if (!$city) $errors[] = t('Vyplňte město.', 'Enter city.');
        if (!$zip) $errors[] = t('Vyplňte PSČ.', 'Enter ZIP code.');
    }

    if (empty($errors)) {
        $shippingPrice = ($shipping === 'osobni' || $subtotal >= FREE_SHIPPING_FROM) ? 0 : SHIPPING_PRICE;
        $total = $subtotal + $shippingPrice;
        $orderNumber = 'CS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, address, city, zip, total, shipping_method, shipping_price, payment_method, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$orderNumber, $name, $email, $phone, $address, $city, $zip, $total, $shipping, $shippingPrice, $payment, $note]);
            $orderId = $pdo->lastInsertId();

            foreach ($cart as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?,?,?,?,?)");
                $stmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity']]);
                // Snížit sklad
                $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$item['quantity'], $item['id']]);
            }

            $pdo->commit();

            // Odeslat HTML emaily přes mailer.php
            $orderData = [
                'id'              => $orderId,
                'order_number'    => $orderNumber,
                'customer_name'   => $name,
                'customer_email'  => $email,
                'customer_phone'  => $phone,
                'address'         => $address,
                'city'            => $city,
                'zip'             => $zip,
                'total'           => $total,
                'shipping_price'  => $shippingPrice,
                'shipping_method' => $shipping,
                'payment_method'  => $payment,
                'note'            => $note,
            ];
            $cartItems = array_values($cart);
            foreach ($cartItems as &$ci) { $ci['product_name'] = $ci['name']; }
            unset($ci);
            sendOrderConfirmation($pdo, $orderData, $cartItems);
            sendOrderAdmin($pdo, $orderData, $cartItems);

            // Přidat věrnostní body
            if (isset($_SESSION["user_id"])) {
                $points = floor($subtotal / 100);
                if ($points > 0) {
                    $expires = date("Y-m-d", strtotime("+1 year"));
                    $pdo->prepare("INSERT INTO loyalty_points (user_id, order_id, points, type, description, expires_at) VALUES (?,?,?,'earned',?,?)")->execute([$_SESSION['user_id'], $orderId, $points, "Objednávka $orderNumber", $expires]);
                }
            }
            // Vyčistit košík
            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = $orderNumber;

            header('Location: ' . BASE_URL . '/dekujeme.php?order=' . $orderNumber);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = t('Chyba při zpracování objednávky. Zkuste to znovu.', 'Error processing order. Please try again.');
        }
    }
}

$shippingPrice = $subtotal >= FREE_SHIPPING_FROM ? 0 : SHIPPING_PRICE;
$total = $subtotal + $shippingPrice;
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a> <span>›</span>
            <a href="<?= BASE_URL ?>/kosik.php"><?= t('Košík', 'Cart') ?></a> <span>›</span>
            <span><?= t('Pokladna', 'Checkout') ?></span>
        </div>
        <h1><?= t('Dokončení <span class="accent">objednávky</span>', 'Complete <span class="accent">order</span>') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                ⚠️ <?= implode('<br>', array_map('e', $errors)) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
        <div class="pokladna-grid" class="checkout-layout" style="display:grid;grid-template-columns:1fr 380px;gap:48px;align-items:start">

            <!-- FORMULÁŘ -->
            <div>
                <!-- Kontaktní údaje -->
                <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px;margin-bottom:24px">
                    <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:24px">
                        1. <?= t('Kontaktní údaje', 'Contact details') ?>
                    </h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group" style="grid-column:1/-1">
                            <label class="form-label"><?= t('Jméno a příjmení *', 'Full name *') ?></label>
                            <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= t('Telefon', 'Phone') ?></label>
                            <input type="tel" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Doprava -->
                <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px;margin-bottom:24px">
                    <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:24px">
                        2. <?= t('Způsob dopravy', 'Shipping method') ?>
                    </h3>

                    <label style="display:flex;align-items:center;gap:16px;padding:16px;border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;margin-bottom:12px;transition:border-color 0.2s" id="ship-kuryrem">
                        <input type="radio" name="shipping" value="kuryrem" <?= ($_POST['shipping'] ?? 'kuryrem') === 'kuryrem' ? 'checked' : '' ?> onchange="toggleAddress(this.value)">
                        <span style="font-size:1.5rem">🚚</span>
                        <div style="flex:1">
                            <div style="font-weight:600"><?= t('Kurýr', 'Courier') ?></div>
                            <div style="font-size:13px;color:var(--gray)"><?= t('Doručení na adresu', 'Delivery to address') ?></div>
                        </div>
                        <div style="font-weight:700;color:var(--orange)">
                            <?= $subtotal >= FREE_SHIPPING_FROM ? t('ZDARMA', 'FREE') : formatPrice(SHIPPING_PRICE) ?>
                        </div>
                    </label>

                    <label style="display:flex;align-items:center;gap:16px;padding:16px;border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:border-color 0.2s" id="ship-osobni">
                        <input type="radio" name="shipping" value="osobni" <?= ($_POST['shipping'] ?? '') === 'osobni' ? 'checked' : '' ?> onchange="toggleAddress(this.value)">
                        <span style="font-size:1.5rem">🏪</span>
                        <div style="flex:1">
                            <div style="font-weight:600"><?= t('Osobní odběr', 'Personal pickup') ?></div>
                            <div style="font-size:13px;color:var(--gray)"><?= t('Po domluvě', 'By appointment') ?></div>
                        </div>
                        <div style="font-weight:700;color:var(--green)"><?= t('ZDARMA', 'FREE') ?></div>
                    </label>

                    <!-- Doručovací adresa -->
                    <div id="address-block" style="margin-top:24px">
                        <div style="display:grid;grid-template-columns:1fr;gap:12px">
                            <div class="form-group">
                                <label class="form-label"><?= t('Ulice a číslo popisné *', 'Street and number *') ?></label>
                                <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? '') ?>">
                            </div>
                            <div style="display:grid;grid-template-columns:1fr auto;gap:12px">
                                <div class="form-group">
                                    <label class="form-label"><?= t('Město *', 'City *') ?></label>
                                    <input type="text" name="city" class="form-control" value="<?= e($_POST['city'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">PSČ *</label>
                                    <input type="text" name="zip" class="form-control" style="width:100px" value="<?= e($_POST['zip'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Platba -->
                <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px;margin-bottom:24px">
                    <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:24px">
                        3. <?= t('Způsob platby', 'Payment method') ?>
                    </h3>

                    <?php
                    $payments = [
                        'karta'   => ['icon' => '💳', 'cs' => 'Platební karta online', 'en' => 'Credit card online'],
                        'prevod'  => ['icon' => '🏦', 'cs' => 'Bankovní převod', 'en' => 'Bank transfer'],
                        'dobirika'=> ['icon' => '📦', 'cs' => 'Dobírka', 'en' => 'Cash on delivery'],
                        'osobni'  => ['icon' => '🤝', 'cs' => 'Platba při osobním odběru', 'en' => 'Pay at pickup'],
                    ];
                    foreach ($payments as $key => $p): ?>
                    <label style="display:flex;align-items:center;gap:16px;padding:14px 16px;border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;margin-bottom:10px;transition:border-color 0.2s">
                        <input type="radio" name="payment" value="<?= $key ?>" <?= ($_POST['payment'] ?? 'prevod') === $key ? 'checked' : '' ?>>
                        <span style="font-size:1.3rem"><?= $p['icon'] ?></span>
                        <span style="font-weight:500"><?= $lang === 'en' ? $p['en'] : $p['cs'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Poznámka -->
                <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px">
                    <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:16px">
                        4. <?= t('Poznámka k objednávce', 'Order note') ?>
                    </h3>
                    <textarea name="note" class="form-control" rows="3" placeholder="<?= t('Nepovinné...', 'Optional...') ?>"><?= e($_POST['note'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- SOUHRN -->
            <div class="checkout-summary-wrap" style="position:sticky;top:100px">
                <div class="cart-summary">
                    <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:20px"><?= t('Shrnutí objednávky', 'Order summary') ?></h3>

                    <!-- Položky -->
                    <?php foreach ($cart as $item): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px">
                        <div>
                            <span style="font-weight:500"><?= e($lang === 'en' && $item['name_en'] ? $item['name_en'] : $item['name']) ?></span>
                            <span style="color:var(--gray)"> × <?= $item['quantity'] ?></span>
                        </div>
                        <span style="font-weight:600"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>

                    <div class="cart-total-row" style="margin-top:8px">
                        <span><?= t('Mezisoučet', 'Subtotal') ?></span>
                        <span><?= formatPrice($subtotal) ?></span>
                    </div>
                    <div class="cart-total-row" id="summary-shipping">
                        <span><?= t('Doprava', 'Shipping') ?></span>
                        <span id="summary-ship-price"><?= $shippingPrice === 0 ? t('ZDARMA', 'FREE') : formatPrice($shippingPrice) ?></span>
                    </div>
                    <div class="cart-total-row">
                        <span style="font-size:1.1rem;font-weight:700"><?= t('Celkem', 'Total') ?></span>
                        <span style="font-size:1.3rem;font-weight:900;color:var(--orange)" id="summary-total"><?= formatPrice($total) ?></span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:24px;padding:16px;font-size:1rem">
                        ✅ <?= t('Odeslat objednávku', 'Place order') ?>
                    </button>

                    <p style="font-size:12px;color:var(--gray);text-align:center;margin-top:12px">
                        <?= t('Odesláním souhlasíte s', 'By submitting you agree to') ?>
                        <a href="<?= BASE_URL ?>/stranka/obchodni-podminky" style="color:var(--orange)"><?= t('obchodními podmínkami', 'terms & conditions') ?></a>.
                    </p>
                </div>
            </div>

        </div>
        </form>
    </div>
</section>

<script>
const subtotal = <?= $subtotal ?>;
const shippingCost = <?= SHIPPING_PRICE ?>;
const freeFrom = <?= FREE_SHIPPING_FROM ?>;

function toggleAddress(val) {
    const block = document.getElementById('address-block');
    block.style.display = val === 'kuryrem' ? 'block' : 'none';

    const price = (val === 'osobni' || subtotal >= freeFrom) ? 0 : shippingCost;
    document.getElementById('summary-ship-price').textContent = price === 0 ? '<?= t('ZDARMA', 'FREE') ?>' : price.toLocaleString('cs-CZ') + ' Kč';
    document.getElementById('summary-total').textContent = (subtotal + price).toLocaleString('cs-CZ') + ' Kč';

    // Highlight vybrané dopravy
    document.querySelectorAll('[id^=ship-]').forEach(el => el.style.borderColor = 'var(--border)');
    document.getElementById('ship-' + val).style.borderColor = 'var(--orange)';
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="shipping"]:checked');
    if (checked) toggleAddress(checked.value);

    // Zvýraznění vybrané platby
    document.querySelectorAll('input[name="payment"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('input[name="payment"]').forEach(r => {
                r.closest('label').style.borderColor = 'var(--border)';
            });
            this.closest('label').style.borderColor = 'var(--orange)';
        });
    });

    const checkedPayment = document.querySelector('input[name="payment"]:checked');
    if (checkedPayment) checkedPayment.closest('label').style.borderColor = 'var(--orange)';
});
</script>

<?php require_once 'includes/footer.php'; ?>
