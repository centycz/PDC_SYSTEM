<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    header('Location: login.php');
    exit;
}

// Get user information from session
$user_name = $_SESSION['order_user'];
$full_name = $_SESSION['order_full_name'];
$user_role = $_SESSION['user_role'];

// Check if user has permission to access statistics
if (!in_array($user_role, ['admin', 'ragazzi'])) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="utf-8" />
    <title>Přehledy prodejů</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/cs.js"></script>
    <style>
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            margin-bottom: 30px;
        }

        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .tabs {
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            margin-right: 5px;
        }

        .tab.active {
            background: #2196F3;
            color: white;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .error {
            background: #fee;
            padding: 20px;
            border-radius: 8px;
            color: #c00;
        }

        .basic-overview {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .daily-total {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
            margin-bottom: 15px;
        }

        .order-count {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }

        /* STYLY PRO GRAFY */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 400px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }

        .additional-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .mini-stat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .mini-stat .value {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .mini-stat .label {
            font-size: 12px;
            opacity: 0.9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }

        th {
            background-color: #f5f5f5;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-weight: bold;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .product-table-header {
            font-size: 18px;
            color: #333;
            margin-top: 30px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        select, input, button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        button {
            background: #2196F3;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #1976D2;
        }

        optgroup {
            font-weight: bold;
            color: #666;
        }

        .export-buttons {
            margin-bottom: 20px;
        }

        .export-btn {
            background: #4CAF50;
            margin-right: 10px;
        }

        .export-btn:hover {
            background: #45a049;
        }

        /* Navigation styles */
        .nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .back-btn {
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .back-btn:hover {
            background: #5a6fd8;
            text-decoration: none;
            color: white;
            transform: translateY(-1px);
        }

        .user-info {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="nav-header">
        <div>
            <h1 style="margin: 0; color: #333;">📊 Statistiky a data</h1>
            <div class="user-info">
                Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
            </div>
        </div>
        <a href="/index.php" class="back-btn">← ZPĚT NA HLAVNÍ STRÁNKU</a>
    </div>
    
    <div class="card">
    <div class="container">
        <div class="header">
            <h1>Přehledy prodejů</h1>
            <div class="user-info" id="userInfo">Načítání...</div>
        </div>
 <div class="tabs">
            <button class="tab active" data-view="default">Základní přehled</button>
            <button class="tab" data-view="categories">Podle kategorií</button>
            <button class="tab" data-view="top_orders">Nejvyšší objednávky</button>
            <button class="tab" data-view="trends">Trendy</button>
            <button class="tab" data-view="analytics">📊 Analytika</button>
        </div>
        <div class="filters">
            <div class="filter-group">
                <label>Datum</label>
                <input type="text" id="datePicker" placeholder="Vyberte datum">
            </div>
            <div class="filter-group">
                <label>Kategorie</label>
                <select id="categoryFilter">
                    <option value="all">Všechny kategorie</option>
                    
                    <!-- META-KATEGORIE -->
                    <optgroup label="📊 Souhrnné kategorie">
                        <option value="meta_jidlo">🍽️ Všechno jídlo</option>
                        <option value="meta_napoje">🥤 Všechny nápoje</option>
                    </optgroup>
                    
                    <!-- JEDNOTLIVÉ KATEGORIE JÍDLA -->
                    <optgroup label="🍽️ Jídlo">
    <option value="predkrm">Předkrmy</option>
    <option value="pizza">Pizza</option>
    <option value="pasta">Těstoviny</option>
    <option value="dezert">🍰 Dezerty</option>
</optgroup>
                    
                    <!-- JEDNOTLIVÉ KATEGORIE NÁPOJŮ -->
                    <optgroup label="🥤 Nápoje">
                        <option value="negroni">Negroni</option>
                        <option value="spritz">Spritz</option>
                        <option value="koktejl">Koktejly</option>
                        <option value="digestiv">Digestiv</option>
                        <option value="vino">Víno</option>
                        <option value="pivo">Pivo</option>
                        <option value="nealko">Nealko</option>
                        <option value="drink">Drink</option>
                    </optgroup>
                </select>
            </div>
            <div class="filter-group">
                <label>Zaměstnanec</label>
                <select id="employeeFilter">
                    <option value="">Všichni zaměstnanci</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Platební metoda</label>
                <select id="paymentMethodFilter">
                    <option value="">Všechny</option>
                    <option value="hotovost">Hotovost</option>
                    <option value="karta">Karta</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Zobrazení</label>
                <select id="viewFilter">
                    <option value="default">Základní přehled</option>
                    <option value="categories">Podle kategorií</option>
                    <option value="top_orders">Nejvyšší objednávky</option>
                    <option value="trends">Trendy prodeje</option>
                    <option value="analytics">Analytika</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button onclick="updateData()">Aktualizovat</button>
            </div>
        </div>

       

        <div class="export-buttons">
            <button class="export-btn" onclick="exportToCSV()">📄 Export CSV</button>
            <button class="export-btn" onclick="printReport()">🖨️ Tisk</button>
        </div>

        <div id="reports">
            <div class="loading">Načítání dat...</div>
        </div>
    </div>

    <script>
    // Globální proměnná pro ukládání posledních dat
    let lastData = null;

    // Funkce pro meta-kategorie
    function getMetaCategoryFilter(category) {
        switch(category) {
            case 'meta_jidlo':
    return ['predkrm', 'pizza', 'pasta', 'dezert'];
            case 'meta_napoje':
                return ['negroni', 'spritz', 'koktejl', 'digestiv', 'vino', 'pivo', 'nealko', 'drink'];
            default:
                return null;
        }
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('cs-CZ', {
            style: 'currency',
            currency: 'CZK',
            minimumFractionDigits: 0
        }).format(amount);
    }

    function formatDate(dateStr) {
        try {
            const date = new Date(dateStr + 'T00:00:00');
            if (isNaN(date.getTime())) {
                return dateStr;
            }
            return date.toLocaleDateString('cs-CZ');
        } catch (error) {
            console.warn('Chyba při formátování data:', dateStr, error);
            return dateStr;
        }
    }

    // NOVÁ POMOCNÁ FUNKCE - formátuje datum v lokálním čase
    function formatDateLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // NOVÁ POMOCNÁ FUNKCE - generuje rozsah dat v lokálním čase
    function generateDateRangeLocal(startStr, endStr) {
        const dates = [];
        const start = new Date(startStr + 'T00:00:00');
        const end = new Date(endStr + 'T00:00:00');
        
        const currentDate = new Date(start);
        
        while (currentDate <= end) {
            dates.push(formatDateLocal(currentDate));
            currentDate.setDate(currentDate.getDate() + 1);
        }
        
        return dates;
    }

    function calculateAdditionalStats(data) {
        if (!data || !data.data) return {};
        
        const dnesni = data.data.dnesni_prodeje;
        const produkty = dnesni?.produkty || [];
        
        const avgOrderValue = dnesni?.pocet > 0 ? (dnesni.total / dnesni.pocet) : 0;
        const totalItems = produkty.reduce((sum, p) => sum + parseInt(p.pocet), 0);
        const avgItemsPerOrder = dnesni?.pocet > 0 ? (totalItems / dnesni.pocet) : 0;
        
        return {
            avgOrderValue,
            totalItems,
            avgItemsPerOrder,
            topProduct: produkty[0]?.nazev || 'N/A'
        };
    }

    function renderDashboard(data) {
        if (!data || !data.data || !data.data.dnesni_prodeje) {
            document.getElementById('reports').innerHTML = '<div class="error">Chyba: Neplatná data</div>';
            return;
        }

        const dnesni = data.data.dnesni_prodeje;
        const stats = calculateAdditionalStats(data);
const foodCostStats = data.data.food_cost_analysis || null;
        // Pouze filtr kategorií, datum už ne
        const categoryFilter = document.getElementById('categoryFilter').value;
        let filterInfo = '';
        if (categoryFilter === 'meta_jidlo') {
    filterInfo = '<div style="background: #e8f5e8; padding: 10px; border-radius: 5px; margin-bottom: 15px;">🍽️ <strong>Filtr:</strong> Zobrazeno pouze jídlo (předkrmy, pizza, těstoviny, dezerty)</div>';
        } else if (categoryFilter === 'meta_napoje') {
            filterInfo = '<div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">🥤 <strong>Filtr:</strong> Zobrazeny pouze nápoje</div>';
        }

        document.getElementById('userInfo').innerHTML = `
            <strong>Přihlášený uživatel:</strong> ${data.user}<br>
            <small>Poslední aktualizace: ${new Date(data.time).toLocaleString('cs-CZ')}</small>
        `;

        document.getElementById('reports').innerHTML = `
            <div class="basic-overview">
                ${filterInfo}
                
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="daily-total">Přehled období</div>
                        <div class="daily-total">${formatCurrency(dnesni.total || 0)}</div>
                        <div class="order-count">Celková tržba</div>
                    </div>
                    <div class="stat-box">
                        <div class="daily-total">${dnesni.pocet || 0}</div>
                        <div class="order-count">Počet objednávek</div>
                    </div>
                </div>

                <div class="additional-stats">
    <div class="mini-stat">
        <div class="value">${formatCurrency(stats.avgOrderValue)}</div>
        <div class="label">Průměrná objednávka</div>
    </div>
    <div class="mini-stat">
        <div class="value">${stats.totalItems}</div>
        <div class="label">Celkem položek</div>
    </div>
    <div class="mini-stat">
        <div class="value">${stats.avgItemsPerOrder.toFixed(1)}</div>
        <div class="label">Položek/objednávka</div>
    </div>
    <div class="mini-stat">
        <div class="value">${stats.topProduct}</div>
        <div class="label">Top produkt</div>
    </div>
    ${foodCostStats ? `
        <div class="mini-stat" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="value">${foodCostStats.total.cost_percent.toFixed(1)}%</div>
            <div class="label">💰 Food Cost</div>
        </div>
        <div class="mini-stat" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
            <div class="value">${formatCurrency(foodCostStats.total.margin)}</div>
            <div class="label">💹 Čistý zisk</div>
        </div>
    ` : ''}
</div>

                <div class="product-table-header">Nejprodávanější produkty v období</div>
                <table>
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Kategorie</th>
                            <th>Počet prodejů</th>
                            <th>Tržba</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(dnesni.produkty || []).map(produkt => `
                            <tr>
                                <td>${produkt.nazev}</td>
                                <td>${produkt.kategorie}</td>
                                <td>${produkt.pocet}</td>
                                <td>${formatCurrency(produkt.trzba)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    // OPRAVENÁ funkce renderAnalytics
    function renderAnalytics(data) {
    console.log('=== RENDER ANALYTICS DEBUG ===');
    console.log('Full analytics data:', data);
    
    if (!data || !data.data) {
        console.error('No data or data.data');
        return '<div class="error">Chyba: Neplatná data pro analytiku</div>';
    }

    // DEBUG: Co máme v data.data?
    console.log('Available data keys:', Object.keys(data.data));
    console.log('dnesni_prodeje exists:', !!data.data.dnesni_prodeje);
    if (data.data.dnesni_prodeje) {
        console.log('dnesni_prodeje keys:', Object.keys(data.data.dnesni_prodeje));
        console.log('produkty exists:', !!data.data.dnesni_prodeje.produkty);
        console.log('produkty length:', data.data.dnesni_prodeje.produkty?.length || 0);
        console.log('First 3 produkty:', data.data.dnesni_prodeje.produkty?.slice(0, 3) || []);
    }

    // Filtr kategorií
    const categoryFilter = document.getElementById('categoryFilter').value;
    let filterInfo = '';
    if (categoryFilter === 'meta_jidlo') {
    filterInfo = '<div style="background: #e8f5e8; padding: 10px; border-radius: 5px; margin-bottom: 15px;">🍽️ <strong>Filtr:</strong> Zobrazeno pouze jídlo (předkrmy, pizza, těstoviny, dezerty)</div>';
    } else if (categoryFilter === 'meta_napoje') {
        filterInfo = '<div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">🥤 <strong>Filtr:</strong> Zobrazeny pouze nápoje</div>';
    }

    const content = `
    <div class="basic-overview">
        ${filterInfo}
        <h2>📊 Pokročilá analytika</h2>
        
        <!-- FOOD COST PŘEHLED -->
        ${data.data.food_cost_analysis ? `
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px 0; color: #495057;">💰 Food Cost Přehled</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <div style="text-align: center;">
                        <div style="font-size: 18px; font-weight: bold; color: ${data.data.food_cost_analysis.total.cost_percent > 35 ? '#dc3545' : data.data.food_cost_analysis.total.cost_percent > 25 ? '#ffc107' : '#28a745'};">
                            ${data.data.food_cost_analysis.total.cost_percent.toFixed(1)}%
                        </div>
                        <div style="font-size: 12px; color: #666;">Celkový Food Cost</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 18px; font-weight: bold; color: #2196F3;">
                            ${formatCurrency(data.data.food_cost_analysis.total.margin)}
                        </div>
                        <div style="font-size: 12px; color: #666;">Celková marže</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 18px; font-weight: bold; color: #17a2b8;">
                            ${formatCurrency(data.data.food_cost_analysis.food.margin)}
                        </div>
                        <div style="font-size: 12px; color: #666;">Marže jídlo</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 18px; font-weight: bold; color: #6f42c1;">
                            ${formatCurrency(data.data.food_cost_analysis.drinks.margin)}
                        </div>
                        <div style="font-size: 12px; color: #666;">Marže nápoje</div>
                    </div>
                </div>
            </div>
        ` : ''}
        
        <div class="charts-container">
            <div class="chart-box">
                <div class="chart-title">Rozdělení podle kategorií</div>
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title">Top 10 produktů</div>
                <canvas id="productsChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title">💰 Food Cost Analýza</div>
                <canvas id="foodCostChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title">💹 Top marže produktů</div>
                <canvas id="marginChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title">Platební metody</div>
                <canvas id="paymentChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title">Výkonnost zaměstnanců</div>
                <canvas id="employeeChart"></canvas>
            </div>
        </div>
    </div>
`;

    // Kratší timeout pro rychlejší debug
    setTimeout(() => {
        console.log('Creating charts after timeout...');
        createCharts(data);
    }, 50);

    return content;
}
    // OPRAVENÁ funkce createCharts
    function createCharts(data) {
    console.log('=== CREATE CHARTS DEBUG ===');
    console.log('createCharts called with data:', data);
    
    // Zničit existující grafy před vytvořením nových
    destroyExistingCharts();
    
    // Existující grafy...
    if (data.data.kategorie && data.data.kategorie.length > 0) {
        console.log('Creating category chart with data:', data.data.kategorie);
        createCategoryChart(data.data.kategorie);
    } else {
        console.warn('No category data available for chart');
        showEmptyChart('categoryChart', 'Žádná data pro kategorie');
    }

    if (data.data.dnesni_prodeje?.produkty) {
        console.log('Products found:', data.data.dnesni_prodeje.produkty.length);
        createProductsChart(data.data.dnesni_prodeje.produkty);
    } else {
        console.warn('No products data available for chart');
        showEmptyChart('productsChart', 'Žádná data pro produkty');
    }

    // NOVÉ FOOD COST GRAFY
    if (data.data.food_cost_analysis) {
        console.log('Creating food cost charts with data:', data.data.food_cost_analysis);
        createFoodCostChart(data.data.food_cost_analysis);
    } else {
        console.warn('No food cost data available');
        showEmptyChart('foodCostChart', 'Žádná data pro food cost');
    }

    if (data.data.top_margin_items && data.data.top_margin_items.length > 0) {
        console.log('Creating margin chart with data:', data.data.top_margin_items);
        createMarginChart(data.data.top_margin_items);
    } else {
        console.warn('No margin data available');
        showEmptyChart('marginChart', 'Žádná data pro marže');
    }

    // Načíst další analytická data
    fetchAnalyticsData();
}

    // NOVÁ funkce pro zničení existujících grafů
    function destroyExistingCharts() {
    const chartIds = ['categoryChart', 'productsChart', 'foodCostChart', 'marginChart', 'paymentChart', 'employeeChart'];
    
    chartIds.forEach(chartId => {
        const canvas = document.getElementById(chartId);
        if (canvas && canvas.chart) {
            canvas.chart.destroy();
        }
    });
}

    // OPRAVENÁ funkce createCategoryChart
    function createCategoryChart(categories) {
        const canvas = document.getElementById('categoryChart');
        if (!canvas) {
            console.warn('Category chart canvas not found');
            return;
        }

        console.log('Creating category chart with data:', categories);

        const ctx = canvas.getContext('2d');
        
        canvas.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categories.map(cat => cat.kategorie),
                datasets: [{
                    data: categories.map(cat => parseFloat(cat.trzba) || 0),
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                return context.label + ': ' + formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    }

    // OPRAVENÁ funkce createProductsChart s debugem
function createProductsChart(products) {
    const canvas = document.getElementById('productsChart');
    if (!canvas) {
        console.warn('Products chart canvas not found');
        return;
    }

    console.log('=== PRODUCTS CHART DEBUG ===');
    console.log('Raw products data:', products);
    console.log('Products count:', products.length);

    if (!products || products.length === 0) {
        console.warn('No products data for chart');
        showEmptyChart('productsChart', 'Žádné produkty');
        return;
    }

    // Filtrovat a připravit top 10
    const validProducts = products.filter(p => {
        const hasName = p.nazev && p.nazev.trim() !== '';
        const hasCount = p.pocet && parseInt(p.pocet) > 0;
        console.log(`Product ${p.nazev}: hasName=${hasName}, hasCount=${hasCount}, count=${p.pocet}`);
        return hasName && hasCount;
    });

    console.log('Valid products count:', validProducts.length);
    console.log('Valid products:', validProducts);

    if (validProducts.length === 0) {
        console.warn('No valid products for chart');
        showEmptyChart('productsChart', 'Žádné platné produkty');
        return;
    }

    // Seřadit podle počtu prodejů a vzít top 10
    const sortedProducts = validProducts.sort((a, b) => parseInt(b.pocet) - parseInt(a.pocet));
    const top10 = sortedProducts.slice(0, 10);
    
    console.log('Top 10 products:', top10);

    const ctx = canvas.getContext('2d');

    // Zničit existující graf
    if (canvas.chart) {
        canvas.chart.destroy();
    }

    // Připravit data pro graf
    const labels = top10.map(p => {
        const name = p.nazev || 'Neznámý';
        return name.length > 20 ? name.substring(0, 20) + '...' : name;
    });

    const data = top10.map(p => parseInt(p.pocet) || 0);
    const colors = top10.map((_, index) => {
        const hue = (index * 360 / 10) % 360;
        return `hsl(${hue}, 70%, 60%)`;
    });

    console.log('Chart labels:', labels);
    console.log('Chart data:', data);
    console.log('Chart colors:', colors);

    canvas.chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Počet prodejů',
                data: data,
                backgroundColor: colors,
                borderColor: colors.map(color => color.replace('60%', '40%')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y', // Horizontální sloupcový graf
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: `Top ${top10.length} produktů`
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const product = top10[context.dataIndex];
                            return [
                                `Prodejů: ${context.parsed.x}`,
                                `Tržba: ${formatCurrency(product.trzba || 0)}`,
                                `Kategorie: ${product.kategorie || 'N/A'}`
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        display: true
                    },
                    title: {
                        display: true,
                        text: 'Počet prodejů'
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            },
            // Přidat animace
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });

    console.log('Products chart created successfully');
}

    function renderCategoryView(data) {
        if (!data || !data.data || !data.data.kategorie) {
            return '<div class="error">Chyba: Neplatná data pro zobrazení kategorií</div>';
        }

        return `
            <div class="basic-overview">
                <table>
                    <thead>
                        <tr>
                            <th>Kategorie</th>
                            <th>Počet prodejů</th>
                            <th>Tržba</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.data.kategorie.map(kat => `
                            <tr>
                                <td>${kat.kategorie}</td>
                                <td>${kat.pocet}</td>
                                <td>${formatCurrency(kat.trzba)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderTopOrders(data) {
    console.log('renderTopOrders called with:', data);
    
    if (!data || !data.data || !data.data.top_orders) {
        console.log('No top_orders data found');
        return '<div class="error">Chyba: Neplatná data pro zobrazení top plateb</div>';
    }

    const topOrders = data.data.top_orders;
    console.log('Top orders count:', topOrders.length);

    if (topOrders.length === 0) {
        return `
            <div class="basic-overview">
                <h3>Top platby</h3>
                <div style="text-align: center; padding: 20px; color: #666;">
                    Žádné platby nebyly nalezeny pro vybrané období a filtry.
                </div>
            </div>
        `;
    }

    return `
        <div class="basic-overview">
            <h3>Top platby (${topOrders.length} záznamů)</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID objednávky</th>
                        <th>Stůl</th>
                        <th>Částka</th>
                        <th>Platební metoda</th>
                        <th>Čas platby</th>
                        <th>Zaměstnanec</th>
                    </tr>
                </thead>
                <tbody>
                    ${topOrders.map(order => `
                        <tr>
                            <td>${order.order_id}</td>
                            <td>Stůl ${order.table_code}</td>
                            <td>${formatCurrency(order.amount)}</td>
                            <td>${order.payment_method === 'cash' ? 'Hotovost' : order.payment_method === 'card' ? 'Karta' : order.payment_method}</td>
                            <td>${new Date(order.paid_at).toLocaleString('cs-CZ')}</td>
                            <td>${order.employee_name || 'N/A'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

    function exportToCSV() {
        if (!lastData) return;
        console.log('Export CSV - připraveno k implementaci');
        alert('CSV export bude implementován v další verzi');
    }

    function printReport() {
        window.print();
    }

    // OPRAVENÁ funkce fetchAnalyticsData
    function fetchAnalyticsData() {
        const params = getCurrentParams();
        params.view = 'analytics_data';
        
        console.log('Fetching analytics data with params:', params);
        
        fetch('api/reports-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(params)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Analytics data received:', data);
            
            if (data.data?.payment_methods && data.data.payment_methods.length > 0) {
                createPaymentChart(data.data.payment_methods);
            } else {
                console.warn('No payment methods data');
                showEmptyChart('paymentChart', 'Žádná data pro platební metody');
            }
            
            if (data.data?.employees && data.data.employees.length > 0) {
                createEmployeeChart(data.data.employees);
            } else {
                console.warn('No employees data');
                showEmptyChart('employeeChart', 'Žádná data pro zaměstnance');
            }
        })
        .catch(error => {
            console.error('Chyba při načítání analytických dat:', error);
            showEmptyChart('paymentChart', 'Chyba při načítání dat');
            showEmptyChart('employeeChart', 'Chyba při načítání dat');
        });
    }

    function renderTrends(data) {
    console.log('renderTrends called with:', data);
    
    if (!data || !data.data || !data.data.trendy) {
        console.log('No trendy data found');
        return '<div class="error">Chyba: Neplatná data pro zobrazení trendů</div>';
    }

    const trendy = data.data.trendy;
    console.log('Trends count:', trendy.length);

    if (trendy.length === 0) {
        return `
            <div class="basic-overview">
                <h3>Trendy prodeje</h3>
                <div style="text-align: center; padding: 20px; color: #666;">
                    Žádné trendy nebyly nalezeny pro vybrané období a filtry.
                </div>
            </div>
        `;
    }

    // Spočítáme celkové součty pro srovnání
    const celkovaTrzba = trendy.reduce((sum, t) => sum + parseFloat(t.trzba || 0), 0);
    const celkoveObjednavky = trendy.reduce((sum, t) => sum + parseInt(t.pocet_objednavek || 0), 0);
    const prumerObjednavka = celkoveObjednavky > 0 ? (celkovaTrzba / celkoveObjednavky) : 0;

    return `
        <div class="basic-overview">
            <h3>📈 Trendy prodeje (${trendy.length} dnů)</h3>
            
            <!-- SOUHRNNÉ STATISTIKY TRENDŮ -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div style="text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #2196F3;">${formatCurrency(celkovaTrzba)}</div>
                    <div style="font-size: 12px; color: #666;">Celková tržba období</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #4CAF50;">${celkoveObjednavky}</div>
                    <div style="font-size: 12px; color: #666;">Celkové objednávky</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #FF9800;">${formatCurrency(prumerObjednavka)}</div>
                    <div style="font-size: 12px; color: #666;">Průměr/objednávka</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: #9C27B0;">${(celkovaTrzba / trendy.length).toFixed(0)} Kč</div>
                    <div style="font-size: 12px; color: #666;">Průměr/den</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>📅 Den</th>
                        <th>📦 Objednávky</th>
                        <th>💰 Tržba</th>
                        <th>📊 Průměr/obj.</th>
                        <th>📈 % z celku</th>
                    </tr>
                </thead>
                <tbody>
                    ${trendy.map(trend => {
                        const avgPerOrder = trend.pocet_objednavek > 0 ? (trend.trzba / trend.pocet_objednavek) : 0;
                        const percentOfTotal = celkovaTrzba > 0 ? ((trend.trzba / celkovaTrzba) * 100) : 0;
                        const date = new Date(trend.den + 'T00:00:00');
                        const dayName = date.toLocaleDateString('cs-CZ', { weekday: 'short' });
                        
                        return `
                            <tr>
                                <td>
                                    <strong>${formatDate(trend.den)}</strong><br>
                                    <small style="color: #666;">${dayName}</small>
                                </td>
                                <td style="text-align: center;">
                                    <strong>${trend.pocet_objednavek}</strong>
                                </td>
                                <td style="text-align: right;">
                                    <strong>${formatCurrency(trend.trzba)}</strong>
                                </td>
                                <td style="text-align: right;">
                                    ${formatCurrency(avgPerOrder)}
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: linear-gradient(90deg, #2196F3 ${percentOfTotal}%, transparent ${percentOfTotal}%); padding: 2px 8px; border-radius: 10px; color: white; font-size: 12px;">
                                        ${percentOfTotal.toFixed(1)}%
                                    </span>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}
    // OPRAVENÁ funkce createPaymentChart
    function createPaymentChart(paymentData) {
        const canvas = document.getElementById('paymentChart');
        if (!canvas) {
            console.warn('Payment chart canvas not found');
            return;
        }

        console.log('Creating payment chart with data:', paymentData);

        const ctx = canvas.getContext('2d');

        canvas.chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: paymentData.map(p => {
    switch(p.payment_method) {
        case 'cash': return 'Hotovost';        // ✅ PŘIDÁNO
        case 'card': return 'Karta';           // ✅ PŘIDÁNO
        case 'hotovost': return 'Hotovost';    // Ponecháno pro zpětnou kompatibilitu
        case 'karta': return 'Karta';          // Ponecháno pro zpětnou kompatibilitu
        default: return p.payment_method;
    }
}),
                datasets: [{
                    data: paymentData.map(p => parseFloat(p.amount) || 0),
                    backgroundColor: ['#4CAF50', '#2196F3', '#FF9800'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                return context.label + ': ' + formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    }

    // OPRAVENÁ funkce createEmployeeChart
    function createEmployeeChart(employeeData) {
        const canvas = document.getElementById('employeeChart');
        if (!canvas) {
            console.warn('Employee chart canvas not found');
            return;
        }

        console.log('Creating employee chart with data:', employeeData);

        const ctx = canvas.getContext('2d');

        canvas.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: employeeData.map(e => e.employee_name || 'Neznámý'),
                datasets: [{
                    label: 'Tržba',
                    data: employeeData.map(e => parseFloat(e.revenue) || 0),
                    backgroundColor: '#FF6384',
                    borderColor: '#E91E63',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const employee = employeeData[context.dataIndex];
                                return [
                                    'Tržba: ' + formatCurrency(context.parsed.y),
                                    'Objednávky: ' + (employee.orders_count || 0)
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    }

    // NOVÁ funkce pro zobrazení prázdného grafu
    function showEmptyChart(chartId, message) {
    const canvas = document.getElementById(chartId);
    if (!canvas) {
        console.warn(`Canvas ${chartId} not found`);
        return;
    }
    
    console.log(`Showing empty chart for ${chartId}: ${message}`);
    
    const ctx = canvas.getContext('2d');
    
    // Zničit existující graf
    if (canvas.chart) {
        canvas.chart.destroy();
    }
    
    canvas.chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [message],
            datasets: [{
                data: [1],
                backgroundColor: ['#E0E0E0'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: message
                }
            }
        }
    });
}

    // VRÁCENO K PŮVODNÍMU FUNGUJÍCÍMU PŘÍSTUPU
    function getCurrentParams() {
        const dateRange = document.getElementById('datePicker').value;
        const category = document.getElementById('categoryFilter').value;
        const employeeName = document.getElementById('employeeFilter').value;
        const paymentMethod = document.getElementById('paymentMethodFilter').value;
        const view = document.getElementById('viewFilter').value;

        // PŮVODNÍ PŘÍSTUP - používáme Flatpickr API pro získání dat
        let dates = [];
        const picker = document.getElementById('datePicker')._flatpickr;
        if (picker && picker.selectedDates && picker.selectedDates.length > 0) {
            if (picker.selectedDates.length === 1) {
                // Jeden den - použít lokální datum
                const date = picker.selectedDates[0];
                const localDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                dates = [formatDateLocal(localDate)];
            } else if (picker.selectedDates.length === 2) {
                // Rozsah - vygenerovat všechny dny mezi (lokální čas)
                const start = picker.selectedDates[0];
                const end = picker.selectedDates[1];
                
                const startLocal = new Date(start.getFullYear(), start.getMonth(), start.getDate());
                const endLocal = new Date(end.getFullYear(), end.getMonth(), end.getDate());
                
                const currentDate = new Date(startLocal);
                
                while (currentDate <= endLocal) {
                    dates.push(formatDateLocal(currentDate));
                    currentDate.setDate(currentDate.getDate() + 1);
                }
            }
        }

        // Fallback pokud nejsou data z Flatpickr
        if (dates.length === 0) {
            if (dateRange && dateRange.trim() !== '') {
                if (dateRange.includes(' to ')) {
                    const [startStr, endStr] = dateRange.split(' to ');
                    dates = generateDateRangeLocal(startStr.trim(), endStr.trim());
                } else {
                    dates = [dateRange.trim()];
                }
            } else {
                dates = [formatDateLocal(new Date())];
            }
        }

        // Zpracování kategorií
        let processedCategory = category;
        if (category !== 'all') {
            const metaCategories = getMetaCategoryFilter(category);
            if (metaCategories) {
                processedCategory = metaCategories;
            }
        }

        console.log('getCurrentParams result:', {
            dates: dates,
            category: processedCategory,
            employee_name: employeeName,
            payment_method: paymentMethod,
            view: view
        });

        return {
            dates: dates,
            category: processedCategory,
            employee_name: employeeName,
            payment_method: paymentMethod,
            view: view
        };
    }

    function fetchData(params = {}) {
    document.getElementById('reports').innerHTML = '<div class="loading">Načítání dat...</div>';

    console.log('fetchData called with params:', params);

    fetch('api/reports-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(params)
    })
        .then(response => response.json())
        .then(data => {
            console.log('fetchData received data:', data);
            lastData = data;

            let content = '';
            
            switch(params.view) {
                case 'categories':
                    content = renderCategoryView(data);
                    break;
                case 'top_orders':
                    console.log('Rendering top_orders view');
                    content = renderTopOrders(data);
                    break;
                case 'trends':
                    content = renderTrends(data);
                    break;
                case 'analytics':
                    content = renderAnalytics(data);
                    break;
                default:
                    renderDashboard(data);
                    return;
            }
            
            document.getElementById('reports').innerHTML = content;
        })
        .catch(error => {
            console.error('fetchData error:', error);
            document.getElementById('reports').innerHTML = `
                <div class="error">
                    <h2>Chyba při načítání dat</h2>
                    <p>${error.message}</p>
                </div>
            `;
        });
}

    // INICIALIZACE FLATPICKR s dnešním datem
    document.addEventListener('DOMContentLoaded', function() {
        const today = formatDateLocal(new Date());
        
        // Nastavit dnešní datum do input pole
        document.getElementById('datePicker').value = today;
        
        // Inicializovat Flatpickr s dnešním datem
        flatpickr("#datePicker", {
            locale: "cs",
            dateFormat: "Y-m-d",
            mode: "range",
            maxDate: "today",
            defaultDate: today,
            time_24hr: true,
            enableTime: false,
            onClose: function(selectedDates, dateStr, instance) {
                console.log('Flatpickr - selected dates:', selectedDates.map(d => formatDateLocal(d)));
                console.log('Flatpickr - date string:', dateStr);
                // Automaticky aktualizovat data po výběru
                setTimeout(updateData, 100);
            }
        });

        // Načíst zaměstnance
        loadEmployees();
        
        // Načíst data
        setTimeout(updateData, 200);
    });

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('viewFilter').value = tab.dataset.view;
            updateData();
        });
    });

    function loadEmployees() {
        fetch('api/reports-api.php?action=get-employees')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.employees) {
                    const select = document.getElementById('employeeFilter');
                    data.data.employees.forEach(employee => {
                        const option = document.createElement('option');
                        option.value = employee.name;
                        option.textContent = `${employee.name} (${employee.count} objednávek)`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Chyba při načítání zaměstnanců:', error));
    }

    function updateData() {
        const params = getCurrentParams();
        fetchData(params);
    }
    function createFoodCostChart(foodCostData) {
    const canvas = document.getElementById('foodCostChart');
    if (!canvas) {
        console.warn('Food cost chart canvas not found');
        return;
    }

    console.log('Creating food cost chart with data:', foodCostData);

    const ctx = canvas.getContext('2d');

    if (canvas.chart) {
        canvas.chart.destroy();
    }

    // Data pro graf
    const labels = ['Jídlo', 'Nápoje'];
    const revenues = [
        foodCostData.food.revenue,
        foodCostData.drinks.revenue
    ];
    const costs = [
        foodCostData.food.costs,
        foodCostData.drinks.costs
    ];
    const margins = [
        foodCostData.food.margin,
        foodCostData.drinks.margin
    ];

    canvas.chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Tržby',
                    data: revenues,
                    backgroundColor: '#36A2EB',
                    borderColor: '#1976D2',
                    borderWidth: 1
                },
                {
                    label: 'Náklady',
                    data: costs,
                    backgroundColor: '#FF6384',
                    borderColor: '#E91E63',
                    borderWidth: 1
                },
                {
                    label: 'Marže',
                    data: margins,
                    backgroundColor: '#4CAF50',
                    borderColor: '#2E7D32',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed.y;
                            const datasetLabel = context.dataset.label;
                            
                            if (datasetLabel === 'Náklady') {
                                const totalRevenue = revenues[context.dataIndex];
                                const costPercent = totalRevenue > 0 ? (value / totalRevenue * 100) : 0;
                                return `${datasetLabel}: ${formatCurrency(value)} (${costPercent.toFixed(1)}%)`;
                            }
                            
                            return `${datasetLabel}: ${formatCurrency(value)}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}

// NOVÁ FUNKCE - Margin Chart (Top ziskové produkty)
function createMarginChart(marginItems) {
    const canvas = document.getElementById('marginChart');
    if (!canvas) {
        console.warn('Margin chart canvas not found');
        return;
    }

    console.log('Creating margin chart with data:', marginItems);

    const ctx = canvas.getContext('2d');

    if (canvas.chart) {
        canvas.chart.destroy();
    }

    // Připravit data - top 10 podle marže
    const top10 = marginItems.slice(0, 10);
    
    const labels = top10.map(item => {
        const name = item.nazev || 'Neznámý';
        return name.length > 15 ? name.substring(0, 15) + '...' : name;
    });

    const margins = top10.map(item => parseFloat(item.total_margin) || 0);
    const colors = top10.map((_, index) => {
        const margin = margins[index];
        if (margin > 1000) return '#28a745'; // Zelená pro vysokou marži
        if (margin > 500) return '#ffc107';  // Žlutá pro střední marži
        return '#dc3545'; // Červená pro nízkou marži
    });

    canvas.chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Marže',
                data: margins,
                backgroundColor: colors,
                borderColor: colors.map(color => color.replace('a745', '6749').replace('c107', '9f06').replace('3545', '2d2d')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y', // Horizontální graf
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Top 10 nejvýnosnějších produktů'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const item = top10[context.dataIndex];
                            return [
                                `Marže: ${formatCurrency(context.parsed.x)}`,
                                `Tržba: ${formatCurrency(item.trzba)}`,
                                `Food cost: ${parseFloat(item.cost_percent).toFixed(1)}%`,
                                `Prodáno: ${item.pocet}ks`
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}
    
    </script>
</body>
</html>