<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('manager');

$id = (int)($_GET['id'] ?? 0);
$article = null;
$success = '';
$error = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) { header('Location: index.php?section=articles'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_cs   = trim($_POST['title_cs'] ?? '');
    $title_en   = trim($_POST['title_en'] ?? '');
    $perex_cs   = trim($_POST['perex_cs'] ?? '');
    $perex_en   = trim($_POST['perex_en'] ?? '');
    $content_cs = trim($_POST['content_cs'] ?? '');
    $content_en = trim($_POST['content_en'] ?? '');
    $published  = isset($_POST['published']) ? 1 : 0;
    $slug       = trim($_POST['slug'] ?? '');

    if (!$slug && $title_cs) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $title_cs)));
        $slug = trim($slug, '-');
    }

    $image = $article['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $filename = 'article-' . time() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $filename);
            $image = $filename;
        }
    }

    if (!$title_cs) {
        $error = 'Vyplňte název článku.';
    } else {
        try {
            if ($id) {
                $pdo->prepare("UPDATE articles SET title_cs=?,title_en=?,perex_cs=?,perex_en=?,content_cs=?,content_en=?,slug=?,published=?,image=? WHERE id=?")
                    ->execute([$title_cs,$title_en,$perex_cs,$perex_en,$content_cs,$content_en,$slug,$published,$image,$id]);
            } else {
                $pdo->prepare("INSERT INTO articles (title_cs,title_en,perex_cs,perex_en,content_cs,content_en,slug,published,image) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$title_cs,$title_en,$perex_cs,$perex_en,$content_cs,$content_en,$slug,$published,$image]);
                $id = $pdo->lastInsertId();
            }
            logAction($pdo, 'Uložen článek', "ID: $id");
            $success = 'Článek byl uložen.';
            $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$id]);
            $article = $stmt->fetch();
        } catch (Exception $e) {
            $error = 'Chyba: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Upravit článek' : 'Nový článek' ?> — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .save-bar{background:white;border-bottom:1px solid #e0e0e0;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:sticky;top:49px;z-index:99}
        .wrap{max-width:900px;margin:24px auto;padding:0 24px;display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start}
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:16px}
        .card-title{padding:14px 20px;border-bottom:1px solid #f0f0f0;font-weight:700;font-size:.9rem;background:#fafafa}
        .card-body{padding:20px}
        .form-group{margin-bottom:16px}.form-group:last-child{margin-bottom:0}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
        input[type=text],input[type=file],select,textarea{width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s}
        input:focus,select:focus,textarea:focus{border-color:#E8631A}
        textarea{resize:vertical;min-height:80px}
        .tabs{display:flex;border-bottom:2px solid #e0e0e0;margin-bottom:16px}
        .tab{padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:#888;user-select:none}
        .tab.active{color:#E8631A;border-bottom-color:#E8631A}
        .tab-panel{display:none}.tab-panel.active{display:block}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:all .2s}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#555}.btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-lg{padding:12px 24px;font-size:15px}.btn-full{width:100%;justify-content:center}
        .toggle-wrap{display:flex;align-items:center;gap:10px;padding:8px 0}
        .toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;inset:0;background:#ddd;border-radius:24px;cursor:pointer;transition:.3s}
        .toggle-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s}
        input:checked+.toggle-slider{background:#2D7A3A}
        input:checked+.toggle-slider:before{transform:translateX(20px)}
        .img-preview{max-width:100%;border-radius:8px;margin-top:8px}
        .ql-container{border-bottom-left-radius:8px;border-bottom-right-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px}
        .ql-toolbar{border-top-left-radius:8px;border-top-right-radius:8px;background:#fafafa}
        .ql-editor{min-height:200px}
    </style>
</head>
<body>

<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php?section=articles">← Články</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a>
    <h1><?= $id ? 'Upravit: ' . htmlspecialchars($article['title_cs'] ?? '') : 'Nový článek' ?></h1>
</div>

<form method="POST" enctype="multipart/form-data" id="articleForm">
<div class="save-bar">
    <div style="font-weight:700"><?= $id ? '✏️ Úprava článku' : '➕ Nový článek' ?>
        <?php if (!empty($article['slug'])): ?>
        <a href="<?= BASE_URL ?>/novinka.php?slug=<?= htmlspecialchars($article['slug']) ?>" target="_blank" style="font-size:12px;color:#E8631A;font-weight:400;margin-left:12px">🌐 Zobrazit →</a>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <?php if ($success): ?><span style="color:#2D7A3A;font-size:13px">✅ <?= htmlspecialchars($success) ?></span><?php endif; ?>
        <?php if ($error): ?><span style="color:#dc3545;font-size:13px">⚠️ <?= htmlspecialchars($error) ?></span><?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/index.php?section=articles" class="btn btn-outline">Zrušit</a>
        <button type="submit" class="btn btn-primary btn-lg">💾 Uložit</button>
    </div>
</div>

<div class="wrap">
<div>
    <div class="card">
        <div class="card-title">📝 Obsah článku</div>
        <div class="card-body">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('cs',this)">🇨🇿 Česky</div>
                <div class="tab" onclick="switchTab('en',this)">🇬🇧 English</div>
            </div>
            <div class="tab-panel active" id="tab-cs">
                <div class="form-group">
                    <label>Název (CZ) *</label>
                    <input type="text" name="title_cs" required value="<?= htmlspecialchars($article['title_cs'] ?? '') ?>" placeholder="Název článku" oninput="autoSlug(this.value)">
                </div>
                <div class="form-group">
                    <label>Perex (krátký úvod)</label>
                    <textarea name="perex_cs" rows="3" placeholder="Stručný popis..."><?= htmlspecialchars($article['perex_cs'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Obsah článku</label>
                    <div id="editor-cs"></div>
                    <input type="hidden" name="content_cs" id="content_cs">
                </div>
            </div>
            <div class="tab-panel" id="tab-en">
                <div class="form-group">
                    <label>Title (EN)</label>
                    <input type="text" name="title_en" value="<?= htmlspecialchars($article['title_en'] ?? '') ?>" placeholder="Article title">
                </div>
                <div class="form-group">
                    <label>Perex (short intro)</label>
                    <textarea name="perex_en" rows="3" placeholder="Brief description..."><?= htmlspecialchars($article['perex_en'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Article content</label>
                    <div id="editor-en"></div>
                    <input type="hidden" name="content_en" id="content_en">
                </div>
            </div>
        </div>
    </div>
</div>

<div>
    <div class="card">
        <div class="card-title">⚙️ Nastavení</div>
        <div class="card-body">
            <label class="toggle-wrap">
                <label class="toggle-switch">
                    <input type="checkbox" name="published" <?= ($article['published'] ?? 0) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span style="font-size:13px;font-weight:600">Publikovat článek</span>
            </label>
            <div class="form-group" style="margin-top:12px">
                <label>URL Slug</label>
                <input type="text" name="slug" id="slugInput" value="<?= htmlspecialchars($article['slug'] ?? '') ?>" placeholder="automaticky-z-nazvu">
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-title">🖼️ Obrázek</div>
        <div class="card-body">
            <?php if (!empty($article['image'])): ?>
                <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($article['image']) ?>" class="img-preview" style="margin-bottom:12px">
            <?php endif; ?>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" onchange="previewImg(this)">
            <img id="newPreview" class="img-preview" style="display:none">
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-full btn-lg">💾 Uložit článek</button>
</div>
</div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
const toolbarOptions = [[{header:[2,3,false]}],['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link'],['clean']];
const quillCs = new Quill('#editor-cs', {theme:'snow', placeholder:'Obsah česky...', modules:{toolbar:toolbarOptions}});
const quillEn = new Quill('#editor-en', {theme:'snow', placeholder:'Content in English...', modules:{toolbar:toolbarOptions}});
quillCs.root.innerHTML = <?= json_encode($article['content_cs'] ?? '') ?>;
quillEn.root.innerHTML = <?= json_encode($article['content_en'] ?? '') ?>;
document.getElementById('articleForm').addEventListener('submit', function() {
    document.getElementById('content_cs').value = quillCs.root.innerHTML;
    document.getElementById('content_en').value = quillEn.root.innerHTML;
});
function switchTab(lang, el) {
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-'+lang).classList.add('active');
}
let manualSlug = false;
function autoSlug(val) {
    if (manualSlug) return;
    document.getElementById('slugInput').value = val.toLowerCase()
        .replace(/[áäàâ]/g,'a').replace(/[čç]/g,'c').replace(/[ďđ]/g,'d')
        .replace(/[éěèê]/g,'e').replace(/[íîì]/g,'i').replace(/[ňñ]/g,'n')
        .replace(/[óöôò]/g,'o').replace(/[řŕ]/g,'r').replace(/[šś]/g,'s')
        .replace(/[ťţ]/g,'t').replace(/[úůüùû]/g,'u').replace(/[ýÿ]/g,'y')
        .replace(/[žź]/g,'z').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
}
document.getElementById('slugInput').addEventListener('input', ()=>{ manualSlug = true; });
function previewImg(input) {
    if (input.files[0]) {
        const r = new FileReader();
        r.onload = e => { const p = document.getElementById('newPreview'); p.src = e.target.result; p.style.display = 'block'; };
        r.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
