#!/bin/bash
REPO="https://raw.githubusercontent.com/Chossekilla/ciaospritz-eshop/main"
FILES=(
  "admin/index.php" "admin/auth.php" "admin/media.php" "admin/hero.php" "admin/export.php"
  "admin/ai-descriptions.php" "admin/emails.php" "admin/product-edit.php"
  "admin/article-edit.php" "admin/gallery.php" "admin/media-list.php"
  "admin/pages.php" "admin/customers.php" "admin/faktura.php"
  "admin/reservations.php" "admin/stanek.php" "admin/log.php"
  "index.php" "produkty.php" "produkt.php" "kosik.php" "pokladna.php"
  "galerie.php" "novinky.php" "novinka.php" "stanek.php" "kontakt.php"
  "stranka.php" "muj-ucet.php" "faktura-zakaznik.php" "cart-action.php"
  "css/style.css" "js/main.js"
  "includes/header.php" "includes/footer.php" "includes/mailer.php"
)
for f in "${FILES[@]}"; do
  curl -s "$REPO/$f" -o "$f" && echo "✓ $f"
done
echo "=== DEPLOY HOTOV ==="
