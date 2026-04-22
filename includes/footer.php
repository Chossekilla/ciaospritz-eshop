</main>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?= BASE_URL ?>/" class="logo">
                    <img src="<?= BASE_URL ?>/images/logo.png" alt="Ciao Spritz" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                    <span class="logo-text" style="display:none">CIAO <span>SPRITZ</span></span>
                </a>
                <p><?= t('Italský aperitiv, který si zamilujete.', 'The Italian aperitif you will fall in love with.') ?></p>
                <div class="social-links">
                    <a href="https://www.facebook.com/ciaospritz" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/ciao_spritz/" target="_blank" rel="noopener" aria-label="Instagram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    </a>
                </div>
            </div>

            <div class="footer-col">
                <h4><?= t('Navigace', 'Navigation') ?></h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a></li>
                    <li><a href="<?= BASE_URL ?>/produkty.php"><?= t('Produkty', 'Products') ?></a></li>
                    <li><a href="<?= BASE_URL ?>/novinky.php"><?= t('Novinky', 'News') ?></a></li>
                    <li><a href="<?= BASE_URL ?>/stanek.php"><?= t('Zapůjčení stánku', 'Rent a stand') ?></a></li>
                    <li><a href="<?= BASE_URL ?>/galerie.php"><?= t('Galerie', 'Gallery') ?></a></li>
                    <li><a href="<?= BASE_URL ?>/kontakt.php"><?= t('Kontakt', 'Contact') ?></a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4><?= t('Informace', 'Information') ?></h4>
                <ul>
                    <?php
                    try {
                        $footerPages = $pdo->query("SELECT slug, title_cs, title_en FROM pages WHERE in_footer=1 AND published=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
                        foreach ($footerPages as $fp):
                            $ftitle = LANG==='en' ? ($fp['title_en']?:$fp['title_cs']) : $fp['title_cs'];
                    ?>
                    <li><a href="<?= BASE_URL ?>/stranka/<?= htmlspecialchars($fp['slug']) ?>"><?= htmlspecialchars($ftitle) ?></a></li>
                    <?php endforeach; } catch(Exception $e) {} ?>
                    <li><a href="<?= BASE_URL ?>/kosik.php"><?= t('Košík', 'Cart') ?></a></li>
                    <li><a href="<?= BASE_URL ?>/prihlaseni.php"><?= t('Přihlášení', 'Login') ?></a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4><?= t('Kontakt', 'Contact') ?></h4>
                <ul>
                    <li>📞 <a href="tel:<?= SHOP_PHONE_RAW ?>"><?= SHOP_PHONE ?></a></li>
                    <li>✉️ <a href="mailto:<?= SHOP_EMAIL ?>"><?= SHOP_EMAIL ?></a></li>
                    <?php if (defined('SHOP_ADDRESS') && SHOP_ADDRESS): ?>
                    <li>📍 <?= SHOP_ADDRESS ?></li>
                    <?php endif; ?>
                    <?php if (defined('SHOP_ICO') && SHOP_ICO): ?>
                    <li style="font-size:12px;color:var(--gray)">IČO: <?= SHOP_ICO ?></li>
                    <?php endif; ?>
                </ul>
                <h4 style="margin-top:1.5rem"><?= t('Platební metody', 'Payment methods') ?></h4>
                <div class="payment-icons">
                    <span class="payment-badge">💳 <?= t('Karta', 'Card') ?></span>
                    <span class="payment-badge">🏦 <?= t('Převod', 'Transfer') ?></span>
                    <span class="payment-badge">📦 <?= t('Dobírka', 'COD') ?></span>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. <?= t('Všechna práva vyhrazena.', 'All rights reserved.') ?></p>
        </div>
    </div>
</footer>

<script src="/js/main.js"></script>
</body>
</html>
