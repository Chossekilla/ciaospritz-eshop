<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';

requireLogin();
requireRole('admin'); // Pouze admin!

$message = '';
$editStaff = null;

// Načti zaměstnance pro editaci
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editStaff = $stmt->fetch();
}

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'prodavac';
        $phone    = trim($_POST['phone'] ?? '');
        $note     = trim($_POST['note'] ?? '');
        $password = $_POST['password'] ?? '';
        $active   = isset($_POST['active']) ? 1 : 0;

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '⚠️ Vyplňte jméno a platný email.';
        } else {
            if ($id) {
                // Editace
                if ($password) {
                    $pdo->prepare("UPDATE staff SET name=?,email=?,role=?,phone=?,note=?,active=?,password=? WHERE id=?")
                        ->execute([$name,$email,$role,$phone,$note,$active,password_hash($password,PASSWORD_DEFAULT),$id]);
                } else {
                    $pdo->prepare("UPDATE staff SET name=?,email=?,role=?,phone=?,note=?,active=? WHERE id=?")
                        ->execute([$name,$email,$role,$phone,$note,$active,$id]);
                }
                logAction($pdo, 'Upraven zaměstnanec', "ID: $id, Role: $role");
                $message = '✅ Zaměstnanec upraven.';
            } else {
                // Nový
                if (!$password || strlen($password) < 6) {
                    $message = '⚠️ Heslo musí mít alespoň 6 znaků.';
                } else {
                    try {
                        $pdo->prepare("INSERT INTO staff (name,email,password,role,phone,note,active) VALUES (?,?,?,?,?,?,?)")
                            ->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$phone,$note,$active]);
                        logAction($pdo, 'Přidán zaměstnanec', "Email: $email, Role: $role");
                        $message = '✅ Zaměstnanec přidán.';
                    } catch (Exception $e) {
                        $message = '⚠️ Email již existuje.';
                    }
                }
            }
        }
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $deleteId = (int)$_POST['id'];
        if ($deleteId !== $_SESSION['staff_id']) { // Nelze smazat sám sebe
            $pdo->prepare("UPDATE staff SET active=0 WHERE id=?")->execute([$deleteId]);
            logAction($pdo, 'Deaktivován zaměstnanec', "ID: $deleteId");
            $message = '✅ Zaměstnanec deaktivován.';
        } else {
            $message = '⚠️ Nemůžete deaktivovat vlastní účet.';
        }
    }

    if ($action === 'activate' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE staff SET active=1 WHERE id=?")->execute([(int)$_POST['id']]);
        $message = '✅ Zaměstnanec aktivován.';
    }

    header('Location: '.BASE_URL.'/admin/staff.php?msg=' . urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);

$staffList = $pdo->query("SELECT * FROM staff ORDER BY active DESC, role ASC, name ASC")->fetchAll();

