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
    <title>Servírování - Vydání objednávek</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f953c6 0%, #b91d73 100%); min-height: 100vh; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: white; font-size: 2.5em; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);}
        .nav-links { display: flex; justify-content: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .nav-link { padding: 10px 20px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 25px; font-weight: 500; transition: all 0.3s ease;}
        .nav-link:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px);}
        .nav-link.active { background: white; color: #b91d73; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);}
        .section-title { font-size: 1.6em; color: #b91d73; margin: 25px 0 10px 0; font-weight: bold; }
        .orders-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; margin-bottom: 30px;}
        .order-card { border: 3px solid #f953c6; border-radius: 15px; padding: 20px; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.07); transition: transform 0.3s ease;}
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee;}
        .table-number { font-size: 1.3em; font-weight: bold; color: #b91d73; display: flex; align-items: center; gap: 10px;}
        .order-time { color: #666; font-size: 0.95em;}
        .order-items { margin-bottom: 20px; }
        .order-item { background: #fdf1fa; border-radius: 8px; border-left: 4px solid #f953c6; padding: 12px 15px; margin-bottom: 9px;}
        .item-name { font-weight: 600; color: #333; margin-bottom: 3px;}
        .item-quantity { color: #666; font-size: 0.93em; margin-bottom: 3px;}
        .item-note { background: #fbeff6; border: 1px solid #fbb1e4; border-radius: 6px; padding: 6px 10px; margin-top: 4px; font-size: 0.95em; color: #b91d73; font-style: italic; line-height: 1.3;}
        .item-actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 7px;}
        .btn { padding: 8px 16px; border: none; border-radius: 8px; font-weight: bold; font-size: 14px; cursor: pointer; transition: all 0.3s ease;}
        .btn-ready { background: #b91d73; color: white;}
        .btn-ready:hover { background: #f953c6; }
        .btn-reklamace { background: #ff5858; color: white; }
        .btn-reklamace:hover { background: #b91d73; }
        .last-update { text-align: center; color: #666; font-size: 0.95em; margin-top: 15px;}
        .empty-state { text-align: center; padding: 60px 20px; color: #999;}
        .empty-state .icon { font-size: 4em; margin-bottom: 20px; display: block;}
        .toast { position: fixed; bottom: 30px; right: 30px; background: #b91d73; color: #fff; padding: 16px 30px; font-size: 1.1em; border-radius: 8px; box-shadow: 0 6px 24px rgba(0,0,0,0.2); opacity: 0.96; z-index: 1000; animation: fadeIn 0.3s;}
        @keyframes fadeIn { from { opacity: 0; transform: translateY(40px);} to { opacity: 0.96; transform: none;} }
        @media (max-width: 700px) { .orders-grid { grid-template-columns: 1fr; } .container { padding: 10px; } }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="login.php" style="background: rgba(255,255,255,0.9); color: #b91d73; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: all 0.3s ease;">← ZPĚT NA PŘIHLÁŠENÍ</a>
        </div>
        <div style="color: white; font-size: 14px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
            Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
            <button onclick="logout()" style="background: rgba(255,255,255,0.9); color: #b91d73; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; margin-left: 15px; cursor: pointer;">🚪 Odhlásit se</button>
        </div>
    </div>
    
    <div class="header">
        <h1>🍽️ Servírování</h1>
        <div class="nav-links">
            <a href="index.php" class="nav-link" id="nav-obsluha">🏠 Objednávky</a>
            <a href="kitchen.php" class="nav-link" id="nav-kuchyn">👨‍🍳 Kuchyň</a>
<a href="pasta-kuchyn.php" class="nav-link " id="nav-pasta">🍝 Pasta kuchyň</a>
            <a href="bar.php" class="nav-link" id="nav-bar">🍺 Bar</a>
            <a href="serving.php" class="nav-link active" id="nav-servirovani">🍽️ Servírování</a>
            <a href="billing.php" class="nav-link" id="nav-uctovani">💰 Účtování</a>
           <a href="historie.php" class="nav-link">📋 Historie</a>

        </div>
    </div>
    <div class="container">
        <div class="section-title">Vydávejte hotové objednávky</div>
        <div class="orders-grid" id="ordersContainer"></div>
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
    
    function showToast(msg, color='#b91d73') {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.background = color;
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(()=>{ toast.style.opacity = '0'; setTimeout(()=>toast.remove(), 400); }, 2600);
    }
    function loadOrders() {
        fetch(API + '?action=ready-items')
            .then(r=>r.json())
            .then(res => {
                items = res.success ? res.data.items : [];
                renderOrders();
                updateLastUpdateTime();
            })
            .catch(()=>{
                showToast('Chyba načítání objednávek', '#e74c3c');
            });
    }
    function markDelivered(id, name, btn) {
        if (btn) { btn.disabled = true; btn.textContent = 'Označuji...'; }
        fetch(API + '?action=item-status', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({item_id:id, status:'delivered'})
        })
        .then(r=>r.json())
        .then(res=>{
            if(res.success){
                showToast(`Vydáno: ${name}`);
            } else {
                showToast(res.error || 'Chyba při označení', '#e74c3c');
            }
            loadOrders();
        })
        .catch(()=>{
            showToast('Chyba API', '#e74c3c');
            loadOrders();
        });
    }
   function returnToKitchen(id, name, btn) {
    if (btn) { btn.disabled = true; btn.textContent = 'Reklamuji...'; }
    
    const data = {item_id:id, status:'pending', note: 'Spalena'};
    console.log('Sending burnt pizza data:', data); // DEBUG
    
    fetch(API + '?action=item-status', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.success){
            showToast(`Vráceno do kuchyně: ${name}`, '#ff5858');
        } else {
            showToast(res.error || 'Chyba při reklamaci', '#e74c3c');
        }
        loadOrders();
    })
    .catch(()=>{
        showToast('Chyba API', '#e74c3c');
        loadOrders();
    });
}
    function renderOrders() {
        const container = document.getElementById('ordersContainer');
        if (items.length === 0) {
            container.innerHTML = `<div class="empty-state">
                <span class="icon">🍽️</span>
                <h3>Žádné hotové položky</h3>
                <p>Momentálně nejsou žádné objednávky k vydání</p>
            </div>`;
            return;
        }
        // Seskupit podle stolů
      const grouped = {};
    items.forEach(order => {
        // Zde používáme table_code místo table_number
        const table = order.table_code || order.table_number || 'Neznámý';
        if (!grouped[table]) grouped[table] = [];
        grouped[table].push(order);
    });
        container.innerHTML = Object.entries(grouped).map(([table, items]) => {
            const oldest = items.reduce((a, b) => {
                if(a.created_at && b.created_at) return a.created_at < b.created_at ? a : b;
                return a.id < b.id ? a : b;
            });
            const minutesAgo = Math.floor((Date.now() - new Date(oldest.created_at || oldest.id)) / 60000);
            const itemsHTML = items.map(item => {
                const name = item.item_name || item.name || 'Položka';
                const quantity = item.quantity || 1;
                const note = item.note || '';
                return `
                    <div class="order-item">
                        <div class="item-name">${name}</div>
                        <div class="item-quantity">${quantity}×</div>
                        ${note ? `<div class="item-note">${note}</div>` : ''}
                        <div class="item-actions">
                            <button class="btn btn-ready" onclick="markDelivered(${item.id}, '${name.replace(/'/g, "\\'")}', this)">
                                Vydáno
                            </button>
                            <button class="btn btn-reklamace" onclick="returnToKitchen(${item.id}, '${name.replace(/'/g, "\\'")}', this)">
                                Vrátit do kuchyně
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
            return `
                <div class="order-card">
                    <div class="order-header">
                        <div class="table-number">🍽️ Stůl ${table}</div>
                        <div class="order-time">⏱️ ${minutesAgo} min</div>
                    </div>
                    <div class="order-items">${itemsHTML}</div>
                </div>
            `;
        }).join('');
    }
    function updateLastUpdateTime() {
        document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('cs-CZ');
    }
    loadOrders();
    setInterval(loadOrders, 8000);
    </script>
</body>
</html>