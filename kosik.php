<?php
$pageTitle = 'Košík';
require_once 'includes/header.php';

$lang = LANG;
$cart = $_SESSION['cart'] ?? [];

// Výpočet celkové ceny
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$shippingMethod = $_POST['shipping'] ?? 'kuryrem';
$shippingPrice = ($shippingMethod === 'osobni') ? 0 : SHIPPING_PRICE;
if ($subtotal >= FREE_SHIPPING_FROM) $shippingPrice = 0;

$total = $subtotal + $shippingPrice;
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a>
            <span>›</span>
            <span><?= t('Košík', 'Cart') ?></span>
        </div>
        <h1><?= t('Váš <span class="accent">košík</span>', 'Your <span class="accent">cart</span>') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">

        <?php if (empty($cart)): ?>
            <div style="text-align:center;padding:80px 0">
                <div style="font-size:4rem;margin-bottom:24px">🛒</div>
                <h2 style="font-family:var(--font-display);margin-bottom:16px"><?= t('Košík je prázdný', 'Your cart is empty') ?></h2>
                <p style="color:var(--gray);margin-bottom:32px"><?= t('Přidejte produkty a vraťte se sem.', 'Add products and come back here.') ?></p>
                <a href="<?= BASE_URL ?>/produkty.php" class="btn btn-primary"><?= t('Přejít na produkty', 'Go to products') ?></a>
            </div>

        <?php else: ?>
        <div class="kosik-grid" class="cart-layout" style="display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start">

            <!-- POLOŽKY -->
            <div>
                <?php if ($subtotal < FREE_SHIPPING_FROM): ?>
                <div class="free-shipping-bar">
                    🚚 <?= t(
                        'Přidejte produkty za ' . formatPrice(FREE_SHIPPING_FROM - $subtotal) . ' a máte dopravu ZDARMA!',
                        'Add ' . formatPrice(FREE_SHIPPING_FROM - $subtotal) . ' more for FREE shipping!'
                    ) ?>
                </div>
                <?php else: ?>
                <div class="free-shipping-bar">✅ <?= t('Máte dopravu ZDARMA!', 'You have FREE shipping!') ?></div>
                <?php endif; ?>

                <table class="cart-table">
                    <thead>
                        <tr>
                            <th><?= t('Produkt', 'Product') ?></th>
                            <th><?= t('Cena', 'Price') ?></th>
                            <th><?= t('Množství', 'Qty') ?></th>
                            <th><?= t('Celkem', 'Total') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $id => $item): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:16px">
                                    <div style="width:60px;height:60px;background:var(--gray-light);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">
                                        <?php if ($item['image']): ?>
                                            <img src="<?= BASE_URL ?>/uploads/<?= e($item['image']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
                                        <?php else: ?>
                                            🍾
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600"><?= e($lang === 'en' && $item['name_en'] ? $item['name_en'] : $item['name']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= formatPrice($item['price']) ?></td>
                            <td>
                                <div class="cart-qty">
                                    <button class="qty-btn" data-action="decrease" data-id="<?= $id ?>">−</button>
                                    <span><?= $item['quantity'] ?></span>
                                    <button class="qty-btn" data-action="increase" data-id="<?= $id ?>">+</button>
                                </div>
                            </td>
                            <td style="font-weight:700"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                            <td>
                                <button class="qty-btn" data-action="remove" data-id="<?= $id ?>" style="color:#dc3545;border-color:#dc3545" title="<?= t('Odebrat', 'Remove') ?>">✕</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- SOUHRN -->
            <div class="cart-summary">
                <h3 style="font-family:var(--font-display);font-size:1.3rem;margin-bottom:24px"><?= t('Souhrn objednávky', 'Order summary') ?></h3>

                <div class="cart-total-row">
                    <span><?= t('Mezisoučet', 'Subtotal') ?></span>
                    <span><?= formatPrice($subtotal) ?></span>
                </div>

                <!-- Doprava -->
                <div style="margin:16px 0">
                    <label class="form-label"><?= t('Způsob dopravy', 'Shipping method') ?></label>
                    <div style="display:flex;flex-direction:column;gap:8px">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
                            <input type="radio" name="shipping_ui" value="kuryrem" checked onchange="updateShipping(this.value)">
                            🚚 <?= t('Kurýr', 'Courier') ?> — <?= $subtotal >= FREE_SHIPPING_FROM ? t('ZDARMA', 'FREE') : formatPrice(SHIPPING_PRICE) ?>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
                            <input type="radio" name="shipping_ui" value="osobni" onchange="updateShipping(this.value)">
                            🏪 <?= t('Osobní odběr', 'Personal pickup') ?> — <?= t('ZDARMA', 'FREE') ?>
                        </label>
                    </div>
                </div>

                <div class="cart-total-row" id="shipping-row">
                    <span><?= t('Doprava', 'Shipping') ?></span>
                    <span id="shipping-price"><?= $subtotal >= FREE_SHIPPING_FROM ? t('ZDARMA', 'FREE') : formatPrice(SHIPPING_PRICE) ?></span>
                </div>

                <div class="cart-total-row">
                    <span><?= t('Celkem', 'Total') ?></span>
                    <span id="total-price"><?= formatPrice($total) ?></span>
                </div>

                <a href="<?= BASE_URL ?>/pokladna.php" class="btn btn-primary" style="width:100%;margin-top:24px;justify-content:center">
                    <?= t('Přejít k pokladně', 'Proceed to checkout') ?> →
                </a>

                <a href="<?= BASE_URL ?>/produkty.php" style="display:block;text-align:center;margin-top:16px;font-size:14px;color:var(--gray)">
                    ← <?= t('Pokračovat v nákupu', 'Continue shopping') ?>
                </a>

                <!-- Platební metody -->
                <div style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border)">
                    <div style="font-size:12px;color:var(--gray);margin-bottom:12px"><?= t('Přijímáme:', 'We accept:') ?></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <span class="payment-badge" style="background:var(--gray-light);color:var(--gray-dark)">💳 <?= t('Karta', 'Card') ?></span>
                        <span class="payment-badge" style="background:var(--gray-light);color:var(--gray-dark)">🏦 <?= t('Převod', 'Transfer') ?></span>
                        <span class="payment-badge" style="background:var(--gray-light);color:var(--gray-dark)">📦 <?= t('Dobírka', 'COD') ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<script>
const subtotal = <?= $subtotal ?>;
const shippingPrice = <?= SHIPPING_PRICE ?>;
const freeFrom = <?= FREE_SHIPPING_FROM ?>;

function updateShipping(method) {
    let shipping = 0;
    if (method === 'kuryrem' && subtotal < freeFrom) {
        shipping = shippingPrice;
    }
    const total = subtotal + shipping;
    document.getElementById('shipping-price').textContent = shipping === 0 ? '<?= t('ZDARMA', 'FREE') ?>' : shipping + ' Kč';
    document.getElementById('total-price').textContent = total.toLocaleString('cs-CZ') + ' Kč';
}
</script>

<?php require_once 'includes/footer.php'; ?>
