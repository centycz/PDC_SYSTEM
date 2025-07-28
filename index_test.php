<?php
// index_test.php - Test version of index.php with mock database for demonstration
session_start();

// Mock user data for testing (simulating the actual database)
$mock_users = [
    'admin' => [
        'id' => 1,
        'username' => 'admin',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'full_name' => 'Administrator',
        'is_admin' => 1,
        'user_role' => 'admin'
    ],
    'ragazzi' => [
        'id' => 2,
        'username' => 'ragazzi',
        'password_hash' => password_hash('ragazzi123', PASSWORD_DEFAULT),
        'full_name' => 'Ragazzi Worker',
        'is_admin' => 0,
        'user_role' => 'ragazzi'
    ],
    'user' => [
        'id' => 3,
        'username' => 'user',
        'password_hash' => password_hash('user123', PASSWORD_DEFAULT),
        'full_name' => 'Regular User',
        'is_admin' => 0,
        'user_role' => 'user'
    ]
];

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index_test.php');
    exit;
}

// Handle login
$login_error = '';
if ($_POST['action'] ?? '' === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $user = $mock_users[$username] ?? null;
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['order_user'] = $user['username'];
            $_SESSION['order_user_id'] = $user['id'];
            $_SESSION['order_full_name'] = $user['full_name'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            $_SESSION['user_role'] = $user['user_role'];
            
            header('Location: index_test.php');
            exit;
        } else {
            $login_error = 'Nesprávné přihlašovací údaje!';
        }
    } else {
        $login_error = 'Vyplňte všechna pole!';
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['order_user']);
$user_role = $_SESSION['user_role'] ?? null;
$full_name = $_SESSION['order_full_name'] ?? '';

