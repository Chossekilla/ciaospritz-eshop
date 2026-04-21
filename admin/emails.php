<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/../includes/mailer.php';
requireLogin();
requireRole('admin');

// Vytvoř tabulku
$pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (
    id int NOT NULL AUTO_INCREMENT,
    type varchar(50) NOT NULL UNIQUE,
    subject_cs varchar(255) NOT NULL,
    subject_en varchar(255) DEFAULT NULL,
    body_cs text NOT NULL,
    body_en text DEFAULT NULL,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Inicializuj výchozí šablony
$types = ['order_confirmation', 'order_shipped', 'order_ready', 'order_admin'];
foreach ($types as $type) {
    $exists = $pdo->prepare("SELECT id FROM email_templates WHERE type=?");
    $exists->execute([$type]);
    if (!$exists->fetch()) {
        $def = getDefaultTemplate($type);
        $pdo->prepare("INSERT INTO email_templates (type, subject_cs, subject_en, body_cs, body_en) VALUES (?,?,?,?,?)")
            ->execute([$type, $def['subject_cs'], $def['subject_en'] ?? '', $def['body_cs'], $def['body_en'] ?? '']);
    }
}

$message = '';

// Uložit šablonu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $type = $_POST['type'] ?? '';
        $pdo->prepare("UPDATE email_templates SET subject_cs=?, subject_en=?, body_cs=?, body_en=? WHERE type=?")
            ->execute([$_POST['subject_cs'] ?? '', $_POST['subject_en'] ?? '', $_POST['body_cs'] ?? '', $_POST['body_en'] ?? '', $type]);
        logAction($pdo, 'Email šablona uložena', $type);
        $message = '✅ Šablona uložena.';
    }
    if ($_POST['action'] === 'test') {
        $type = $_POST['type'] ?? 'order_confirmation';
        $tpl = getEmailTemplate($pdo, $type);
        $testBody = $tpl['body_cs'];
        $testBody = str_replace(
            ['{customer_name}', '{order_number}', '{items_table}', '{shipping_method}', '{payment_method}', '{payment_instructions}', '{customer_email}', '{customer_phone}', '{customer_address}', '{note}', '{admin_url}'],
            ['Jan Novák', 'CS-20260421-TEST', '<p><em>[Tabulka produktů]</em></p>', 'Kurýr GLS', 'Bankovní převod', paymentInstructions('prevod', 399, 'CS-TEST'), 'test@example.com', '777 123 456', 'Testovací 1, 110 00 Praha', 'Testovací poznámka', BASE_URL.'/admin/'],
            $testBody
        );
        $subject = str_replace(['{order_number}', '{customer_name}'], ['CS-20260421-TEST', 'Jan Novák'], $tpl['subject_cs']);
        $sent = sendEmail('rcaffe@email.cz', '[TEST] ' . $subject, emailWrapper($testBody));
        $message = $sent ? '✅ Testovací email odeslán na rcaffe@email.cz' : '❌ Chyba odesílání. Zkontroluj SMTP nastavení.';
    }
    header('Location: '.BASE_URL.'/admin/emails.php?type='.urlencode($_POST['type'] ?? '').'&msg='.urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);
$activeType = $_GET['type'] ?? 'order_confirmation';
$templates = $pdo->query("SELECT * FROM email_templates ORDER BY type")->fetchAll();
$activeTemplate = null;
foreach ($templates as $t) {
    if ($t['type'] === $activeType) { $activeTemplate = $t; break; }
}
if (!$activeTemplate && !empty($templates)) { $activeTemplate = $templates[0]; $activeType = $activeTemplate['type']; }

$typeLabels = [
    'order_confirmation' => ['icon' => '✅', 'label' => 'Potvrzení objednávky', 'desc' => 'Odesílá se zákazníkovi ihned po objednávce'],
    'order_shipped'      => ['icon' => '🚚', 'label' => 'Objednávka odeslána', 'desc' => 'Odesílá se zákazníkovi při změně stavu na "odesláno"'],
    'order_ready'        => ['icon' => '📦', 'label' => 'Připraveno k odběru', 'desc' => 'Odesílá se zákazníkovi při změně stavu na "připraveno"'],
    'order_admin'        => ['icon' => '🛒', 'label' => 'Oznámení adminovi', 'desc' => 'Odesílá se na rcaffe@email.cz při každé nové objednávce'],
];