$roleLabels = ['admin' => '👑 Admin', 'manager' => '📊 Manager', 'prodavac' => '🛒 Prodavač'];
$rolePermissions = [
    'admin'    => ['Vše včetně správy zaměstnanců a nastavení'],
    'manager'  => ['Objednávky', 'Produkty', 'Články', 'Galerie', 'Rezervace', 'Zákazníci'],
    'prodavac' => ['Objednávky', 'Produkty'],
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaměstnanci - Admin Ciao Spritz</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; color: #111; }
        .topbar { background: #111; color: white; padding: 14px 32px; display: flex; align-items: center; gap: 16px; }
        .topbar a { color: rgba(255,255,255,0.6); font-size: 14px; text-decoration: none; }
        .topbar h1 { font-size: 1rem; font-weight: 600; color: white; }
        .content { max-width: 1100px; margin: 32px auto; padding: 0 24px; display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
        .card { background: white; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1rem; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #888; border-bottom: 1px solid #e0e0e0; background: #fafafa; }
        td { padding: 13px 20px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #555; }
        input, select, textarea { width: 100%; padding: 9px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: inherit; font-size: 13px; outline: none; transition: border-color 0.2s; }
        input:focus, select:focus, textarea:focus { border-color: #E8631A; }
        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
        .btn-primary { background: #2D7A3A; color: white; width: 100%; justify-content: center; padding: 11px; }
        .btn-orange { background: #E8631A; color: white; }
        .btn-outline { background: none; border: 1.5px solid #e0e0e0; color: #555; }
        .btn-outline:hover { border-color: #E8631A; color: #E8631A; }
        .btn-danger { background: rgba(220,53,69,0.1); color: #dc3545; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .alert { padding: 10px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: rgba(45,122,58,0.1); color: #2D7A3A; border: 1px solid rgba(45,122,58,0.2); }
        .alert-error { background: rgba(220,53,69,0.1); color: #dc3545; border: 1px solid rgba(220,53,69,0.2); }
        .toggle-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .toggle-label input { width: auto; }
        .permissions-list { font-size: 11px; color: #888; margin-top: 3px; }
        .inactive-row td { opacity: 0.5; }
    </style>
</head>
<body>
<div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">← Dashboard</a>
    <span style="color:rgba(255,255,255,0.3)">›</span>
    <h1>👨‍💼 Správa zaměstnanců</h1>
</div>

<div class="content">

    <!-- SEZNAM -->
    <div>
        <?php if ($message): ?>
        <div class="alert <?= str_starts_with($message,'✅') ? 'alert-success' : 'alert-error' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- ROLE přehled -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><h2>🔐 Přehled oprávnění rolí</h2></div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0">
                <?php foreach ($rolePermissions as $r => $perms): ?>
                <div style="padding:16px 20px;border-right:1px solid #e0e0e0">
                    <div style="font-weight:700;margin-bottom:8px"><?= $roleLabels[$r] ?></div>
                    <?php foreach ($perms as $p): ?>
                    <div style="font-size:12px;color:#555;padding:2px 0">✓ <?= $p ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SEZNAM ZAMĚSTNANCŮ -->
        <div class="card">
            <div class="card-header"><h2>👥 Zaměstnanci (<?= count($staffList) ?>)</h2></div>
            <table>
                <thead>
                    <tr><th>Zaměstnanec</th><th>Role</th><th>Poslední přihlášení</th><th>Stav</th><th>Akce</th></tr>
                </thead>
                <tbody>
                <?php foreach ($staffList as $s): ?>
                <tr class="<?= !$s['active'] ? 'inactive-row' : '' ?>">
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="font-size:11px;color:#888"><?= htmlspecialchars($s['email']) ?></div>
                        <?php if ($s['phone']): ?><div style="font-size:11px;color:#888">📞 <?= htmlspecialchars($s['phone']) ?></div><?php endif; ?>
                    </td>
                    <td><?= getRoleBadge($s['role']) ?></td>
                    <td style="font-size:12px;color:#888">
                        <?= $s['last_login'] ? date('d.m.Y H:i', strtotime($s['last_login'])) : 'Nikdy' ?>
                    </td>
                    <td>
                        <?php if ($s['active']): ?>
                            <span class="badge" style="background:rgba(45,122,58,0.1);color:#2D7A3A">✅ Aktivní</span>
                        <?php else: ?>
                            <span class="badge" style="background:rgba(220,53,69,0.1);color:#dc3545">❌ Neaktivní</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/staff.php?edit=<?= $s['id'] ?>" class="btn btn-outline">✏️ Upravit</a>
                        <?php if ($s['id'] !== (int)$_SESSION['staff_id']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Opravdu?')">
                            <input type="hidden" name="action" value="<?= $s['active'] ? 'delete' : 'activate' ?>">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn <?= $s['active'] ? 'btn-danger' : 'btn-orange' ?>">
                                <?= $s['active'] ? '🔒 Deaktivovat' : '✅ Aktivovat' ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:11px;color:#888">(váš účet)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FORMULÁŘ -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2><?= $editStaff ? '✏️ Upravit zaměstnance' : '➕ Nový zaměstnanec' ?></h2>
                <?php if ($editStaff): ?>
                <a href="<?= BASE_URL ?>/admin/staff.php" class="btn btn-outline">+ Nový</a>
                <?php endif; ?>
            </div>
            <div style="padding:24px">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $editStaff['id'] ?? 0 ?>">

                    <div class="form-group">
                        <label>Jméno a příjmení *</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($editStaff['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($editStaff['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Heslo <?= $editStaff ? '(prázdné = beze změny)' : '*' ?></label>
                        <input type="password" name="password" <?= $editStaff ? '' : 'required' ?> placeholder="min. 6 znaků" minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role">
                            <?php foreach ($roleLabels as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($editStaff['role'] ?? 'prodavac') === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="role-desc" style="font-size:11px;color:#888;margin-top:4px"></div>
                    </div>
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($editStaff['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Poznámka (interní)</label>
                        <textarea name="note" rows="2"><?= htmlspecialchars($editStaff['note'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="active" <?= ($editStaff['active'] ?? 1) ? 'checked' : '' ?>>
                            Účet je aktivní
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $editStaff ? '💾 Uložit změny' : '➕ Přidat zaměstnance' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- INFO box -->
        <div style="background:rgba(232,99,26,0.05);border:1px solid rgba(232,99,26,0.15);border-radius:10px;padding:16px;font-size:13px;color:#555">
            <strong style="color:#E8631A">⚠️ Bezpečnostní tipy:</strong>
            <ul style="margin-top:8px;padding-left:16px;display:flex;flex-direction:column;gap:4px">
                <li>Admin roli udělujte pouze důvěryhodným osobám</li>
                <li>Prodavač nemá přístup k zákazníkům ani financím</li>
                <li>Deaktivujte účty odcházejících zaměstnanců</li>
                <li>Každá akce je zaznamenána v přístupovém logu</li>
            </ul>
        </div>
    </div>
</div>

<script>
const perms = {
    admin: 'Přístup ke všemu včetně správy zaměstnanců',
    manager: 'Objednávky, produkty, články, galerie, rezervace, zákazníci',
    prodavac: 'Pouze objednávky a produkty'
};
document.querySelector('select[name="role"]').addEventListener('change', function() {
    document.getElementById('role-desc').textContent = perms[this.value] || '';
});
document.getElementById('role-desc').textContent = perms[document.querySelector('select[name="role"]').value] || '';
</script>
</body>
</html>
