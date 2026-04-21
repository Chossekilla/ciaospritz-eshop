<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();
requireRole('admin');

$message = '';

// Uložení upraveného popisu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_desc') {
    $id = (int)$_POST['id'];
    $desc_cs = trim($_POST['description_cs'] ?? '');
    $desc_en = trim($_POST['description_en'] ?? '');
    $short_cs = trim($_POST['short_desc_cs'] ?? '');
    $short_en = trim($_POST['short_desc_en'] ?? '');

    $pdo->prepare("UPDATE products SET description_cs=?, description_en=?, short_desc_cs=?, short_desc_en=? WHERE id=?")
        ->execute([$desc_cs, $desc_en, $short_cs, $short_en, $id]);
    logAction($pdo, 'AI popis uložen', "ID: $id");
    header('Location: '.BASE_URL.'/admin/ai-descriptions.php?msg='.urlencode('✅ Popis uložen.'));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

$products = $pdo->query("SELECT id, name_cs, name_en, description_cs, description_en, short_desc_cs, short_desc_en, price, category FROM products WHERE active=1 ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Popisy produktů — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
        .topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
        .topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar a:hover{color:white}
        .topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
        .content{max-width:1000px;margin:28px auto;padding:0 24px}
        .card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:20px}
        .card-header{padding:14px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#fafafa}
        .card-header h2{font-size:.95rem;font-weight:700}
        .card-body{padding:20px}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:inherit}
        .btn-primary{background:#2D7A3A;color:white}.btn-primary:hover{background:#38973f}
        .btn-ai{background:linear-gradient(135deg,#6f42c1,#E8631A);color:white}
        .btn-ai:hover{opacity:.9}
        .btn-outline{background:none;border:2px solid #e0e0e0;color:#444}.btn-outline:hover{border-color:#E8631A;color:#E8631A}
        .btn-sm{padding:5px 12px;font-size:12px}
        label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px}
        textarea{width:100%;padding:10px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;outline:none;transition:border-color .2s;line-height:1.6}
        textarea:focus{border-color:#E8631A}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .alert{padding:12px 20px;border-radius:8px;font-size:14px;margin-bottom:20px;background:rgba(45,122,58,.1);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
        .spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .6s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .ai-badge{background:linear-gradient(135deg,#6f42c1,#E8631A);color:white;font-size:10px;font-weight:700;padding:2px 8px;border-radius:50px;margin-left:6px}
        .product-row{border:1px solid #e0e0e0;border-radius:10px;padding:16px;margin-bottom:12px;background:white}
        .product-row-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
        .product-name{font-weight:700;font-size:.95rem}
        .product-cat{font-size:12px;color:#888;margin-left:8px}
        .status-has{color:#2D7A3A;font-size:12px;font-weight:600}
        .status-empty{color:#dc3545;font-size:12px;font-weight:600}
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,.3)">›</span>
    <h1>🤖 AI Popisy produktů</h1>
</div>

<div class="content">
    <?php if ($message): ?>
    <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="display:flex;align-items:center;gap:16px">
            <div style="font-size:2rem">🤖</div>
            <div>
                <div style="font-weight:700;margin-bottom:4px">Generování popisů pomocí Claude AI <span class="ai-badge">AI</span></div>
                <div style="font-size:13px;color:#666">Klikni na „Generovat AI popis" u každého produktu. Claude vygeneruje popis v češtině i angličtině na základě kontextu značky Ciao Spritz.</div>
            </div>
            <button onclick="generateAll()" class="btn btn-ai" style="margin-left:auto;white-space:nowrap">
                🤖 Generovat všechny
            </button>
        </div>
    </div>

    <?php foreach ($products as $p): ?>
    <div class="product-row" id="row-<?= $p['id'] ?>">
        <div class="product-row-header">
            <div>
                <span class="product-name"><?= htmlspecialchars($p['name_cs']) ?></span>
                <span class="product-cat"><?= htmlspecialchars($p['category']) ?> · <?= number_format($p['price'],0,',',' ') ?> Kč</span>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <span class="<?= !empty($p['description_cs']) ? 'status-has' : 'status-empty' ?>">
                    <?= !empty($p['description_cs']) ? '✅ Má popis' : '❌ Bez popisu' ?>
                </span>
                <button onclick="generateDesc(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p['name_cs'])) ?>, <?= htmlspecialchars(json_encode($p['category'])) ?>)"
                        class="btn btn-ai btn-sm" id="btn-<?= $p['id'] ?>">
                    <span class="spinner" id="spin-<?= $p['id'] ?>"></span>
                    🤖 Generovat
                </button>
            </div>
        </div>

        <form method="POST" id="form-<?= $p['id'] ?>">
            <input type="hidden" name="action" value="save_desc">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <div style="margin-bottom:12px">
                <label>Krátký popis CZ (přehled produktů)</label>
                <textarea name="short_desc_cs" id="short_cs_<?= $p['id'] ?>" rows="2"><?= htmlspecialchars($p['short_desc_cs']??'') ?></textarea>
            </div>
            <div class="grid-2" style="margin-bottom:12px">
                <div>
                    <label>Popis CZ (detail stránky)</label>
                    <textarea name="description_cs" id="desc_cs_<?= $p['id'] ?>" rows="5"><?= htmlspecialchars($p['description_cs']??'') ?></textarea>
                </div>
                <div>
                    <label>Popis EN</label>
                    <textarea name="description_en" id="desc_en_<?= $p['id'] ?>" rows="5"><?= htmlspecialchars($p['description_en']??'') ?></textarea>
                </div>
            </div>
            <div class="grid-2" style="margin-bottom:12px">
                <div>
                    <label>Krátký popis EN</label>
                    <textarea name="short_desc_en" id="short_en_<?= $p['id'] ?>" rows="2"><?= htmlspecialchars($p['short_desc_en']??'') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">💾 Uložit popis</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<script>
const brandContext = `Ciao Spritz je italský aperitiv s historií od 19. století z Benátska. Vyrábí se z hroznů odrůdy Glera (stejné jako prosecco) doplněných přírodními bylinnými aromaty. Má hořkosladkou chuť s tóny tropického ovoce a příjemnou oranžovou barvu. Je namíchán přímo v lahvi – stačí vychladit a podávat. Obsah alkoholu cca 7%. Značka Ciao Spritz také nabízí nealko varianty (0% Spritz, Lemon Spritz, Hugo Spritz, Negroni), Love Ciao Spritz, Lemon Farm Spritz, Negroni Farm Spritz, a merch (klobouk, brýle, kelímky z tritanu, skleničky).`;

async function generateDesc(id, name, category) {
    const btn = document.getElementById('btn-' + id);
    const spin = document.getElementById('spin-' + id);
    btn.disabled = true;
    spin.style.display = 'inline-block';
    btn.querySelector('span:last-child') && (btn.lastChild.textContent = ' Generuji...');

    const prompt = `Napiš produktové popisy pro e-shop Ciao Spritz.

Produkt: ${name}
Kategorie: ${category}

Kontext značky: ${brandContext}

Napiš ve formátu JSON (bez markdown backticks, jen čistý JSON):
{
  "short_cs": "krátký popis CZ max 120 znaků pro přehled produktů",
  "short_en": "short EN description max 120 chars",
  "desc_cs": "delší popis CZ 2-3 věty pro detail stránky, lákavý, s důrazem na chuť a příležitost",
  "desc_en": "longer EN description 2-3 sentences"
}

Piš přirozeně, lákavě, bez klišé. Zdůrazni italský původ, chuť, příležitosti použití.`;

    try {
        const response = await fetch('https://api.anthropic.com/v1/messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model: 'claude-sonnet-4-20250514',
                max_tokens: 1000,
                messages: [{ role: 'user', content: prompt }]
            })
        });

        const data = await response.json();
        const text = data.content?.[0]?.text ?? '';

        let parsed;
        try {
            parsed = JSON.parse(text.replace(/```json|```/g, '').trim());
        } catch(e) {
            const match = text.match(/\{[\s\S]*\}/);
            parsed = match ? JSON.parse(match[0]) : null;
        }

        if (parsed) {
            document.getElementById('short_cs_' + id).value = parsed.short_cs || '';
            document.getElementById('short_en_' + id).value = parsed.short_en || '';
            document.getElementById('desc_cs_' + id).value = parsed.desc_cs || '';
            document.getElementById('desc_en_' + id).value = parsed.desc_en || '';
            // Automaticky ulož
            document.getElementById('form-' + id).submit();
        } else {
            alert('Chyba parsování odpovědi: ' + text.substring(0, 200));
        }
    } catch(err) {
        alert('Chyba API: ' + err.message);
    } finally {
        btn.disabled = false;
        spin.style.display = 'none';
    }
}

async function generateAll() {
    const rows = document.querySelectorAll('[id^="row-"]');
    for (const row of rows) {
        const id = row.id.replace('row-', '');
        const btn = document.getElementById('btn-' + id);
        if (btn) btn.click();
        await new Promise(r => setTimeout(r, 3000)); // 3s pauza mezi požadavky
    }
}
</script>
</body>
</html>
