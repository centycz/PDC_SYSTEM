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

// Check if user is admin
if (!$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizza Admin - Správa Menu</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            min-height: 100vh;
            padding: 20px;
            color: #2d3748;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .card {
            background: #f8fafc;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h1, .card h2 {
            color: #ee5a24;
            margin-bottom: 15px;
            text-align: center;
        }
        .nav-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .nav-links a {
            padding: 10px 20px;
            background: #5a67d8;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.18s;
        }
        .nav-links a:hover {
            background: #3c366b;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1em;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn {
            background: #5a67d8;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.18s;
        }
        .btn:hover {
            background: #3c366b;
        }
        .btn.btn-success {
            background: #38a169;
        }
        .btn.btn-success:hover {
            background: #276749;
        }
        .btn.btn-warning {
            background: #d69e2e;
        }
        .btn.btn-warning:hover {
            background: #b7791f;
        }
        .btn.btn-danger {
            background: #e53e3e;
        }
        .btn.btn-danger:hover {
            background: #c53030;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        .alert-success {
            background: #e6fffa;
            color: #234e52;
        }
        .alert-error {
            background: #fff5f5;
            color: #c53030;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        code.code-field {
            background-color: #f0f0f0;
            padding: 4px 6px;
            border-radius: 5px;
            font-family: monospace;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            width: 80%;
            max-width: 600px;
        }
        .close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 20px;
            cursor: pointer;
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        .loading {
            text-align: center;
            font-style: italic;
            color: #888;
        }
        
        /* NOVÉ STYLY PRO FOOD COST */
        .cost-info {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 5px;
            padding: 8px 12px;
            margin-top: 5px;
            font-size: 0.9em;
        }
        
        .margin-good {
            color: #28a745;
            font-weight: bold;
        }
        
        .margin-warning {
            color: #ffc107;
            font-weight: bold;
        }
        
        .margin-danger {
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="index.php" style="background: rgba(255,255,255,0.9); color: #ee5a24; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: all 0.3s ease;">← ZPĚT NA HLAVNÍ STRÁNKU</a>
        </div>
        <div style="color: white; font-size: 14px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
            Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
            <button onclick="logout()" style="background: rgba(255,255,255,0.9); color: #ee5a24; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; margin-left: 15px; cursor: pointer;">🚪 Odhlásit se</button>
        </div>
    </div>
    
    <div class="container">
        <div id="alerts"></div>
        <div class="card">
            <h1>Food Admin - Správa Menu</h1>
            <div class="nav-links">
                <a href="index.php">Domů</a>
                <a href="bar-admin.html">Bar Admin</a>
                <a href="data.php">Statistiky</a>
            </div>
        </div>
        
        <div class="card">
            <h2>Přidat Novou Položku</h2>
            <form id="addPizzaForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategorie</label>
                        <select name="category" required>
                            <option value="">Vyberte kategorii</option>
                            <option value="predkrm">Předkrm</option>
                            <option value="pizza">Pizza</option>
                            <option value="pasta">Pasta</option>
                            <option value="dezert">Dezert</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Typ (klíč)</label>
                        <input type="text" name="type" placeholder="např. margherita" required>
                        <small style="color: #666;">Jedinečný identifikátor - pouze malá písmena a čísla</small>
                    </div>
                    <div class="form-group">
                        <label>Název</label>
                        <input type="text" name="name" placeholder="např. Pizza Margherita" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Popis</label>
                    <textarea name="description" placeholder="Popis položky a ingrediencí"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>💰 Prodejní cena (Kč)</label>
                        <input type="number" name="price" placeholder="189" required step="0.01" min="0" oninput="calculateMargin()">
                    </div>
                    <div class="form-group">
                        <label>🥘 Nákladová cena (Kč)</label>
                        <input type="number" name="cost_price" placeholder="65" step="0.01" min="0" oninput="calculateMargin()">
                        <small style="color: #666;">Cena surovin a přípravy</small>
                    </div>
                    <div class="form-group">
                        <label>Stav</label>
                        <select name="is_active">
                            <option value="1">Aktivní</option>
                            <option value="0">Neaktivní</option>
                        </select>
                    </div>
                </div>
                
                <!-- FOOD COST KALKULÁTOR -->
                <div class="cost-info" id="marginCalculator" style="display: none;">
                    <strong>💹 Food Cost Analýza:</strong><br>
                    <span id="marginInfo">Vyplňte prodejní a nákladovou cenu</span>
                </div>
                
                <button type="submit" class="btn btn-success">Přidat Položku</button>
            </form>
        </div>

        <div class="card">
            <h2>Seznam Položek</h2>
            <button onclick="loadPizzas()" class="btn">Obnovit seznam</button>
            <div id="pizzaList" class="loading">Načítám...</div>
        </div>
    </div>

    <!-- Modal pro editaci -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Upravit Položku</h2>
            <form id="editPizzaForm">
                <input type="hidden" name="editType" id="editType">
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategorie</label>
                        <select name="category" id="editCategory" required>
                            <option value="">Vyberte kategorii</option>
                            <option value="predkrm">Předkrm</option>
                            <option value="pizza">Pizza</option>
                            <option value="pasta">Pasta</option>
                            <option value="dezert">Dezert</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Typ (klíč)</label>
                        <input type="text" name="type" id="editTypeField" readonly class="code-field">
                        <small style="color: #666;">Typ nelze měnit</small>
                    </div>
                    <div class="form-group">
                        <label>Název</label>
                        <input type="text" name="name" id="editName" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Popis</label>
                    <textarea name="description" id="editDescription"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>💰 Prodejní cena (Kč)</label>
                        <input type="number" name="price" id="editPrice" required step="0.01" min="0" oninput="calculateEditMargin()">
                    </div>
                    <div class="form-group">
                        <label>🥘 Nákladová cena (Kč)</label>
                        <input type="number" name="cost_price" id="editCostPrice" step="0.01" min="0" oninput="calculateEditMargin()">
                        <small style="color: #666;">Cena surovin a přípravy</small>
                    </div>
                    <div class="form-group">
                        <label>Stav</label>
                        <select name="is_active" id="editActive">
                            <option value="1">Aktivní</option>
                            <option value="0">Neaktivní</option>
                        </select>
                    </div>
                </div>
                
                <!-- FOOD COST KALKULÁTOR PRO EDITACI -->
                <div class="cost-info" id="editMarginCalculator" style="display: none;">
                    <strong>💹 Food Cost Analýza:</strong><br>
                    <span id="editMarginInfo">Vyplňte prodejní a nákladovou cenu</span>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">Uložit změny</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Zrušit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = 'api/restaurant-api.php';
        let pizzas = [];
        
        // Logout function
        function logout() {
            if (confirm('Opravdu se chcete odhlásit?')) {
                window.location.href = 'login.php?logout=1';
            }
        }

        // NOVÉ FUNKCE PRO FOOD COST KALKULACI
        function calculateMargin() {
            const price = parseFloat(document.querySelector('input[name="price"]').value) || 0;
            const costPrice = parseFloat(document.querySelector('input[name="cost_price"]').value) || 0;
            const calculator = document.getElementById('marginCalculator');
            const info = document.getElementById('marginInfo');
            
            if (price > 0 && costPrice >= 0) {
                calculator.style.display = 'block';
                const margin = price - costPrice;
                const marginPercent = price > 0 ? ((margin / price) * 100) : 0;
                const foodCostPercent = price > 0 ? ((costPrice / price) * 100) : 0;
                
                let marginClass = 'margin-good';
                let status = '✅ Výborná marže';
                if (foodCostPercent > 35) {
                    marginClass = 'margin-danger';
                    status = '❌ Vysoký food cost';
                } else if (foodCostPercent > 25) {
                    marginClass = 'margin-warning';
                    status = '⚠️ Střední food cost';
                }
                
                info.innerHTML = `
                    <span class="${marginClass}">${status}</span><br>
                    Marže: <strong>${margin.toFixed(2)} Kč</strong> (${marginPercent.toFixed(1)}%)<br>
                    Food cost: <strong>${foodCostPercent.toFixed(1)}%</strong>
                `;
            } else {
                calculator.style.display = 'none';
            }
        }
        
        function calculateEditMargin() {
            const price = parseFloat(document.getElementById('editPrice').value) || 0;
            const costPrice = parseFloat(document.getElementById('editCostPrice').value) || 0;
            const calculator = document.getElementById('editMarginCalculator');
            const info = document.getElementById('editMarginInfo');
            
            if (price > 0 && costPrice >= 0) {
                calculator.style.display = 'block';
                const margin = price - costPrice;
                const marginPercent = price > 0 ? ((margin / price) * 100) : 0;
                const foodCostPercent = price > 0 ? ((costPrice / price) * 100) : 0;
                
                let marginClass = 'margin-good';
                let status = '✅ Výborná marže';
                if (foodCostPercent > 35) {
                    marginClass = 'margin-danger';
                    status = '❌ Vysoký food cost';
                } else if (foodCostPercent > 25) {
                    marginClass = 'margin-warning';
                    status = '⚠️ Střední food cost';
                }
                
                info.innerHTML = `
                    <span class="${marginClass}">${status}</span><br>
                    Marže: <strong>${margin.toFixed(2)} Kč</strong> (${marginPercent.toFixed(1)}%)<br>
                    Food cost: <strong>${foodCostPercent.toFixed(1)}%</strong>
                `;
            } else {
                calculator.style.display = 'none';
            }
        }

        function showAlert(message, type) {
            const alertsContainer = document.getElementById('alerts');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertsContainer.appendChild(alert);
            setTimeout(() => { if (alert.parentNode) alert.remove(); }, 5000);
        }

        async function loadPizzas() {
            try {
                const response = await fetch(`${API_BASE}?action=pizza-menu-admin`);
                const data = await response.json();
                if (data.success) {
                    pizzas = data.data.pizzas || [];
                } else {
                    showAlert(data.error || 'Chyba při načítání', 'error');
                }
            } catch (e) {
                showAlert('Chyba při načítání: ' + e.message, 'error');
            } finally {
                renderPizzaList();
            }
        }

        function renderPizzaList() {
            const container = document.getElementById('pizzaList');
            if (pizzas.length === 0) {
                container.innerHTML = '<p>Žádné položky v nabídce</p>';
                return;
            }
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Kategorie</th>
                            <th>Typ</th>
                            <th>Název</th>
                            <th>Popis</th>
                            <th>💰 Cena</th>
                            <th>🥘 Náklady</th>
                            <th>💹 Marže</th>
                            <th>📊 Food Cost</th>
                            <th>Stav</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            pizzas.forEach(pizza => {
                const isActive = pizza.is_active == 1;
                const statusClass = isActive ? 'status-active' : 'status-inactive';
                const statusText = isActive ? 'AKTIVNÍ' : 'NEAKTIVNÍ';
                const description = pizza.description ? pizza.description.substring(0, 50) + (pizza.description.length > 50 ? '...' : '') : '-';
                
                // FOOD COST KALKULACE
                const price = parseFloat(pizza.price) || 0;
                const costPrice = parseFloat(pizza.cost_price) || 0;
                const margin = price - costPrice;
                const marginPercent = price > 0 ? ((margin / price) * 100) : 0;
                const foodCostPercent = price > 0 ? ((costPrice / price) * 100) : 0;
                
                let foodCostClass = 'margin-good';
                if (foodCostPercent > 35) {
                    foodCostClass = 'margin-danger';
                } else if (foodCostPercent > 25) {
                    foodCostClass = 'margin-warning';
                }
                
                html += `
                    <tr>
                        <td><strong>${pizza.category || 'Pizza'}</strong></td>
                        <td><code class="code-field">${pizza.type}</code></td>
                        <td><strong>${pizza.name}</strong></td>
                        <td title="${pizza.description || ''}">${description}</td>
                        <td><strong>${price.toFixed(2)} Kč</strong></td>
                        <td><strong>${costPrice.toFixed(2)} Kč</strong></td>
                        <td><strong class="${foodCostClass}">${margin.toFixed(2)} Kč</strong><br>
                            <small>(${marginPercent.toFixed(1)}%)</small></td>
                        <td><strong class="${foodCostClass}">${foodCostPercent.toFixed(1)}%</strong></td>
                        <td class="${statusClass}">${statusText}</td>
                        <td>
                            <button class="btn" onclick="editPizza('${pizza.type}')" title="Upravit položku">
                                Upravit
                            </button>
                            <button class="btn btn-warning" onclick="toggleStatus('${pizza.type}')" title="Změnit stav">
                                ${isActive ? 'Deaktivovat' : 'Aktivovat'}
                            </button>
                            <button class="btn btn-danger" onclick="deletePizza('${pizza.type}')" title="Smazat položku">
                                Smazat
                            </button>
                        </td>
                    </tr>
                `;
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Přidání pizzy
        document.getElementById('addPizzaForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = {
                type: formData.get('type').trim().toLowerCase(),
                name: formData.get('name').trim(),
                description: formData.get('description').trim(),
                price: parseFloat(formData.get('price')),
                cost_price: parseFloat(formData.get('cost_price')) || 0, // NOVÉ
                is_active: parseInt(formData.get('is_active')),
                category: formData.get('category')
            };
            if (!data.type.match(/^[a-z0-9_-]+$/)) {
                showAlert('Typ položky může obsahovat pouze malá písmena, číslice, pomlčky a podtržítka', 'error');
                return;
            }
            try {
                const response = await fetch(`${API_BASE}?action=add-pizza`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('Položka byla úspěšně přidána!', 'success');
                    e.target.reset();
                    document.getElementById('marginCalculator').style.display = 'none';
                    await loadPizzas();
                } else {
                    showAlert('Chyba: ' + (result.error || 'Neznámá chyba'), 'error');
                }
            } catch (error) {
                showAlert('Chyba: ' + error.message, 'error');
            }
        });

        // Otevření modalu pro editaci
        function editPizza(type) {
            const pizza = pizzas.find(p => p.type === type);
            if (!pizza) { showAlert('Položka nebyla nalezena', 'error'); return; }
            document.getElementById('editType').value = pizza.type;
            document.getElementById('editTypeField').value = pizza.type;
            document.getElementById('editName').value = pizza.name;
            document.getElementById('editDescription').value = pizza.description || '';
            document.getElementById('editPrice').value = pizza.price;
            document.getElementById('editCostPrice').value = pizza.cost_price || 0; // NOVÉ
            document.getElementById('editActive').value = pizza.is_active;
            document.getElementById('editCategory').value = pizza.category || 'pizza';
            calculateEditMargin(); // Spočítat marži
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() { 
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editMarginCalculator').style.display = 'none';
        }

        // Uložení editace
        document.getElementById('editPizzaForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const type = formData.get('editType');
            const data = {
                name: formData.get('name').trim(),
                description: formData.get('description').trim(),
                price: parseFloat(formData.get('price')),
                cost_price: parseFloat(formData.get('cost_price')) || 0, // NOVÉ
                is_active: parseInt(formData.get('is_active')),
                category: formData.get('category')
            };
            try {
                const response = await fetch(`${API_BASE}?action=edit-pizza&type=${encodeURIComponent(type)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('Položka byla úspěšně upravena!', 'success');
                    closeEditModal();
                    await loadPizzas();
                } else {
                    showAlert('Chyba: ' + (result.error || 'Neznámá chyba'), 'error');
                }
            } catch (error) {
                showAlert('Chyba: ' + error.message, 'error');
            }
        });

        async function toggleStatus(type) {
            console.log('toggleStatus called for type:', type);
            try {
                const response = await fetch(`${API_BASE}?action=toggle-pizza&type=${encodeURIComponent(type)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();

                if (!result.success) {
                    showAlert('Chyba při změně stavu pizzy: ' + (result.error || 'Neznámá chyba'), 'error');
                }

                loadPizzas();
            } catch (error) {
                showAlert('Chyba při změně stavu pizzy: ' + error.message, 'error');
                loadPizzas();
            }
        }
        
        async function deletePizza(type) {
            const pizza = pizzas.find(p => p.type === type);
            const pizzaName = pizza ? pizza.name : type;
            if (!confirm(`Opravdu chcete smazat pizzu "${pizzaName}"?\n\nTato akce je nevratná.`)) return;
            try {
                const response = await fetch(`${API_BASE}?action=delete-pizza&type=${encodeURIComponent(type)}`, {
                    method: 'DELETE'
                });
                loadPizzas();
            } catch (error) {
                loadPizzas();
            }
        }

        // Event listeners
        document.querySelector('.close').addEventListener('click', closeEditModal);
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) closeEditModal();
        });

        document.addEventListener('DOMContentLoaded', function() { loadPizzas(); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEditModal();
            if (e.key === 'F5') { e.preventDefault(); loadPizzas(); }
        });
    </script>
</body>
</html>