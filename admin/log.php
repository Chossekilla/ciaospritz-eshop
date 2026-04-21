<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';

requireLogin();
requireRole('admin');

$logs = $pdo->query("
    SELECT sl.*, s.name as staff_name, s.email as staff_email, s.role
    FROM staff_log sl
    JOIN staff s ON s.id = sl.staff_id
    ORDER BY sl.created_at DESC
    LIMIT 200
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přístupový log - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; color: #111; }
        .topbar { background: #111; color: white; padding: 14px 32px; display: flex; align-items: center; gap: 16px; }
        .topbar a { color: rgba(255,255,255,0.6); font-size: 14px; text-decoration: none; }
        .topbar h1 { font-size: 1rem; font-weight: 600; color: white; }
        .content { max-width: 1000px; margin: 32px auto; padding: 0 24px; }
        .card { background: white; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #e0e0e0; }
        .card-header h2 { font-size: 1rem; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #888; border-bottom: 1px solid #e0e0e0; background: #fafafa; }
        td { padding: 11px 20px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 50px; font-size: 10px; font-weight: 700; }
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,0.3)">›</span>
    <h1>📋 Přístupový log (posledních 200 záznamů)</h1>
</div>
<div class="content">
    <div class="card">
        <div class="card-header"><h2>📋 Log aktivit zaměstnanců</h2></div>
        <table>
            <thead><tr><th>Čas</th><th>Zaměstnanec</th><th>Role</th><th>Akce</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="color:#888;font-size:12px;white-space:nowrap"><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($log['staff_name']) ?></div>
                    <div style="font-size:11px;color:#888"><?= htmlspecialchars($log['staff_email']) ?></div>
                </td>
                <td><?= getRoleBadge($log['role']) ?></td>
                <td style="font-weight:500"><?= htmlspecialchars($log['action']) ?></td>
                <td style="color:#888;font-size:12px"><?= htmlspecialchars($log['detail']) ?></td>
                <td style="color:#aaa;font-size:11px"><?= htmlspecialchars($log['ip']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="text-align:center;padding:32px;color:#888">Zatím žádné záznamy.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
