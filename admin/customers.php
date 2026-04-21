<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

// Načti zákazníky s body
$customers = $pdo->query("
    SELECT u.*,
        COALESCE(SUM(CASE WHEN lp.type IN ('earned','bonus') THEN lp.points ELSE -lp.points END), 0) as points_balance,
        (SELECT COUNT(*) FROM orders WHERE customer_email = u.email) as order_count,
        (SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_email = u.email AND status != 'zrusena') as total_spent
    FROM users u
    LEFT JOIN loyalty_points lp ON lp.user_id = u.id AND (lp.expires_at IS NULL OR lp.expires_at > NOW())
    WHERE u.active = 1
    GROUP BY u.id
    ORDER BY total_spent DESC
")->fetchAll();

// Načti objednávky pro zprávy
$orders = $pdo->query("SELECT id, order_number, customer_name, customer_email FROM orders ORDER BY created_at DESC LIMIT 100")->fetchAll();

// Nedávné zprávy
$recentMessages = $pdo->query("
    SELECT om.*, o.order_number, o.customer_name
    FROM order_messages om
    JOIN orders o ON o.id = om.order_id
    ORDER BY om.created_at DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Zákazníci & Zprávy - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; color: #111; }
        .topbar { background: #111; color: white; padding: 14px 32px; display: flex; align-items: center; gap: 16px; }
        .topbar a { color: rgba(255,255,255,0.6); font-size: 14px; text-decoration: none; }
        .topbar h1 { font-size: 1rem; font-weight: 600; color: white; }
        .content { max-width: 1200px; margin: 32px auto; padding: 0 24px; display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start; }
        .card { background: white; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1rem; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #888; border-bottom: 1px solid #e0e0e0; background: #fafafa; }
        td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #555; }
        input, textarea, select { width: 100%; padding: 9px 12px; border: 2px solid #e0e0e0; border-radius: 7px; font-family: inherit; font-size: 13px; outline: none; }
        input:focus, textarea:focus, select:focus { border-color: #E8631A; }
        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 8px 14px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; width: 100%; justify-content: center; }
        .btn-primary { background: #2D7A3A; color: white; }
        .btn-orange { background: #E8631A; color: white; }
        .alert { padding: 10px 16px; border-radius: 7px; font-size: 13px; margin-bottom: 16px; background: rgba(45,122,58,0.1); color: #2D7A3A; border: 1px solid rgba(45,122,58,0.2); }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .points-badge { background: rgba(45,122,58,0.1); color: #2D7A3A; }
        .msg-bubble { background: #f5f5f5; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin-bottom: 8px; }
        .msg-bubble.admin { background: rgba(232,99,26,0.08); border-left: 3px solid #E8631A; }
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,0.3)">›</span>
    <h1>Zákazníci, body & zprávy</h1>
</div>

<div class="content">
    <div>
        <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <!-- ZÁKAZNÍCI -->
        <div class="card">
            <div class="card-header"><h2>👥 Zákazníci (<?= count($customers) ?>)</h2></div>
            <?php if (empty($customers)): ?>
                <div style="padding:32px;text-align:center;color:#888">Zatím žádní registrovaní zákazníci.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Zákazník</th><th>Objednávky</th><th>Útrata</th><th>Body</th><th>Úroveň</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c):
                    $level = $c['total_spent'] >= 10000 ? '💎 Platinum' : ($c['total_spent'] >= 5000 ? '🥇 Gold' : ($c['total_spent'] >= 2000 ? '🥈 Silver' : '🥉 Bronze'));
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($c['name']) ?></div>
                        <div style="font-size:11px;color:#888"><?= htmlspecialchars($c['email']) ?></div>
                    </td>
                    <td><?= $c['order_count'] ?>×</td>
                    <td><?= number_format($c['total_spent'],0,',',' ') ?> Kč</td>
                    <td><span class="badge points-badge">⭐ <?= $c['points_balance'] ?></span></td>
                    <td style="font-size:12px"><?= $level ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ZPRÁVY -->
        <div class="card">
            <div class="card-header"><h2>💬 Nedávné zprávy</h2></div>
            <?php if (empty($recentMessages)): ?>
                <div style="padding:32px;text-align:center;color:#888">Žádné zprávy.</div>
            <?php else: ?>
            <div style="padding:16px 24px">
                <?php foreach ($recentMessages as $msg): ?>
                <div class="msg-bubble <?= $msg['sender'] === 'admin' ? 'admin' : '' ?>">
                    <div style="font-size:11px;color:#888;margin-bottom:4px">
                        <strong><?= $msg['sender'] === 'admin' ? 'Admin' : htmlspecialchars($msg['customer_name']) ?></strong>
                        · <?= htmlspecialchars($msg['order_number']) ?>
                        · <?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?>
                    </div>
                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PRAVÝ PANEL -->
    <div>
        <!-- Odeslat zprávu -->
        <div class="card">
            <div class="card-header"><h2>💬 Odeslat zprávu</h2></div>
            <div style="padding:20px">
                <form method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <div class="form-group">
                        <label>Objednávka</label>
                        <select name="order_id" required>
                            <option value="">Vyberte objednávku...</option>
                            <?php foreach ($orders as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['order_number']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Zpráva</label>
                        <textarea name="message" rows="4" required placeholder="Vaše zpráva zákazníkovi..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-orange">📨 Odeslat + email zákazníkovi</button>
                </form>
            </div>
        </div>

        <!-- Přidat body -->
        <div class="card">
            <div class="card-header"><h2>⭐ Přidat body zákazníkovi</h2></div>
            <div style="padding:20px">
                <form method="POST">
                    <input type="hidden" name="action" value="add_points">
                    <div class="form-group">
                        <label>Zákazník</label>
                        <select name="user_id" required>
                            <option value="">Vyberte zákazníka...</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['points_balance'] ?> bodů)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Počet bodů</label>
                        <input type="number" name="points" min="1" required placeholder="např. 50">
                    </div>
                    <div class="form-group">
                        <label>Typ</label>
                        <select name="type">
                            <option value="bonus">Bonus</option>
                            <option value="earned">Za nákup</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Popis</label>
                        <input type="text" name="description" placeholder="Bonus za věrnost...">
                    </div>
                    <button type="submit" class="btn btn-primary">⭐ Přidat body</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