$variables = [
    '{customer_name}' => 'Jméno zákazníka',
    '{order_number}' => 'Číslo objednávky',
    '{customer_email}' => 'Email zákazníka',
    '{customer_phone}' => 'Telefon zákazníka',
    '{customer_address}' => 'Adresa zákazníka',
    '{shipping_method}' => 'Způsob dopravy',
    '{payment_method}' => 'Způsob platby',
    '{items_table}' => 'Tabulka produktů',
    '{payment_instructions}' => 'Platební instrukce',
    '{note}' => 'Poznámka k objednávce',
    '{admin_url}' => 'Odkaz do adminu',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Email šablony — Admin Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 50px)}
        .sidebar{background:white;border-right:1px solid #e0e0e0;padding:20px 0}
        .sidebar h3{font-size:11px;font-weight:700;text-transform:uppercase;color:#aaa;letter-spacing:.08em;padding:0 16px 10px}
        .tpl-item{display:block;padding:12px 16px;text-decoration:none;border-left:3px solid transparent;transition:all .2s;cursor:pointer}
        .tpl-item:hover{background:#f9f9f9}
        .tpl-item.active{border-left-color:#E8631A;background:#fff8f4}
        .tpl-item .icon{font-size:1.2rem;margin-right:8px}
        .tpl-item .label{font-size:14px;font-weight:600;color:#222}
        .tpl-item .desc{font-size:12px;color:#888;margin-top:2px}
        .main{padding:28px}
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:20px}
        .card-header{padding:16px 24px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#fafafa}
        .card-header h2{font-size:1rem;font-weight:700}
        .card-body{padding:24px}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:inherit}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#444}.btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-blue{background:#007bff;color:white}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
        input[type=text]{width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s;margin-bottom:14px}
        input[type=text]:focus{border-color:#E8631A}
        textarea{width:100%;padding:10px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:monospace;font-size:12px;resize:vertical;outline:none;transition:border-color .2s;line-height:1.6}
        textarea:focus{border-color:#E8631A}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .alert{padding:12px 20px;border-radius:8px;font-size:14px;margin-bottom:20px;background:rgba(45,122,58,.1);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
        .alert-err{background:rgba(220,53,69,.08);color:#dc3545;border-color:rgba(220,53,69,.2)}
        .vars-grid{display:flex;flex-wrap:wrap;gap:6px}
        .var-tag{background:#f0f0f0;border:1px solid #ddd;border-radius:4px;padding:3px 8px;font-family:monospace;font-size:11px;cursor:pointer;transition:all .2s}
        .var-tag:hover{background:#E8631A;color:white;border-color:#E8631A}
        .preview-frame{width:100%;height:500px;border:none;border-radius:8px;background:white}
        .tabs{display:flex;border-bottom:2px solid #e0e0e0;margin-bottom:20px}
        .tab{padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:#888;transition:all .2s}
        .tab.active{color:#E8631A;border-bottom-color:#E8631A}
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <h1>📧 Email šablony</h1>
</div>

<div class="layout">
    <!-- Sidebar -->
    <div class="sidebar">
        <h3>Šablony</h3>
        <?php foreach ($typeLabels as $type => $info): ?>
        <a class="tpl-item <?= $type === $activeType ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/admin/emails.php?type=<?= $type ?>">
            <div><span class="icon"><?= $info['icon'] ?></span><span class="label"><?= $info['label'] ?></span></div>
            <div class="desc" style="padding-left:28px"><?= $info['desc'] ?></div>
        </a>
        <?php endforeach; ?>

        <div style="padding:20px 16px 0">
            <h3 style="margin-bottom:10px">Proměnné</h3>
            <div style="font-size:12px;color:#666;margin-bottom:8px">Klikni pro vložení do textu:</div>
            <?php foreach ($variables as $var => $desc): ?>
            <div class="var-tag" onclick="insertVar('<?= $var ?>')" title="<?= $desc ?>" style="display:inline-block;margin:2px"><?= $var ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Hlavní obsah -->
    <div class="main">
        <?php if ($message): ?>
        <div class="alert <?= str_starts_with($message,'❌')?'alert-err':'' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($activeTemplate): ?>
        <div class="card">
            <div class="card-header">
                <h2><?= $typeLabels[$activeType]['icon'] ?> <?= $typeLabels[$activeType]['label'] ?></h2>
                <div style="font-size:12px;color:#888"><?= $typeLabels[$activeType]['desc'] ?></div>
            </div>
            <div class="card-body">
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('edit')">✏️ Editace</div>
                    <div class="tab" onclick="switchTab('preview')">👁️ Náhled</div>
                </div>

                <!-- EDITACE -->
                <div id="tabEdit">
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="type" value="<?= $activeType ?>">

                        <div class="grid-2">
                            <div>
                                <label>Předmět emailu (CZ)</label>
                                <input type="text" name="subject_cs" value="<?= htmlspecialchars($activeTemplate['subject_cs']) ?>" id="subjectCs">
                            </div>
                            <div>
                                <label>Předmět emailu (EN)</label>
                                <input type="text" name="subject_en" value="<?= htmlspecialchars($activeTemplate['subject_en'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="grid-2" style="margin-bottom:16px">
                            <div>
                                <label>Tělo emailu (CZ) — HTML</label>
                                <textarea name="body_cs" id="bodyCs" rows="16"><?= htmlspecialchars($activeTemplate['body_cs']) ?></textarea>
                            </div>
                            <div>
                                <label>Tělo emailu (EN) — HTML</label>
                                <textarea name="body_en" id="bodyEn" rows="16"><?= htmlspecialchars($activeTemplate['body_en'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;align-items:center">
                            <button type="submit" class="btn btn-primary">💾 Uložit šablonu</button>
                            <button type="button" onclick="showPreview()" class="btn btn-outline">👁️ Náhled</button>
                        </div>
                    </form>

                    <form method="POST" style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
                        <input type="hidden" name="action" value="test">
                        <input type="hidden" name="type" value="<?= $activeType ?>">
                        <div style="display:flex;align-items:center;gap:12px">
                            <button type="submit" class="btn btn-blue">📨 Poslat testovací email na rcaffe@email.cz</button>
                            <span style="font-size:12px;color:#aaa">Odešle email s testovacími daty</span>
                        </div>
                    </form>
                </div>

                <!-- NÁHLED -->
                <div id="tabPreview" style="display:none">
                    <iframe class="preview-frame" id="previewFrame"></iframe>
                </div>
            </div>
        </div>

        <!-- Naposledy upraveno -->
        <?php if (!empty($activeTemplate['updated_at'])): ?>
        <div style="font-size:12px;color:#aaa;text-align:right">
            Naposledy upraveno: <?= date('j. n. Y H:i', strtotime($activeTemplate['updated_at'])) ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
let activeTab = 'edit';

function switchTab(tab) {
    activeTab = tab;
    document.getElementById('tabEdit').style.display = tab === 'edit' ? 'block' : 'none';
    document.getElementById('tabPreview').style.display = tab === 'preview' ? 'block' : 'none';
    document.querySelectorAll('.tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && tab === 'edit') || (i === 1 && tab === 'preview'));
    });
    if (tab === 'preview') showPreview();
}

function showPreview() {
    const body = document.getElementById('bodyCs').value;
    const preview = body
        .replace(/{customer_name}/g, 'Jan Novák')
        .replace(/{order_number}/g, 'CS-20260421-TEST')
        .replace(/{items_table}/g, '<table style="width:100%;border:1px solid #eee;padding:10px;margin:16px 0"><tr><td>Ciao Spritz 0,75 l</td><td>2x</td><td style="text-align:right"><strong>318 Kč</strong></td></tr><tr style="background:#f9f9f9"><td colspan="2" style="text-align:right;font-weight:700">Celkem:</td><td style="text-align:right;font-size:18px;color:#E8631A;font-weight:900">318 Kč</td></tr></table>')
        .replace(/{payment_instructions}/g, '<div style="background:#f0f8f1;border:1px solid #c3e6cb;border-radius:8px;padding:16px;margin:16px 0"><strong>Bankovní převod:</strong> 2502996691/2010, VS: 20260421</div>')
        .replace(/{shipping_method}/g, 'Kurýr GLS')
        .replace(/{payment_method}/g, 'Bankovní převod')
        .replace(/\{[^}]+\}/g, '<em style="color:#aaa">[proměnná]</em>');

    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Helvetica,Arial,sans-serif;background:#f5f0eb;padding:20px}</style></head><body><table width="600" style="margin:0 auto;background:white;border-radius:12px;overflow:hidden"><tr><td style="background:#111;padding:24px;text-align:center"><div style="font-family:Georgia,serif;font-size:24px;font-weight:900;color:white">CIAO <span style="color:#E8631A">SPRITZ</span></div></td></tr><tr><td style="padding:32px;font-size:15px;line-height:1.7;color:#333">${preview}</td></tr><tr><td style="background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#888">rcaffe@email.cz · 602 556 323</td></tr></table></body></html>`;

    const frame = document.getElementById('previewFrame');
    frame.srcdoc = html;
    switchTab('preview');
}

function insertVar(v) {
    const active = document.activeElement;
    if (active && (active.id === 'bodyCs' || active.id === 'bodyEn' || active.id === 'subjectCs')) {
        const start = active.selectionStart;
        const end = active.selectionEnd;
        active.value = active.value.substring(0, start) + v + active.value.substring(end);
        active.selectionStart = active.selectionEnd = start + v.length;
        active.focus();
    } else {
        navigator.clipboard.writeText(v);
        alert('Zkopírováno: ' + v);
    }
}
</script>
</body>
</html>
