<?php
$pageTitle = 'Produkty';
require_once 'includes/header.php';

$lang = LANG;
$category = isset($_GET['kategorie']) ? $_GET['kategorie'] : 'vse';

$sql = "
    SELECT p.*, 
        COALESCE(
            (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1),
            (SELECT pi2.filename FROM product_images pi2 WHERE pi2.product_id = p.id ORDER BY pi2.id ASC LIMIT 1),
            p.image
        ) as main_image
    FROM products p
    WHERE p.active = 1
";

if ($category !== 'vse' && in_array($category, ['napoje', 'sety', 'merch'])) {
    $stmt = $pdo->prepare($sql . " AND p.category = ? ORDER BY p.sort_order ASC, p.id ASC");
    $stmt->execute([$category]);
} else {
    $stmt = $pdo->query($sql . " ORDER BY p.category, p.sort_order ASC, p.id ASC");
}
$products = $stmt->fetchAll();
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a>
            <span>›</span>
            <span><?= t('Produkty', 'Products') ?></span>
        </div>
        <h1><?= t('Naše <span class="accent">produkty</span>', 'Our <span class="accent">products</span>') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">

        <!-- FILTR -->
        <div class="category-filter">
            <button class="filter-btn <?= $category === 'vse' ? 'active' : '' ?>" onclick="location.href='<?= BASE_URL ?>/produkty.php'"><?= t('Vše', 'All') ?></button>
            <button class="filter-btn <?= $category === 'napoje' ? 'active' : '' ?>" onclick="location.href='<?= BASE_URL ?>/produkty.php?kategorie=napoje'">🍾 <?= t('Nápoje', 'Drinks') ?></button>
            <button class="filter-btn <?= $category === 'sety' ? 'active' : '' ?>" onclick="location.href='<?= BASE_URL ?>/produkty.php?kategorie=sety'">📦 <?= t('Sety', 'Sets') ?></button>
            <button class="filter-btn <?= $category === 'merch' ? 'active' : '' ?>" onclick="location.href='<?= BASE_URL ?>/produkty.php?kategorie=merch'">👒 <?= t('Merch', 'Merch') ?></button>
        </div>

        <?php if (empty($products)): ?>
            <div class="alert alert-info"><?= t('V této kategorii nejsou žádné produkty.', 'No products in this category.') ?></div>
        <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card" data-category="<?= e($product['category']) ?>">
                <a href="<?= BASE_URL ?>/produkt.php?id=<?= $product['id'] ?>">
                    <div class="product-img">
                        <?php if (!empty($product['main_image'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= e($product['main_image']) ?>" alt="<?= e($product['name_' . $lang]) ?>">
                        <?php else: ?>
                            <div class="product-img-placeholder">
                                <?= $product['category'] === 'merch' ? '👒' : '🍾' ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product['badge'])): ?>
                            <div style="position:absolute;top:10px;left:10px;background:var(--orange);color:white;font-size:11px;font-weight:700;padding:3px 10px;border-radius:50px">
                                <?= e($product['badge']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="product-info">
                    <div class="product-category"><?= e($product['category']) ?></div>
                    <div class="product-name">
                        <a href="<?= BASE_URL ?>/produkt.php?id=<?= $product['id'] ?>"><?= e($product['name_' . $lang]) ?></a>
                    </div>
                    <div class="product-desc"><?= strip_tags($product['short_desc_' . $lang] ?? $product['description_' . $lang] ?? '') ?></div>
                    <div class="product-footer">
                        <div>
                            <?php if (!empty($product['price_sale'])): ?>
                                <div class="product-price" style="color:var(--orange)"><?= formatPrice($product['price_sale']) ?></div>
                                <div style="font-size:12px;color:var(--gray);text-decoration:line-through"><?= formatPrice($product['price']) ?></div>
                            <?php else: ?>
                                <div class="product-price"><?= formatPrice($product['price']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($product['stock'] > 0): ?>
                            <button class="btn btn-primary btn-sm add-to-cart" data-id="<?= $product['id'] ?>">
                                🛒 <?= t('Do košíku', 'Add to cart') ?>
                            </button>
                        <?php else: ?>
                            <span style="font-size:13px;color:var(--gray)"><?= t('Vyprodáno', 'Out of stock') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
