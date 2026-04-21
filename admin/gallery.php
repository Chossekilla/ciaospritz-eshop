<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('manager');

$albumId = (int)($_GET['album'] ?? 0);
$message = '';

// AKCE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_album') {
        $name_cs = trim($_POST['name_cs'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $desc_cs = trim($_POST['description_cs'] ?? '');
        $desc_en = trim($_POST['description_en'] ?? '');
        $cover = null;
        if (!empty($_FILES['cover']['name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $cover = 'album-' . time() . '.' . $ext;
                move_uploaded_file($_FILES['cover']['tmp_name'], '../uploads/' . $cover);
            }
        }
        if ($name_cs) {
            $pdo->prepare("INSERT INTO gallery_albums (name_cs,name_en,description_cs,description_en,cover_image,active) VALUES (?,?,?,?,?,1)")
                ->execute([$name_cs,$name_en,$desc_cs,$desc_en,$cover]);
            logAction($pdo, 'Přidáno album', $name_cs);
            $message = '✅ Album bylo vytvořeno.';
        }
    }

    if ($action === 'delete_album' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE gallery_albums SET active=0 WHERE id=?")->execute([$_POST['id']]);
        logAction($pdo, 'Smazáno album', 'ID: '.$_POST['id']);
        $message = '✅ Album odstraněno.';
        $albumId = 0;
    }

    if ($action === 'upload_photos' && $albumId) {
        $uploaded = 0;
        if (!empty($_FILES['photos']['name'][0])) {
            $sortBase = (int)$pdo->prepare("SELECT COUNT(*) FROM gallery_photos WHERE album_id=?")->execute([$albumId]) ? 0 : 0;
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM gallery_photos WHERE album_id=?");
            $stmtCount->execute([$albumId]);
            $sortBase = (int)$stmtCount->fetchColumn();

            foreach ($_FILES['photos']['name'] as $i => $name) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                if ($_FILES['photos']['size'][$i] > 10 * 1024 * 1024) continue;
                $filename = 'gallery-' . $albumId . '-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], '../uploads/' . $filename)) {
                    $caption = trim($_POST['caption'] ?? '');
                    $pdo->prepare("INSERT INTO gallery_photos (album_id,filename,caption,sort_order) VALUES (?,?,?,?)")
                        ->execute([$albumId, $filename, $caption, $sortBase + $i]);
                    $uploaded++;
                }
            }
        }
        logAction($pdo, 'Nahráno fotek', "$uploaded do alba ID $albumId");
        $message = "✅ Nahráno $uploaded fotek.";
    }

    if ($action === 'delete_photo' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT filename FROM gallery_photos WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $photo = $stmt->fetch();
        if ($photo) {
            $filepath = '../uploads/' . $photo['filename'];
            if (file_exists($filepath)) @unlink($filepath);
            $pdo->prepare("DELETE FROM gallery_photos WHERE id=?")->execute([$_POST['id']]);
            logAction($pdo, 'Smazána fotka', $photo['filename']);
            $message = '✅ Fotka smazána.';
        }
    }

    if ($action === 'update_cover' && isset($_POST['album_id'], $_POST['photo_filename'])) {
        $pdo->prepare("UPDATE gallery_albums SET cover_image=? WHERE id=?")->execute([$_POST['photo_filename'], $_POST['album_id']]);
        $message = '✅ Titulní fotka nastavena.';
    }

    $redirect = BASE_URL . '/admin/gallery.php' . ($albumId ? '?album='.$albumId : '') . '&msg=' . urlencode($message);
    header('Location: ' . $redirect);
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

// Načti data
$albums = $pdo->query("SELECT ga.*, COUNT(gp.id) as photo_count FROM gallery_albums ga LEFT JOIN gallery_photos gp ON gp.album_id = ga.id WHERE ga.active=1 GROUP BY ga.id ORDER BY ga.id DESC")->fetchAll();

