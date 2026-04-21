<?php
require_once 'includes/header.php';
$lang = LANG;
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location:'.BASE_URL.'/produkty.php'); exit; }
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND active=1");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { header('Location:'.BASE_URL.'/produkty.php'); exit; }
$pageTitle = $product['name_'.$lang];

// Fotky
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY is_main DESC, sort_order ASC, id ASC LIMIT 10");
$stmt->execute([$id]);
$imgs = $stmt->fetchAll();
if (empty($imgs) && $product['image']) {
    $imgs = [['filename'=>$product['image'],'is_main'=>1,'id'=>0]];
}
$mainImg = $imgs[0] ?? null;

// Tagy
$stmt = $pdo->prepare("SELECT t.* FROM tags t JOIN product_tags pt ON pt.tag_id=t.id WHERE pt.product_id=?");
$stmt->execute([$id]);
$tags = $stmt->fetchAll();

// Sklad barva
if ($product['stock'] <= 0) { $sColor='#dc3545'; $sText=t('Vyprodáno','Out of stock'); }
elseif ($product['stock'] <= 3) { $sColor='#dc3545'; $sText=t('Zbývají pouze','Only').' '.$product['stock'].' '.t('ks!','pcs left!'); }
elseif ($product['stock'] <= 10) { $sColor='#E8631A'; $sText=t('Zbývají pouze','Only').' '.$product['stock'].' '.t('ks!','pcs left!'); }
else { $sColor='#2D7A3A'; $sText=t('Skladem','In stock'); }

// Related
$stmt = $pdo->prepare("SELECT p.*, COALESCE((SELECT pi.filename FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1), p.image) as main_image FROM products p WHERE p.category=? AND p.id!=? AND p.active=1 LIMIT 4");
$stmt->execute([$product['category'],$id]);
$related = $stmt->fetchAll();
?>

<section style="background:#fdf8f4;padding:18px 0">
  <div class="container">
    <div style="font-size:13px;color:#888;display:flex;gap:6px;align-items:center">
      <a href="<?= BASE_URL ?>/" style="color:#888;text-decoration:none"><?= t('Domů','Home') ?></a>
      <span>›</span>
      <a href="<?= BASE_URL ?>/produkty.php" style="color:#888;text-decoration:none"><?= t('Produkty','Products') ?></a>
      <span>›</span>
      <span style="color:#111"><?= e($product['name_'.$lang]) ?></span>
    </div>
  </div>
</section>

