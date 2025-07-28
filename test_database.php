<?php
// test_database.php - Script to test login functionality with mock database
// This simulates the database for testing the login system

session_start();

// Mock user data simulating the database
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
    header('Location: test_database.php');
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
            
            header('Location: test_database.php');
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
    <title>Test Database - Login System</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .test-info {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .login-section, .user-info {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .login-error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }

        .tabs-demo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .tab-item {
            background: rgba(255,255,255,0.95);
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .tab-item.allowed {
            border-left: 4px solid #4CAF50;
        }

        .tab-item.denied {
            border-left: 4px solid #f44336;
            opacity: 0.6;
        }

        .tab-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .tab-status {
            font-size: 0.9rem;
            color: #666;
        }

        .credentials-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .credentials-info h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .credential-item {
            margin-bottom: 8px;
            font-family: monospace;
            background: #f5f5f5;
            padding: 5px 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Test Database - Login System</h1>
            <p>Testing role-based access control</p>
        </div>

        <div class="test-info">
            <h3>📋 Test Information</h3>
            <p>This page simulates the database functionality to test the login system with different user roles.</p>
        </div>

        <?php if ($is_logged_in): ?>
        <div class="user-info">
            <div>
                <strong>🙋‍♂️ Přihlášen jako:</strong> <?= htmlspecialchars($full_name) ?> 
                <span style="color: #666;">(<?= ucfirst($user_role) ?>)</span>
            </div>
            <a href="test_database.php?logout=1" class="btn btn-danger">Odhlásit se</a>
        </div>

        <div class="test-info">
            <h3>🔐 Access Control Test Results</h3>
            <p>Based on your role <strong><?= ucfirst($user_role) ?></strong>, here are the tabs you can access:</p>
            
            <div class="tabs-demo">
                <div class="tab-item <?= canAccessTab('restaurant', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">🍕 Restaurační systém</div>
                    <div class="tab-status"><?= canAccessTab('restaurant', $user_role) ? '✅ Povoleno' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('status', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">🍽️ Aktuální stav</div>
                    <div class="tab-status"><?= canAccessTab('status', $user_role) ? '✅ Povoleno' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('reservations', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">📅 Rezervace</div>
                    <div class="tab-status"><?= canAccessTab('reservations', $user_role) ? '✅ Povoleno' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('orders', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">🛒 Objednávky</div>
                    <div class="tab-status"><?= canAccessTab('orders', $user_role) ? '✅ Povoleno' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('shifts', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">⏰ Směny</div>
                    <div class="tab-status"><?= canAccessTab('shifts', $user_role) ? '✅ Povoleno' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('payroll', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">💰 Mzdy</div>
                    <div class="tab-status"><?= canAccessTab('payroll', $user_role) ? '✅ Povoleno' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('finance', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">📊 Finanční sledování</div>
                    <div class="tab-status"><?= canAccessTab('finance', $user_role) ? '✅ Povoleno (NOVÁ ZÁLOŽKA)' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('statistics', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">📈 Statistiky a Data</div>
                    <div class="tab-status"><?= canAccessTab('statistics', $user_role) ? '✅ Povoleno' : '❌ Zakázáno' ?></div>
                </div>
                
                <div class="tab-item <?= canAccessTab('phpmyadmin', $user_role) ? 'allowed' : 'denied' ?>">
                    <div class="tab-title">🗄️ phpMyAdmin</div>
                    <div class="tab-status"><?= canAccessTab('phpmyadmin', $user_role) ? '✅ Povoleno' : '❌ Zakázáno (pouze Admin)' ?></div>
                </div>
                
                <div class="tab-item allowed">
                    <div class="tab-title">⚙️ Systémové informace</div>
                    <div class="tab-status">✅ Vždy dostupné</div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="login-section">
            <h3>🔐 Přihlášení k testování</h3>
            <?php if ($login_error): ?>
                <div class="login-error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Uživatelské jméno:</label>
                    <input type="text" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Heslo:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">🔓 Přihlásit se</button>
            </form>

            <div class="credentials-info">
                <h4>🔑 Testovací přihlašovací údaje:</h4>
                <div class="credential-item"><strong>Admin:</strong> admin / admin123</div>
                <div class="credential-item"><strong>Ragazzi:</strong> ragazzi / ragazzi123</div>
                <div class="credential-item"><strong>User:</strong> user / user123</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>