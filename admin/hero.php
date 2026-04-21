<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('manager');

// Vytvoř tabulku pokud neexistuje
$pdo->exec("CREATE TABLE IF NOT EXISTS hero_slides (
    id int NOT NULL AUTO_INCREMENT,
    image varchar(255) NOT NULL,
    title_cs varchar(255) DEFAULT NULL,
    title_en varchar(255) DEFAULT NULL,
    subtitle_cs varchar(255) DEFAULT NULL,
    subtitle_en varchar(255) DEFAULT NULL,
    btn_text_cs varchar(100) DEFAULT 'Nakoupit nyní',
    btn_text_en varchar(100) DEFAULT 'Shop now',
    btn_url varchar(255) DEFAULT '/produkty.php',
    sort_order int DEFAULT 0,
    active tinyint DEFAULT 1,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Inicializuj výchozí slidy pokud tabulka prázdná
$count = $pdo->query("SELECT COUNT(*) FROM hero_slides")->fetchColumn();
if ($count == 0) {
    $pdo->exec("INSERT INTO hero_slides (image, title_cs, title_en, subtitle_cs, subtitle_en, sort_order, active) VALUES
        ('r29-hero.jpg', 'Pochutnaj si na italském létě', 'Taste the Italian summer', 'Ciao Spritz – míchán přímo v lahvi z těch nejlepších přísad.', 'Ciao Spritz – mixed directly in the bottle from the finest ingredients.', 1, 1),
        ('r33-edit-hero.jpg', 'Italský aperitiv s tradicí', 'Italian aperitif with tradition', 'The oldest Italian aperitif – stačí vychladit a vychutnat.', 'The oldest Italian aperitif – just chill and enjoy.', 2, 1)");
}

$message = '';

// AKCE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Přidat slide
    if ($action === 'add') {
        $image = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $filename = 'hero-' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $filename);
                $image = $filename;
            }
        }
        // Nebo z media knihovny
        if (empty($image) && !empty($_POST['media_image'])) {
            $image = basename($_POST['media_image']);
        }

        if ($image) {
            $maxOrder = $pdo->query("SELECT MAX(sort_order) FROM hero_slides")->fetchColumn() ?? 0;
            $pdo->prepare("INSERT INTO hero_slides (image, title_cs, title_en, subtitle_cs, subtitle_en, btn_text_cs, btn_text_en, btn_url, sort_order, active) VALUES (?,?,?,?,?,?,?,?,?,1)")
                ->execute([$image, $_POST['title_cs']??'', $_POST['title_en']??'', $_POST['subtitle_cs']??'', $_POST['subtitle_en']??'', $_POST['btn_text_cs']??'Nakoupit nyní', $_POST['btn_text_en']??'Shop now', $_POST['btn_url']??'/produkty.php', $maxOrder+1]);
            logAction($pdo, 'Přidán hero slide', $image);
            $message = '✅ Slide byl přidán.';
        } else {
            $message = '❌ Nahrajte obrázek nebo vyberte z media knihovny.';
        }
    }

    // Uložit slide
    if ($action === 'save' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $image = $_POST['current_image'] ?? '';

        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $filename = 'hero-' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $filename);
                $image = $filename;
            }
        }
        if (!empty($_POST['media_image'])) {
            $image = basename($_POST['media_image']);
        }

        $pdo->prepare("UPDATE hero_slides SET image=?, title_cs=?, title_en=?, subtitle_cs=?, subtitle_en=?, btn_text_cs=?, btn_text_en=?, btn_url=?, sort_order=?, active=? WHERE id=?")
            ->execute([$image, $_POST['title_cs']??'', $_POST['title_en']??'', $_POST['subtitle_cs']??'', $_POST['subtitle_en']??'', $_POST['btn_text_cs']??'', $_POST['btn_text_en']??'', $_POST['btn_url']??'', (int)($_POST['sort_order']??0), isset($_POST['active'])?1:0, $id]);
        logAction($pdo, 'Upraven hero slide', "ID: $id");
        $message = '✅ Slide byl uložen.';
    }

    // Smazat slide
    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM hero_slides WHERE id=?")->execute([$_POST['id']]);
        logAction($pdo, 'Smazán hero slide', 'ID: '.$_POST['id']);
        $message = '✅ Slide byl smazán.';
    }

    // Toggle aktivní
    if ($action === 'toggle' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE hero_slides SET active = 1 - active WHERE id=?")->execute([$_POST['id']]);
        $message = '✅ Změněno.';
    }

    header('Location: '.BASE_URL.'/admin/hero.php?msg='.urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

$slides = $pdo->query("SELECT * FROM hero_slides ORDER BY sort_order ASC, id ASC")->fetchAll();

// Media soubory pro výběr
$uploadDir = __DIR__ . '/../uploads/';
$mediaFiles = [];
foreach (scandir($uploadDir) as $f) {
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp']) && filesize($uploadDir.$f) > 10000) {
        $mediaFiles[] = $f;
    }
}
usort($mediaFiles, fn($a,$b) => filemtime($uploadDir.$b) - filemtime($uploadDir.$a));
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero Banner — Admin Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .content{max-width:1100px;margin:28px auto;padding:0 24px}
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:20px}
        .card-header{padding:16px 24px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between}
        .card-header h2{font-size:1rem;font-weight:700}
        .card-body{padding:24px}
        .form-group{margin-bottom:14px}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
        input[type=text],input[type=url],input[type=file],textarea,select{width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s}
        input:focus,textarea:focus,select:focus{border-color:#E8631A}
        textarea{resize:vertical;min-height:60px}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:inherit}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-danger{background:#dc3545;color:white}.btn-danger:hover{background:#c82333}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#444}.btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-orange{background:#E8631A;color:white}
        .btn-sm{padding:5px 10px;font-size:12px}
        .alert{padding:12px 20px;border-radius:8px;font-size:14px;margin-bottom:20px;background:rgba(45,122,58,.1);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
        .alert-err{background:rgba(220,53,69,.08);color:#dc3545;border-color:rgba(220,53,69,.2)}

        /* Slide card */
        .slide-card{display:grid;grid-template-columns:220px 1fr;gap:20px;padding:20px;border:1px solid #e0e0e0;border-radius:10px;margin-bottom:16px;background:white;align-items:start}
        .slide-card.inactive{opacity:0.55}
        .slide-preview{aspect-ratio:16/9;border-radius:8px;overflow:hidden;background:#f5f5f5;position:relative}
        .slide-preview img{width:100%;height:100%;object-fit:cover}
        .slide-order{position:absolute;top:8px;left:8px;background:rgba(0,0,0,0.6);color:white;font-size:12px;font-weight:700;padding:3px 8px;border-radius:4px}
        .slide-status{position:absolute;top:8px;right:8px;font-size:11px;font-weight:700;padding:3px 8px;border-radius:4px}
        .status-on{background:#2D7A3A;color:white}
        .status-off{background:#dc3545;color:white}

        /* Media grid */
        .media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;max-height:280px;overflow-y:auto;border:2px solid #e0e0e0;border-radius:8px;padding:10px;background:#fafafa}
        .media-item{aspect-ratio:1;border-radius:6px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:border-color .2s;position:relative}
        .media-item img{width:100%;height:100%;object-fit:cover}
        .media-item.selected{border-color:#E8631A}
        .media-item.selected::after{content:'✓';position:absolute;top:4px;right:4px;background:#E8631A;color:white;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}

        /* Toggle */
        .toggle-wrap{display:flex;align-items:center;gap:10px}
        .toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;inset:0;background:#ddd;border-radius:24px;cursor:pointer;transition:.3s}
        .toggle-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s}
        input:checked+.toggle-slider{background:#2D7A3A}
        input:checked+.toggle-slider:before{transform:translateX(20px)}

        /* Preview */
        .hero-preview{position:relative;border-radius:10px;overflow:hidden;aspect-ratio:16/6;background:#111;margin-top:12px}
        .hero-preview img{width:100%;height:100%;object-fit:cover;opacity:0.7}
        .hero-preview-text{position:absolute;inset:0;display:flex;align-items:center;padding:24px;color:white}
        .hero-preview-text h3{font-size:1.2rem;font-weight:700;margin-bottom:6px}
        .hero-preview-text p{font-size:13px;opacity:.8}
    </style>
</head>
<body>

<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <h1>🖼️ Správa Hero Banneru</h1>
    <a href="<?= BASE_URL ?>/" target="_blank" style="margin-left:12px;color:#E8631A">🌐 Zobrazit web →</a>
</div>

<div class="content">
    <?php if ($message): ?>
    <div class="alert <?= str_starts_with($message,'❌') ? 'alert-err' : '' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- AKTUÁLNÍ SLIDY -->
    <div class="card">
        <div class="card-header">
            <h2>📋 Aktuální slidy (<?= count($slides) ?>)</h2>
            <small style="color:#888">Přetažením změníte pořadí · kliknutím rozbalíte úpravu</small>
        </div>
        <div class="card-body">
            <?php if (empty($slides)): ?>
            <p style="color:#888;text-align:center;padding:32px">Žádné slidy. Přidejte první →</p>
            <?php endif; ?>

            <?php foreach ($slides as $slide): ?>
            <div class="slide-card <?= $slide['active'] ? '' : 'inactive' ?>">
                <!-- Náhled -->
                <div>
                    <div class="slide-preview">
                        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($slide['image']) ?>" alt=""
                             onerror="this.style.display='none'">
                        <div class="slide-order">#<?= $slide['sort_order'] ?></div>
                        <div class="slide-status <?= $slide['active'] ? 'status-on' : 'status-off' ?>">
                            <?= $slide['active'] ? 'Aktivní' : 'Skrytý' ?>
                        </div>
                    </div>
                    <div style="margin-top:8px;display:flex;gap:6px">
                        <form method="POST" style="flex:1">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $slide['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="width:100%">
                                <?= $slide['active'] ? '👁️ Skrýt' : '✅ Aktivovat' ?>
                            </button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Smazat slide?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $slide['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </div>
                </div>

                <!-- Úprava -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $slide['id'] ?>">
                    <input type="hidden" name="current_image" value="<?= htmlspecialchars($slide['image']) ?>">

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Nadpis (CZ)</label>
                            <input type="text" name="title_cs" value="<?= htmlspecialchars($slide['title_cs']??'') ?>" placeholder="Pochutnaj si na italském létě">
                        </div>
                        <div class="form-group">
                            <label>Nadpis (EN)</label>
                            <input type="text" name="title_en" value="<?= htmlspecialchars($slide['title_en']??'') ?>" placeholder="Taste the Italian summer">
                        </div>
                        <div class="form-group">
                            <label>Podtitulek (CZ)</label>
                            <input type="text" name="subtitle_cs" value="<?= htmlspecialchars($slide['subtitle_cs']??'') ?>" placeholder="Stručný popis...">
                        </div>
                        <div class="form-group">
                            <label>Podtitulek (EN)</label>
                            <input type="text" name="subtitle_en" value="<?= htmlspecialchars($slide['subtitle_en']??'') ?>" placeholder="Brief description...">
                        </div>
                        <div class="form-group">
                            <label>Text tlačítka (CZ)</label>
                            <input type="text" name="btn_text_cs" value="<?= htmlspecialchars($slide['btn_text_cs']??'Nakoupit nyní') ?>">
                        </div>
                        <div class="form-group">
                            <label>Text tlačítka (EN)</label>
                            <input type="text" name="btn_text_en" value="<?= htmlspecialchars($slide['btn_text_en']??'Shop now') ?>">
                        </div>
                    </div>
                    <div class="grid-2" style="margin-bottom:12px">
                        <div class="form-group">
                            <label>URL tlačítka</label>
                            <input type="text" name="btn_url" value="<?= htmlspecialchars($slide['btn_url']??'/produkty.php') ?>" placeholder="/produkty.php">
                        </div>
                        <div class="form-group">
                            <label>Pořadí</label>
                            <input type="number" name="sort_order" value="<?= $slide['sort_order'] ?>" min="1" style="width:80px">
                        </div>
                    </div>

                    <!-- Změna obrázku -->
                    <details style="margin-bottom:12px">
                        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#555;padding:8px 0">🖼️ Změnit obrázek (klikněte pro rozbalení)</summary>
                        <div style="margin-top:10px">
                            <label>Nahrát nový obrázek</label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" style="margin-bottom:10px">
                            <label>Nebo vybrat z media knihovny</label>
                            <div class="media-grid" id="media-<?= $slide['id'] ?>">
                                <?php foreach (array_slice($mediaFiles, 0, 30) as $mf): ?>
                                <div class="media-item <?= $mf===$slide['image']?'selected':'' ?>"
                                     onclick="selectMedia(this, '<?= $slide['id'] ?>', '<?= htmlspecialchars($mf) ?>')">
                                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($mf) ?>" alt="">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="media_image" id="media-val-<?= $slide['id'] ?>">
                        </div>
                    </details>

                    <div style="display:flex;gap:8px;align-items:center">
                        <label class="toggle-wrap">
                            <label class="toggle-switch">
                                <input type="checkbox" name="active" <?= $slide['active']?'checked':'' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-size:13px">Aktivní</span>
                        </label>
                        <button type="submit" class="btn btn-primary" style="margin-left:auto">💾 Uložit</button>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PŘIDAT NOVÝ SLIDE -->
    <div class="card">
        <div class="card-header"><h2>➕ Přidat nový slide</h2></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="grid-2">
                    <div class="form-group">
                        <label>Nadpis (CZ) *</label>
                        <input type="text" name="title_cs" required placeholder="Pochutnaj si na italském létě">
                    </div>
                    <div class="form-group">
                        <label>Nadpis (EN)</label>
                        <input type="text" name="title_en" placeholder="Taste the Italian summer">
                    </div>
                    <div class="form-group">
                        <label>Podtitulek (CZ)</label>
                        <input type="text" name="subtitle_cs" placeholder="Stručný popis...">
                    </div>
                    <div class="form-group">
                        <label>Podtitulek (EN)</label>
                        <input type="text" name="subtitle_en" placeholder="Brief description...">
                    </div>
                    <div class="form-group">
                        <label>Text tlačítka (CZ)</label>
                        <input type="text" name="btn_text_cs" value="Nakoupit nyní">
                    </div>
                    <div class="form-group">
                        <label>URL tlačítka</label>
                        <input type="text" name="btn_url" value="/produkty.php">
                    </div>
                </div>

                <div class="form-group">
                    <label>Nahrát obrázek *</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="form-group">
                    <label>Nebo vybrat z media knihovny</label>
                    <div class="media-grid" id="media-new">
                        <?php foreach (array_slice($mediaFiles, 0, 40) as $mf): ?>
                        <div class="media-item"
                             onclick="selectMedia(this, 'new', '<?= htmlspecialchars($mf) ?>')">
                            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($mf) ?>" alt="">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="media_image" id="media-val-new">
                </div>

                <button type="submit" class="btn btn-primary">➕ Přidat slide</button>
            </form>
        </div>
    </div>
</div>

<script>
function selectMedia(el, slideId, filename) {
    // Odznač ostatní ve stejné skupině
    const grid = document.getElementById('media-' + slideId);
    grid.querySelectorAll('.media-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('media-val-' + slideId).value = filename;
}
</script>
</body>
</html>
