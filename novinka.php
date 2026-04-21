<?php
require_once 'includes/header.php';

$lang = LANG;
$slug = $_GET['slug'] ?? '';

if (!$slug) { header('Location: '.BASE_URL.'/novinky.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND published = 1");
$stmt->execute([$slug]);
$article = $stmt->fetch();

if (!$article) { header('Location: '.BASE_URL.'/novinky.php'); exit; }

$pageTitle = $article['title_' . $lang];

// Načti ostatní články
$stmt = $pdo->prepare("SELECT * FROM articles WHERE published = 1 AND id != ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$article['id']]);
$others = $stmt->fetchAll();
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a> <span>›</span>
            <a href="<?= BASE_URL ?>/novinky.php"><?= t('Novinky', 'News') ?></a> <span>›</span>
            <span><?= e($article['title_' . $lang]) ?></span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:820px">

        <!-- HEADER ČLÁNKU -->
        <div style="text-align:center;margin-bottom:48px">
            <div style="font-size:13px;color:var(--gray);margin-bottom:16px">
                📅 <?= date('d. m. Y', strtotime($article['created_at'])) ?>
            </div>
            <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,3rem);font-weight:900;line-height:1.2;margin-bottom:20px">
                <?= e($article['title_' . $lang]) ?>
            </h1>
            <?php if ($article['perex_' . $lang]): ?>
            <p style="font-size:1.15rem;color:var(--gray-dark);line-height:1.7;max-width:640px;margin:0 auto">
                <?= e($article['perex_' . $lang]) ?>
            </p>
            <?php endif; ?>
        </div>

        <!-- OBRÁZEK -->
        <?php if ($article['image']): ?>
        <div style="border-radius:var(--radius-lg);overflow:hidden;margin-bottom:48px;aspect-ratio:16/9">
            <img src="<?= BASE_URL ?>/uploads/<?= e($article['image']) ?>" alt="<?= e($article['title_' . $lang]) ?>" style="width:100%;height:100%;object-fit:cover">
        </div>
        <?php else: ?>
        <div style="border-radius:var(--radius-lg);background:linear-gradient(135deg,rgba(232,99,26,0.08),rgba(45,122,58,0.08));height:300px;display:flex;align-items:center;justify-content:center;font-size:5rem;margin-bottom:48px">
            🍊
        </div>
        <?php endif; ?>

        <!-- OBSAH -->
        <div style="font-size:1.05rem;line-height:1.9;color:var(--gray-dark)">
            <?= nl2br(e($article['content_' . $lang] ?? $article['perex_' . $lang])) ?>
        </div>

        <!-- ZPĚT -->
        <div style="margin-top:48px;padding-top:32px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:gap:16px">
            <a href="<?= BASE_URL ?>/novinky.php" class="btn btn-secondary">← <?= t('Zpět na novinky', 'Back to news') ?></a>
            <div style="display:flex;gap:12px">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL . '/novinka.php?slug=' . $slug) ?>" target="_blank" class="btn btn-outline btn-sm">📘 <?= t('Sdílet', 'Share') ?></a>
            </div>
        </div>

    </div>
</section>

<!-- DALŠÍ ČLÁNKY -->
<?php if (!empty($others)): ?>
<section class="section section-gray">
    <div class="container">
        <div class="section-header">
            <span class="section-label"><?= t('Více článků', 'More articles') ?></span>
            <h2 class="section-title"><?= t('Další <span class="accent">novinky</span>', 'More <span class="accent">news</span>') ?></h2>
        </div>
        <div class="articles-grid">
            <?php foreach ($others as $other): ?>
            <div class="article-card">
                <a href="<?= BASE_URL ?>/novinka.php?slug=<?= e($other['slug']) ?>">
                    <div class="article-img">
                        <?php if ($other['image']): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= e($other['image']) ?>" alt="<?= e($other['title_' . $lang]) ?>">
                        <?php else: ?>
                            <div class="article-img-placeholder">📰</div>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="article-body">
                    <div class="article-date"><?= date('d. m. Y', strtotime($other['created_at'])) ?></div>
                    <div class="article-title"><a href="<?= BASE_URL ?>/novinka.php?slug=<?= e($other['slug']) ?>"><?= e($other['title_' . $lang]) ?></a></div>
                    <div class="article-perex"><?= e($other['perex_' . $lang]) ?></div>
                    <a href="<?= BASE_URL ?>/novinka.php?slug=<?= e($other['slug']) ?>" class="btn btn-secondary btn-sm"><?= t('Číst více', 'Read more') ?></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
