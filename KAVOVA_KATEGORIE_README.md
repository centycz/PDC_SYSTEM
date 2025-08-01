# ☕ Přidání kategorie kávy do PDC systému

## Přehled změn

Tento commit přidává novou kategorii **"Káva"** do restauračního systému PDC s 4 druhy kávy podle specifikace.

## 📋 Specifikace kávy

| Název | Cena | Typ (klíč) | Kategorie |
|-------|------|------------|-----------|
| Espresso | 50 Kč | `espresso` | kava |
| Espresso lungo | 50 Kč | `espresso_lungo` | kava |
| Espresso na ledu | 50 Kč | `espresso_na_ledu` | kava |
| Espresso tonik | 80 Kč | `espresso_tonik` | kava |

## 🔧 Nasazení změn

### 1. Aktualizace kódu
Kód je již připraven v tomto commit - soubor `pizza/bar-admin.html` obsahuje novou kategorii "Káva".

### 2. Aktualizace databáze
Spusťte SQL skript pro přidání kávy do databáze:

```bash
mysql -u pizza_user -p pizza_orders < add_coffee_category.sql
```

Nebo ručně v phpMyAdmin/MySQL konzoli:

```sql
INSERT INTO `drink_types` (
    `type`, `name`, `price`, `description`, `is_active`, 
    `created_at`, `updated_at`, `category`, `display_order`, `cost_price`
) VALUES 
('espresso', 'Espresso', 50.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00),
('espresso_lungo', 'Espresso lungo', 50.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00),
('espresso_na_ledu', 'Espresso na ledu', 50.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00),
('espresso_tonik', 'Espresso tonik', 80.00, '', 1, NOW(), NOW(), 'kava', 4, 0.00);
```

### 3. Ověření funkčnosti
1. Otevřete **Bar Admin** (pizza/bar-admin.html)
2. V kategorii nápojů zkontrolujte, že je dostupná možnost **"Káva"**
3. Přidejte testovací kávu a ověřte, že se uloží
4. Zkontrolujte v restauračním systému, že káva je viditelná k objednání

## 🎯 Technické detaily

- **Display order**: 4 (káva se zobrazí před ostatními kategoriemi)
- **Kategorie**: "kava" 
- **Stav**: Všechny kávy jsou aktivní (`is_active = 1`)
- **API kompatibilita**: Používá stávající API endpointy
- **Bar/Kitchen rozlišení**: Kávy jsou bar items (ne kitchen items)

## 📁 Změněné soubory

- `pizza/bar-admin.html` - přidána kategorie "Káva" do dropdownů
- `add_coffee_category.sql` - SQL skript pro přidání kávy do databáze

## ✅ Ověření po nasazení

Po nasazení by mělo být možné:
- Vybrat kategorii "Káva" v bar admin rozhraní
- Přidat nové druhy kávy
- Editovat existující kávy 
- Objednávat kávy v restauračním systému
- Zobrazit kávy v účtech a statistikách