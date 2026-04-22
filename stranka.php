<?php
// /stranka.php - zobrazení CMS stránek
require_once 'includes/header.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) { header('Location: '.BASE_URL.'/'); exit; }

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug=? AND published=1");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    $pageTitle = '404 - Stránka nenalezena';
}

$lang = LANG;
$title = $lang === 'en' ? ($page['title_en'] ?: $page['title_cs']) : $page['title_cs'];
$content = $lang === 'en' ? ($page['content_en'] ?: $page['content_cs']) : $page['content_cs'];
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů','Home') ?></a>
            <span>›</span>
            <span><?= e($title) ?></span>
        </div>
        <h1><?= e($title) ?></h1>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:800px">
        <?php if (!$page): ?>
        <div style="text-align:center;padding:64px 0">
            <div style="font-size:4rem;margin-bottom:16px">🔍</div>
            <h2 style="margin-bottom:12px">Stránka nenalezena</h2>
            <p style="color:var(--gray);margin-bottom:24px">Požadovaná stránka neexistuje.</p>
            <a href="<?= BASE_URL ?>/" class="btn btn-primary">← Zpět na úvod</a>
        </div>
        <?php else: ?>
        <div class="cms-content" style="line-height:1.8;color:var(--gray-dark)">
            <?= $content ?>
        </div>
        <div style="margin-top:40px;padding-top:24px;border-top:1px solid var(--border);font-size:12px;color:var(--gray)">
            <?= t('Naposledy aktualizováno','Last updated') ?>: <?= date('d. m. Y', strtotime($page['updated_at'])) ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.cms-content h2 { font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; margin: 2rem 0 1rem; color: var(--black); }
.cms-content h3 { font-size: 1.15rem; font-weight: 700; margin: 1.5rem 0 0.75rem; color: var(--black); }
.cms-content p { margin-bottom: 1rem; }
.cms-content ul, .cms-content ol { padding-left: 1.5rem; margin-bottom: 1rem; }
.cms-content li { margin-bottom: 0.5rem; }
.cms-content strong { color: var(--black); }
.cms-content a { color: var(--orange); text-decoration: underline; }
</style>

<?php require_once 'includes/footer.php'; ?>
