# CIAO SPRITZ - Instalace (FINÁLNÍ verze)

## Instalace na Hostinger

### 1. Databáze
1. hPanel → Databáze → MySQL → vytvoř databázi
2. hPanel → phpMyAdmin → vyber databázi → záložka SQL
3. Vlož obsah souboru database.sql → Spustit

### 2. Konfigurace
Uprav includes/config.php:
  DB_NAME = název databáze
  DB_USER = uživatel databáze
  DB_PASS = heslo databáze
  SITE_URL = https://www.ciaospritz.cz

### 3. Nahrání souborů
File Manager → public_html → nahraj ZIP → rozbal
NEBO přes FTP (FileZilla)

### 4. Práva uploads složky
Klikni pravým na uploads/ → Change Permissions → 755

### 5. Admin panel
URL: ciaospritz.cz/admin/
Uživatel: admin
Heslo: admin123
!! ZMĚŇ HESLO IHNED !!

### 6. Logo a obrázky
- Logo: nahraj jako /images/logo.png
- Hero: nahraj jako /images/hero-product.png
- Produkty: přes admin panel

## Co je hotové
- Úvodní stránka (hero, produkty, benefity, novinky)
- E-shop (produkty, detail, košík, pokladna)
- Platba: karta / převod / dobírka / osobní odběr
- Doprava: kurýr / osobní (zdarma od 2000 Kč)
- Emaily po objednávce (zákazník + admin)
- Přihlášení + registrace + můj účet + historie objednávek
- Novinky (přehled + detail)
- Galerie s alby + lightbox (šipky + klávesnice)
- Zapůjčení stánku + interaktivní kalendář + formulář
- Kontakt + mapa Google
- CZ/EN přepínač
- Admin panel (produkty, objednávky, články, galerie, rezervace)
- Responzivní design (mobil, tablet, desktop)
