<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('manager');

$id = (int)($_GET['id'] ?? 0);
$product = null;
$productImages = [];
$productTags = [];
$productVariants = [];
$productRelated = [];
$allTags = $pdo->query("SELECT * FROM tags ORDER BY name_cs")->fetchAll();
$allProducts = $pdo->query("SELECT id, name_cs FROM products WHERE active=1 ORDER BY name_cs")->fetchAll();
$success = '';
$error = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) { header('Location: index.php?section=products'); exit; }

    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
    $stmt->execute([$id]);
    $productImages = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT tag_id FROM product_tags WHERE product_id = ?");
    $stmt->execute([$id]);
    $productTags = array_column($stmt->fetchAll(), 'tag_id');

    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$id]);
    $productVariants = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT related_id FROM product_related WHERE product_id = ?");
    $stmt->execute([$id]);
    $productRelated = array_column($stmt->fetchAll(), 'related_id');
}

// ULOŽENÍ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    // Smazání obrázku
    if ($action === 'delete_image' && isset($_POST['image_id'])) {
        $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE id = ? AND product_id = ?");
        $stmt->execute([$_POST['image_id'], $id]);
        $img = $stmt->fetch();
        if ($img) {
            @unlink('../uploads/' . $img['filename']);
            $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$_POST['image_id']]);
        }
        header('Location: product-edit.php?id=' . $id . '&msg=image_deleted');
        exit;
    }

    // Smazání varianty
    if ($action === 'delete_variant' && isset($_POST['variant_id'])) {
        $pdo->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?")->execute([$_POST['variant_id'], $id]);
        header('Location: product-edit.php?id=' . $id . '&msg=variant_deleted');
        exit;
    }

    // Uložení hlavního produktu
    if ($action === 'save') {
        $data = [
            'name_cs'       => trim($_POST['name_cs'] ?? ''),
            'name_en'       => trim($_POST['name_en'] ?? ''),
            'slug'          => trim($_POST['slug'] ?? ''),
            'short_desc_cs' => trim($_POST['short_desc_cs'] ?? ''),
            'short_desc_en' => trim($_POST['short_desc_en'] ?? ''),
            'description_cs'=> trim($_POST['description_cs'] ?? ''),
            'description_en'=> trim($_POST['description_en'] ?? ''),
            'price'         => (float)str_replace(',', '.', $_POST['price'] ?? 0),
            'price_sale'    => !empty($_POST['price_sale']) ? (float)str_replace(',', '.', $_POST['price_sale']) : null,
            'price_sale_from'=> !empty($_POST['price_sale_from']) ? $_POST['price_sale_from'] : null,
            'price_sale_to' => !empty($_POST['price_sale_to']) ? $_POST['price_sale_to'] : null,
            'category'      => $_POST['category'] ?? 'napoje',
            'stock'         => (int)($_POST['stock'] ?? 0),
            'stock_min'     => (int)($_POST['stock_min'] ?? 5),
            'weight'        => !empty($_POST['weight']) ? (float)$_POST['weight'] : null,
            'badge'         => !empty($_POST['badge']) ? $_POST['badge'] : null,
            'featured'      => isset($_POST['featured']) ? 1 : 0,
            'active'        => isset($_POST['active']) ? 1 : 0,
            'sort_order'    => (int)($_POST['sort_order'] ?? 0),
            'meta_title_cs' => trim($_POST['meta_title_cs'] ?? ''),
            'meta_desc_cs'  => trim($_POST['meta_desc_cs'] ?? ''),
            'meta_title_en' => trim($_POST['meta_title_en'] ?? ''),
            'meta_desc_en'  => trim($_POST['meta_desc_en'] ?? ''),
        ];

        // Auto-generuj slug
        if (empty($data['slug']) && $data['name_cs']) {
            $slug = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $data['name_cs']));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $data['slug'] = trim($slug, '-');
        }

        if (!$data['name_cs'] || $data['price'] <= 0) {
            $error = 'Vyplňte název a cenu.';
        } else {
            try {
                if ($id) {
                    $pdo->prepare("UPDATE products SET name_cs=?,name_en=?,slug=?,short_desc_cs=?,short_desc_en=?,description_cs=?,description_en=?,price=?,price_sale=?,price_sale_from=?,price_sale_to=?,category=?,stock=?,stock_min=?,weight=?,badge=?,featured=?,active=?,sort_order=?,meta_title_cs=?,meta_desc_cs=?,meta_title_en=?,meta_desc_en=? WHERE id=?")
                        ->execute([...array_values($data), $id]);
                } else {
                    $pdo->prepare("INSERT INTO products (name_cs,name_en,slug,short_desc_cs,short_desc_en,description_cs,description_en,price,price_sale,price_sale_from,price_sale_to,category,stock,stock_min,weight,badge,featured,active,sort_order,meta_title_cs,meta_desc_cs,meta_title_en,meta_desc_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute(array_values($data));
                    $id = $pdo->lastInsertId();
                }

                // Štítky
                $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$id]);
                foreach ($_POST['tags'] ?? [] as $tagId) {
                    $pdo->prepare("INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?,?)")->execute([$id, (int)$tagId]);
                }

                // Křížový prodej
                $pdo->prepare("DELETE FROM product_related WHERE product_id = ?")->execute([$id]);
                foreach ($_POST['related'] ?? [] as $relId) {
                    if ((int)$relId !== $id) {
                        $pdo->prepare("INSERT IGNORE INTO product_related (product_id, related_id) VALUES (?,?)")->execute([$id, (int)$relId]);
                    }
                }

                // Varianta - přidat novou
                if (!empty($_POST['variant_name_cs'])) {
                    $pdo->prepare("INSERT INTO product_variants (product_id,name_cs,name_en,price,price_sale,stock,sku,sort_order) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$id, trim($_POST['variant_name_cs']), trim($_POST['variant_name_en'] ?? ''), (float)($_POST['variant_price'] ?? 0), !empty($_POST['variant_price_sale']) ? (float)$_POST['variant_price_sale'] : null, (int)($_POST['variant_stock'] ?? 0), trim($_POST['variant_sku'] ?? ''), (int)($_POST['variant_sort'] ?? 0)]);
                }

                // Přidat fotky z media knihovny
                if (!empty($_POST['media_files'])) {
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id=?");
                    $stmtCount->execute([$id]);
                    $existingCount = (int)$stmtCount->fetchColumn();
                    $mainSet = $existingCount > 0;
                    foreach ($_POST['media_files'] as $i => $mediaFile) {
                        $mediaFile = basename($mediaFile); // bezpečnost
                        if (!file_exists('../uploads/' . $mediaFile)) continue;
                        if ($existingCount + $i >= 10) break;
                        // Zkontroluj jestli už není přiřazena
                        $stmtCheck = $pdo->prepare("SELECT id FROM product_images WHERE product_id=? AND filename=?");
                        $stmtCheck->execute([$id, $mediaFile]);
                        if ($stmtCheck->fetch()) continue;
                        $isMain = !$mainSet ? 1 : 0;
                        $mainSet = true;
                        $pdo->prepare("INSERT INTO product_images (product_id,filename,alt_cs,alt_en,sort_order,is_main) VALUES (?,?,?,?,?,?)")
                            ->execute([$id, $mediaFile, $data['name_cs'], $data['name_en'], $existingCount + $i, $isMain]);
                    }
                }

                // Nahrání fotek
                if (!empty($_FILES['images']['name'][0])) {
                    $mainSet = false;
                    $existingCount = count($productImages);
                    foreach ($_FILES['images']['name'] as $i => $fname) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                        $maxSize = 5 * 1024 * 1024;
                        if ($_FILES['images']['size'][$i] > $maxSize) continue;

                        // Max 10 fotek celkem
                        if ($existingCount + $i >= 10) continue;
                        $newName = 'product-' . $id . '-' . time() . '-' . $i . '.' . $ext;
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], '../uploads/' . $newName)) {
                            $isMain = ($existingCount === 0 && !$mainSet) ? 1 : 0;
                            $mainSet = true;
                            $pdo->prepare("INSERT INTO product_images (product_id,filename,alt_cs,alt_en,sort_order,is_main) VALUES (?,?,?,?,?,?)")
                                ->execute([$id, $newName, $data['name_cs'], $data['name_en'], $existingCount + $i, $isMain]);
                        }
                    }
                }

                // Nastavit hlavní obrázek
                if (isset($_POST['main_image'])) {
                    $pdo->prepare("UPDATE product_images SET is_main=0 WHERE product_id=?")->execute([$id]);
                    $pdo->prepare("UPDATE product_images SET is_main=1 WHERE id=? AND product_id=?")->execute([$_POST['main_image'], $id]);
                }

                // Aktualizuj hlavní image v products tabulce - vždy
                $mainImg = $pdo->prepare("SELECT filename FROM product_images WHERE product_id=? ORDER BY is_main DESC, id ASC LIMIT 1");
                $mainImg->execute([$id]);
                $main = $mainImg->fetch();
                if ($main) {
                    $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([$main['filename'], $id]);
                }

                logAction($pdo, $id ? 'Upraven produkt' : 'Přidán produkt', "ID: $id, {$data['name_cs']}");
                $success = 'Produkt byl uložen.';

                // Reload dat
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch();
                $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
                $stmt->execute([$id]);
                $productImages = $stmt->fetchAll();
                $stmt = $pdo->prepare("SELECT tag_id FROM product_tags WHERE product_id = ?");
                $stmt->execute([$id]);
                $productTags = array_column($stmt->fetchAll(), 'tag_id');
                $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order");
                $stmt->execute([$id]);
                $productVariants = $stmt->fetchAll();
                $stmt = $pdo->prepare("SELECT related_id FROM product_related WHERE product_id = ?");
                $stmt->execute([$id]);
                $productRelated = array_column($stmt->fetchAll(), 'related_id');

            } catch (Exception $e) {
                $error = 'Chyba: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['image_deleted' => '✅ Obrázek smazán.', 'variant_deleted' => '✅ Varianta smazána.'];
    $success = $msgs[$_GET['msg']] ?? '';
}