// Define tabs based on roles
function canAccessTab($tab, $user_role) {
    if (!$user_role) return false; // Not logged in
    
    $tab_permissions = [
        'restaurant' => ['admin', 'ragazzi', 'user'],
        'status' => ['admin', 'ragazzi', 'user'],
        'reservations' => ['admin', 'ragazzi', 'user'],
        'orders' => ['admin', 'ragazzi'],
        'shifts' => ['admin', 'ragazzi', 'user'],
        'payroll' => ['admin', 'ragazzi'],
        'finance' => ['admin', 'ragazzi'],  // New tab
        'statistics' => ['admin', 'ragazzi'],
        'phpmyadmin' => ['admin'] // Only admin
    ];
    
    return in_array($user_role, $tab_permissions[$tab] ?? []);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PdC Systém - Centrum aplikací</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .test-notice {
            background: rgba(255, 193, 7, 0.9);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            text-align: center;
            color: #856404;
            font-weight: 600;
            border: 2px solid #ffc107;
        }

        .user-info {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-welcome {
            color: #2c3e50;
            font-weight: 600;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
            text-decoration: none;
            color: white;
        }

        .server-status {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .login-section {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .login-section h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .login-btn:hover {
            background: #5a6fd8;
        }

        .login-error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }

        .test-credentials {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }

        .test-credentials h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .credential-item {
            margin-bottom: 8px;
            font-family: monospace;
            background: #f5f5f5;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .service-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            text-decoration: none;
            color: #333;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), var(--accent-color-light));
        }

        .service-card.pizza::before {
            --accent-color: #FF6B35;
            --accent-color-light: #F7931E;
        }

        .service-card.database::before {
            --accent-color: #4285F4;
            --accent-color-light: #34A853;
        }

        .service-card.system::before {
            --accent-color: #9C27B0;
            --accent-color-light: #E91E63;
        }

        .service-card.statistics::before {
            --accent-color: #FF5722;
            --accent-color-light: #FF9800;
        }

        .service-card.finance::before {
            --accent-color: #4CAF50;
            --accent-color-light: #8BC34A;
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .service-card h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .service-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .service-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }

        .status-online {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .footer {
            text-align: center;
            color: white;
            margin-top: 40px;
            opacity: 0.8;
        }

        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .info-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .info-item h4 {
            margin-bottom: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
        }

        .info-item p {
            font-size: 0.9rem;
            opacity: 0.95;
            color: #fff;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
            }

            .system-info {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🍓 PdC Systém</h1>
            <p>Centrum aplikací a služeb</p>
        </div>

        <!-- Test Notice -->
        <div class="test-notice">
            🧪 <strong>TEST REŽIM</strong> - Tato verze používá simulovanou databázi pro demonstraci funkcí
        </div>

        <!-- User Info (shown when logged in) -->
        <?php if ($is_logged_in): ?>
        <div class="user-info">
            <div class="user-welcome">
                🙋‍♂️ Vítejte, <strong><?= htmlspecialchars($full_name) ?></strong> 
                <span style="color: #666; font-size: 0.9rem;">(<?= ucfirst($user_role) ?>)</span>
            </div>
            <a href="index_test.php?logout=1" class="logout-btn">Odhlásit se</a>
        </div>
        <?php endif; ?>

        <!-- Login Section (shown when not logged in) -->
        <?php if (!$is_logged_in): ?>
        <div class="login-section">
            <h2>🔐 Přihlášení</h2>
            <?php if ($login_error): ?>
                <div class="login-error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            <form method="POST" class="login-form">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Uživatelské jméno:</label>
                    <input type="text" id="username" name="username" required placeholder="např. admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Heslo:</label>
                    <input type="password" id="password" name="password" required placeholder="Zadejte heslo">
                </div>
                <button type="submit" class="login-btn">🔓 Přihlásit se</button>
            </form>

            <div class="test-credentials">
                <h4>🔑 Testovací přihlašovací údaje:</h4>
                <div class="credential-item"><strong>Admin:</strong> admin / admin123</div>
                <div class="credential-item"><strong>Ragazzi:</strong> ragazzi / ragazzi123</div>
                <div class="credential-item"><strong>User:</strong> user / user123</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Server Status -->
        <div class="server-status">
            <h2><span class="status-indicator"></span>Stav serveru</h2>
            <p>Server běží správně a všechny služby jsou dostupné</p>
            
            <div class="system-info">
                <div class="info-item">
                    <h4>IP Adresa</h4>
                    <p>192.168.30.201</p>
                </div>
                <div class="info-item">
                    <h4>Systém</h4>
                    <p>Raspberry Pi OS</p>
                </div>
                <div class="info-item">
                    <h4>Web Server</h4>
                    <p>Apache + PHP</p>
                </div>
                <div class="info-item">
                    <h4>Databáze</h4>
                    <p>MySQL/MariaDB</p>
                </div>
            </div>
        </div>

        <!-- Services Grid -->
        <div class="services-grid">
            <!-- Pizza Application -->
            <?php if (canAccessTab('restaurant', $user_role)): ?>
            <a href="/pizza/" class="service-card pizza">
                <span class="service-icon">🍕</span>
                <h3>Restaurační systém</h3>
                <p>Kompletní restaurační systém pro objednávky, menu a správu. Včetně připojení k tiskárně pro účtenky.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Dashboard -->
            <?php if (canAccessTab('status', $user_role)): ?>
            <a href="/pizza/status_dashboard.php" class="service-card pizza">
                <span class="service-icon">🍽️</span>
                <h3>Aktuální stav</h3>
                <p>Zobrazuje aktuální stav objednávek, kolik zbýva kusů těsta a zbývající burratu.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Rezervace -->
            <?php if (canAccessTab('reservations', $user_role)): ?>
            <a href="/pizza/reservations.html" class="service-card pizza">
                <span class="service-icon">📅</span>
                <h3>Rezervace</h3>
                <p>Rezervační systém.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Objednavky -->
            <?php if (canAccessTab('orders', $user_role)): ?>
            <a href="/pizza/orders_system.php" class="service-card pizza">
                <span class="service-icon">🛒</span>
                <h3>Objednávky</h3>
                <p>Objednávkový systém pro příjetí objednávky.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Směny -->
            <?php if (canAccessTab('shifts', $user_role)): ?>
            <a href="/pizza/shifts_system.php" class="service-card pizza">
                <span class="service-icon">⏰</span>
                <h3>Směny</h3>
                <p>Výběr směn. ROZPRACOVÁNO</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Výplaty -->
            <?php if (canAccessTab('payroll', $user_role)): ?>
            <a href="/pizza/payroll_system.php" class="service-card pizza">
                <span class="service-icon">💰</span>
                <h3>Mzdy</h3>
                <p>Mzdy. ROZPRACOVÁNO</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- NEW: Financial Tracking -->
            <?php if (canAccessTab('finance', $user_role)): ?>
            <a href="/finance/" class="service-card finance">
                <span class="service-icon">📊</span>
                <h3>Finanční sledování</h3>
                <p>Sledování příjmů a výdajů, finanční přehledy a analýzy hospodaření restaurace.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Statistics -->
            <?php if (canAccessTab('statistics', $user_role)): ?>
            <a href="protected_statistics.php" class="service-card statistics">
                <span class="service-icon">📈</span>
                <h3>Statistiky a Data</h3>
                <p>Přehled statistik serveru, analýza dat a reporting. Detailní grafy a metriky výkonu systému.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- Database Management -->
            <?php if (canAccessTab('phpmyadmin', $user_role)): ?>
            <a href="phpmyadmin_redirect.php" class="service-card database">
                <span class="service-icon">🗄️</span>
                <h3>phpMyAdmin</h3>
                <p>Správa MySQL databází, tabulek a dat. Webové rozhraní pro administraci databázového serveru.</p>
                <span class="service-status status-online">● Online</span>
            </a>
            <?php endif; ?>

            <!-- System Info -->
            <a href="#" class="service-card system" onclick="showSystemInfo()">
                <span class="service-icon">⚙️</span>
                <h3>Systémové informace</h3>
                <p>Monitoring výkonu, využití zdrojů a systémové statistiky Raspberry Pi serveru.</p>
                <span class="service-status status-online">● Online</span>
            </a>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2025 Raspberry Pi Server | Poslední aktualizace: 21.7.2025</p>
        </div>
    </div>

    <script>
        function showSystemInfo() {
            alert('Systémové informace:\n\n' +
                  '• Raspberry Pi 5\n' +
                  '• IP: 192.168.30.201\n' +
                  '• OS: Raspberry Pi OS\n' +
                  '• Služby: Apache, PHP, MySQL\n' +
                  '• Aplikace: Pizza Restaurant, phpMyAdmin, Statistiky');
        }

        // Animate cards on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.service-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(30px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 200);
            });
        });
    </script>
</body>
</html>