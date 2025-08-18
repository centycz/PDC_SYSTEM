# Dokumentace: Rozdělení zásob pro rezervované a walk-in hosty

## Implementované funkce

### 1. Dashboard zásoby (status_dashboard.php)
- **🍕 Pizzy CELKEM**: Zobrazuje celkový počet testů (např. 112/120)
- **📅 Pizzy REZERVOVANÉ**: Počet testů vyhrazených pro rezervace (např. 95/100) - modrý rámeček
- **🚶 Pizzy WALK-IN**: Zbývající testy pro hosty bez rezervace (např. 17/20) - zelený rámeček

### 2. Vytváření objednávek (index.html)
- Přidán checkbox "📅 Rezervovaná objednávka"
- Číšník může označit objednávku jako rezervovanou
- Zásoby se odečítají ze správné kategorie podle označení

### 3. Kuchyňské rozhraní (kitchen.html)
- Rezervované objednávky mají modré ohraničení
- Zobrazují badge "📅 REZERVACE"
- Vizuální rozlišení od běžných objednávek

### 4. API rozšíření (restaurant-api.php)
- Endpoint `add-order` podporuje parametr `is_reserved`
- Endpoint `all-kitchen-items` vrací informaci o rezervaci
- Automatické vytvoření databázových sloupců při prvním použití

## Databázové změny

### Tabulka `orders`
```sql
ALTER TABLE orders ADD COLUMN is_reserved BOOLEAN DEFAULT FALSE;
```

### Tabulka `daily_supplies`
```sql
ALTER TABLE daily_supplies 
ADD COLUMN pizza_reserved INT(11) NOT NULL DEFAULT 0,
ADD COLUMN pizza_walkin INT(11) NOT NULL DEFAULT 0,
ADD COLUMN burrata_reserved INT(11) NOT NULL DEFAULT 0,
ADD COLUMN burrata_walkin INT(11) NOT NULL DEFAULT 0;
```

## Výchozí nastavení
- **Pizzy rezervované**: 100ks (80% z celkových 120ks)
- **Pizzy walk-in**: 20ks (20% z celkových 120ks)
- **Burrata rezervovaná**: 12 porcí (80% z celkových 15 porcí)
- **Burrata walk-in**: 3 porce (20% z celkových 15 porcí)

## Logika odpočítávání
- **Rezervovaná objednávka**: Testy se odečítají z `pizza_reserved`
- **Walk-in objednávka**: Testy se odečítají z `pizza_walkin`
- **Spálené pizzy**: Počítají se proti walk-in zásobám
- **Zpětná kompatibilita**: Existující data se automaticky rozdělí 80/20

## Uživatelské rozhraní

### Vizuální rozlišení:
- **Rezervované objednávky**: Modré ohraničení, ikona 📅
- **Walk-in objednávky**: Standard (bez speciálního označení)
- **Rezervované zásoby**: Modrý rámeček v dashboardu
- **Walk-in zásoby**: Zelený rámeček v dashboardu

### Editace zásob:
- **Celkové zásoby**: Možnost nastavit celkové počty a jejich rozdělení
- **Ruční nastavení**: Možnost nastavit zbývající množství v každé kategorii
- **Resetování dne**: Automaticky nastaví výchozí hodnoty (100/20 split)

## Použití

1. **Číšník vytváří objednávku**:
   - Vybere položky do košíku
   - Zadá jméno zákazníka
   - Zaškrtne "📅 Rezervovaná objednávka" pokud host má rezervaci
   - Odešle objednávku

2. **Kuchyň vidí objednávky**:
   - Rezervované objednávky jsou vizuálně odlišeny
   - Badge "📅 REZERVACE" označuje rezervované objednávky

3. **Dashboard sleduje zásoby**:
   - Správce vidí aktuální stav obou kategorií
   - Může upravovat zásoby podle potřeby
   - Systém automaticky odpočítává ze správné kategorie

## Testování
- Logika byla otestována s mock daty
- Všechny scénáře prošly správně
- Zpětná kompatibilita zachována