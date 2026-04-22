<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_customer' && canAccess('staff')) {
        $name = trim($_POST['new_name'] ?? '');
        $email = trim($_POST['new_email'] ?? '');
        $phone = trim($_POST['new_phone'] ?? '');
        $address = trim($_POST['new_address'] ?? '');
        $city = trim($_POST['new_city'] ?? '');
        $zip = trim($_POST['new_zip'] ?? '');
        $password = $_POST['new_password'] ?? 'CiaoSpritz2024';
        if ($name && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $message = '❌ Email již existuje.';
            } else {
                $pdo->prepare("INSERT INTO users (name,email,password,phone,address,city,zip) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$phone,$address,$city,$zip]);
                $message = '✅ Zákazník přidán. Heslo: '.$password;
                logAction($pdo, 'Přidán zákazník', $email);
            }
        } else {
            $message = '❌ Vyplňte jméno a platný email.';
        }
        header('Location: '.BASE_URL.'/admin/customers.php?msg='.urlencode($message)); exit;
    }
    if ($action === 'send_message' && canAccess('orders')) {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $msg = trim($_POST['message'] ?? '');
        if ($orderId && $msg) {
            $pdo->prepare("INSERT INTO order_messages (order_id, sender, message) VALUES (?, 'admin', ?)")->execute([$orderId, $msg]);
            $order = $pdo->prepare("SELECT * FROM orders WHERE id=?");
            $order->execute([$orderId]);
            $order = $order->fetch();
            if ($order) {
                mail($order['customer_email'], "Zpráva k objednávce #{$order['order_number']} — Ciao Spritz",
                    "Dobrý den {$order['customer_name']},\n\nZpráva k objednávce #{$order['order_number']}:\n\n{$msg}\n\nCiao Spritz tým\nrcaffe@email.cz",
                    "From: Ciao Spritz <rcaffe@email.cz>\r\nContent-Type: text/plain; charset=UTF-8");
            }
            $message = '✅ Zpráva odeslána.';
            logAction($pdo, 'Zpráva zákazníkovi', "Objednávka ID: $orderId");
        }
    }
    if ($action === 'add_points' && canAccess('staff')) {
        $userId = (int)($_POST['user_id'] ?? 0);
        $points = (int)($_POST['points'] ?? 0);
        $type = in_array($_POST['type'],['bonus','earned']) ? $_POST['type'] : 'bonus';
        $desc = trim($_POST['description'] ?? 'Admin bonus');
        if ($userId && $points > 0) {
            $pdo->prepare("INSERT INTO loyalty_points (user_id,points,type,description) VALUES (?,?,?,?)")->execute([$userId,$points,$type,$desc]);
            $message = "✅ Přidáno $points bodů.";
        }
    }
    if ($action === 'save_note') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        // Přidej sloupec note pokud neexistuje
        try { $pdo->exec("ALTER TABLE users ADD COLUMN note text DEFAULT NULL"); } catch(Exception $e) {}
        $pdo->prepare("UPDATE users SET note=? WHERE id=?")->execute([$note, $userId]);
        $message = '✅ Poznámka uložena.';
    }
    header('Location: '.BASE_URL.'/admin/customers.php?msg='.urlencode($message).(isset($_POST['user_id'])&&$_POST['action']!=='send_message'?'&detail='.(int)$_POST['user_id']:''));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

// Přidej sloupec note pokud neexistuje
try { $pdo->exec("ALTER TABLE users ADD COLUMN note text DEFAULT NULL"); } catch(Exception $e) {}

