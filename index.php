<?php
$pageTitle = 'Italský aperitiv';
$pageDesc = 'Ciao Spritz - Italský aperitiv, který si zamilujete. Objednejte online s doručením po celé ČR.';
require_once 'includes/header.php';

// Načti hero slidy z DB (fallback na výchozí pokud tabulka neexistuje)
try {
    $heroSlides = $pdo->query("SELECT * FROM hero_slides WHERE active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch(Exception $e) {
    $heroSlides = [
        ['image'=>'r29-hero.jpg','title_cs'=>'Pochutnaj si na italském létě','title_en'=>'Taste the Italian summer','subtitle_cs'=>'Ciao Spritz – míchán přímo v lahvi.','subtitle_en'=>'Ciao Spritz – mixed directly in the bottle.','btn_text_cs'=>'Nakoupit nyní','btn_text_en'=>'Shop now','btn_url'=>'/produkty.php'],
        ['image'=>'r33-edit-hero.jpg','title_cs'=>'Italský aperitiv s tradicí','title_en'=>'Italian aperitif with tradition','subtitle_cs'=>'The oldest Italian aperitif.','subtitle_en'=>'The oldest Italian aperitif.','btn_text_cs'=>'Nakoupit nyní','btn_text_en'=>'Shop now','btn_url'=>'/produkty.php'],
    ];
}
if (empty($heroSlides)) {
    $heroSlides = [['image'=>'r29-hero.jpg','title_cs'=>'Pochutnaj si na italském létě','title_en'=>'Taste the Italian summer','subtitle_cs'=>'','subtitle_en'=>'','btn_text_cs'=>'Nakoupit nyní','btn_text_en'=>'Shop now','btn_url'=>'/produkty.php']];
}

// Načti featured produkty
$stmt = $pdo->query("
    SELECT p.*,
        COALESCE(
            (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1),
            (SELECT pi2.filename FROM product_images pi2 WHERE pi2.product_id = p.id ORDER BY pi2.id ASC LIMIT 1),
            p.image
        ) as main_image
    FROM products p
    WHERE p.featured = 1 AND p.active = 1 
    ORDER BY p.id ASC LIMIT 4
");
$featuredProducts = $stmt->fetchAll();

// Načti články
$stmt = $pdo->query("SELECT * FROM articles WHERE published = 1 ORDER BY created_at DESC LIMIT 3");
$articles = $stmt->fetchAll();

$lang = LANG;
?>

<!-- HERO SEKCE - FULLSCREEN SLIDESHOW -->
<section style="position:relative;height:90vh;min-height:520px;max-height:860px;overflow:hidden;background:#111">

    <?php foreach ($heroSlides as $si => $slide): ?>
    <div class="hero-slide <?= $si===0?'active':'' ?>" style="position:absolute;inset:0;opacity:<?= $si===0?'1':'0' ?>;transition:opacity 1.2s ease">
        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($slide['image']) ?>" alt="<?= htmlspecialchars($slide['title_'.$lang]??$slide['title_cs']??'Ciao Spritz') ?>"
             style="width:100%;height:100%;object-fit:cover;object-position:center 30%;opacity:0.85">
    </div>
    <?php endforeach; ?>

    <!-- Tmavý overlay pro čitelnost textu -->
    <div style="position:absolute;inset:0;background:linear-gradient(to right, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.2) 60%, rgba(0,0,0,0.1) 100%)"></div>

    <!-- Obsah hero -->
    <div class="container" style="position:relative;z-index:2;height:100%;display:flex;align-items:center">
        <div style="max-width:600px;color:white">
            <div style="display:inline-block;background:rgba(232,99,26,0.9);color:white;font-size:12px;font-weight:700;padding:6px 16px;border-radius:50px;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:20px">
                🍊 <?= t('Italský aperitiv', 'Italian aperitif') ?>
            </div>
            <h1 id="heroTitle" style="font-family:var(--font-display);font-size:clamp(2.2rem,5vw,3.8rem);font-weight:900;line-height:1.1;margin-bottom:20px;color:white">
                <?= htmlspecialchars($heroSlides[0]['title_'.$lang] ?? $heroSlides[0]['title_cs'] ?? '') ?>
            </h1>
            <p id="heroSubtitle" style="font-size:clamp(1rem,2vw,1.15rem);color:rgba(255,255,255,0.88);line-height:1.7;margin-bottom:32px;max-width:480px">
                <?= htmlspecialchars($heroSlides[0]['subtitle_'.$lang] ?? $heroSlides[0]['subtitle_cs'] ?? '') ?>
            </p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:40px">
                <a id="heroBtn" href="<?= BASE_URL ?><?= htmlspecialchars($heroSlides[0]['btn_url'] ?? '/produkty.php') ?>" class="btn btn-primary btn-lg" style="font-size:16px;padding:14px 32px">
                    🛒 <?= htmlspecialchars($heroSlides[0]['btn_text_'.$lang] ?? $heroSlides[0]['btn_text_cs'] ?? 'Nakoupit nyní') ?>
                </a>
                <a href="#o-nas" style="display:inline-flex;align-items:center;gap:8px;color:white;font-size:15px;font-weight:600;text-decoration:none;padding:14px 24px;border:2px solid rgba(255,255,255,0.5);border-radius:8px;transition:all 0.2s" onmouseover="this.style.borderColor='white'" onmouseout="this.style.borderColor='rgba(255,255,255,0.5)'">
                    <?= t('Zjistit více', 'Learn more') ?>
                </a>
            </div>
            <div style="display:flex;gap:32px;flex-wrap:wrap">
                <div>
                    <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:900;color:white">100%</div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:0.05em"><?= t('Italská kvalita', 'Italian quality') ?></div>
                </div>
                <div>
                    <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:900;color:white">RTD</div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:0.05em"><?= t('Ihned k pití', 'Ready to drink') ?></div>
                </div>
                <div>
                    <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:900;color:white">🚚</div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:0.05em"><?= t('Doprava po ČR', 'Delivery CZ') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tečky navigace -->
    <?php if (count($heroSlides) > 1): ?>
    <div style="position:absolute;bottom:24px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:3">
        <?php foreach ($heroSlides as $si => $slide): ?>
        <div class="hero-dot <?= $si===0?'active':'' ?>" onclick="goSlide(<?= $si ?>)" style="width:10px;height:10px;border-radius:50%;background:white;cursor:pointer;transition:all 0.3s;opacity:<?= $si===0?'1':'0.4' ?>"></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>


</section>
<style>
@media(max-width:768px){
    section[style*="90vh"] { height:70vh !important; min-height:420px !important; }
}
</style>
<script>
var heroIdx = 0;
var slides = document.querySelectorAll('.hero-slide');
var dots = document.querySelectorAll('.hero-dot');
var heroData = <?= json_encode(array_map(fn($s) => [
    'title' => $s['title_'.$lang] ?? $s['title_cs'] ?? '',
    'subtitle' => $s['subtitle_'.$lang] ?? $s['subtitle_cs'] ?? '',
    'btn_text' => $s['btn_text_'.$lang] ?? $s['btn_text_cs'] ?? 'Nakoupit nyní',
    'btn_url' => BASE_URL . ($s['btn_url'] ?? '/produkty.php'),
], $heroSlides)) ?>;

function goSlide(n) {
    if (slides[heroIdx]) { slides[heroIdx].style.opacity = 0; }
    if (dots[heroIdx]) { dots[heroIdx].style.opacity = 0.4; }
    heroIdx = n;
    if (slides[heroIdx]) { slides[heroIdx].style.opacity = 1; }
    if (dots[heroIdx]) { dots[heroIdx].style.opacity = 1; }
    // Aktualizuj text
    if (heroData[n]) {
        var t = document.getElementById('heroTitle');
        var s = document.getElementById('heroSubtitle');
        var b = document.getElementById('heroBtn');
        if (t) t.textContent = heroData[n].title;
        if (s) s.textContent = heroData[n].subtitle;
        if (b) { b.textContent = '🛒 ' + heroData[n].btn_text; b.href = heroData[n].btn_url; }
    }
}
if (slides.length > 1) {
    setInterval(function(){ goSlide((heroIdx + 1) % slides.length); }, 5000);
}
</script>

<!-- DOPRAVA ZDARMA BANNER -->
<div style="background:var(--green);color:white;text-align:center;padding:14px;font-size:14px;font-weight:500">
    🚚 <?= t('Doprava ZDARMA při objednávce nad 2 000 Kč!', 'FREE shipping on orders over 2 000 CZK!') ?>
</div>

<!-- NEJPRODÁVANĚJŠÍ PRODUKTY -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-label"><?= t('Naše produkty', 'Our products') ?></span>
            <h2 class="section-title"><?= t('Nejprodávanější <span class="accent">produkty</span>', 'Best selling <span class="accent">products</span>') ?></h2>
            <p class="section-desc"><?= t('Vyberte si z naší nabídky italských aperitivů a doplňkového zboží.', 'Choose from our range of Italian aperitifs and accessories.') ?></p>
        </div>

        <div class="products-grid">
            <?php foreach ($featuredProducts as $product): ?>
            <div class="product-card">
                <a href="<?= BASE_URL ?>/produkt.php?id=<?= $product['id'] ?>">
                    <div class="product-img">
                        <?php if ($product['image']): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= e($product['image']) ?>" alt="<?= e($product['name_' . $lang]) ?>">
                        <?php else: ?>
                            <div class="product-img-placeholder">🍾</div>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="product-info">
                    <div class="product-category"><?= e($product['category']) ?></div>
                    <div class="product-name">
                        <a href="<?= BASE_URL ?>/produkt.php?id=<?= $product['id'] ?>"><?= e($product['name_' . $lang]) ?></a>
                    </div>
                    <div class="product-desc"><?= strip_tags($product['short_desc_' . $lang] ?? $product['description_' . $lang] ?? '') ?></div>
                    <div class="product-footer">
                        <div class="product-price"><?= formatPrice($product['price']) ?></div>
                        <button class="btn btn-primary btn-sm add-to-cart" data-id="<?= $product['id'] ?>">
                            🛒 <?= t('Do košíku', 'Add to cart') ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center;margin-top:40px">
            <a href="<?= BASE_URL ?>/produkty.php" class="btn btn-secondary"><?= t('Zobrazit všechny produkty', 'View all products') ?></a>
        </div>
    </div>
</section>

<!-- O NÁS / BENEFITY -->
<section class="section section-gray" id="o-nas">
    <div class="container">
        <div class="section-header">
            <span class="section-label"><?= t('Proč Ciao Spritz', 'Why Ciao Spritz') ?></span>
            <h2 class="section-title"><?= t('Italská <span class="accent">tradice</span> v každém doušku', 'Italian <span class="accent">tradition</span> in every sip') ?></h2>
        </div>

        <div class="benefits-grid">
            <div class="benefit-card">
                <div class="benefit-icon">🍾</div>
                <div class="benefit-title"><?= t('Ihned k pití', 'Ready to drink') ?></div>
                <div class="benefit-desc"><?= t('Míchán přímo v lahvi. Stačí vychladit, otevřít a vychutnat.', 'Mixed directly in the bottle. Just chill, open and enjoy.') ?></div>
            </div>
            <div class="benefit-card">
                <div class="benefit-icon">🌿</div>
                <div class="benefit-title"><?= t('Přírodní složení', 'Natural ingredients') ?></div>
                <div class="benefit-desc"><?= t('Vyrobeno z pečlivě vybraných přírodních ingrediencí nejvyšší kvality.', 'Made from carefully selected natural ingredients of the highest quality.') ?></div>
            </div>
            <div class="benefit-card">
                <div class="benefit-icon">☀️</div>
                <div class="benefit-title"><?= t('Italský styl', 'Italian style') ?></div>
                <div class="benefit-desc"><?= t('Každá lahev přináší kousek italského léta a dolce vita.', 'Every bottle brings a piece of Italian summer and dolce vita.') ?></div>
            </div>
            <div class="benefit-card">
                <div class="benefit-icon">🎉</div>
                <div class="benefit-title"><?= t('Pro každou příležitost', 'For every occasion') ?></div>
                <div class="benefit-desc"><?= t('Ideální na festival, párty, venkovní posezení i klidný večer doma.', 'Ideal for festivals, parties, outdoor gatherings or a quiet evening at home.') ?></div>
            </div>
        </div>
    </div>
</section>

<!-- NOVINKY -->
<?php if (!empty($articles)): ?>
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-label"><?= t('Novinky', 'News') ?></span>
            <h2 class="section-title"><?= t('Co je <span class="accent">nového</span>', 'What\'s <span class="accent">new</span>') ?></h2>
        </div>

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
    </div>
</section>
<?php endif; ?>

<!-- ZAPŮJČENÍ STÁNKU BANNER -->
<section class="banner-section">
    <div class="container">
        <span class="section-label" style="color:rgba(255,255,255,0.7)"><?= t('Služby', 'Services') ?></span>
        <h2 class="section-title" style="color:white;margin:12px 0 16px"><?= t('Zapůjčení mobilního stánku', 'Mobile stand rental') ?></h2>
        <p class="section-desc"><?= t('Chystáte festival, firemní akci nebo soukromou párty? Zapůjčíme vám náš mobilní stánek Ciao Spritz!', 'Planning a festival, corporate event or private party? Rent our Ciao Spritz mobile stand!') ?></p>
        <div style="margin-top:32px">
            <a href="<?= BASE_URL ?>/stanek.php" class="btn btn-dark btn-lg"><?= t('Zjistit více a rezervovat', 'Find out more & book') ?></a>
        </div>
    </div>
</section>

<!-- KONTAKT PRUH -->
<section class="section-sm">
    <div class="container" style="text-align:center">
        <p style="font-size:1.1rem;color:var(--gray-dark)">
            <?= t('Máte otázky? Kontaktujte nás!', 'Got questions? Contact us!') ?>
            &nbsp;
            <a href="tel:602556323" style="color:var(--orange);font-weight:600">602 556 323</a>
            &nbsp;|&nbsp;
            <a href="mailto:rcaffe@email.cz" style="color:var(--orange);font-weight:600">rcaffe@email.cz</a>
        </p>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