$photos = [];
$currentAlbum = null;
if ($albumId) {
    $stmt = $pdo->prepare("SELECT * FROM gallery_albums WHERE id=? AND active=1");
    $stmt->execute([$albumId]);
    $currentAlbum = $stmt->fetch();
    if ($currentAlbum) {
        $stmt = $pdo->prepare("SELECT * FROM gallery_photos WHERE album_id=? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$albumId]);
        $photos = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galerie — Admin Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f5f5f5;color:#111}
        .topbar{background:#111;color:white;padding:14px 32px;display:flex;align-items:center;gap:16px}
        .topbar a{color:rgba(255,255,255,.6);font-size:14px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:1rem;font-weight:600;color:white;margin-left:auto}
        .content{max-width:1200px;margin:28px auto;padding:0 24px}
        .layout{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start}
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:20px}
        .card-header{padding:16px 24px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between}
        .card-header h2{font-size:1rem;font-weight:700}
        .card-body{padding:24px}
        .form-group{margin-bottom:14px}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
        input[type=text],input[type=file],textarea{width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s}
        input:focus,textarea:focus{border-color:#E8631A}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-danger{background:#dc3545;color:white}.btn-danger:hover{background:#c82333}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#444}.btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-orange{background:#E8631A;color:white}
        .btn-full{width:100%;justify-content:center}
        .alert{padding:12px 20px;border-radius:8px;font-size:14px;margin-bottom:20px;background:rgba(45,122,58,.1);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
        /* Albums grid */
        .album-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
        .album-card{border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;background:white;transition:all .2s}
        .album-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);border-color:#E8631A}
        .album-cover{aspect-ratio:16/10;background:#f5f5f5;display:flex;align-items:center;justify-content:center;font-size:2.5rem;overflow:hidden}
        .album-cover img{width:100%;height:100%;object-fit:cover}
        .album-info{padding:12px 16px}
        .album-info h3{font-size:14px;font-weight:700;margin-bottom:3px}
        .album-info small{color:#888;font-size:12px}
        .album-actions{display:flex;gap:8px;padding:0 16px 12px;flex-wrap:wrap}
        /* Photos grid */
        .photo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
        .photo-item{position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;background:#f5f5f5;border:2px solid transparent;transition:border-color .2s}
        .photo-item:hover{border-color:#E8631A}
        .photo-item img{width:100%;height:100%;object-fit:cover}
        .photo-overlay{position:absolute;inset:0;background:rgba(0,0,0,0);display:flex;align-items:flex-end;justify-content:center;padding:6px;gap:4px;transition:background .2s;opacity:0}
        .photo-item:hover .photo-overlay{background:rgba(0,0,0,.5);opacity:1}
        /* Upload dropzone */
        .dropzone{border:2px dashed #e0e0e0;border-radius:10px;padding:24px;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:#fafafa}
        .dropzone:hover,.dropzone.drag-over{border-color:#E8631A;background:rgba(232,99,26,.03)}
        .dropzone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:6px;margin-top:12px}
        .preview-item{aspect-ratio:1;border-radius:6px;overflow:hidden;background:#f5f5f5}
        .preview-item img{width:100%;height:100%;object-fit:cover}
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <?php if ($currentAlbum): ?>
        <a href="<?= BASE_URL ?>/admin/gallery.php">Galerie</a>
        <span style="color:rgba(255,255,255,.3)">›</span>
    <?php endif; ?>
    <h1><?= $currentAlbum ? htmlspecialchars($currentAlbum['name_cs']) : '🖼️ Správa galerie' ?></h1>
</div>

<div class="content">
    <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <?php if (!$currentAlbum): ?>
    <!-- PŘEHLED ALB -->
    <div class="layout">
        <div>
            <div class="card">
                <div class="card-header">
                    <h2>📁 Alba (<?= count($albums) ?>)</h2>
                    <a href="<?= BASE_URL ?>/galerie.php" target="_blank" class="btn btn-outline">🌐 Zobrazit →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($albums)): ?>
                        <p style="color:#888;text-align:center;padding:32px 0">Žádná alba. Vytvoř první album →</p>
                    <?php else: ?>
                    <div class="album-grid">
                        <?php foreach ($albums as $album): ?>
                        <div class="album-card">
                            <div class="album-cover">
                                <?php if ($album['cover_image']): ?>
                                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($album['cover_image']) ?>" alt="">
                                <?php else: ?>
                                    🖼️
                                <?php endif; ?>
                            </div>
                            <div class="album-info">
                                <h3><?= htmlspecialchars($album['name_cs']) ?></h3>
                                <small><?= $album['photo_count'] ?> fotek</small>
                            </div>
                            <div class="album-actions">
                                <a href="<?= BASE_URL ?>/admin/gallery.php?album=<?= $album['id'] ?>" class="btn btn-primary" style="flex:1;justify-content:center">📷 Fotky</a>
                                <form method="POST" onsubmit="return confirm('Smazat album?')">
                                    <input type="hidden" name="action" value="delete_album">
                                    <input type="hidden" name="id" value="<?= $album['id'] ?>">
                                    <button type="submit" class="btn btn-danger">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Nové album -->
        <div>
            <div class="card">
                <div class="card-header"><h2>➕ Nové album</h2></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_album">
                        <div class="form-group">
                            <label>Název (CZ) *</label>
                            <input type="text" name="name_cs" required placeholder="např. Akce 2025">
                        </div>
                        <div class="form-group">
                            <label>Name (EN)</label>
                            <input type="text" name="name_en" placeholder="e.g. Events 2025">
                        </div>
                        <div class="form-group">
                            <label>Popis (CZ)</label>
                            <textarea name="description_cs" rows="2" placeholder="Krátký popis alba..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Titulní fotka</label>
                            <input type="file" name="cover" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Vytvořit album</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- FOTKY V ALBU -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
        <a href="<?= BASE_URL ?>/admin/gallery.php" class="btn btn-outline">← Zpět na alba</a>
        <div style="font-size:14px;color:#888"><?= count($photos) ?> fotek v albu</div>
    </div>

    <div class="layout">
        <div>
            <!-- Fotky -->
            <div class="card">
                <div class="card-header"><h2>🖼️ Fotky v albu</h2></div>
                <div class="card-body">
                    <?php if (empty($photos)): ?>
                        <p style="color:#888;text-align:center;padding:32px 0">Album je prázdné. Nahraj fotky →</p>
                    <?php else: ?>
                    <div class="photo-grid">
                        <?php foreach ($photos as $photo): ?>
                        <div class="photo-item">
                            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($photo['filename']) ?>" alt="<?= htmlspecialchars($photo['caption'] ?? '') ?>">
                            <div class="photo-overlay">
                                <!-- Nastavit jako cover -->
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="update_cover">
                                    <input type="hidden" name="album_id" value="<?= $albumId ?>">
                                    <input type="hidden" name="photo_filename" value="<?= htmlspecialchars($photo['filename']) ?>">
                                    <button type="submit" class="btn btn-orange" style="padding:4px 8px;font-size:11px" title="Nastavit jako titulní">⭐</button>
                                </form>
                                <!-- Smazat -->
                                <form method="POST" style="display:inline" onsubmit="return confirm('Smazat fotku?')">
                                    <input type="hidden" name="action" value="delete_photo">
                                    <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:4px 8px;font-size:11px">🗑️</button>
                                </form>
                            </div>
                            <?php if ($currentAlbum['cover_image'] === $photo['filename']): ?>
                            <div style="position:absolute;top:6px;left:6px;background:#E8631A;color:white;font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px">COVER</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upload -->
        <div>
            <div class="card">
                <div class="card-header"><h2>📤 Nahrát fotky</h2></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="action" value="upload_photos">
                        <div class="dropzone" id="dropzone">
                            <input type="file" name="photos[]" id="photoInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewPhotos(this)">
                            <div style="font-size:2rem;margin-bottom:8px">📸</div>
                            <div style="font-weight:600;margin-bottom:4px">Přetáhni nebo klikni</div>
                            <div style="font-size:12px;color:#aaa">JPG, PNG, WebP · více najednou · max 10 MB/foto</div>
                        </div>
                        <div id="previewGrid" class="preview-grid" style="display:none"></div>
                        <div class="form-group" style="margin-top:12px">
                            <label>Popisek (volitelný)</label>
                            <input type="text" name="caption" placeholder="Popisek pro všechny nahrané fotky...">
                        </div>
                        <button type="submit" class="btn btn-primary btn-full" id="uploadBtn">📤 Nahrát fotky</button>
                    </form>
                </div>
            </div>

            <!-- Info alba -->
            <div class="card">
                <div class="card-header"><h2>ℹ️ Info o albu</h2></div>
                <div class="card-body">
                    <div style="font-size:14px;display:flex;flex-direction:column;gap:8px">
                        <div><strong>Název:</strong> <?= htmlspecialchars($currentAlbum['name_cs']) ?></div>
                        <?php if ($currentAlbum['name_en']): ?>
                        <div><strong>EN:</strong> <?= htmlspecialchars($currentAlbum['name_en']) ?></div>
                        <?php endif; ?>
                        <?php if ($currentAlbum['description_cs']): ?>
                        <div><strong>Popis:</strong> <?= htmlspecialchars($currentAlbum['description_cs']) ?></div>
                        <?php endif; ?>
                        <div><strong>Fotek:</strong> <?= count($photos) ?></div>
                    </div>
                    <div style="margin-top:16px">
                        <a href="<?= BASE_URL ?>/galerie.php?album=<?= $albumId ?>" target="_blank" class="btn btn-outline btn-full">🌐 Zobrazit album →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function previewPhotos(input) {
    const grid = document.getElementById('previewGrid');
    grid.innerHTML = '';
    if (!input.files.length) { grid.style.display = 'none'; return; }
    grid.style.display = 'grid';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = '<img src="'+e.target.result+'">';
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
    document.getElementById('uploadBtn').textContent = '📤 Nahrát ' + input.files.length + ' fotek';
}

// Drag & drop
const dz = document.getElementById('dropzone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('drag-over');
        const input = document.getElementById('photoInput');
        input.files = e.dataTransfer.files;
        previewPhotos(input);
    });
}
</script>
</body>
</html>