$detailUser = null; $detailOrders = []; $detailPoints = [];
if (isset($_GET['detail'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([(int)$_GET['detail']]);
    $detailUser = $stmt->fetch();
    if ($detailUser) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_email=? ORDER BY created_at DESC");
        $stmt->execute([$detailUser['email']]); $detailOrders = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT * FROM loyalty_points WHERE user_id=? ORDER BY created_at DESC LIMIT 15");
        $stmt->execute([$detailUser['id']]); $detailPoints = $stmt->fetchAll();
    }
}

$customers = $pdo->query("
    SELECT u.*,
        COALESCE(SUM(CASE WHEN lp.type IN ('earned','bonus') THEN lp.points ELSE -lp.points END),0) as points_balance,
        (SELECT COUNT(*) FROM orders WHERE customer_email=u.email) as order_count,
        (SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_email=u.email AND status!='zrusena') as total_spent
    FROM users u
    LEFT JOIN loyalty_points lp ON lp.user_id=u.id AND (lp.expires_at IS NULL OR lp.expires_at>NOW())
    GROUP BY u.id ORDER BY total_spent DESC
")->fetchAll();

$orders = $pdo->query("SELECT id,order_number,customer_name,customer_email FROM orders ORDER BY created_at DESC LIMIT 100")->fetchAll();
$recentMessages = $pdo->query("SELECT om.*,o.order_number,o.customer_name FROM order_messages om JOIN orders o ON o.id=om.order_id ORDER BY om.created_at DESC LIMIT 20")->fetchAll();

function levelLabel($s){ if($s>=10000)return '💎 Platinum'; if($s>=5000)return '🥇 Gold'; if($s>=2000)return '🥈 Silver'; return '🥉 Bronze'; }
?>
<!DOCTYPE html><html lang="cs"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Zákazníci — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'DM Sans',sans-serif;background:#f0f0f0;color:#111;font-size:14px}
.topbar{background:#111;color:white;padding:14px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
.topbar a{color:rgba(255,255,255,.6);font-size:13px;text-decoration:none}.topbar h1{font-size:.95rem;font-weight:600;color:white;margin-left:auto}
.layout{display:grid;grid-template-columns:1fr 360px;gap:24px;max-width:1300px;margin:28px auto;padding:0 24px}
.card{background:white;border-radius:12px;border:1px solid #e0e0e0;overflow:hidden;margin-bottom:20px}
.card-header{padding:14px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#fafafa}
.card-header h2{font-size:.95rem;font-weight:700}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;font-family:inherit}
.btn-primary{background:#2D7A3A;color:white}.btn-orange{background:#E8631A;color:white}.btn-outline{background:none;border:2px solid #e0e0e0;color:#444}.btn-sm{padding:4px 10px;font-size:12px}
label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px}
input,textarea,select{width:100%;padding:8px 12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:13px;outline:none}
input:focus,textarea:focus,select:focus{border-color:#E8631A}
.fg{margin-bottom:12px}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:9px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;border-bottom:1px solid #e0e0e0;background:#fafafa}
td{padding:11px 16px;border-bottom:1px solid #f5f5f5;font-size:13px;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr.click:hover{background:#fafafa;cursor:pointer}
tr.sel{background:rgba(232,99,26,.05)}
.badge{display:inline-block;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:700}
.bg{background:rgba(45,122,58,.1);color:#2D7A3A}
.alert{padding:12px 20px;border-radius:8px;font-size:14px;margin-bottom:20px;background:rgba(45,122,58,.1);color:#2D7A3A;border:1px solid rgba(45,122,58,.2)}
.mb{background:#f5f5f5;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:8px}
.mb.adm{background:rgba(232,99,26,.08);border-left:3px solid #E8631A}
.sm{text-align:center;padding:12px;background:#f9f9f9;border-radius:8px}
.sm .n{font-size:1.3rem;font-weight:900}.sm .l{font-size:11px;color:#888;margin-top:2px}
.dh{background:linear-gradient(135deg,#E8631A,#c4521a);color:white;padding:20px 24px}
.av{width:48px;height:48px;background:rgba(255,255,255,.25);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;flex-shrink:0}
</style></head><body>
<div class="topbar"><a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a><span style="color:rgba(255,255,255,.3)">›</span><h1>👥 Zákazníci & zprávy</h1></div>
<div class="layout">
<div>
<?php if($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<?php if($detailUser): ?>
<div class="card">
    <div class="dh">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
            <div class="av"><?= strtoupper(substr($detailUser['name'],0,1)) ?></div>
            <div style="flex:1">
                <div style="font-size:1.1rem;font-weight:700"><?= htmlspecialchars($detailUser['name']) ?></div>
                <div style="opacity:.8;font-size:13px"><?= htmlspecialchars($detailUser['email']) ?></div>
                <?php if($detailUser['phone']): ?><div style="opacity:.7;font-size:12px">📞 <?= htmlspecialchars($detailUser['phone']) ?></div><?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:white">✕</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
            <div class="sm"><div class="n"><?= count($detailOrders) ?></div><div class="l">Objednávky</div></div>
            <div class="sm"><div class="n"><?= number_format(array_sum(array_column($detailOrders,'total')),0,',',' ') ?></div><div class="l">Útrata Kč</div></div>
            <div class="sm"><div class="n"><?= levelLabel(array_sum(array_column($detailOrders,'total'))) ?></div><div class="l">Úroveň</div></div>
        </div>
    </div>
    <div style="padding:16px 20px">
        <div style="font-weight:700;margin-bottom:10px;font-size:13px">📦 Objednávky</div>
        <?php if(empty($detailOrders)): ?><p style="color:#888;font-size:13px">Žádné objednávky.</p>
        <?php else: foreach($detailOrders as $o): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f5f5f5;font-size:13px">
            <div><div style="font-weight:600"><?= htmlspecialchars($o['order_number']) ?></div><div style="font-size:11px;color:#888"><?= date('d.m.Y',strtotime($o['created_at'])) ?></div></div>
            <div style="text-align:right"><div style="font-weight:700"><?= number_format($o['total'],0,',',' ') ?> Kč</div><div style="font-size:11px;color:#888"><?= $o['status'] ?></div></div>
            <a href="<?= BASE_URL ?>/admin/faktura.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-sm btn-outline" style="margin-left:8px">🧾</a>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div style="padding:0 20px 16px">
        <form method="POST">
            <input type="hidden" name="action" value="save_note">
            <input type="hidden" name="user_id" value="<?= $detailUser['id'] ?>">
            <div class="fg"><label>📝 Interní poznámka</label>
            <textarea name="note" rows="3" placeholder="Poznámky k zákazníkovi..."><?= htmlspecialchars($detailUser['note']??'') ?></textarea></div>
            <button type="submit" class="btn btn-primary btn-sm">💾 Uložit poznámku</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2>👥 Zákazníci (<?= count($customers) ?>)</h2><span style="font-size:12px;color:#888">Klikni pro detail</span></div>
    <?php if(empty($customers)): ?>
    <div style="padding:32px;text-align:center;color:#888">Žádní zákazníci.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Zákazník</th><th>Obj.</th><th>Útrata</th><th>Body</th><th>Úroveň</th><th>Od</th></tr></thead>
        <tbody>
        <?php foreach($customers as $c): ?>
        <tr class="click <?= isset($_GET['detail'])&&(int)$_GET['detail']===$c['id']?'sel':'' ?>" onclick="location.href='<?= BASE_URL ?>/admin/customers.php?detail=<?= $c['id'] ?>'">
            <td><div style="font-weight:600"><?= htmlspecialchars($c['name']) ?></div><div style="font-size:11px;color:#888"><?= htmlspecialchars($c['email']) ?></div><?php if(!empty($c['note'])): ?><div style="font-size:11px;color:#E8631A">📝</div><?php endif; ?></td>
            <td><?= $c['order_count'] ?>×</td>
            <td style="font-weight:600"><?= number_format($c['total_spent'],0,',',' ') ?> Kč</td>
            <td><span class="badge bg">⭐ <?= $c['points_balance'] ?></span></td>
            <td style="font-size:12px"><?= levelLabel($c['total_spent']) ?></td>
            <td style="font-size:12px;color:#888"><?= date('d.m.Y',strtotime($c['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h2>💬 Nedávné zprávy</h2></div>
    <?php if(empty($recentMessages)): ?><div style="padding:32px;text-align:center;color:#888">Žádné zprávy.</div>
    <?php else: ?><div style="padding:16px 20px">
    <?php foreach($recentMessages as $msg): ?>
    <div class="mb <?= $msg['sender']==='admin'?'adm':'' ?>">
        <div style="font-size:11px;color:#888;margin-bottom:4px"><strong><?= $msg['sender']==='admin'?'👨‍💼 Admin':'👤 '.htmlspecialchars($msg['customer_name']) ?></strong> · <?= htmlspecialchars($msg['order_number']) ?> · <?= date('d.m.Y H:i',strtotime($msg['created_at'])) ?></div>
        <?= nl2br(htmlspecialchars($msg['message'])) ?>
    </div>
    <?php endforeach; ?></div><?php endif; ?>
</div>
</div>

<div>
<div class="card">
    <div class="card-header"><h2>💬 Odeslat zprávu</h2></div>
    <div style="padding:20px">
        <form method="POST">
            <input type="hidden" name="action" value="send_message">
            <div class="fg"><label>Objednávka</label><select name="order_id" required><option value="">Vyberte...</option><?php foreach($orders as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['order_number']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option><?php endforeach; ?></select></div>
            <div class="fg"><label>Zpráva</label><textarea name="message" rows="5" required placeholder="Zpráva zákazníkovi..."></textarea></div>
            <button type="submit" class="btn btn-orange" style="width:100%">📨 Odeslat + email zákazníkovi</button>
        </form>
    </div>
</div>

<?php if(canAccess('staff')): ?>
<div class="card">
    <div class="card-header"><h2>➕ Přidat zákazníka</h2></div>
    <div style="padding:20px">
        <form method="POST">
            <input type="hidden" name="action" value="add_customer">
            <div class="fg"><label>Jméno *</label><input type="text" name="new_name" required placeholder="Jan Novák"></div>
            <div class="fg"><label>Email *</label><input type="email" name="new_email" required placeholder="jan@example.com"></div>
            <div class="fg"><label>Telefon</label><input type="tel" name="new_phone" placeholder="777 123 456"></div>
            <div class="fg"><label>Adresa</label><input type="text" name="new_address" placeholder="Ulice 1"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
                <div><label>Město</label><input type="text" name="new_city" placeholder="Praha"></div>
                <div><label>PSČ</label><input type="text" name="new_zip" placeholder="110 00"></div>
            </div>
            <div class="fg"><label>Heslo (výchozí: CiaoSpritz2024)</label><input type="text" name="new_password" placeholder="CiaoSpritz2024"></div>
            <button type="submit" class="btn btn-primary" style="width:100%">➕ Přidat zákazníka</button>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>⭐ Přidat body</h2></div>
    <div style="padding:20px">
        <form method="POST">
            <input type="hidden" name="action" value="add_points">
            <div class="fg"><label>Zákazník</label><select name="user_id" required><option value="">Vyberte...</option><?php foreach($customers as $c): ?><option value="<?= $c['id'] ?>" <?= isset($_GET['detail'])&&(int)$_GET['detail']===$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?> (<?= $c['points_balance'] ?> b.)</option><?php endforeach; ?></select></div>
            <div class="fg"><label>Počet bodů</label><input type="number" name="points" min="1" placeholder="50"></div>
            <div class="fg"><label>Typ</label><select name="type"><option value="bonus">Bonus</option><option value="earned">Za nákup</option></select></div>
            <div class="fg"><label>Popis</label><input type="text" name="description" placeholder="Bonus za věrnost..."></div>
            <button type="submit" class="btn btn-primary" style="width:100%">⭐ Přidat body</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2>📊 Statistiky</h2></div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <?php $tr=array_sum(array_column($customers,'total_spent')); $tc=count($customers); $oc=max(1,array_sum(array_column($customers,'order_count'))); ?>
        <div class="sm"><div class="n"><?= $tc ?></div><div class="l">Zákazníků</div></div>
        <div class="sm"><div class="n"><?= number_format($tr,0,',',' ') ?></div><div class="l">Tržby (Kč)</div></div>
        <div class="sm"><div class="n"><?= number_format($tr/max(1,$oc),0,',',' ') ?></div><div class="l">Průměr obj.</div></div>
        <div class="sm"><div class="n">💎 <?= count(array_filter($customers,fn($c)=>$c['total_spent']>=10000)) ?></div><div class="l">Platinum</div></div>
    </div>
</div>
</div>
</div>
</body></html>