<section style="padding:48px 0">
  <div class="container">

    <!-- 2 SLOUPCE -->
    <div id="prodGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:start">

      <!-- LEVÝ - Galerie (vše inline) -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Hlavní fotka -->
        <div onclick="openLB(0)"
             style="aspect-ratio:1/1;border-radius:12px;overflow:hidden;background:#fff;border:1px solid #e0e0e0;display:flex;align-items:center;justify-content:center;cursor:zoom-in;box-shadow:0 4px 20px rgba(0,0,0,0.06)">
          <?php if ($mainImg): ?>
            <img id="mainImg"
                 src="<?= BASE_URL ?>/uploads/<?= e($mainImg['filename']) ?>"
                 alt="<?= e($product['name_'.$lang]) ?>"
                 style="width:100%;height:100%;object-fit:contain;padding:20px;transition:transform 0.3s">
          <?php else: ?>
            <span style="font-size:5rem">🍾</span>
          <?php endif; ?>
        </div>

        <!-- Miniaturky -->
        <?php if (count($imgs) > 1): ?>
        <div style="display:flex;flex-direction:row;flex-wrap:wrap;gap:8px">
          <?php foreach ($imgs as $i => $img): ?>
          <div onclick="switchImg(<?= $i ?>,'<?= BASE_URL ?>/uploads/<?= e($img['filename']) ?>')"
               id="th<?= $i ?>"
               style="width:100px;height:100px;border-radius:8px;overflow:hidden;cursor:pointer;background:#fff;flex-shrink:0;transition:border-color 0.2s;border:2px solid <?= $i===0?'#E8631A':'#e0e0e0' ?>">
            <img src="<?= BASE_URL ?>/uploads/<?= e($img['filename']) ?>"
                 style="width:100%;height:100%;object-fit:contain;padding:4px">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>

      <!-- PRAVÝ - Info (vše inline) -->
      <div style="display:flex;flex-direction:column">

        <!-- Kategorie -->
        <div style="font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#E8631A;margin-bottom:10px"><?= e($product['category']) ?></div>

        <!-- Název -->
        <h1 style="font-family:'Playfair Display',serif;font-size:clamp(1.5rem,2.5vw,2.1rem);font-weight:900;line-height:1.2;color:#111;margin-bottom:16px"><?= e($product['name_'.$lang]) ?></h1>

        <!-- Štítky -->
        <?php if (!empty($product['badge']) || !empty($tags)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px">
          <?php if (!empty($product['badge'])): ?>
          <span style="background:#E8631A;color:white;font-size:11px;font-weight:700;padding:4px 12px;border-radius:4px;letter-spacing:0.05em;text-transform:uppercase"><?= e($product['badge']) ?></span>
          <?php endif; ?>
          <?php foreach ($tags as $tag): ?>
          <span style="background:<?= e($tag['color']) ?>;color:white;font-size:11px;font-weight:700;padding:4px 12px;border-radius:4px;letter-spacing:0.05em;text-transform:uppercase"><?= e($tag['name_'.$lang] ?? $tag['name_cs']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Oddělovač -->
        <div style="height:1px;background:#e0e0e0;margin-bottom:18px"></div>

        <!-- Cena -->
        <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:16px">
          <?php if (!empty($product['price_sale'])): ?>
            <span style="font-family:'Playfair Display',serif;font-size:2.1rem;font-weight:900;color:#E8631A"><?= formatPrice($product['price_sale']) ?></span>
            <span style="font-size:1rem;color:#888;text-decoration:line-through"><?= formatPrice($product['price']) ?></span>
          <?php else: ?>
            <span style="font-family:'Playfair Display',serif;font-size:2.1rem;font-weight:900;color:#E8631A"><?= formatPrice($product['price']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Popis -->
        <div style="font-size:15px;color:#444;line-height:1.8;margin-bottom:18px"><?= strip_tags($product['short_desc_'.$lang] ?? '') ?: strip_tags($product['description_'.$lang] ?? '') ?></div>

        <!-- Sklad -->
        <div style="display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:<?= $sColor ?>;margin-bottom:24px">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= $sColor ?>;flex-shrink:0;display:inline-block"></span>
          <?= $sText ?>
        </div>

        <!-- Košík -->
        <?php if ($product['stock'] > 0): ?>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:24px">
          <div style="display:flex;align-items:center;border:2px solid #e0e0e0;border-radius:8px;overflow:hidden;height:48px;flex-shrink:0">
            <button onclick="chQ(-1)" style="width:42px;height:100%;border:none;background:none;font-size:1.4rem;cursor:pointer;color:#444;transition:background 0.15s" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">−</button>
            <input type="number" id="qty<?= $id ?>" value="1" min="1" max="<?= $product['stock'] ?>"
                   style="width:48px;height:100%;border:none;border-left:1px solid #e0e0e0;border-right:1px solid #e0e0e0;text-align:center;font-size:1rem;font-weight:700;font-family:inherit;outline:none;-moz-appearance:textfield;appearance:textfield">
            <button onclick="chQ(1)" style="width:42px;height:100%;border:none;background:none;font-size:1.4rem;cursor:pointer;color:#444;transition:background 0.15s" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">+</button>
          </div>
          <button class="btn btn-primary add-to-cart" data-id="<?= $id ?>"
                  style="height:48px;font-size:15px;padding:0 28px;white-space:nowrap">
            🛒 <?= t('Přidat do košíku','Add to cart') ?>
          </button>
        </div>
        <?php endif; ?>

        <!-- Benefity -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding-top:18px;border-top:1px solid #e0e0e0">
          <div style="font-size:13px;color:#444;display:flex;align-items:center;gap:8px"><span style="font-size:18px">🚚</span> <?= t('Doprava zdarma od 2 000 Kč','Free shipping from 2 000 CZK') ?></div>
          <div style="font-size:13px;color:#444;display:flex;align-items:center;gap:8px"><span style="font-size:18px">📦</span> <?= t('Kurýr nebo osobní odběr','Courier or pickup') ?></div>
          <div style="font-size:13px;color:#444;display:flex;align-items:center;gap:8px"><span style="font-size:18px">💳</span> <?= t('Více způsobů platby','Multiple payment methods') ?></div>
          <div style="font-size:13px;color:#444;display:flex;align-items:center;gap:8px"><span style="font-size:18px">🇮🇹</span> <?= t('Italská kvalita','Italian quality') ?></div>
        </div>

      </div>
    </div>

    <!-- RELATED -->
    <?php if (!empty($related)): ?>
    <div style="margin-top:72px">
      <div class="section-header">
        <span class="section-label"><?= t('Mohlo by vás zajímat','You might also like') ?></span>
        <h2 class="section-title"><?= t('Související <span class="accent">produkty</span>','Related <span class="accent">products</span>') ?></h2>
      </div>
      <div class="products-grid">
        <?php foreach ($related as $rel): ?>
        <div class="product-card">
          <a href="<?= BASE_URL ?>/produkt.php?id=<?= $rel['id'] ?>">
            <div class="product-img">
              <?php if (!empty($rel['main_image'])): ?>
                <img src="<?= BASE_URL ?>/uploads/<?= e($rel['main_image']) ?>" alt="<?= e($rel['name_'.$lang]) ?>">
              <?php else: ?>
                <div class="product-img-placeholder">🍾</div>
              <?php endif; ?>
            </div>
          </a>
          <div class="product-info">
            <div class="product-category"><?= e($rel['category']) ?></div>
            <div class="product-name"><a href="<?= BASE_URL ?>/produkt.php?id=<?= $rel['id'] ?>"><?= e($rel['name_'.$lang]) ?></a></div>
            <div class="product-footer">
              <div class="product-price"><?= formatPrice($rel['price']) ?></div>
              <button class="btn btn-primary btn-sm add-to-cart" data-id="<?= $rel['id'] ?>">🛒</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- Lightbox -->
<div id="lb" onclick="if(event.target===this)closeLB()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.93);z-index:9999;align-items:center;justify-content:center">
  <button onclick="closeLB()" style="position:absolute;top:20px;right:24px;background:none;border:none;color:white;font-size:2.2rem;cursor:pointer;line-height:1">✕</button>
  <button onclick="prevImg()" style="position:absolute;left:16px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.12);border:none;color:white;font-size:2rem;cursor:pointer;padding:14px 18px;border-radius:8px">‹</button>
  <div style="max-width:90vw;max-height:90vh;text-align:center">
    <img id="lbImg" src="" style="max-width:100%;max-height:84vh;border-radius:8px;object-fit:contain">
    <div id="lbNum" style="color:rgba(255,255,255,0.4);font-size:13px;margin-top:10px"></div>
  </div>
  <button onclick="nextImg()" style="position:absolute;right:16px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.12);border:none;color:white;font-size:2rem;cursor:pointer;padding:14px 18px;border-radius:8px">›</button>
</div>

<!-- Mobile CSS -->
<style>
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
@media(max-width:768px){
  #prodGrid{grid-template-columns:1fr!important;gap:24px!important}
}
</style>

<script>
const allImgs = <?= json_encode(array_map(fn($i) => BASE_URL.'/uploads/'.$i['filename'], $imgs)) ?>;
let cur = 0;

function switchImg(i, src) {
    cur = i;
    document.getElementById('mainImg').src = src;
    allImgs.forEach((_,idx) => {
        const t = document.getElementById('th'+idx);
        if(t) t.style.borderColor = idx===i ? '#E8631A' : '#e0e0e0';
    });
}

function openLB(i) {
    cur = i;
    document.getElementById('lb').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    updLB();
}
function closeLB() {
    document.getElementById('lb').style.display = 'none';
    document.body.style.overflow = '';
}
function updLB() {
    document.getElementById('lbImg').src = allImgs[cur];
    document.getElementById('lbNum').textContent = (cur+1)+' / '+allImgs.length;
}
function prevImg() { cur=(cur-1+allImgs.length)%allImgs.length; updLB(); }
function nextImg() { cur=(cur+1)%allImgs.length; updLB(); }

document.addEventListener('keydown', e => {
    if(document.getElementById('lb').style.display==='flex'){
        if(e.key==='ArrowLeft') prevImg();
        if(e.key==='ArrowRight') nextImg();
        if(e.key==='Escape') closeLB();
    }
});

function chQ(d) {
    const inp = document.getElementById('qty<?= $id ?>');
    inp.value = Math.max(1, Math.min(<?= $product['stock'] ?>, parseInt(inp.value)+d));
}
</script>

<?php require_once 'includes/footer.php'; ?>
