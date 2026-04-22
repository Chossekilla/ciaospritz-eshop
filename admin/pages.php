<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('manager');

// Vytvoř tabulku pokud neexistuje
$pdo->exec("CREATE TABLE IF NOT EXISTS pages (
    id int NOT NULL AUTO_INCREMENT,
    title_cs varchar(255) NOT NULL,
    title_en varchar(255) DEFAULT NULL,
    slug varchar(100) NOT NULL UNIQUE,
    content_cs longtext,
    content_en longtext,
    meta_desc varchar(255) DEFAULT NULL,
    in_footer tinyint DEFAULT 1,
    in_menu tinyint DEFAULT 0,
    sort_order int DEFAULT 0,
    published tinyint DEFAULT 1,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $slug = trim(preg_replace('/[^a-z0-9-]/', '-', strtolower($_POST['slug'] ?? '')), '-');
        $data = [
            $_POST['title_cs'] ?? '',
            $_POST['title_en'] ?? '',
            $slug,
            $_POST['content_cs'] ?? '',
            $_POST['content_en'] ?? '',
            $_POST['meta_desc'] ?? '',
            isset($_POST['in_footer']) ? 1 : 0,
            isset($_POST['in_menu']) ? 1 : 0,
            (int)($_POST['sort_order'] ?? 0),
            isset($_POST['published']) ? 1 : 0,
        ];

        if ($id) {
            $pdo->prepare("UPDATE pages SET title_cs=?,title_en=?,slug=?,content_cs=?,content_en=?,meta_desc=?,in_footer=?,in_menu=?,sort_order=?,published=? WHERE id=?")
                ->execute([...$data, $id]);
            $message = '✅ Stránka uložena.';
        } else {
            $pdo->prepare("INSERT INTO pages (title_cs,title_en,slug,content_cs,content_en,meta_desc,in_footer,in_menu,sort_order,published) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute($data);
            $id = $pdo->lastInsertId();
            $message = '✅ Stránka vytvořena.';
        }
        logAction($pdo, 'Stránka uložena', $slug);
        header('Location: '.BASE_URL.'/admin/pages.php?id='.$id.'&msg='.urlencode($message));
        exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM pages WHERE id=?")->execute([$_POST['id']]);
        $message = '✅ Stránka smazána.';
        header('Location: '.BASE_URL.'/admin/pages.php?msg='.urlencode($message));
        exit;
    }

    if ($action === 'toggle') {
        $pdo->prepare("UPDATE pages SET published=1-published WHERE id=?")->execute([$_POST['id']]);
        header('Location: '.BASE_URL.'/admin/pages.php?msg='.urlencode('✅ Stav změněn.'));
        exit;
    }
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

$pages = $pdo->query("SELECT * FROM pages ORDER BY sort_order ASC, id ASC")->fetchAll();

$editPage = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id=?");
    $stmt->execute([(int)$_GET['id']]);
    $editPage = $stmt->fetch();
} elseif (isset($_GET['new'])) {
    $editPage = ['id'=>0,'title_cs'=>'','title_en'=>'','slug'=>'','content_cs'=>'','content_en'=>'','meta_desc'=>'','in_footer'=>1,'in_menu'=>0,'sort_order'=>0,'published'=>1];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Stránky (CMS) — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .layout{display:grid;grid-template-columns:300px 1fr;min-height:calc(100vh - 50px)}
        .sidebar{background:white;border-right:1px solid #e0e0e0;padding:0}
        .sidebar-header{padding:16px 20px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between}
        .sidebar-header h2{font-size:.9rem;font-weight:700}
        .page-item{display:block;padding:12px 20px;border-bottom:1px solid #f5f5f5;text-decoration:none;transition:background .15s;border-left:3px solid transparent}
        .page-item:hover{background:#fafafa}
        .page-item.active{border-left-color:#E8631A;background:#fff8f4}
        .page-item .title{font-size:14px;font-weight:600;color:#111}
        .page-item .slug{font-size:11px;color:#888;font-family:monospace}
        .page-item .status{font-size:11px;margin-top:3px}
        .main{padding:28px}
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:20px}
        .card-header{padding:14px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#fafafa}
        .card-header h2{font-size:.95rem;font-weight:700}
        .card-body{padding:24px}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:inherit}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-danger{background:#dc3545;color:white}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#444}.btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-sm{padding:5px 10px;font-size:12px}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
        input[type=text],input[type=number],textarea,select{width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s}
        input:focus,textarea:focus{border-color:#E8631A}
        textarea{resize:vertical;line-height:1.6}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .fg{margin-bottom:14px}
        .alert{padding:12px 20px;border-radius:8px;font-size:14px;margin-bottom:20px;background:rgba(45,122,58,.1);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
        .toggle-wrap{display:flex;align-items:center;gap:10px}
        .toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;inset:0;background:#ddd;border-radius:24px;cursor:pointer;transition:.3s}
        .toggle-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s}
        input:checked+.toggle-slider{background:#2D7A3A}
        input:checked+.toggle-slider:before{transform:translateX(20px)}
        .url-preview{background:#f5f5f5;border-radius:6px;padding:8px 12px;font-family:monospace;font-size:12px;color:#444;margin-top:6px}
        .empty-state{text-align:center;padding:48px;color:#aaa}
        .empty-state .icon{font-size:3rem;margin-bottom:12px}
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <h1>📄 Správa stránek (CMS)</h1>
</div>

<div class="layout">
    <!-- SEZNAM STRÁNEK -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Stránky (<?= count($pages) ?>)</h2>
            <a href="<?= BASE_URL ?>/admin/pages.php?new=1" class="btn btn-primary btn-sm">+ Nová</a>
        </div>

        <?php if (empty($pages)): ?>
        <div style="padding:24px;text-align:center;color:#aaa;font-size:13px">
            Žádné stránky.<br>Vytvořte první →
        </div>
        <?php endif; ?>

        <?php foreach ($pages as $p): ?>
        <a href="<?= BASE_URL ?>/admin/pages.php?id=<?= $p['id'] ?>"
           class="page-item <?= isset($_GET['id']) && (int)$_GET['id']===$p['id'] ? 'active' : '' ?>">
            <div class="title"><?= htmlspecialchars($p['title_cs']) ?></div>
            <div class="slug">/stranka/<?= htmlspecialchars($p['slug']) ?></div>
            <div class="status">
                <?= $p['published'] ? '✅ Publikovaná' : '🔒 Skrytá' ?>
                <?= $p['in_footer'] ? ' · 🔗 Footer' : '' ?>
                <?= $p['in_menu'] ? ' · 📋 Menu' : '' ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- EDITOR -->
    <div class="main">
        <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($editPage !== null): ?>
        <div class="card">
            <div class="card-header">
                <h2><?= $editPage['id'] ? '✏️ Upravit: '.htmlspecialchars($editPage['title_cs']) : '➕ Nová stránka' ?></h2>
                <?php if ($editPage['id'] && $editPage['published']): ?>
                <a href="<?= BASE_URL ?>/stranka/<?= $editPage['slug'] ?>" target="_blank" class="btn btn-outline btn-sm">🌐 Zobrazit →</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $editPage['id'] ?>">

                    <div class="grid-2">
                        <div class="fg">
                            <label>Název stránky (CZ) *</label>
                            <input type="text" name="title_cs" value="<?= htmlspecialchars($editPage['title_cs']) ?>" required
                                   oninput="autoSlug(this.value)" placeholder="Obchodní podmínky">
                        </div>
                        <div class="fg">
                            <label>Název stránky (EN)</label>
                            <input type="text" name="title_en" value="<?= htmlspecialchars($editPage['title_en']??'') ?>" placeholder="Terms & Conditions">
                        </div>
                    </div>

                    <div class="fg">
                        <label>URL slug *</label>
                        <input type="text" name="slug" id="slugInput" value="<?= htmlspecialchars($editPage['slug']) ?>" required placeholder="obchodni-podminky">
                        <div class="url-preview" id="urlPreview"><?= BASE_URL ?>/stranka/<?= htmlspecialchars($editPage['slug'] ?: 'slug') ?></div>
                    </div>

                    <div class="fg">
                        <label>Meta popis (pro SEO, max 160 znaků)</label>
                        <input type="text" name="meta_desc" value="<?= htmlspecialchars($editPage['meta_desc']??'') ?>" maxlength="160" placeholder="Krátký popis stránky pro vyhledávače...">
                    </div>

                    <div class="fg">
                        <label>Obsah stránky (CZ) — HTML</label>
                        <textarea name="content_cs" rows="16" placeholder="<h2>Nadpis</h2><p>Text stránky...</p>"><?= htmlspecialchars($editPage['content_cs']??'') ?></textarea>
                    </div>

                    <div class="fg">
                        <label>Obsah stránky (EN) — HTML (volitelné)</label>
                        <textarea name="content_en" rows="8" placeholder="<h2>Heading</h2><p>Page content...</p>"><?= htmlspecialchars($editPage['content_en']??'') ?></textarea>
                    </div>

                    <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:20px;padding:16px;background:#f9f9f9;border-radius:8px">
                        <label class="toggle-wrap">
                            <label class="toggle-switch">
                                <input type="checkbox" name="published" <?= $editPage['published']?'checked':'' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Publikovaná</span>
                        </label>
                        <label class="toggle-wrap">
                            <label class="toggle-switch">
                                <input type="checkbox" name="in_footer" <?= $editPage['in_footer']?'checked':'' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Zobrazit v patičce</span>
                        </label>
                        <label class="toggle-wrap">
                            <label class="toggle-switch">
                                <input type="checkbox" name="in_menu" <?= $editPage['in_menu']?'checked':'' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Zobrazit v navigaci</span>
                        </label>
                        <div style="display:flex;align-items:center;gap:8px">
                            <label style="margin:0">Pořadí:</label>
                            <input type="number" name="sort_order" value="<?= $editPage['sort_order'] ?>" style="width:70px">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;align-items:center">
                        <button type="submit" class="btn btn-primary">💾 Uložit stránku</button>
                        <?php if ($editPage['id']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Smazat stránku?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $editPage['id'] ?>">
                            <button type="submit" class="btn btn-danger">🗑️ Smazat</button>
                        </form>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/admin/pages.php" class="btn btn-outline">Zrušit</a>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- PŘEHLED -->
        <div class="card">
            <div class="card-header">
                <h2>📄 Všechny stránky (<?= count($pages) ?>)</h2>
                <a href="<?= BASE_URL ?>/admin/pages.php?new=1" class="btn btn-primary">+ Přidat stránku</a>
            </div>
            <?php if (empty($pages)): ?>
            <div class="empty-state">
                <div class="icon">📄</div>
                <div style="font-weight:600;margin-bottom:8px">Žádné stránky</div>
                <div style="font-size:13px;margin-bottom:16px">Vytvořte první CMS stránku — obchodní podmínky, GDPR, O nás...</div>
                <a href="<?= BASE_URL ?>/admin/pages.php?new=1" class="btn btn-primary">+ Vytvořit stránku</a>
            </div>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#fafafa">
                        <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;border-bottom:1px solid #e0e0e0">Název</th>
                        <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;border-bottom:1px solid #e0e0e0">URL</th>
                        <th style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;border-bottom:1px solid #e0e0e0">Umístění</th>
                        <th style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;border-bottom:1px solid #e0e0e0">Stav</th>
                        <th style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;border-bottom:1px solid #e0e0e0">Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $p): ?>
                <tr style="border-bottom:1px solid #f5f5f5">
                    <td style="padding:12px 16px">
                        <div style="font-weight:600"><?= htmlspecialchars($p['title_cs']) ?></div>
                        <?php if ($p['title_en']): ?><div style="font-size:11px;color:#aaa"><?= htmlspecialchars($p['title_en']) ?></div><?php endif; ?>
                    </td>
                    <td style="padding:12px 16px;font-family:monospace;font-size:12px;color:#666">/stranka/<?= htmlspecialchars($p['slug']) ?></td>
                    <td style="padding:12px 16px;font-size:12px">
                        <?= $p['in_footer'] ? '<span style="background:#e8f4ea;color:#2D7A3A;padding:2px 8px;border-radius:4px;margin-right:4px">Footer</span>' : '' ?>
                        <?= $p['in_menu'] ? '<span style="background:#e8f0fe;color:#4285f4;padding:2px 8px;border-radius:4px">Menu</span>' : '' ?>
                    </td>
                    <td style="padding:12px 16px">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:<?= $p['published']?'rgba(45,122,58,.1);color:#2D7A3A':'rgba(220,53,69,.1);color:#dc3545' ?>">
                                <?= $p['published'] ? '✅ Pub.' : '🔒 Skrytá' ?>
                            </button>
                        </form>
                    </td>
                    <td style="padding:12px 16px">
                        <a href="<?= BASE_URL ?>/admin/pages.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">✏️ Upravit</a>
                        <a href="<?= BASE_URL ?>/stranka/<?= $p['slug'] ?>" target="_blank" class="btn btn-sm" style="background:#f0f0f0;color:#444">🌐</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function autoSlug(val) {
    if (document.getElementById('slugInput').value !== '') return; // Neměn pokud ručně nastaven
    const slug = val.toLowerCase()
        .replace(/[áàä]/g,'a').replace(/[éèě]/g,'e').replace(/[íì]/g,'i')
        .replace(/[óò]/g,'o').replace(/[úùů]/g,'u').replace(/[ýÿ]/g,'y')
        .replace(/[čc]/g,'c').replace(/[šs]/g,'s').replace(/[žz]/g,'z')
        .replace(/[ňn]/g,'n').replace(/[ďd]/g,'d').replace(/[ťt]/g,'t')
        .replace(/[řr]/g,'r').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    document.getElementById('slugInput').value = slug;
    document.getElementById('urlPreview').textContent = '<?= BASE_URL ?>/stranka/' + slug;
}
document.getElementById('slugInput')?.addEventListener('input', function() {
    document.getElementById('urlPreview').textContent = '<?= BASE_URL ?>/stranka/' + this.value;
});
</script>
</body>
</html>
