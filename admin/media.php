<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('manager');

// Vytvoř tabulku media_library
$pdo->exec("CREATE TABLE IF NOT EXISTS media_library (
    id int NOT NULL AUTO_INCREMENT,
    filename varchar(255) NOT NULL,
    original_name varchar(255) DEFAULT NULL,
    title varchar(255) DEFAULT NULL,
    alt_text varchar(255) DEFAULT NULL,
    filesize int DEFAULT 0,
    mime_type varchar(100) DEFAULT NULL,
    width int DEFAULT NULL,
    height int DEFAULT NULL,
    folder varchar(100) DEFAULT 'general',
    uploaded_by int DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uploadDir = __DIR__ . '/../uploads/';
$message = '';

// Synchronizuj uploads/ s DB
$existingInDb = $pdo->query("SELECT filename FROM media_library")->fetchAll(PDO::FETCH_COLUMN);
$filesOnDisk = [];
foreach (scandir($uploadDir) as $f) {
    if (in_array($f, ['.', '..', '.htaccess'])) continue;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
    $filesOnDisk[] = $f;
    if (!in_array($f, $existingInDb)) {
        $size = filesize($uploadDir . $f);
        $mime = mime_content_type($uploadDir . $f) ?: 'image/jpeg';
        $dims = @getimagesize($uploadDir . $f);
        $w = $dims ? $dims[0] : null;
        $h = $dims ? $dims[1] : null;
        $pdo->prepare("INSERT IGNORE INTO media_library (filename, original_name, title, filesize, mime_type, width, height) VALUES (?,?,?,?,?,?,?)")
            ->execute([$f, $f, pathinfo($f, PATHINFO_FILENAME), $size, $mime, $w, $h]);
    }
}

// AKCE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Upload
    if ($action === 'upload' && !empty($_FILES['files']['name'][0])) {
        $uploaded = 0;
        foreach ($_FILES['files']['name'] as $i => $fname) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
            if ($_FILES['files']['size'][$i] > 10 * 1024 * 1024) continue;

            $clean = preg_replace('/[^a-z0-9_-]/', '-', strtolower(pathinfo($fname, PATHINFO_FILENAME)));
            $newName = $clean . '-' . time() . '-' . $i . '.' . $ext;
            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadDir . $newName)) {
                $size = filesize($uploadDir . $newName);
                $mime = mime_content_type($uploadDir . $newName);
                $dims = @getimagesize($uploadDir . $newName);
                $folder = $_POST['folder'] ?? 'general';
                $pdo->prepare("INSERT IGNORE INTO media_library (filename, original_name, title, filesize, mime_type, width, height, folder) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$newName, $fname, pathinfo($fname, PATHINFO_FILENAME), $size, $mime, $dims[0]??null, $dims[1]??null, $folder]);
                $uploaded++;
            }
        }
        logAction($pdo, 'Media upload', "$uploaded souborů");
        $message = "✅ Nahráno $uploaded souborů.";
    }

    // Uložit metadata
    if ($action === 'save_meta' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE media_library SET title=?, alt_text=?, folder=? WHERE id=?")
            ->execute([$_POST['title']??'', $_POST['alt_text']??'', $_POST['folder']??'general', $_POST['id']]);
        $message = '✅ Metadata uložena.';
    }

    // Smazat
    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT filename FROM media_library WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $row = $stmt->fetch();
        if ($row) {
            @unlink($uploadDir . $row['filename']);
            $pdo->prepare("DELETE FROM media_library WHERE id=?")->execute([$_POST['id']]);
            logAction($pdo, 'Media smazáno', $row['filename']);
        }
        $message = '✅ Soubor smazán.';
    }

    header('Location: '.BASE_URL.'/admin/media.php?folder='.urlencode($_POST['folder']??'').'&msg='.urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

// Filtrování
$folder = $_GET['folder'] ?? '';
$search = $_GET['q'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

$sql = "SELECT * FROM media_library WHERE 1=1";
$params = [];
if ($folder) { $sql .= " AND folder=?"; $params[] = $folder; }
if ($search) { $sql .= " AND (filename LIKE ? OR title LIKE ? OR alt_text LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= match($sortBy) {
    'oldest' => " ORDER BY created_at ASC",
    'name' => " ORDER BY filename ASC",
    'size' => " ORDER BY filesize DESC",
    default => " ORDER BY created_at DESC"
};

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mediaItems = $stmt->fetchAll();

// Složky
$folders = $pdo->query("SELECT folder, COUNT(*) as cnt FROM media_library GROUP BY folder ORDER BY folder")->fetchAll();

// Statistiky
$totalFiles = $pdo->query("SELECT COUNT(*) FROM media_library")->fetchColumn();
$totalSize = $pdo->query("SELECT SUM(filesize) FROM media_library")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Manager — Admin Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .layout{display:grid;grid-template-columns:220px 1fr;min-height:calc(100vh - 50px)}
        .sidebar{background:white;border-right:1px solid #e0e0e0;padding:20px 0}
        .sidebar h3{font-size:11px;font-weight:700;text-transform:uppercase;color:#aaa;letter-spacing:.08em;padding:0 16px 8px}
        .sidebar a{display:flex;align-items:center;justify-content:space-between;padding:8px 16px;font-size:13px;color:#444;text-decoration:none;transition:background .15s;gap:8px}
        .sidebar a:hover,.sidebar a.active{background:#f5f5f5;color:#E8631A}
        .sidebar a .cnt{background:#e0e0e0;color:#666;font-size:11px;padding:1px 7px;border-radius:50px}
        .sidebar a.active .cnt{background:rgba(232,99,26,.15);color:#E8631A}
        .main{padding:24px;overflow-y:auto}
        .toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
        .search-wrap{position:relative;flex:1;min-width:200px}
        .search-wrap input{width:100%;padding:9px 12px 9px 36px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s}
        .search-wrap input:focus{border-color:#E8631A}
        .search-wrap::before{content:'🔍';position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:14px}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:inherit;white-space:nowrap}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-danger{background:#dc3545;color:white}.btn-danger:hover{background:#c82333}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#444}.btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-sm{padding:5px 10px;font-size:12px}
        select{padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;background:white;cursor:pointer}
        select:focus{border-color:#E8631A}
        /* Stats */
        .stats{display:flex;gap:12px;margin-bottom:20px}
        .stat{background:white;border-radius:8px;border:1px solid #e0e0e0;padding:12px 16px;font-size:13px}
        .stat strong{display:block;font-size:1.3rem;font-weight:700;color:#111}
        .stat span{color:#888;font-size:12px}
        /* Grid */
        .media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}
        .media-item{background:white;border-radius:10px;border:2px solid transparent;overflow:hidden;cursor:pointer;transition:all .2s;position:relative}
        .media-item:hover{border-color:#E8631A;box-shadow:0 4px 16px rgba(0,0,0,.1)}
        .media-item.selected{border-color:#2D7A3A;box-shadow:0 0 0 3px rgba(45,122,58,.15)}
        .media-thumb{aspect-ratio:1;background:#f5f5f5;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .media-thumb img{width:100%;height:100%;object-fit:contain;padding:6px}
        .media-info{padding:8px 10px;border-top:1px solid #f0f0f0}
        .media-name{font-size:11px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .media-size{font-size:10px;color:#aaa;margin-top:2px}
        .media-check{position:absolute;top:6px;right:6px;background:#2D7A3A;color:white;width:20px;height:20px;border-radius:50%;display:none;align-items:center;justify-content:center;font-size:11px;font-weight:700}
        .media-item.selected .media-check{display:flex}
        /* Upload zone */
        .dropzone{border:2px dashed #e0e0e0;border-radius:12px;padding:40px;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:white;margin-bottom:20px}
        .dropzone:hover,.dropzone.drag-over{border-color:#E8631A;background:rgba(232,99,26,.03)}
        .dropzone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .dropzone-icon{font-size:2.5rem;margin-bottom:10px}
        .dropzone-text{font-size:15px;font-weight:600;color:#444;margin-bottom:4px}
        .dropzone-hint{font-size:12px;color:#aaa}
        .preview-strip{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        .preview-thumb{width:60px;height:60px;border-radius:6px;overflow:hidden;background:#f5f5f5;flex-shrink:0}
        .preview-thumb img{width:100%;height:100%;object-fit:cover}
        /* Detail panel */
        .detail-panel{position:fixed;right:-380px;top:50px;width:360px;height:calc(100vh - 50px);background:white;border-left:1px solid #e0e0e0;z-index:50;transition:right .3s;overflow-y:auto;padding:20px}
        .detail-panel.open{right:0}
        .detail-panel h2{font-size:1rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between}
        .detail-preview{aspect-ratio:1;background:#f5f5f5;border-radius:8px;overflow:hidden;margin-bottom:16px;display:flex;align-items:center;justify-content:center}
        .detail-preview img{max-width:100%;max-height:100%;object-fit:contain;padding:8px}
        .form-group{margin-bottom:12px}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px}
        input[type=text]{width:100%;padding:8px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s}
        input[type=text]:focus{border-color:#E8631A}
        .url-box{background:#f5f5f5;border-radius:6px;padding:8px 10px;font-family:monospace;font-size:11px;color:#444;word-break:break-all;cursor:pointer;user-select:all}
        .url-box:hover{background:#e8f4ea;color:#2D7A3A}
        .meta-row{display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #f5f5f5}
        .meta-row:last-child{border:none}
        .meta-label{color:#888}
        .meta-val{font-weight:600;color:#333}
        .alert{padding:12px 20px;border-radius:8px;font-size:14px;margin-bottom:20px;background:rgba(45,122,58,.1);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
        .empty{text-align:center;padding:60px;color:#aaa}
        .empty-icon{font-size:3rem;margin-bottom:12px}
        /* Upload form */
        .upload-section{background:white;border-radius:12px;border:1px solid #e0e0e0;padding:20px;margin-bottom:20px}
        .upload-section h3{font-size:.9rem;font-weight:700;margin-bottom:12px}
        .folder-select{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
        .folder-btn{padding:5px 14px;border-radius:50px;border:1.5px solid #e0e0e0;font-size:12px;font-weight:600;cursor:pointer;background:white;transition:all .2s}
        .folder-btn:hover,.folder-btn.active{border-color:#E8631A;background:rgba(232,99,26,.08);color:#E8631A}
    </style>
</head>
<body>

<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <h1>🖼️ Media Manager</h1>
    <span style="font-size:12px;color:rgba(255,255,255,.5);margin-left:12px"><?= $totalFiles ?> souborů · <?= round($totalSize/1024/1024, 1) ?> MB</span>
</div>

<div class="layout">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h3>Složky</h3>
        <a href="<?= BASE_URL ?>/admin/media.php" class="<?= !$folder ? 'active' : '' ?>">
            📁 Vše <span class="cnt"><?= $totalFiles ?></span>
        </a>
        <?php foreach ($folders as $f): ?>
        <a href="<?= BASE_URL ?>/admin/media.php?folder=<?= urlencode($f['folder']) ?>"
           class="<?= $folder === $f['folder'] ? 'active' : '' ?>">
            📂 <?= htmlspecialchars($f['folder']) ?> <span class="cnt"><?= $f['cnt'] ?></span>
        </a>
        <?php endforeach; ?>

        <div style="margin-top:16px;padding:0 16px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#aaa;margin-bottom:8px">Rychlé akce</div>
            <a href="<?= BASE_URL ?>/admin/index.php?section=products" style="display:block;padding:8px 0;font-size:13px;color:#444;text-decoration:none">🍾 Produkty</a>
            <a href="<?= BASE_URL ?>/admin/article-edit.php" style="display:block;padding:8px 0;font-size:13px;color:#444;text-decoration:none">📰 Nový článek</a>
            <a href="<?= BASE_URL ?>/admin/gallery.php" style="display:block;padding:8px 0;font-size:13px;color:#444;text-decoration:none">🖼️ Galerie</a>
            <a href="<?= BASE_URL ?>/admin/hero.php" style="display:block;padding:8px 0;font-size:13px;color:#444;text-decoration:none">🌅 Hero banner</a>
        </div>
    </div>

    <!-- HLAVNÍ OBSAH -->
    <div class="main">

        <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- UPLOAD -->
        <div class="upload-section">
            <h3>📤 Nahrát soubory</h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                <div class="folder-select">
                    <span style="font-size:12px;font-weight:600;color:#555;align-self:center">Složka:</span>
                    <?php foreach (['general','produkty','galerie','banner','hero','clanky'] as $fl): ?>
                    <label>
                        <input type="radio" name="folder" value="<?= $fl ?>" <?= $fl==='general'?'checked':'' ?> style="display:none" onchange="this.closest('.folder-select').querySelectorAll('.folder-btn').forEach(b=>b.classList.remove('active'));this.nextElementSibling.classList.add('active')">
                        <span class="folder-btn <?= $fl==='general'?'active':'' ?>"><?= $fl ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="dropzone" id="dropzone">
                    <input type="file" name="files[]" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewFiles(this)">
                    <div class="dropzone-icon">📸</div>
                    <div class="dropzone-text">Přetáhni fotky nebo klikni pro výběr</div>
                    <div class="dropzone-hint">JPG, PNG, WebP, GIF · max 10 MB · více najednou</div>
                </div>
                <div id="previewStrip" class="preview-strip"></div>
                <button type="submit" class="btn btn-primary" id="uploadBtn" style="display:none">📤 Nahrát</button>
            </form>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <form method="GET" style="display:contents">
                <?php if ($folder): ?><input type="hidden" name="folder" value="<?= e($folder) ?>"><?php endif; ?>
                <div class="search-wrap">
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Hledat soubory...">
                </div>
                <select name="sort" onchange="this.form.submit()">
                    <option value="newest" <?= $sortBy==='newest'?'selected':'' ?>>Nejnovější</option>
                    <option value="oldest" <?= $sortBy==='oldest'?'selected':'' ?>>Nejstarší</option>
                    <option value="name" <?= $sortBy==='name'?'selected':'' ?>>Název A-Z</option>
                    <option value="size" <?= $sortBy==='size'?'selected':'' ?>>Největší</option>
                </select>
                <button type="submit" class="btn btn-outline">🔍 Hledat</button>
            </form>
            <span style="color:#888;font-size:13px;margin-left:auto"><?= count($mediaItems) ?> souborů</span>
        </div>

        <!-- GRID -->
        <?php if (empty($mediaItems)): ?>
        <div class="empty">
            <div class="empty-icon">📭</div>
            <div style="font-weight:600;margin-bottom:4px">Žádné soubory</div>
            <div style="font-size:13px">Nahraj první fotky výše</div>
        </div>
        <?php else: ?>
        <div class="media-grid" id="mediaGrid">
            <?php foreach ($mediaItems as $item): ?>
            <div class="media-item" id="item-<?= $item['id'] ?>" onclick="selectItem(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item)) ?>)">
                <div class="media-check">✓</div>
                <div class="media-thumb">
                    <img src="<?= BASE_URL ?>/uploads/<?= e($item['filename']) ?>"
                         alt="<?= e($item['alt_text'] ?? $item['title'] ?? '') ?>"
                         loading="lazy"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'%3E%3Crect fill=\'%23f0f0f0\' width=\'80\' height=\'80\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23aaa\' font-size=\'24\'%3E🖼️%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="media-info">
                    <div class="media-name" title="<?= e($item['filename']) ?>"><?= e($item['title'] ?: $item['filename']) ?></div>
                    <div class="media-size"><?= $item['width'] ? $item['width'].'×'.$item['height'].' · ' : '' ?><?= round($item['filesize']/1024) ?>KB</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- DETAIL PANEL -->
<div class="detail-panel" id="detailPanel">
    <h2>
        <span>Detail souboru</span>
        <button onclick="closeDetail()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:#888">✕</button>
    </h2>

    <div class="detail-preview">
        <img id="detailImg" src="" alt="">
    </div>

    <div style="margin-bottom:16px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#aaa;margin-bottom:6px">URL souboru</div>
        <div class="url-box" id="detailUrl" onclick="copyUrl(this)" title="Klikni pro zkopírování"></div>
        <div style="font-size:11px;color:#aaa;margin-top:3px">klikni pro zkopírování</div>
    </div>

    <form method="POST" id="metaForm">
        <input type="hidden" name="action" value="save_meta">
        <input type="hidden" name="id" id="metaId">
        <input type="hidden" name="folder" id="metaFolder">

        <div class="form-group">
            <label>Název</label>
            <input type="text" name="title" id="metaTitle" placeholder="Popis souboru">
        </div>
        <div class="form-group">
            <label>Alt text (SEO)</label>
            <input type="text" name="alt_text" id="metaAlt" placeholder="Popis pro vyhledávače">
        </div>

        <div style="margin-bottom:16px">
            <div style="font-size:12px;font-weight:600;color:#555;margin-bottom:6px">Informace</div>
            <div class="meta-row"><span class="meta-label">Soubor:</span><span class="meta-val" id="detailFilename">—</span></div>
            <div class="meta-row"><span class="meta-label">Rozměry:</span><span class="meta-val" id="detailDims">—</span></div>
            <div class="meta-row"><span class="meta-label">Velikost:</span><span class="meta-val" id="detailSize">—</span></div>
            <div class="meta-row"><span class="meta-label">Složka:</span><span class="meta-val" id="detailFolder">—</span></div>
            <div class="meta-row"><span class="meta-label">Datum:</span><span class="meta-val" id="detailDate">—</span></div>
        </div>

        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-primary btn-sm">💾 Uložit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Smazat soubor? Tuto akci nelze vrátit!')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <button type="submit" class="btn btn-danger btn-sm">🗑️ Smazat</button>
            </form>
        </div>
    </form>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let selectedId = null;

function selectItem(id, data) {
    // Odznač předchozí
    document.querySelectorAll('.media-item').forEach(i => i.classList.remove('selected'));
    document.getElementById('item-' + id).classList.add('selected');
    selectedId = id;

    // Naplň detail panel
    const url = BASE_URL + '/uploads/' + data.filename;
    document.getElementById('detailImg').src = url;
    document.getElementById('detailUrl').textContent = url;
    document.getElementById('metaId').value = id;
    document.getElementById('deleteId').value = id;
    document.getElementById('metaTitle').value = data.title || '';
    document.getElementById('metaAlt').value = data.alt_text || '';
    document.getElementById('metaFolder').value = data.folder || 'general';
    document.getElementById('detailFilename').textContent = data.filename;
    document.getElementById('detailDims').textContent = data.width ? data.width + '×' + data.height + ' px' : '—';
    document.getElementById('detailSize').textContent = Math.round(data.filesize / 1024) + ' KB';
    document.getElementById('detailFolder').textContent = data.folder || 'general';
    document.getElementById('detailDate').textContent = data.created_at ? data.created_at.substring(0, 10) : '—';

    document.getElementById('detailPanel').classList.add('open');
}

function closeDetail() {
    document.getElementById('detailPanel').classList.remove('open');
    document.querySelectorAll('.media-item').forEach(i => i.classList.remove('selected'));
    selectedId = null;
}

function copyUrl(el) {
    navigator.clipboard.writeText(el.textContent).then(() => {
        const orig = el.style.background;
        el.style.background = '#e8f4ea';
        setTimeout(() => el.style.background = orig, 1000);
    });
}

// Preview při uploadu
function previewFiles(input) {
    const strip = document.getElementById('previewStrip');
    const btn = document.getElementById('uploadBtn');
    strip.innerHTML = '';
    if (!input.files.length) { btn.style.display = 'none'; return; }
    btn.style.display = 'inline-flex';
    btn.textContent = '📤 Nahrát ' + input.files.length + ' ' + (input.files.length === 1 ? 'soubor' : 'souborů');
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'preview-thumb';
            div.innerHTML = '<img src="' + e.target.result + '">';
            strip.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

// Drag & drop
const dz = document.getElementById('dropzone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('drag-over');
    document.getElementById('fileInput').files = e.dataTransfer.files;
    previewFiles(document.getElementById('fileInput'));
});

// Zavři panel kliknutím mimo
document.addEventListener('click', e => {
    if (!e.target.closest('.detail-panel') && !e.target.closest('.media-item') && selectedId) {
        closeDetail();
    }
});
</script>
</body>
</html>
