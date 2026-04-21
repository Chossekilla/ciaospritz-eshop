<?php
$pageTitle = 'Galerie';
require_once 'includes/header.php';

$lang = LANG;

// Načti alba
$albums = $pdo->query("SELECT ga.*, COUNT(gp.id) as photo_count FROM gallery_albums ga LEFT JOIN gallery_photos gp ON gp.album_id = ga.id WHERE ga.active = 1 GROUP BY ga.id ORDER BY ga.sort_order ASC, ga.id DESC")->fetchAll();

// Pokud je vybrané album
$albumId = (int)($_GET['album'] ?? 0);
$currentAlbum = null;
$photos = [];

if ($albumId) {
    $stmt = $pdo->prepare("SELECT * FROM gallery_albums WHERE id = ? AND active = 1");
    $stmt->execute([$albumId]);
    $currentAlbum = $stmt->fetch();

    if ($currentAlbum) {
        $stmt = $pdo->prepare("SELECT * FROM gallery_photos WHERE album_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$albumId]);
        $photos = $stmt->fetchAll();
    }
}
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a> <span>›</span>
            <?php if ($currentAlbum): ?>
                <a href="<?= BASE_URL ?>/galerie.php"><?= t('Galerie', 'Gallery') ?></a> <span>›</span>
                <span><?= e($currentAlbum['name_' . $lang] ?? $currentAlbum['name_cs']) ?></span>
            <?php else: ?>
                <span><?= t('Galerie', 'Gallery') ?></span>
            <?php endif; ?>
        </div>
        <h1>
            <?php if ($currentAlbum): ?>
                <?= e($currentAlbum['name_' . $lang] ?? $currentAlbum['name_cs']) ?>
            <?php else: ?>
                <?= t('Naše <span class="accent">galerie</span>', 'Our <span class="accent">gallery</span>') ?>
            <?php endif; ?>
        </h1>
    </div>
</section>

<section class="section">
    <div class="container">

        <?php if (!$currentAlbum): ?>
        <!-- PŘEHLED ALB -->
        <?php if (empty($albums)): ?>
            <div class="alert alert-info"><?= t('Galerie bude brzy k dispozici.', 'Gallery coming soon.') ?></div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px">
            <?php foreach ($albums as $album): ?>
            <a href="<?= BASE_URL ?>/galerie.php?album=<?= $album['id'] ?>" style="text-decoration:none;color:inherit">
                <div style="border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--border);transition:all 0.3s;background:white" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 8px 32px rgba(0,0,0,0.15)';this.style.borderColor='var(--orange)'" onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='var(--border)'">
                    <!-- Cover -->
                    <div style="aspect-ratio:16/10;background:linear-gradient(135deg,rgba(232,99,26,0.1),rgba(45,122,58,0.1));display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative">
                        <?php if ($album['cover_image']): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= e($album['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover">
                        <?php else: ?>
                            <span style="font-size:4rem;opacity:0.4">🖼️</span>
                        <?php endif; ?>
                        <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,0.5));padding:16px;color:white">
                            <div style="font-size:12px;opacity:0.8"><?= $album['photo_count'] ?> <?= t('fotek', 'photos') ?></div>
                        </div>
                    </div>
                    <div style="padding:16px 20px">
                        <div style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;margin-bottom:4px"><?= e($album['name_' . $lang] ?? $album['name_cs']) ?></div>
                        <?php if ($album['description_' . $lang] ?? $album['description_cs']): ?>
                        <div style="font-size:13px;color:var(--gray)"><?= e($album['description_' . $lang] ?? $album['description_cs']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- FOTKY V ALBU -->
        <div style="margin-bottom:24px">
            <a href="<?= BASE_URL ?>/galerie.php" class="btn btn-outline btn-sm">← <?= t('Všechna alba', 'All albums') ?></a>
        </div>

        <?php if ($currentAlbum['description_cs']): ?>
        <p style="color:var(--gray-dark);margin-bottom:32px;font-size:1.05rem"><?= e($currentAlbum['description_' . $lang] ?? $currentAlbum['description_cs']) ?></p>
        <?php endif; ?>

        <?php if (empty($photos)): ?>
            <div class="alert alert-info"><?= t('Toto album zatím neobsahuje žádné fotky.', 'This album has no photos yet.') ?></div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px" id="photo-grid">
            <?php foreach ($photos as $i => $photo): ?>
            <div style="aspect-ratio:1;overflow:hidden;border-radius:var(--radius);cursor:pointer;background:var(--gray-light)" onclick="openLightbox(<?= $i ?>)">
                <img
                    src="<?= BASE_URL ?>/uploads/<?= e($photo['filename']) ?>"
                    alt="<?= e($photo['caption'] ?? '') ?>"
                    style="width:100%;height:100%;object-fit:cover;transition:transform 0.4s"
                    onmouseover="this.style.transform='scale(1.05)'"
                    onmouseout="this.style.transform='scale(1)'"
                >
            </div>
            <?php endforeach; ?>
        </div>

        <!-- LIGHTBOX -->
        <div id="lightbox" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.92);z-index:9999;align-items:center;justify-content:center">
            <button onclick="closeLightbox()" style="position:absolute;top:20px;right:24px;background:none;border:none;color:white;font-size:2rem;cursor:pointer;opacity:0.8">✕</button>
            <button onclick="prevPhoto()" style="position:absolute;left:20px;background:rgba(255,255,255,0.1);border:none;color:white;font-size:2rem;cursor:pointer;padding:12px 16px;border-radius:8px">◀</button>
            <div style="max-width:90vw;max-height:85vh;text-align:center">
                <img id="lightbox-img" src="" style="max-width:100%;max-height:80vh;border-radius:8px;object-fit:contain">
                <div id="lightbox-caption" style="color:rgba(255,255,255,0.7);margin-top:12px;font-size:14px"></div>
                <div id="lightbox-counter" style="color:rgba(255,255,255,0.4);font-size:12px;margin-top:4px"></div>
            </div>
            <button onclick="nextPhoto()" style="position:absolute;right:20px;background:rgba(255,255,255,0.1);border:none;color:white;font-size:2rem;cursor:pointer;padding:12px 16px;border-radius:8px">▶</button>
        </div>

        <script>
        const photos = <?= json_encode(array_map(fn($p) => ['src' => BASE_URL.'/uploads/' . $p['filename'], 'caption' => $p['caption'] ?? ''], $photos)) ?>;
        let current = 0;

        function openLightbox(i) {
            current = i;
            document.getElementById('lightbox').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            showPhoto();
        }

        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
            document.body.style.overflow = '';
        }

        function showPhoto() {
            document.getElementById('lightbox-img').src = photos[current].src;
            document.getElementById('lightbox-caption').textContent = photos[current].caption;
            document.getElementById('lightbox-counter').textContent = (current + 1) + ' / ' + photos.length;
        }

        function prevPhoto() { current = (current - 1 + photos.length) % photos.length; showPhoto(); }
        function nextPhoto() { current = (current + 1) % photos.length; showPhoto(); }

        document.getElementById('lightbox').addEventListener('click', function(e) {
            if (e.target === this) closeLightbox();
        });

        document.addEventListener('keydown', function(e) {
            if (document.getElementById('lightbox').style.display === 'flex') {
                if (e.key === 'ArrowLeft') prevPhoto();
                if (e.key === 'ArrowRight') nextPhoto();
                if (e.key === 'Escape') closeLightbox();
            }
        });
        </script>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
