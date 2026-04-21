<?php
$pageTitle = 'Novinky';
require_once 'includes/header.php';

$lang = LANG;
$stmt = $pdo->query("SELECT * FROM articles WHERE published = 1 ORDER BY created_at DESC");
$articles = $stmt->fetchAll();
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a>
            <span>›</span>
            <span><?= t('Novinky', 'News') ?></span>
        </div>
        <h1><?= t('Naše <span class="accent">novinky</span>', 'Our <span class="accent">news</span>') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($articles)): ?>
            <div class="alert alert-info"><?= t('Zatím žádné novinky.', 'No news yet.') ?></div>
        <?php else: ?>
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
            <div class="article-card">
                <a href="<?= BASE_URL ?>/novinka.php?slug=<?= e($article['slug']) ?>">
                    <div class="article-img">
                        <?php if ($article['image']): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= e($article['image']) ?>" alt="<?= e($article['title_' . $lang]) ?>">
                        <?php else: ?>
                            <div class="article-img-placeholder">📰</div>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="article-body">
                    <div class="article-date"><?= date('d. m. Y', strtotime($article['created_at'])) ?></div>
                    <div class="article-title">
                        <a href="<?= BASE_URL ?>/novinka.php?slug=<?= e($article['slug']) ?>"><?= e($article['title_' . $lang]) ?></a>
                    </div>
                    <div class="article-perex"><?= e($article['perex_' . $lang]) ?></div>
                    <a href="<?= BASE_URL ?>/novinka.php?slug=<?= e($article['slug']) ?>" class="btn btn-secondary btn-sm"><?= t('Číst více', 'Read more') ?></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
