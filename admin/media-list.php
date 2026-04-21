<?php
session_start();
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$uploadDir = __DIR__ . '/../uploads/';
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$files = [];
if (is_dir($uploadDir)) {
    $items = scandir($uploadDir);
    foreach ($items as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        // Přeskočit malé soubory (ikony atd.)
        $size = filesize($uploadDir . $file);
        if ($size < 5000) continue;
        $files[] = [
            'name' => $file,
            'size' => $size,
            'time' => filemtime($uploadDir . $file),
        ];
    }
}

// Seřaď od nejnovějšího
usort($files, fn($a, $b) => $b['time'] - $a['time']);

echo json_encode(array_column($files, 'name'));
