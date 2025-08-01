-- ========================================
-- PŘIDÁNÍ KATEGORIE KÁVY DO PDC SYSTÉMU
-- ========================================
-- Datum: 2025-01-22
-- Autor: PDC System Enhancement
-- Popis: Přidání 4 druhů kávy podle specifikace požadavků

-- Kontrola existence kategorie káva
-- (Pokud už existuje, přeskočit vložení)

-- Vložení 4 druhů kávy podle specifikace:
-- 1. Espresso - 50 Kč
-- 2. Espresso lungo - 50 Kč  
-- 3. Espresso na ledu - 50 Kč
-- 4. Espresso tonik - 80 Kč

INSERT INTO `drink_types` (
    `type`, 
    `name`, 
    `price`, 
    `description`, 
    `is_active`, 
    `created_at`, 
    `updated_at`, 
    `category`, 
    `display_order`, 
    `cost_price`
) VALUES 
-- Espresso klasické (50 Kč)
('espresso', 'Espresso', 50.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00),

-- Espresso lungo (50 Kč)
('espresso_lungo', 'Espresso lungo', 50.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00),

-- Espresso na ledu (50 Kč)
('espresso_na_ledu', 'Espresso na ledu', 50.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00),

-- Espresso tonik (80 Kč - vyšší cena kvůli toniku)
('espresso_tonik', 'Espresso tonik', 80.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00);

-- Výsledek: Přidány 4 položky kávy s kategorií "kava" a display_order 4
-- Kategorie se zobrazí mezi stávajícími kategoriemi před negroni (5)

-- Poznámky:
-- - Všechny kávy jsou aktivní (is_active = 1)
-- - Kategorie "kava" má display_order = 4 pro správné řazení v menu
-- - cost_price je nastavena na 0.00 (lze upravit později dle skutečných nákladů)
-- - Typ items jsou unikátní klíče pro API operace
-- ========================================