<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('manager');

$message = '';

// Generování XML
if (isset($_GET['generate'])) {
    $type = $_GET['generate']; // heureka | google

    $products = $pdo->query("
        SELECT p.*,
            COALESCE(
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1),
                (SELECT pi2.filename FROM product_images pi2 WHERE pi2.product_id = p.id ORDER BY pi2.id ASC LIMIT 1),
                p.image
            ) as main_image,
            (SELECT GROUP_CONCAT(t.name_cs SEPARATOR ', ') FROM tags t JOIN product_tags pt ON pt.tag_id = t.id WHERE pt.product_id = p.id) as tag_names
        FROM products p
        WHERE p.active = 1 AND p.stock > 0
        ORDER BY p.id
    ")->fetchAll();

    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="ciao-spritz-' . $type . '-' . date('Y-m-d') . '.xml"');

    $shopUrl = BASE_URL;
    $shopName = 'Ciao Spritz';
    $currency = 'CZK';

    echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";

    if ($type === 'heureka') {
        echo '<SHOP>' . "\n";
        foreach ($products as $p) {
            $price = !empty($p['price_sale']) ? $p['price_sale'] : $p['price'];
            $imgUrl = $p['main_image'] ? $shopUrl . '/uploads/' . $p['main_image'] : '';
            $productUrl = $shopUrl . '/produkt.php?id=' . $p['id'];
            $desc = strip_tags($p['description_cs'] ?? '');
            $catMap = ['napoje' => 'Nápoje a lihoviny | Aperitivy', 'sety' => 'Nápoje a lihoviny | Aperitivy', 'merch' => 'Doplňky a gadgety'];
            $cat = $catMap[$p['category']] ?? 'Nápoje a lihoviny';

            echo '  <SHOPITEM>' . "\n";
            echo '    <ITEM_ID>' . $p['id'] . '</ITEM_ID>' . "\n";
            echo '    <PRODUCTNAME>' . htmlspecialchars($p['name_cs']) . '</PRODUCTNAME>' . "\n";
            echo '    <PRODUCT>' . htmlspecialchars($p['name_cs']) . '</PRODUCT>' . "\n";
            echo '    <DESCRIPTION>' . htmlspecialchars(mb_substr($desc, 0, 500)) . '</DESCRIPTION>' . "\n";
            echo '    <URL>' . htmlspecialchars($productUrl) . '</URL>' . "\n";
            if ($imgUrl) echo '    <IMGURL>' . htmlspecialchars($imgUrl) . '</IMGURL>' . "\n";
            echo '    <PRICE_VAT>' . number_format((float)$price, 2, '.', '') . '</PRICE_VAT>' . "\n";
            echo '    <VAT>21</VAT>' . "\n";
            echo '    <CATEGORY>' . htmlspecialchars($cat) . '</CATEGORY>' . "\n";
            echo '    <MANUFACTURER>Ciao Spritz</MANUFACTURER>' . "\n";
            echo '    <DELIVERY_DATE>3</DELIVERY_DATE>' . "\n";
            if ($p['stock'] > 0) {
                echo '    <STOCK quantity="' . (int)$p['stock'] . '"/>' . "\n";
            }
            if (!empty($p['price_sale']) && $p['price_sale'] < $p['price']) {
                echo '    <PRICE_ORIGINAL>' . number_format((float)$p['price'], 2, '.', '') . '</PRICE_ORIGINAL>' . "\n";
            }
            echo '  </SHOPITEM>' . "\n";
        }
        echo '</SHOP>';

    } elseif ($type === 'google') {
        echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . htmlspecialchars($shopName) . '</title>' . "\n";
        echo '  <link>' . htmlspecialchars($shopUrl) . '</link>' . "\n";
        echo '  <description>Italský aperitiv Ciao Spritz – obchod</description>' . "\n";

        foreach ($products as $p) {
            $price = !empty($p['price_sale']) ? $p['price_sale'] : $p['price'];
            $origPrice = !empty($p['price_sale']) ? $p['price'] : null;
            $imgUrl = $p['main_image'] ? $shopUrl . '/uploads/' . $p['main_image'] : '';
            $productUrl = $shopUrl . '/produkt.php?id=' . $p['id'];
            $desc = strip_tags($p['description_cs'] ?? '');
            $catMap = ['napoje' => 'Food, Beverages & Tobacco > Beverages > Alcoholic Beverages', 'sety' => 'Food, Beverages & Tobacco > Beverages > Alcoholic Beverages', 'merch' => 'Apparel & Accessories'];
            $cat = $catMap[$p['category']] ?? 'Food, Beverages & Tobacco';
            $condition = 'new';

            echo '  <item>' . "\n";
            echo '    <g:id>' . $p['id'] . '</g:id>' . "\n";
            echo '    <g:title>' . htmlspecialchars($p['name_cs']) . '</g:title>' . "\n";
            echo '    <g:description>' . htmlspecialchars(mb_substr($desc, 0, 1000)) . '</g:description>' . "\n";
            echo '    <g:link>' . htmlspecialchars($productUrl) . '</g:link>' . "\n";
            if ($imgUrl) echo '    <g:image_link>' . htmlspecialchars($imgUrl) . '</g:image_link>' . "\n";
            echo '    <g:price>' . number_format((float)$price, 2, '.', '') . ' ' . $currency . '</g:price>' . "\n";
            if ($origPrice) echo '    <g:sale_price>' . number_format((float)$price, 2, '.', '') . ' ' . $currency . '</g:sale_price>' . "\n";
            echo '    <g:availability>in_stock</g:availability>' . "\n";
            echo '    <g:condition>' . $condition . '</g:condition>' . "\n";
            echo '    <g:brand>Ciao Spritz</g:brand>' . "\n";
            echo '    <g:product_type>' . htmlspecialchars($cat) . '</g:product_type>' . "\n";
            echo '    <g:google_product_category>' . htmlspecialchars($cat) . '</g:google_product_category>' . "\n";
            echo '    <g:shipping>' . "\n";
            echo '      <g:country>CZ</g:country>' . "\n";
            echo '      <g:service>Kurýr</g:service>' . "\n";
            echo '      <g:price>120.00 CZK</g:price>' . "\n";
            echo '    </g:shipping>' . "\n";
            echo '    <g:identifier_exists>no</g:identifier_exists>' . "\n";
            echo '  </item>' . "\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XML Export — Admin Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .content{max-width:900px;margin:28px auto;padding:0 24px}
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:20px}
        .card-header{padding:16px 24px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between}
        .card-header h2{font-size:1rem;font-weight:700}
        .card-body{padding:24px}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:inherit}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-blue{background:#007bff;color:white}.btn-blue:hover{background:#0056b3}
        .btn-orange{background:#E8631A;color:white}.btn-orange:hover{background:#c4521a}
        .export-card{display:flex;align-items:center;gap:20px;padding:20px;border:1px solid #e0e0e0;border-radius:10px;margin-bottom:12px;background:white}
        .export-logo{width:56px;height:56px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0}
        .export-info h3{font-size:1rem;font-weight:700;margin-bottom:4px}
        .export-info p{font-size:13px;color:#666;line-height:1.5}
        .export-actions{margin-left:auto;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
        .badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700}
        .badge-green{background:rgba(45,122,58,.1);color:#2D7A3A}
        .badge-blue{background:rgba(0,123,255,.1);color:#007bff}
        .url-box{background:#f8f8f8;border:1px solid #e0e0e0;border-radius:8px;padding:12px 16px;font-family:monospace;font-size:12px;color:#444;word-break:break-all;margin-top:8px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th{text-align:left;padding:8px 16px;background:#fafafa;border-bottom:1px solid #e0e0e0;font-size:11px;font-weight:700;text-transform:uppercase;color:#888}
        td{padding:10px 16px;border-bottom:1px solid #f5f5f5}
        tr:last-child td{border-bottom:none}
    </style>
</head>
<body>

<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <h1>📤 XML Export — Heureka & Google Merchant</h1>
</div>

<div class="content">

    <!-- HEUREKA -->
    <div class="export-card">
        <div class="export-logo" style="background:#ff6600;color:white">🛍️</div>
        <div class="export-info">
            <h3>Heureka.cz <span class="badge badge-green">CZ standard</span></h3>
            <p>XML feed ve formátu Heureka SHOP XML. Obsahuje všechny aktivní produkty se skladem > 0.<br>
            Nahraj URL do Heureka administrace pod <strong>Produktový katalog → Import XML</strong>.</p>
            <div class="url-box"><?= BASE_URL ?>/admin/export.php?generate=heureka</div>
        </div>
        <div class="export-actions">
            <a href="<?= BASE_URL ?>/admin/export.php?generate=heureka" class="btn btn-orange" download>
                ⬇️ Stáhnout XML
            </a>
            <a href="<?= BASE_URL ?>/admin/export.php?generate=heureka" target="_blank" class="btn" style="background:#f5f5f5;color:#444;padding:8px 16px;font-size:12px">
                👁️ Náhled
            </a>
        </div>
    </div>

    <!-- GOOGLE MERCHANT -->
    <div class="export-card">
        <div class="export-logo" style="background:#4285f4;color:white">🔍</div>
        <div class="export-info">
            <h3>Google Merchant Center <span class="badge badge-blue">RSS 2.0</span></h3>
            <p>Product feed ve formátu Google Merchant Center RSS 2.0 s namespace g:.<br>
            V Google Merchant Center: <strong>Produkty → Zdroje dat → Přidat zdroj → URL zdroje</strong>.</p>
            <div class="url-box"><?= BASE_URL ?>/admin/export.php?generate=google</div>
        </div>
        <div class="export-actions">
            <a href="<?= BASE_URL ?>/admin/export.php?generate=google" class="btn btn-blue" download>
                ⬇️ Stáhnout XML
            </a>
            <a href="<?= BASE_URL ?>/admin/export.php?generate=google" target="_blank" class="btn" style="background:#f5f5f5;color:#444;padding:8px 16px;font-size:12px">
                👁️ Náhled
            </a>
        </div>
    </div>

    <!-- NÁHLED PRODUKTŮ -->
    <div class="card">
        <div class="card-header">
            <h2>📦 Produkty v exportu</h2>
            <span style="font-size:13px;color:#888">aktivní · skladem > 0</span>
        </div>
        <div class="card-body" style="padding:0">
            <?php
            $exportProducts = $pdo->query("
                SELECT p.id, p.name_cs, p.price, p.price_sale, p.stock, p.category,
                    COALESCE((SELECT pi.filename FROM product_images pi WHERE pi.product_id=p.id AND pi.is_main=1 LIMIT 1), p.image) as main_image
                FROM products p WHERE p.active=1 AND p.stock>0 ORDER BY p.id
            ")->fetchAll();
            ?>
            <table>
                <thead><tr><th>Foto</th><th>Název</th><th>Cena</th><th>Sklad</th><th>Kategorie</th></tr></thead>
                <tbody>
                <?php foreach ($exportProducts as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['main_image']): ?>
                        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($p['main_image']) ?>" style="width:40px;height:40px;object-fit:contain;border-radius:4px;background:#f5f5f5">
                        <?php else: ?>🍾<?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($p['name_cs']) ?></strong></td>
                    <td>
                        <?php if (!empty($p['price_sale'])): ?>
                        <span style="color:#E8631A;font-weight:600"><?= number_format($p['price_sale'],0,',',' ') ?> Kč</span>
                        <small style="text-decoration:line-through;color:#aaa;margin-left:4px"><?= number_format($p['price'],0,',',' ') ?></small>
                        <?php else: ?>
                        <?= number_format($p['price'],0,',',' ') ?> Kč
                        <?php endif; ?>
                    </td>
                    <td><?= $p['stock'] ?> ks</td>
                    <td><?= htmlspecialchars($p['category']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- NÁVOD -->
    <div class="card">
        <div class="card-header"><h2>📋 Jak nastavit automatické stahování</h2></div>
        <div class="card-body">
            <p style="margin-bottom:12px;font-size:14px;color:#555">Místo ručního stahování XML můžeš Heurece a Googlu dát přímo URL — budou si feed stahovat automaticky každých 24 hodin.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div style="background:#fff8f4;border:1px solid #fde8d8;border-radius:8px;padding:16px">
                    <div style="font-weight:700;margin-bottom:8px;color:#E8631A">🛍️ Heureka.cz</div>
                    <ol style="font-size:13px;color:#555;padding-left:16px;line-height:2">
                        <li>Přihlas se na heureka.cz/vendor</li>
                        <li>Produktový katalog → Import XML</li>
                        <li>Vlož URL feedu</li>
                        <li>Nastav interval stahování</li>
                    </ol>
                </div>
                <div style="background:#f0f7ff;border:1px solid #d0e8ff;border-radius:8px;padding:16px">
                    <div style="font-weight:700;margin-bottom:8px;color:#007bff">🔍 Google Merchant</div>
                    <ol style="font-size:13px;color:#555;padding-left:16px;line-height:2">
                        <li>merchants.google.com</li>
                        <li>Produkty → Zdroje dat</li>
                        <li>Přidat zdroj → URL zdroje</li>
                        <li>Vlož URL feedu</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

</div>
</body>
</html>
