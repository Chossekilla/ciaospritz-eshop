-- Přiřazení fotek produktům
-- Spusť v phpMyAdmin

UPDATE products SET image='r34-bottle-transparent.png' WHERE slug='ciao-spritz-075l' OR (name_cs LIKE '%Ciao Spritz 0,75%' AND category='napoje');
UPDATE products SET image='key-keg-transparent.png' WHERE name_cs LIKE '%sud 20%' OR name_cs LIKE '%Glera%';
UPDATE products SET image='love-ciao-spritz-plechovka.png' WHERE name_cs LIKE '%Love Ciao Spritz 200%' OR name_cs LIKE '%Love Ciao Spritz%Plech%';
UPDATE products SET image='love-ciao-spritz-250ml-transparent.png' WHERE name_cs LIKE '%Love Ciao Spritz 250%';
UPDATE products SET image='lemon-farm-spritz-plechovka.png' WHERE name_cs LIKE '%Lemon Farm%Plech%' OR name_cs LIKE '%Lemon Farm%200%';
UPDATE products SET image='negroni-farm-spritz-lahev.png' WHERE name_cs LIKE '%Negroni Farm%';
UPDATE products SET image='0pct-negroni-plechovka-transparent.png' WHERE name_cs LIKE '%Negroni%' AND name_cs LIKE '%0%';
UPDATE products SET image='0pct-hugo-plechovka-transparent.png' WHERE name_cs LIKE '%Hugo%' AND name_cs LIKE '%0%';

-- Sety
UPDATE products SET image='r34-bottle-transparent.png' WHERE name_cs LIKE '%set 3%' OR name_cs LIKE '%Set 3%';
UPDATE products SET image='r34-bottle-transparent.png' WHERE name_cs LIKE '%Set 6%' OR name_cs LIKE '%set 6%';
