<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

// Načti rezervace
$reservations = $pdo->query("SELECT * FROM reservations ORDER BY created_at DESC")->fetchAll();

$statusColors = [
    'ceka'      => '#E8631A',
    'schvaleno' => '#2D7A3A',
    'zamitnuto' => '#dc3545',
];
$statusLabels = [
    'ceka'      => 'Čeká na schválení',
    'schvaleno' => 'Schváleno',
    'zamitnuto' => 'Zamítnuto',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Rezervace stánku - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; color: #111; }
        .topbar { background: #111; color: white; padding: 14px 32px; display: flex; align-items: center; gap: 16px; }
        .topbar a { color: rgba(255,255,255,0.6); font-size: 14px; text-decoration: none; }
        .topbar h1 { font-size: 1rem; font-weight: 600; color: white; }
        .content { max-width: 1200px; margin: 32px auto; padding: 0 24px; }
        .card { background: white; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1.1rem; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #888; border-bottom: 1px solid #e0e0e0; background: #fafafa; }
        td { padding: 14px 24px; border-bottom: 1px solid #f0f0f0; font-size: 14px; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #2D7A3A; color: white; }
        .btn-outline { background: none; border: 2px solid #e0e0e0; color: #444; text-decoration: none; }
        .alert { padding: 12px 20px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; background: rgba(45,122,58,0.1); color: #2D7A3A; border: 1px solid rgba(45,122,58,0.2); }
        select, textarea, input { padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-family: inherit; font-size: 13px; outline: none; }
        select:focus, textarea:focus { border-color: #E8631A; }
        .detail-row { display: flex; flex-direction: column; gap: 4px; }
        .detail-row small { color: #888; font-size: 12px; }
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,0.3)">›</span>
    <h1>Rezervace stánku</h1>
</div>

<div class="content">
    <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>📅 Všechny rezervace (<?= count($reservations) ?>)</h2>
            <a href="<?= BASE_URL ?>/stanek.php" target="_blank" class="btn btn-outline">🌐 Zobrazit stránku →</a>
        </div>

        <?php if (empty($reservations)): ?>
            <div style="text-align:center;padding:48px;color:#888">
                <div style="font-size:3rem;margin-bottom:12px">📅</div>
                <p>Zatím žádné rezervace.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Zákazník</th>
                    <th>Termín</th>
                    <th>Akce / Místo</th>
                    <th>Stav</th>
                    <th>Zpráva</th>
                    <th>Změnit stav</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                <tr>
                    <td>
                        <div class="detail-row">
                            <strong><?= htmlspecialchars($r['name']) ?></strong>
                            <small><a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="color:#E8631A"><?= htmlspecialchars($r['email']) ?></a></small>
                            <small><?= htmlspecialchars($r['phone']) ?></small>
                            <small style="color:#aaa"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></small>
                        </div>
                    </td>
                    <td>
                        <div class="detail-row">
                            <strong><?= htmlspecialchars($r['date_from']) ?></strong>
                            <small>až</small>
                            <strong><?= htmlspecialchars($r['date_to']) ?></strong>
                        </div>
                    </td>
                    <td>
                        <div class="detail-row">
                            <span><?= htmlspecialchars($r['event_type'] ?? '—') ?></span>
                            <small><?= htmlspecialchars($r['location'] ?? '—') ?></small>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background:<?= isset($statusColors[$r['status']]) ? $statusColors[$r['status']] . '20' : '#f5f5f5' ?>;color:<?= $statusColors[$r['status']] ?? '#888' ?>">
                            <?= $statusLabels[$r['status']] ?? $r['status'] ?>
                        </span>
                        <?php if ($r['admin_note']): ?>
                        <div style="font-size:12px;color:#888;margin-top:6px"><?= htmlspecialchars($r['admin_note']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size:13px;color:#555;max-width:200px"><?= nl2br(htmlspecialchars($r['message'] ?? '')) ?></div>
                    </td>
                    <td>
                        <form method="POST" style="display:flex;flex-direction:column;gap:8px;min-width:180px">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <select name="status">
                                <?php foreach ($statusLabels as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $r['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <textarea name="admin_note" rows="2" placeholder="Poznámka zákazníkovi..." style="font-size:12px"><?= htmlspecialchars($r['admin_note'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary" style="justify-content:center">Uložit + poslat email</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