$badges = [
    '' => '— Žádný —',
    'novinka' => '🟢 Novinka',
    'akce' => '🔴 Akce',
    'doprodej' => '🟠 Doprodej',
    'bestseller' => '🟣 Bestseller',
    'doporucujeme' => '🔵 Doporučujeme',
    'limitovana' => '🟡 Limitovaná edice',
    'vyprodano' => '⚫ Vyprodáno',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Upravit produkt' : 'Nový produkt' ?> — Admin Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Quill WYSIWYG editor -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}

        /* LAYOUT */
        .admin-wrap{display:grid;grid-template-columns:1fr 320px;gap:20px;max-width:1300px;margin:0 auto;padding:20px 24px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}
        .topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .save-bar{background:white;border-bottom:1px solid #e0e0e0;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:sticky;top:49px;z-index:99}
        .save-bar-title{font-weight:700;font-size:1rem}
        .save-bar-actions{display:flex;gap:10px;align-items:center}

        /* CARDS */
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:16px}
        .card-title{padding:14px 20px;border-bottom:1px solid #f0f0f0;font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:8px;background:#fafafa}
        .card-body{padding:20px}

        /* FORMS */
        .form-group{margin-bottom:16px}
        .form-group:last-child{margin-bottom:0}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px;letter-spacing:.02em}
        label .req{color:#dc3545}
        input[type=text],input[type=number],input[type=date],input[type=email],select,textarea{width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;color:#111;outline:none;transition:border-color .2s,box-shadow .2s;background:white}
        input:focus,select:focus,textarea:focus{border-color:#E8631A;box-shadow:0 0 0 3px rgba(232,99,26,.08)}
        textarea{resize:vertical;min-height:80px}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
        .input-suffix{position:relative}
        .input-suffix input{padding-right:40px}
        .input-suffix span{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#888;font-size:13px;pointer-events:none}

        /* TABS */
        .tabs{display:flex;border-bottom:2px solid #e0e0e0;margin-bottom:20px;gap:0}
        .tab{padding:10px 18px;font-size:13px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:#888;transition:all .2s;user-select:none}
        .tab.active{color:#E8631A;border-bottom-color:#E8631A}
        .tab-panel{display:none}
        .tab-panel.active{display:block}

        /* BUTTONS */
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .2s;font-family:inherit;white-space:nowrap}
        .btn-primary{background:#2D7A3A;color:white}
        .btn-primary:hover{background:#38973f;transform:translateY(-1px)}
        .btn-orange{background:#E8631A;color:white}
        .btn-orange:hover{background:#c4521a}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#555}
        .btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-danger{background:rgba(220,53,69,.08);color:#dc3545;border:1px solid rgba(220,53,69,.2)}
        .btn-danger:hover{background:#dc3545;color:white}
        .btn-sm{padding:6px 12px;font-size:12px}
        .btn-lg{padding:12px 24px;font-size:15px}
        .btn-full{width:100%;justify-content:center}

        /* ALERTS */
        .alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .alert-success{background:rgba(45,122,58,.08);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
        .alert-error{background:rgba(220,53,69,.08);color:#dc3545;border:1px solid rgba(220,53,69,.2)}

        /* IMAGES */
        .images-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:16px}
        .image-item{position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;border:2px solid #e0e0e0;cursor:pointer;transition:border-color .2s}
        .image-item.is-main{border-color:#2D7A3A;box-shadow:0 0 0 3px rgba(45,122,58,.15)}
        .image-item img{width:100%;height:100%;object-fit:cover}
        .image-item .img-overlay{position:absolute;inset:0;background:rgba(0,0,0,0);display:flex;align-items:flex-end;justify-content:center;padding:6px;gap:4px;transition:background .2s;opacity:0}
        .image-item:hover .img-overlay{background:rgba(0,0,0,.5);opacity:1}
        .main-badge{position:absolute;top:6px;left:6px;background:#2D7A3A;color:white;font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px}

        /* UPLOAD DROP ZONE */
        .dropzone{border:2px dashed #e0e0e0;border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:#fafafa}
        .dropzone:hover,.dropzone.drag-over{border-color:#E8631A;background:rgba(232,99,26,.03)}
        .dropzone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .dropzone-icon{font-size:2.5rem;margin-bottom:10px}
        .dropzone-text{font-size:14px;font-weight:600;color:#555;margin-bottom:4px}
        .dropzone-hint{font-size:12px;color:#aaa}

        /* TAGS */
        .tags-grid{display:flex;flex-wrap:wrap;gap:8px}
        .tag-check{display:none}
        .tag-label{padding:6px 14px;border-radius:50px;border:2px solid #e0e0e0;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;user-select:none;display:flex;align-items:center;gap:5px}
        .tag-check:checked + .tag-label{border-color:var(--tc);background:var(--tc);color:white}

        /* BADGE SELECTOR */
        .badge-options{display:flex;flex-wrap:wrap;gap:8px}
        .badge-radio{display:none}
        .badge-opt{padding:7px 14px;border-radius:8px;border:2px solid #e0e0e0;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;user-select:none}
        .badge-radio:checked + .badge-opt{border-color:#E8631A;background:rgba(232,99,26,.08);color:#E8631A}

        /* VARIANTS TABLE */
        .variants-table{width:100%;border-collapse:collapse;font-size:13px}
        .variants-table th{text-align:left;padding:8px 12px;background:#fafafa;border-bottom:1px solid #e0e0e0;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;letter-spacing:.04em}
        .variants-table td{padding:10px 12px;border-bottom:1px solid #f5f5f5;vertical-align:middle}

        /* SEO PREVIEW */
        .seo-preview{background:#f8f8f8;border-radius:8px;padding:16px;margin-top:12px;font-size:13px}
        .seo-preview .seo-url{color:#1a0dab;font-size:14px;font-weight:600;word-break:break-all}
        .seo-preview .seo-title{color:#1a0dab;font-size:18px;margin:4px 0;font-weight:400}
        .seo-preview .seo-desc{color:#545454;line-height:1.5;font-size:13px}

        /* STOCK INDICATOR */
        .stock-indicator{display:flex;align-items:center;gap:8px;font-size:12px;margin-top:4px}
        .stock-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}

        /* TOGGLE SWITCH */
        .toggle-wrap{display:flex;align-items:center;gap:10px;padding:10px 0}
        .toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;inset:0;background:#ddd;border-radius:24px;cursor:pointer;transition:.3s}
        .toggle-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s}
        input:checked + .toggle-slider{background:#2D7A3A}
        input:checked + .toggle-slider:before{transform:translateX(20px)}
        .toggle-label-text{font-size:13px;font-weight:600;cursor:pointer}

        /* QUILL */
        .ql-container{border-bottom-left-radius:8px;border-bottom-right-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;min-height:160px}
        .ql-toolbar{border-top-left-radius:8px;border-top-right-radius:8px;background:#fafafa}
        .ql-editor{min-height:140px}

        /* SIDEBAR */
        .sidebar-sticky{position:sticky;top:100px}

        /* CHAR COUNTER */
        .char-counter{font-size:11px;color:#aaa;text-align:right;margin-top:3px}
        .char-counter.warning{color:#E8631A}
        .char-counter.danger{color:#dc3545}

        /* RELATED */
        .related-product{display:flex;align-items:center;gap:10px;padding:8px 12px;border:1px solid #e0e0e0;border-radius:8px;margin-bottom:6px}
        .related-product input{width:auto}
        .related-product span{font-size:13px}

        /* SCROLLBAR */
        ::-webkit-scrollbar{width:6px;height:6px}
        ::-webkit-scrollbar-thumb{background:#ddd;border-radius:3px}
        ::-webkit-scrollbar-thumb:hover{background:#bbb}
    </style>
</head>
<body>

<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php?section=products">← Produkty</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a>
    <h1><?= $id ? 'Upravit: ' . htmlspecialchars($product['name_cs'] ?? '') : 'Nový produkt' ?></h1>
</div>

<form method="POST" enctype="multipart/form-data" id="productForm">
<input type="hidden" name="action" value="save">

<div class="save-bar">
    <div class="save-bar-title">
        <?= $id ? '✏️ Úprava produktu' : '➕ Nový produkt' ?>
        <?php if ($product): ?>
        <a href="<?= BASE_URL ?>/produkt.php?id=<?= $id ?>" target="_blank" style="font-size:12px;color:#E8631A;font-weight:400;margin-left:12px">🌐 Zobrazit na webu →</a>
        <?php endif; ?>
    </div>
    <div class="save-bar-actions">
        <?php if ($success): ?><span style="color:#2D7A3A;font-size:13px">✅ <?= htmlspecialchars($success) ?></span><?php endif; ?>
        <?php if ($error): ?><span style="color:#dc3545;font-size:13px">⚠️ <?= htmlspecialchars($error) ?></span><?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/index.php?section=products" class="btn btn-outline">Zrušit</a>
        <button type="submit" class="btn btn-primary btn-lg">💾 Uložit produkt</button>
    </div>
</div>

<div class="admin-wrap">

<!-- LEVÝ SLOUPEC - hlavní obsah -->
<div>

    <!-- TABS pro jazyky -->
    <div class="card">
        <div class="card-title">📝 Název a popis</div>
        <div class="card-body">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('cs')">🇨🇿 Česky</div>
                <div class="tab" onclick="switchTab('en')">🇬🇧 English</div>
            </div>

            <!-- CZ -->
            <div class="tab-panel active" id="tab-cs">
                <div class="form-group">
                    <label>Název <span class="req">*</span></label>
                    <input type="text" name="name_cs" value="<?= htmlspecialchars($product['name_cs'] ?? '') ?>" required placeholder="Název produktu česky" oninput="updateSlug(this.value); updateSeoPreview()">
                </div>
                <div class="form-group">
                    <label>Krátký popis <small style="color:#aaa;font-weight:400">(zobrazí se v přehledu produktů)</small></label>
                    <textarea name="short_desc_cs" rows="2" placeholder="Stručný popis..."><?= htmlspecialchars($product['short_desc_cs'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Popis produktu <small style="color:#aaa;font-weight:400">(detail stránky)</small></label>
                    <div id="editor-cs" style="border-radius:8px;overflow:hidden"></div>
                    <input type="hidden" name="description_cs" id="desc-cs-input">
                </div>
            </div>

            <!-- EN -->
            <div class="tab-panel" id="tab-en">
                <div class="form-group">
                    <label>Product name</label>
                    <input type="text" name="name_en" value="<?= htmlspecialchars($product['name_en'] ?? '') ?>" placeholder="Product name in English">
                </div>
                <div class="form-group">
                    <label>Short description</label>
                    <textarea name="short_desc_en" rows="2" placeholder="Brief description..."><?= htmlspecialchars($product['short_desc_en'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Product description</label>
                    <div id="editor-en" style="border-radius:8px;overflow:hidden"></div>
                    <input type="hidden" name="description_en" id="desc-en-input">
                </div>
            </div>
        </div>
    </div>

    <!-- FOTKY -->
    <div class="card">
        <div class="card-title">🖼️ Fotky produktu
            <span style="font-size:11px;color:#aaa;font-weight:400;margin-left:auto"><?= count($productImages) ?> nahraných · kliknutím nastavíš hlavní</span>
        </div>
        <div class="card-body">

            <!-- Nahrané fotky -->
            <?php if (!empty($productImages)): ?>
            <div class="images-grid" id="imagesGrid">
                <?php foreach ($productImages as $img): ?>
                <div class="image-item <?= $img['is_main'] ? 'is-main' : '' ?>" onclick="setMainImage(<?= $img['id'] ?>)">
                    <?php if ($img['is_main']): ?><div class="main-badge">⭐ Hlavní</div><?php endif; ?>
                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($img['filename']) ?>" alt="">
                    <div class="img-overlay">
                        <button type="button" onclick="event.stopPropagation(); deleteImage(<?= $img['id'] ?>)" class="btn btn-danger btn-sm">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Záložky: Upload / Media knihovna -->
            <div style="display:flex;gap:0;border-bottom:2px solid #e0e0e0;margin-bottom:16px">
                <div class="tab active" id="tabUpload" onclick="switchImgTab('upload')" style="padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;border-bottom:3px solid #E8631A;margin-bottom:-2px;color:#E8631A">📤 Nahrát z disku</div>
                <div class="tab" id="tabMedia" onclick="switchImgTab('media')" style="padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:#888">🗂️ Media knihovna</div>
            </div>

            <!-- Panel: Upload -->
            <div id="panelUpload">
                <div class="dropzone" id="dropzone">
                    <input type="file" name="images[]" id="imageInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImages(this)">
                    <div class="dropzone-icon">📸</div>
                    <div class="dropzone-text">Přetáhněte fotky nebo klikněte pro výběr</div>
                    <div class="dropzone-hint">JPG, PNG, WebP · max 5 MB · více fotek najednou · max 10 fotek celkem</div>
                </div>
                <div id="imagePreview" style="display:none;margin-top:12px">
                    <div style="font-size:12px;font-weight:600;color:#555;margin-bottom:8px">📎 Připraveno k nahrání:</div>
                    <div class="images-grid" id="previewGrid"></div>
                </div>
            </div>

            <!-- Panel: Media knihovna -->
            <div id="panelMedia" style="display:none">
                <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center">
                    <input type="text" id="mediaSearch" placeholder="🔍 Hledat soubor..." oninput="filterMedia(this.value)"
                           style="flex:1;padding:8px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                    <span style="font-size:12px;color:#aaa" id="mediaCount"></span>
                </div>
                <div id="mediaGrid" class="images-grid" style="max-height:380px;overflow-y:auto;padding:4px">
                    <div style="text-align:center;padding:32px;color:#aaa;font-size:13px">Načítám soubory...</div>
                </div>
                <input type="hidden" name="media_files[]" id="mediaFilesInput">
                <div id="mediaSelected" style="display:none;margin-top:10px;padding:10px 14px;background:#f0f8f1;border-radius:8px;font-size:13px;color:#2D7A3A;font-weight:600"></div>
            </div>

            <input type="hidden" name="main_image" id="mainImageInput" value="">
        </div>
    </div>

    <!-- VARIACE -->
    <div class="card">
        <div class="card-title">⚡ Varianty produktu
            <span style="font-size:11px;color:#aaa;font-weight:400;margin-left:auto">např. různé objemy, balení</span>
        </div>
        <div class="card-body">
            <?php if (!empty($productVariants)): ?>
            <table class="variants-table" style="margin-bottom:16px">
                <thead><tr><th>Název</th><th>Cena</th><th>Akční cena</th><th>Sklad</th><th>SKU</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($productVariants as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['name_cs']) ?></strong>
                        <?php if ($v['name_en']): ?><br><small style="color:#aaa"><?= htmlspecialchars($v['name_en']) ?></small><?php endif; ?>
                    </td>
                    <td><?= number_format($v['price'],0,',',' ') ?> Kč</td>
                    <td><?= $v['price_sale'] ? number_format($v['price_sale'],0,',',' ').' Kč' : '—' ?></td>
                    <td><?= $v['stock'] ?> ks</td>
                    <td style="color:#aaa;font-size:11px"><?= htmlspecialchars($v['sku'] ?? '') ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Smazat variantu?')">
                            <input type="hidden" name="action" value="delete_variant">
                            <input type="hidden" name="variant_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div style="background:#f8f8f8;border-radius:8px;padding:16px">
                <div style="font-size:12px;font-weight:700;margin-bottom:12px;color:#555">➕ Přidat variantu</div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Název CZ</label>
                        <input type="text" name="variant_name_cs" placeholder="např. 0,75 l">
                    </div>
                    <div class="form-group">
                        <label>Název EN</label>
                        <input type="text" name="variant_name_en" placeholder="e.g. 0.75 l">
                    </div>
                    <div class="form-group">
                        <label>Cena (Kč)</label>
                        <input type="number" name="variant_price" step="0.01" min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Akční cena</label>
                        <input type="number" name="variant_price_sale" step="0.01" min="0" placeholder="—">
                    </div>
                    <div class="form-group">
                        <label>Sklad (ks)</label>
                        <input type="number" name="variant_stock" min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>SKU / kód</label>
                        <input type="text" name="variant_sku" placeholder="CS-001">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEO -->
    <div class="card">
        <div class="card-title">🔍 SEO nastavení</div>
        <div class="card-body">
            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="slug" id="slugInput" value="<?= htmlspecialchars($product['slug'] ?? '') ?>" placeholder="automaticky-z-nazvu">
                <div style="font-size:11px;color:#aaa;margin-top:3px">URL: <?= BASE_URL ?>/produkt.php?id=<?= $id ?></div>
            </div>
            <div class="tabs" style="margin-top:16px">
                <div class="tab active" onclick="switchSeoTab('cs')">🇨🇿 Česky</div>
                <div class="tab" onclick="switchSeoTab('en')">🇬🇧 English</div>
            </div>
            <div class="tab-panel active" id="seo-tab-cs">
                <div class="form-group">
                    <label>Meta title CZ</label>
                    <input type="text" name="meta_title_cs" id="metaTitleCs" value="<?= htmlspecialchars($product['meta_title_cs'] ?? '') ?>" placeholder="<?= htmlspecialchars($product['name_cs'] ?? '') ?>" oninput="updateSeoPreview()">
                    <div class="char-counter" id="metaTitleCsCount">0 / 60</div>
                </div>
                <div class="form-group">
                    <label>Meta popis CZ</label>
                    <textarea name="meta_desc_cs" id="metaDescCs" rows="2" placeholder="Stručný popis pro vyhledávače..." oninput="updateSeoPreview()"><?= htmlspecialchars($product['meta_desc_cs'] ?? '') ?></textarea>
                    <div class="char-counter" id="metaDescCsCount">0 / 160</div>
                </div>
                <div class="seo-preview" id="seoPreview">
                    <div class="seo-url"><?= BASE_URL ?>/produkt/<span id="previewSlug"><?= htmlspecialchars($product['slug'] ?? 'produkt') ?></span></div>
                    <div class="seo-title" id="previewTitle"><?= htmlspecialchars($product['meta_title_cs'] ?? $product['name_cs'] ?? 'Název produktu') ?></div>
                    <div class="seo-desc" id="previewDesc"><?= htmlspecialchars($product['meta_desc_cs'] ?? 'Popis produktu se zobrazí zde...') ?></div>
                </div>
            </div>
            <div class="tab-panel" id="seo-tab-en">
                <div class="form-group">
                    <label>Meta title EN</label>
                    <input type="text" name="meta_title_en" value="<?= htmlspecialchars($product['meta_title_en'] ?? '') ?>" placeholder="<?= htmlspecialchars($product['name_en'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Meta description EN</label>
                    <textarea name="meta_desc_en" rows="2" placeholder="Brief description for search engines..."><?= htmlspecialchars($product['meta_desc_en'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- KŘÍŽOVÝ PRODEJ -->
    <div class="card">
        <div class="card-title">🔗 Doporučené produkty <span style="font-size:11px;color:#aaa;font-weight:400;margin-left:4px">(zobrazí se na detailu produktu)</span></div>
        <div class="card-body">
            <div style="max-height:200px;overflow-y:auto;display:flex;flex-direction:column;gap:6px">
                <?php foreach ($allProducts as $p): ?>
                <?php if ($p['id'] === $id) continue; ?>
                <label class="related-product">
                    <input type="checkbox" name="related[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $productRelated) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($p['name_cs']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<!-- PRAVÝ SLOUPEC - sidebar -->
<div class="sidebar-sticky">

    <!-- STAV -->
    <div class="card">
        <div class="card-title">⚙️ Stav produktu</div>
        <div class="card-body">
            <label class="toggle-wrap">
                <label class="toggle-switch">
                    <input type="checkbox" name="active" id="activeToggle" <?= ($product['active'] ?? 1) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label-text">Produkt je aktivní (viditelný)</span>
            </label>
            <label class="toggle-wrap">
                <label class="toggle-switch">
                    <input type="checkbox" name="featured" <?= ($product['featured'] ?? 0) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label-text">Zobrazit na úvodní stránce</span>
            </label>
        </div>
    </div>

    <!-- CENA & SKLAD -->
    <div class="card">
        <div class="card-title">💰 Cena a sklad</div>
        <div class="card-body">
            <div class="form-group">
                <label>Běžná cena (Kč) <span class="req">*</span></label>
                <div class="input-suffix">
                    <input type="number" name="price" step="0.01" min="0" required value="<?= $product['price'] ?? '' ?>" placeholder="0">
                    <span>Kč</span>
                </div>
            </div>
            <div class="form-group">
                <label>Akční cena (Kč)</label>
                <div class="input-suffix">
                    <input type="number" name="price_sale" step="0.01" min="0" value="<?= $product['price_sale'] ?? '' ?>" placeholder="—" id="salePriceInput" oninput="toggleSaleDates()">
                    <span>Kč</span>
                </div>
            </div>
            <div id="saleDates" style="display:<?= !empty($product['price_sale']) ? 'block' : 'none' ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Platí od</label>
                        <input type="date" name="price_sale_from" value="<?= $product['price_sale_from'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Platí do</label>
                        <input type="date" name="price_sale_to" value="<?= $product['price_sale_to'] ?? '' ?>">
                    </div>
                </div>
            </div>
            <hr style="border:none;border-top:1px solid #f0f0f0;margin:12px 0">
            <div class="form-group">
                <label>Skladem (ks)</label>
                <input type="number" name="stock" min="0" value="<?= $product['stock'] ?? 0 ?>" id="stockInput" oninput="updateStockIndicator()">
                <div class="stock-indicator" id="stockIndicator">
                    <div class="stock-dot" style="background:#2D7A3A"></div>
                    <span style="color:#2D7A3A">Skladem</span>
                </div>
            </div>
            <div class="form-group">
                <label>Min. sklad — upozornění</label>
                <input type="number" name="stock_min" min="0" value="<?= $product['stock_min'] ?? 5 ?>">
            </div>
            <div class="form-group">
                <label>Hmotnost (kg)</label>
                <div class="input-suffix">
                    <input type="number" name="weight" step="0.001" min="0" value="<?= $product['weight'] ?? '' ?>" placeholder="—">
                    <span>kg</span>
                </div>
            </div>
        </div>
    </div>

    <!-- KATEGORIE -->
    <div class="card">
        <div class="card-title">📂 Kategorie</div>
        <div class="card-body">
            <div class="form-group">
                <label>Kategorie</label>
                <select name="category">
                    <option value="napoje" <?= ($product['category'] ?? '') === 'napoje' ? 'selected' : '' ?>>🍾 Nápoje</option>
                    <option value="sety" <?= ($product['category'] ?? '') === 'sety' ? 'selected' : '' ?>>📦 Sety</option>
                    <option value="merch" <?= ($product['category'] ?? '') === 'merch' ? 'selected' : '' ?>>👒 Merch</option>
                </select>
            </div>
            <div class="form-group">
                <label>Řazení (číslo)</label>
                <input type="number" name="sort_order" min="0" value="<?= $product['sort_order'] ?? 0 ?>">
            </div>
        </div>
    </div>

    <!-- BADGE / ŠTÍTEK -->
    <div class="card">
        <div class="card-title">🏷️ Badge — visačka produktu</div>
        <div class="card-body">
            <div class="badge-options">
                <?php foreach ($badges as $val => $label): ?>
                <label>
                    <input type="radio" name="badge" value="<?= $val ?>" class="badge-radio" <?= ($product['badge'] ?? '') === $val ? 'checked' : '' ?>>
                    <span class="badge-opt"><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ŠTÍTKY / TAGY -->
    <div class="card">
        <div class="card-title">🔖 Štítky (tagy)</div>
        <div class="card-body">
            <div class="tags-grid">
                <?php foreach ($allTags as $tag): ?>
                <label>
                    <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" class="tag-check" <?= in_array($tag['id'], $productTags) ? 'checked' : '' ?>>
                    <span class="tag-label" style="--tc:<?= $tag['color'] ?>">
                        <?= htmlspecialchars($tag['name_cs']) ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ULOŽIT znovu dole -->
    <button type="submit" class="btn btn-primary btn-full btn-lg">💾 Uložit produkt</button>
    <?php if ($id): ?>
    <div style="text-align:center;margin-top:10px">
        <a href="<?= BASE_URL ?>/produkt.php?id=<?= $id ?>" target="_blank" style="font-size:13px;color:#E8631A">🌐 Zobrazit na webu →</a>
    </div>
    <?php endif; ?>

</div>
</div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
// ===== WYSIWYG EDITORY =====
const quillCs = new Quill('#editor-cs', {
    theme: 'snow',
    placeholder: 'Popis produktu česky...',
    modules: {
        toolbar: [
            [{ header: [2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

const quillEn = new Quill('#editor-en', {
    theme: 'snow',
    placeholder: 'Product description in English...',
    modules: {
        toolbar: [
            [{ header: [2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

// Načti existující obsah
quillCs.root.innerHTML = <?= json_encode($product['description_cs'] ?? '') ?>;
quillEn.root.innerHTML = <?= json_encode($product['description_en'] ?? '') ?>;

// Před odesláním formuláře ulož HTML
document.getElementById('productForm').addEventListener('submit', function() {
    document.getElementById('desc-cs-input').value = quillCs.root.innerHTML;
    document.getElementById('desc-en-input').value = quillEn.root.innerHTML;
});

// ===== TABS =====
function switchTab(lang) {
    document.querySelectorAll('#tab-cs, #tab-en').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + lang).classList.add('active');
    document.querySelectorAll('.tabs')[0].querySelectorAll('.tab').forEach((t, i) => {
        t.classList.toggle('active', (lang === 'cs' && i === 0) || (lang === 'en' && i === 1));
    });
}

function switchSeoTab(lang) {
    document.querySelectorAll('#seo-tab-cs, #seo-tab-en').forEach(p => p.classList.remove('active'));
    document.getElementById('seo-tab-' + lang).classList.add('active');
    document.querySelectorAll('.tabs')[1].querySelectorAll('.tab').forEach((t, i) => {
        t.classList.toggle('active', (lang === 'cs' && i === 0) || (lang === 'en' && i === 1));
    });
}

// ===== SLUG AUTO-GENEROVÁNÍ =====
function updateSlug(val) {
    const slug = val.toLowerCase()
        .replace(/[áäàâ]/g,'a').replace(/[čç]/g,'c').replace(/[ďđ]/g,'d')
        .replace(/[éěèê]/g,'e').replace(/[íîì]/g,'i').replace(/[ňñ]/g,'n')
        .replace(/[óöôò]/g,'o').replace(/[řŕ]/g,'r').replace(/[šś]/g,'s')
        .replace(/[ťţ]/g,'t').replace(/[úůüùû]/g,'u').replace(/[ýÿ]/g,'y')
        .replace(/[žź]/g,'z').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    document.getElementById('slugInput').value = slug;
    document.getElementById('previewSlug').textContent = slug || 'produkt';
}

// ===== SEO PREVIEW =====
function updateSeoPreview() {
    const title = document.getElementById('metaTitleCs').value ||
                  document.querySelector('input[name=name_cs]').value || 'Název produktu';
    const desc = document.getElementById('metaDescCs').value || 'Popis produktu se zobrazí zde...';

    document.getElementById('previewTitle').textContent = title;
    document.getElementById('previewDesc').textContent = desc;

    // Char counters
    const tc = document.getElementById('metaTitleCsCount');
    tc.textContent = title.length + ' / 60';
    tc.className = 'char-counter' + (title.length > 60 ? ' danger' : title.length > 50 ? ' warning' : '');

    const dc = document.getElementById('metaDescCsCount');
    dc.textContent = desc.length + ' / 160';
    dc.className = 'char-counter' + (desc.length > 160 ? ' danger' : desc.length > 140 ? ' warning' : '');
}
updateSeoPreview();

// ===== STOCK INDICATOR =====
function updateStockIndicator() {
    const stock = parseInt(document.getElementById('stockInput').value) || 0;
    const min = parseInt(document.querySelector('input[name=stock_min]').value) || 5;
    const indicator = document.getElementById('stockIndicator');
    if (stock <= 0) {
        indicator.innerHTML = '<div class="stock-dot" style="background:#dc3545"></div><span style="color:#dc3545">Vyprodáno</span>';
    } else if (stock <= min) {
        indicator.innerHTML = '<div class="stock-dot" style="background:#E8631A"></div><span style="color:#E8631A">Nízký stav ('+stock+' ks)</span>';
    } else {
        indicator.innerHTML = '<div class="stock-dot" style="background:#2D7A3A"></div><span style="color:#2D7A3A">Skladem ('+stock+' ks)</span>';
    }
}
updateStockIndicator();

// ===== AKČNÍ CENA - zobraz/skryj data =====
function toggleSaleDates() {
    const val = document.getElementById('salePriceInput').value;
    document.getElementById('saleDates').style.display = val ? 'block' : 'none';
}

// ===== OBRÁZKY - preview =====
function previewImages(input) {
    const preview = document.getElementById('imagePreview');
    const grid = document.getElementById('previewGrid');
    grid.innerHTML = '';

    if (input.files.length === 0) { preview.style.display = 'none'; return; }

    preview.style.display = 'block';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'image-item';
            div.innerHTML = '<img src="'+e.target.result+'" alt="">';
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

// ===== NASTAVIT HLAVNÍ OBRÁZEK =====
function setMainImage(id) {
    document.getElementById('mainImageInput').value = id;
    document.querySelectorAll('.image-item').forEach(el => {
        el.classList.remove('is-main');
        const badge = el.querySelector('.main-badge');
        if (badge) badge.remove();
    });
    const clicked = document.querySelector('.image-item[onclick="setMainImage('+id+')"]');
    if (clicked) {
        clicked.classList.add('is-main');
        const badge = document.createElement('div');
        badge.className = 'main-badge';
        badge.textContent = '⭐ Hlavní';
        clicked.prepend(badge);
    }
}

// ===== SMAZAT OBRÁZEK =====
function deleteImage(id) {
    if (!confirm('Smazat tento obrázek?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input name="action" value="delete_image"><input name="image_id" value="'+id+'">';
    document.body.appendChild(form);
    form.submit();
}

// ===== MEDIA KNIHOVNA =====
let allMediaFiles = [];
let selectedMedia = [];

function switchImgTab(tab) {
    const isUpload = tab === 'upload';
    document.getElementById('panelUpload').style.display = isUpload ? 'block' : 'none';
    document.getElementById('panelMedia').style.display = isUpload ? 'none' : 'block';
    document.getElementById('tabUpload').style.borderBottomColor = isUpload ? '#E8631A' : 'transparent';
    document.getElementById('tabUpload').style.color = isUpload ? '#E8631A' : '#888';
    document.getElementById('tabMedia').style.borderBottomColor = !isUpload ? '#E8631A' : 'transparent';
    document.getElementById('tabMedia').style.color = !isUpload ? '#E8631A' : '#888';
    if (!isUpload && allMediaFiles.length === 0) loadMedia();
}

function loadMedia() {
    fetch('<?= BASE_URL ?>/admin/media-list.php')
        .then(r => r.json())
        .then(files => {
            allMediaFiles = files;
            document.getElementById('mediaCount').textContent = files.length + ' souborů';
            renderMedia(files);
        })
        .catch(() => {
            document.getElementById('mediaGrid').innerHTML = '<div style="text-align:center;padding:32px;color:#dc3545;font-size:13px">Chyba načítání. Zkontroluj media-list.php</div>';
        });
}

function renderMedia(files) {
    const grid = document.getElementById('mediaGrid');
    if (!files.length) {
        grid.innerHTML = '<div style="text-align:center;padding:32px;color:#aaa;font-size:13px">Žádné soubory nenalezeny</div>';
        return;
    }
    grid.innerHTML = files.map(f => `
        <div class="image-item ${selectedMedia.includes(f) ? 'is-main' : ''}"
             onclick="toggleMedia('${f}')" style="cursor:pointer;position:relative">
            ${selectedMedia.includes(f) ? '<div class="main-badge" style="background:#2D7A3A">✅</div>' : ''}
            <img src="<?= BASE_URL ?>/uploads/${f}" alt="${f}"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'72\' height=\'72\'%3E%3Crect fill=\'%23f0f0f0\' width=\'72\' height=\'72\'/%3E%3C/svg%3E'">
            <div class="img-overlay"><small style="color:white;font-size:10px;word-break:break-all;padding:2px">${f.substring(0,20)}...</small></div>
        </div>
    `).join('');
}

function toggleMedia(filename) {
    const idx = selectedMedia.indexOf(filename);
    if (idx >= 0) {
        selectedMedia.splice(idx, 1);
    } else {
        if (selectedMedia.length >= 10) { alert('Maximum 10 fotek'); return; }
        selectedMedia.push(filename);
    }
    // Aktualizuj hidden input
    document.getElementById('mediaFilesInput').value = selectedMedia.join(',');
    // Přepíš hidden input na multiple
    const existing = document.querySelectorAll('input[name="media_files[]"]');
    existing.forEach(e => e.remove());
    selectedMedia.forEach(f => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'media_files[]';
        inp.value = f;
        document.getElementById('productForm').appendChild(inp);
    });
    // Update UI
    const selDiv = document.getElementById('mediaSelected');
    if (selectedMedia.length) {
        selDiv.style.display = 'block';
        selDiv.textContent = '✅ Vybráno ' + selectedMedia.length + ' fotek — budou přiřazeny po uložení';
    } else {
        selDiv.style.display = 'none';
    }
    renderMedia(allMediaFiles);
}

function filterMedia(query) {
    const filtered = allMediaFiles.filter(f => f.toLowerCase().includes(query.toLowerCase()));
    document.getElementById('mediaCount').textContent = filtered.length + ' / ' + allMediaFiles.length + ' souborů';
    renderMedia(filtered);
}

// ===== DRAG & DROP =====
const dropzone = document.getElementById('dropzone');
dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const dt = e.dataTransfer;
    if (dt.files.length) {
        document.getElementById('imageInput').files = dt.files;
        previewImages(document.getElementById('imageInput'));
    }
});
</script>
</body>
</html>
