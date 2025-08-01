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
$user_role = $_SESSION['is_admin'] ? 'admin' : 'user';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasta Kuchyně - Pasta • Předkrmy • Dezerty</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2E8B57 0%, #3CB371 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 {
            color: white;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .nav-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .nav-link {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .nav-link.active { background: white; color: #2E8B57; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.6em;
            color: #2E8B57;
            margin: 25px 0 10px 0;
            font-weight: bold;
            padding: 15px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border-left: 5px solid #2E8B57;
        }
        
        .section-title.predkrm { border-left-color: #27ae60; }
        .section-title.pasta-waiting { border-left-color: #f39c12; }
        .section-title.pasta-ready { 
            border-left-color: #e74c3c; 
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            animation: pulse-bg 2s infinite;
        }
        .section-title.dezert { border-left-color: #8e44ad; }
        
        @keyframes pulse-bg {
            0% { background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); }
            50% { background: linear-gradient(135deg, #ffcdd2 0%, #ffebee 100%); }
            100% { background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); }
        }
        
        .pasta-stats {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            padding: 20px;
            margin: 0 auto 25px auto;
            max-width: 1000px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border-left: 4px solid #27ae60;
        }

        .stat-item.pasta { border-left-color: #f39c12; }
        .stat-item.predkrm { border-left-color: #27ae60; }
        .stat-item.dezert { border-left-color: #8e44ad; }
        .stat-item.pasta-ready { border-left-color: #e74c3c; }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2E8B57;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 1.1em;
            color: #495057;
            font-weight: 500;
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .order-card {
            border: 3px solid #2E8B57;
            border-radius: 15px;
            padding: 20px;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.07);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            height: fit-content;
        }
        
        .order-card.waiting {
            border-color: #f39c12;
            background: #fef9e7;
        }
        
        .order-card.ready {
            border-color: #e74c3c;
            background: #ffeae8;
            animation: pulse-card 2s infinite;
        }
        
        .order-card.dessert {
            border-color: #8e44ad;
            background: #f4f0f7;
        }
        
        @keyframes pulse-card {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.3); }
            70% { box-shadow: 0 0 0 20px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .table-number {
            font-size: 1.3em;
            font-weight: bold;
            color: #2E8B57;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .order-time {
            color: #666;
            font-size: 0.95em;
        }
        
        .status-badge {
            background: #f39c12;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .status-badge.ready {
            background: #e74c3c;
            animation: pulse-text 1s infinite;
        }
        
        .status-badge.dessert-time {
            background: #8e44ad;
        }
        
        @keyframes pulse-text {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .order-items { 
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .order-item {
            background: #f4fafd;
            border-radius: 8px;
            border-left: 4px solid #2E8B57;
            padding: 12px 15px;
            margin-bottom: 9px;
            flex-grow: 1;
        }
        
        .order-item.pasta { border-left-color: #f39c12; }
        .order-item.predkrm { border-left-color: #27ae60; }
        .order-item.dezert { border-left-color: #8e44ad; }
        
        .item-name { font-weight: 600; color: #333; margin-bottom: 3px;}
        .item-quantity { color: #666; font-size: 0.93em; margin-bottom: 3px;}
        .item-note {
            background: #e0f7fa;
            border: 1px solid #b2ebf2;
            border-radius: 6px;
            padding: 6px 10px;
            margin-top: 4px;
            font-size: 0.95em;
            color: #00796b;
            font-style: italic;
            line-height: 1.3;
        }
        
        .item-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
            min-height: 45px;
        }

        .btn-ready {
            background: #2E8B57;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            width: 100%;
            text-align: center;
            padding: 12px 0;
            margin: 0;
        }

        .btn-ready:hover { 
            background: #1e5f3f; 
            transform: translateY(-2px);
        }
        
        .btn-waiting {
            background: #f39c12;
            color: white;
            cursor: not-allowed;
        }

        .last-update {
            text-align: center;
            color: #666;
            font-size: 0.95em;
            margin-top: 15px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state .icon {
            font-size: 4em;
            margin-bottom: 20px;
            display: block;
        }
        
        .print-info {
            margin: 10px 0;
            padding: 8px 12px;
            background: #e8f5e8;
            border-radius: 6px;
            font-size: 0.9em;
            color: #2e7d32;
        }
        
        .sync-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #856404;
        }
        
        @media (max-width: 700px) {
            .orders-grid { grid-template-columns: 1fr; }
            .container { padding: 10px; }
            .pasta-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="login.php" style="background: rgba(255,255,255,0.9); color: #2E8B57; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: all 0.3s ease;">← ZPĚT NA PŘIHLÁŠENÍ</a>
        </div>
        <div style="color: white; font-size: 14px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
            Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
            <button onclick="logout()" style="background: rgba(255,255,255,0.9); color: #2E8B57; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; margin-left: 15px; cursor: pointer;">🚪 Odhlásit se</button>
        </div>
    </div>
    
    <div class="header">
        <h1>🍝 Pasta Kuchyně</h1>
        <div class="nav-links">
            <a href="index.php" class="nav-link" id="nav-obsluha">🏠 Objednávky</a>
            <a href="kitchen.php" class="nav-link" id="nav-kuchyn">🍕 Pizza kuchyň</a>
            <a href="pasta-kuchyn.php" class="nav-link active" id="nav-pasta">🍝 Pasta kuchyň</a>
            <a href="bar.php" class="nav-link" id="nav-bar">🍺 Bar</a>
            <a href="serving.php" class="nav-link" id="nav-servirovani">🍽️ Servírování</a>
            <a href="billing.php" class="nav-link" id="nav-uctovani">💰 Účtování</a>
            <a href="historie.php" class="nav-link">📋 Historie</a>
        </div>
    </div>
    
    <!-- Statistiky pasta kuchyně -->
    <div class="pasta-stats" id="pastaStats">
        <div class="stat-item predkrm">
            <div class="stat-number" id="predkrmCount">0</div>
            <div class="stat-label">🥗 Předkrmy</div>
        </div>
        <div class="stat-item pasta">
            <div class="stat-number" id="pastaWaitingCount">0</div>
            <div class="stat-label">🍝 Pasta čeká</div>
        </div>
        <div class="stat-item pasta-ready">
            <div class="stat-number" id="pastaReadyCount">0</div>
            <div class="stat-label">🍝 Pasta připravit!</div>
        </div>
        <div class="stat-item dezert">
            <div class="stat-number" id="dezertCount">0</div>
            <div class="stat-label">🍰 Dezerty</div>
        </div>
    </div>

    <div class="container">
        <!-- Předkrmy - připravovat ihned -->
        <div class="section-title predkrm">🥗 Předkrmy - Připravovat ihned</div>
        <div class="orders-grid" id="predkrmyContainer"></div>
        
        <!-- Pasta čeká na povolení -->
        <div class="section-title pasta-waiting">🍝 Pasta - Čeká na povolení z pizza kuchyně</div>
        <div class="orders-grid" id="pastaWaitingContainer"></div>
        
        <!-- Pasta připravovat nyní -->
        <div class="section-title pasta-ready">🍝 Pasta - PŘIPRAVOVAT NYNÍ!</div>
        <div class="orders-grid" id="pastaReadyContainer"></div>
        
        <!-- Dezerty -->
        <div class="section-title dezert">🍰 Dezerty - Připravit po hlavním chodu</div>
        <div class="orders-grid" id="dezertyContainer"></div>
        
        <div class="last-update">Poslední aktualizace: <span id="lastUpdate">-</span></div>
    </div>

    <script>
        const API = 'api/restaurant-api.php';
        let items = [];
        
        // Logout function
        function logout() {
            if (confirm('Opravdu se chcete odhlásit?')) {
                window.location.href = 'login.php?logout=1';
            }
        }

        function markReady(id, name, btn) {
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Označuji...';

                console.log(`🍝 Marking pasta item ${id} as ready...`);
                fetch(API + '?action=item-status', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        item_id: id,
                        status: 'ready'
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(result => {
                    console.log('📦 Mark ready response:', result);
                    if (result.success) {
                        // Odstraň z DOM až po úspěšném API volání
                        const itemElement = btn.closest('.order-item');
                        if (itemElement) {
                            itemElement.remove();
                            items = items.filter(item => item.id !== id);
                            sortItems();
                            renderOrders();
                        }
                    } else {
                        console.error('❌ API error:', result.error);
                        btn.disabled = false;
                        btn.textContent = 'Hotovo';
                    }
                })
                .catch(error => {
                    console.error('❌ Network Error:', error);
                    btn.disabled = false;
                    btn.textContent = 'Hotovo';
                });
            }
        }
function autoReleaseDesserts() {
    items.forEach(item => {
        if (item.item_type === 'dezert' && item.status === 'pending') {
            const orderItems = window.allOrderItems.filter(i => i.order_id === item.order_id);
            
            // Kontrola 1: Je hlavní chod (pizza/pasta) hotový?
            const mainCourseReady = orderItems.some(i => 
                ['pizza', 'pasta'].includes(i.item_type) && i.status === 'ready'
            );
            
            // Kontrola 2: Prošlo 15 minut od vytvoření objednávky?
            const orderTime = new Date(item.created_at);
            const now = new Date();
            const minutesPassed = Math.floor((now - orderTime) / (1000 * 60));
            const timeExpired = minutesPassed >= 15;
            
            console.log(`Dezert ${item.id}: mainCourseReady=${mainCourseReady}, timeExpired=${timeExpired} (${minutesPassed}min)`);
            
            // Pokud je hlavní chod hotový NEBO prošlo 15 minut
            if (mainCourseReady || timeExpired) {
                console.log(`🍰 Auto-releasing dessert ${item.id}`);
                autoReleaseItem(item.id, timeExpired ? 'Timer expired (15min)' : 'Main course ready');
            }
        }
    });
}

function autoReleaseItem(itemId, reason) {
    console.log(`🍰 Auto-releasing item ${itemId}: ${reason}`);
    fetch(API + '?action=item-status', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            item_id: itemId,
            status: 'preparing',
            note: `Auto-povoleno: ${reason}`
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(result => {
        console.log('📦 Auto-release response:', result);
        if (result.success) {
            console.log(`✅ Auto-released item ${itemId}: ${reason}`);
        } else {
            console.error(`❌ Failed to auto-release item ${itemId}:`, result.error);
        }
    })
    .catch(error => {
        console.error(`❌ Error auto-releasing item ${itemId}:`, error);
    });
}
function loadOrders() {
    console.log('🔄 Loading pasta kitchen orders...');
    fetch(API + '?action=pasta-kitchen-items')
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            }
            return r.json();
        })
        .then(res => {
            console.log('📦 Pasta Kitchen API response:', res);
            if (res.success) {
                const allItems = res.data.items;
                window.allOrderItems = allItems;
                
                const pastaItems = allItems.filter(item => 
                    ['pasta', 'predkrm', 'dezert'].includes(item.item_type)
                );
                
                items = pastaItems;
                console.log(`✅ Loaded ${pastaItems.length} pasta items`);
                
                // NOVÉ: Automaticky povol dezerty
                autoReleaseDesserts();
                
                updatePastaStats(pastaItems);
                sortItems();
                renderOrders();
            } else {
                console.error('❌ API error:', res.error);
                items = [];
                window.allOrderItems = [];
                updatePastaStats([]);
            }
            
            updateLastUpdateTime();
        })
        .catch(error => {
            console.error('❌ Network Error:', error);
            items = [];
            window.allOrderItems = [];
            updatePastaStats([]);
            updateLastUpdateTime();
        });
}

        function sortItems() {
            items.sort((a, b) => {
                // Priorita: předkrmy > pasta ready > pasta waiting > dezerty
                const priorityOrder = { 'predkrm': 1, 'pasta': 2, 'dezert': 3 };
                
                if (priorityOrder[a.item_type] !== priorityOrder[b.item_type]) {
                    return priorityOrder[a.item_type] - priorityOrder[b.item_type];
                }
                
                return new Date(a.created_at) - new Date(b.created_at);
            });
        }

        // NAHRAĎ FUNKCI updatePastaStats (kolem řádku 180):
function updatePastaStats(pastaItems) {
    let predkrmCount = 0;
    let pastaWaitingCount = 0;
    let pastaReadyCount = 0;
    let dezertCount = 0;
    
    if (pastaItems && pastaItems.length > 0) {
        pastaItems.forEach(item => {
            const quantity = parseInt(item.quantity) || 1;
            
            switch(item.item_type) {
                case 'predkrm':
                    predkrmCount += quantity;
                    break;
                case 'pasta':
                    // STEJNÁ LOGIKA jako v renderOrders
                    if (item.status === 'pending' && hasOrderPizza(item.order_id)) {
                        pastaWaitingCount += quantity; // S pizzou = čeká
                    } else {
                        pastaReadyCount += quantity; // Bez pizzy nebo už povoleno = ready
                    }
                    break;
                case 'dezert':
                    dezertCount += quantity;
                    break;
            }
        });
    }
    
    // Aktualizuj DOM
    const predkrmEl = document.getElementById('predkrmCount');
    const pastaWaitingEl = document.getElementById('pastaWaitingCount');
    const pastaReadyEl = document.getElementById('pastaReadyCount');
    const dezertEl = document.getElementById('dezertCount');
    
    if (predkrmEl) predkrmEl.textContent = predkrmCount;
    if (pastaWaitingEl) pastaWaitingEl.textContent = pastaWaitingCount;
    if (pastaReadyEl) pastaReadyEl.textContent = pastaReadyCount;
    if (dezertEl) dezertEl.textContent = dezertCount;
}

// ZMĚŇ funkci hasOrderPizza() na jednoduchou logiku:
function hasOrderPizza(orderId) {
    // JEDNODUCHÁ LOGIKA: Pokud order_id obsahuje více než jen pasta položky
    // nebo podle čísla objednávky
    
    // Možnost 1: Podle čísla objednávky (P25+ má pizzu, P24- nemá)
    const orderNumber = parseInt(orderId);
    const hasPizza = orderNumber >= 25; // Adjustuj podle potřeby
    
    console.log(`Order ${orderId}: has pizza = ${hasPizza} (number-based logic)`);
    return hasPizza;
    
    // NEBO Možnost 2: Podle počtu položek (více položek = má pizzu)
    /*
    const orderItems = items.filter(item => item.order_id === orderId);
    const hasPizza = orderItems.length > 1; // Více než 1 položka = má pizzu
    
    console.log(`Order ${orderId}: has pizza = ${hasPizza} (count-based logic)`, {
        itemCount: orderItems.length,
        items: orderItems.map(i => i.item_name)
    });
    return hasPizza;
    */
}

        // OPRAVENO - změň řádky kolem 300:
function renderOrders() {
    const predkrmyContainer = document.getElementById('predkrmyContainer');
    const pastaWaitingContainer = document.getElementById('pastaWaitingContainer');
    const pastaReadyContainer = document.getElementById('pastaReadyContainer');
    const dezertyContainer = document.getElementById('dezertyContainer');
    
    // Rozdělení položek podle typu a stavu
    const predkrmy = items.filter(item => item.item_type === 'predkrm');
    
    // KLÍČOVÁ LOGIKA pro pasta:
    const pastaWaiting = items.filter(item => 
        item.item_type === 'pasta' && 
        item.status === 'pending' && 
        hasOrderPizza(item.order_id)  // Má pizzu = čeká na povolení
    );
    
    const pastaReady = items.filter(item => 
        item.item_type === 'pasta' && 
        (item.status === 'preparing' || // Už povoleno
         (item.status === 'pending' && !hasOrderPizza(item.order_id))) // Bez pizzy = rovnou ready
    );
    
    const dezerty = items.filter(item => item.item_type === 'dezert');
    
    // Debug info (můžeš později smazat)
    console.log('Pasta waiting:', pastaWaiting.length, 'Pasta ready:', pastaReady.length);
    
    // Renderování sekcí...
    renderSection(predkrmyContainer, predkrmy, 'predkrm', {
        emptyIcon: '🥗',
        emptyMessage: 'Žádné předkrmy k přípravě'
    });
    renderSection(pastaWaitingContainer, pastaWaiting, 'pasta-waiting', {
        emptyIcon: '🍝',
        emptyMessage: 'Žádné pasta čekající na povolení'
    });
    renderSection(pastaReadyContainer, pastaReady, 'pasta-ready', {
        emptyIcon: '🍝',
        emptyMessage: 'Žádné pasta k přípravě'
    });
    renderSection(dezertyContainer, dezerty, 'dezert', {
        emptyIcon: '🍰',
        emptyMessage: 'Žádné dezerty'
    });
}

        // NAHRAĎ CELOU FUNKCI renderSection (kolem řádku 350):
function renderSection(container, items, itemType, options = {}) {
    if (items.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="icon">${options.emptyIcon || '🍽️'}</div>
                <h3>${options.emptyMessage || 'Žádné položky'}</h3>
            </div>
        `;
        return;
    }

    // Seskupení podle stolů
    const grouped = {};
    items.forEach(item => {
        const table = item.table_code || item.table_number || 'Neznámý';
        if (!grouped[table]) grouped[table] = [];
        grouped[table].push(item);
    });

    container.innerHTML = Object.entries(grouped).map(([table, tableItems]) => {
        const oldest = tableItems.reduce((a, b) => a.id < b.id ? a : b);
        
        let minutesAgo = 0;
        if (oldest.created_at) {
            const orderTime = new Date(oldest.created_at);
            const currentTime = new Date();
            minutesAgo = Math.floor((currentTime - orderTime) / (1000 * 60));
        }

        const isUrgent = minutesAgo > 20;
        const hasWaiting = tableItems.some(item => item.status === 'pending');
        const hasReady = tableItems.some(item => item.status === 'preparing');

        let cardClass = 'order-card';
        if (itemType === 'pasta-waiting') cardClass += ' waiting';
        else if (itemType === 'pasta-ready') cardClass += ' ready';
        else if (itemType === 'dezert') cardClass += ' dessert';

        // Synchronizační info
let syncInfo = '';
if (itemType === 'pasta-waiting') {
    syncInfo = `<div class="sync-info">⏳ Čeká na povolení z pizza kuchyně</div>`;
} else if (itemType === 'pasta-ready') {
    syncInfo = `<div class="sync-info ready">✅ Povoleno - můžete začít vařit!</div>`;
} else if (itemType === 'dezert') {
    // PŘESUNUTÉ: Dezert info
    const orderTime = new Date(oldest.created_at);
    const now = new Date();
    const minutesPassed = Math.floor((now - orderTime) / (1000 * 60));
    const remainingMinutes = Math.max(0, 15 - minutesPassed);
    
    if (hasReady) {
        syncInfo = `<div class="sync-info ready">✅ Hlavní chod hotový - připravte dezert!</div>`;
    } else if (remainingMinutes > 0) {
        syncInfo = `<div class="sync-info">⏱️ Dezert za ${remainingMinutes} min nebo po hlavním chodu</div>`;
    } else {
        syncInfo = `<div class="sync-info ready">⏰ 15 minut uplynulo - můžete připravit!</div>`;
    }
}

        const itemsHTML = tableItems.map(item => {
            const name = item.item_name || 'Položka';
            const quantity = item.quantity || 1;
            const note = item.note || '';

            let actionsHTML = '';

if (itemType === 'pasta-waiting' && item.status === 'pending') {
    actionsHTML = `
        <div class="item-actions">
            <button class="btn btn-release" onclick="releaseItem(${item.id}, '${name}', this)">
                Povolit přípravu
            </button>
        </div>
    `;
} else if (itemType === 'pasta-ready' || itemType === 'predkrm') {
    actionsHTML = `
        <div class="item-actions">
            <button class="btn btn-ready" onclick="markReady(${item.id}, '${name}', this)">
                Hotovo
            </button>
        </div>
    `;
} else if (itemType === 'dezert') {
    // OPRAVA: Kontrola statusu pro dezerty + tlačítka
    if (item.status === 'preparing') {
        // Dezert je povolen - můžeme připravit
        actionsHTML = `
            <div class="item-actions">
                <button class="btn btn-ready" onclick="markReady(${item.id}, '${name}', this)">
                    Hotovo
                </button>
            </div>
        `;
    } else {
        // Dezert čeká na hlavní chod
        actionsHTML = `
            <div class="item-actions">
                <button class="btn btn-waiting" disabled>Čeká na hlavní chod</button>
            </div>
        `;
    }
}

            return `
                <div class="order-item ${itemType.replace('-waiting', '').replace('-ready', '')}">
                    <div class="item-name">${name}</div>
                    <div class="item-quantity">${quantity}×</div>
                    ${note ? `<div class="item-note">${note}</div>` : ''}
                    ${actionsHTML}
                </div>
            `;
        }).join('');

        return `
            <div class="${cardClass}">
                <div class="order-header">
                    <div class="table-number">🍽️ ${table}</div>
                    <div class="order-time">⏱️ ${minutesAgo} min</div>
                </div>
                ${syncInfo}
                <div class="order-items">${itemsHTML}</div>
            </div>
        `;
    }).join('');
}

        function getEmptyIcon(sectionType) {
            switch(sectionType) {
                case 'predkrm': return '🥗';
                case 'pasta-waiting': case 'pasta-ready': return '🍝';
                case 'dezert': return '🍰';
                default: return '🍽️';
            }
        }

// Povolit položku k přípravě
function releaseItem(id, name, btn) {
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Povoluje...';
    }

    fetch(API + '?action=item-status', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            item_id: id,
            status: 'preparing',
            note: 'Manuálně povoleno k přípravě'
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadOrders(); // Refresh
        } else {
            console.error('Chyba při povolování položky:', result.error);
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Povolit přípravu';
            }
        }
    })
    .catch(error => {
        console.error('Chyba při povolování položky:', error);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Povolit přípravu';
        }
    });
}

        function getStatusBadge(sectionType, minutesAgo) {
            switch(sectionType) {
                case 'predkrm':
                    return minutesAgo > 10 ? '<span class="status-badge">PRIORITA</span>' : '';
                case 'pasta-waiting':
                    return '<span class="status-badge">ČEKÁ</span>';
                case 'pasta-ready':
                    return '<span class="status-badge ready">PŘIPRAVIT!</span>';
                case 'dezert':
                    return '<span class="status-badge dessert-time">POZDĚJI</span>';
                default:
                    return '';
            }
        }

        function getCardClass(sectionType) {
            switch(sectionType) {
                case 'pasta-waiting': return 'waiting';
                case 'pasta-ready': return 'ready';
                case 'dezert': return 'dessert';
                default: return '';
            }
        }

        function getActionButton(item, sectionType, name) {
            switch(sectionType) {
                case 'predkrm':
                case 'pasta-ready':
                    return `<button class="btn btn-ready" onclick="markReady(${item.id}, '${name}', this)">Hotovo</button>`;
                case 'pasta-waiting':
                    return `<button class="btn btn-waiting" disabled>Čeká na povolení</button>`;
                case 'dezert':
                    return `<button class="btn btn-waiting" disabled>Čeká na hlavní chod</button>`;
                default:
                    return '';
            }
        }

        function getSyncInfo(sectionType) {
            switch(sectionType) {
                case 'pasta-waiting':
                    return '<div class="sync-info">⏳ Čeká na povolení z pizza kuchyně. Pasta se připravuje současně s pizzou.</div>';
                case 'pasta-ready':
                    return '<div class="sync-info">🔥 Pizza je hotová! Pasta může být nyní připravena.</div>';
                case 'dezert':
                    return '<div class="sync-info">🍽️ Dezert se připravuje až po dokončení hlavního chodu.</div>';
                default:
                    return '';
            }
        }

        function updateLastUpdateTime() {
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('cs-CZ');
        }

        // Inicializace
        loadOrders();
        setInterval(loadOrders, 3000); // Častější refresh kvůli synchronizaci
    </script>
</body>
</html>