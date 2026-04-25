<?php
// CIAO SPRITZ - Admin autentizace a oprávnění

function requireLogin() {
    if (!isset($_SESSION['staff_id'])) {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }
}

function requireRole($minRole) {
    $roles = ['prodavac' => 1, 'manager' => 2, 'admin' => 3];
    $currentRole = $_SESSION['staff_role'] ?? 'prodavac';
    if (($roles[$currentRole] ?? 0) < ($roles[$minRole] ?? 99)) {
        die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>⛔ Přístup odepřen</h2><p>Nemáte oprávnění k této sekci.</p><a href="' . BASE_URL . '/admin/index.php">← Zpět</a></div>');
    }
}

function canAccess($section) {
    $role = $_SESSION['staff_role'] ?? 'prodavac';
    $permissions = [
        'prodavac' => ['dashboard', 'orders', 'products'],
        'manager'  => ['dashboard', 'orders', 'products', 'articles', 'gallery', 'reservations', 'customers'],
        'admin'    => ['dashboard', 'orders', 'products', 'articles', 'gallery', 'reservations', 'customers', 'staff', 'settings'],
    ];
    return in_array($section, $permissions[$role] ?? []);
}

function logAction($pdo, $action, $detail = '') {
    if (isset($_SESSION['staff_id'])) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare("INSERT INTO staff_log (staff_id, action, detail, ip) VALUES (?,?,?,?)")
            ->execute([$_SESSION['staff_id'], $action, $detail, $ip]);
    }
}

function getRoleBadge($role) {
    $badges = [
        'admin'    => ['label' => 'Admin',     'color' => '#6f42c1'],
        'manager'  => ['label' => 'Manager',   'color' => '#E8631A'],
        'prodavac' => ['label' => 'Prodavač',  'color' => '#2D7A3A'],
    ];
    $b = $badges[$role] ?? ['label' => $role, 'color' => '#888'];
    return "<span style='background:{$b['color']}20;color:{$b['color']};padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700'>{$b['label']}</span>";
}
?>
