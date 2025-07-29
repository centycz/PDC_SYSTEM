<?php
session_start();

// For testing purposes, bypass database connection
$test_mode = true;

if ($test_mode) {
    // Handle logout
    if (isset($_GET['logout'])) {
        session_destroy();
        $success_message = "✅ Úspěšně odhlášen!";
    }
    
    // Redirect if already logged in
    if (isset($_SESSION['order_user'])) {
        header("Location: index.php");
        exit;
    }
    
    // Handle test login
    if ($_POST['action'] ?? false) {
        if ($_POST['action'] === 'login') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            if ($username && $password) {
                // Test user credentials
                if ($username === 'test' && $password === 'test') {
                    $_SESSION['order_user'] = 'test';
                    $_SESSION['order_user_id'] = 1;
                    $_SESSION['order_full_name'] = 'Test User';
                    $_SESSION['is_admin'] = false;
                    
                    $_SESSION['success_message'] = "✅ Úspěšně přihlášen jako Test User!";
                    header("Location: index.php");
                    exit;
                } elseif ($username === 'admin' && $password === 'admin') {
                    $_SESSION['order_user'] = 'admin';
                    $_SESSION['order_user_id'] = 2;
                    $_SESSION['order_full_name'] = 'Admin User';
                    $_SESSION['is_admin'] = true;
                    
                    $_SESSION['success_message'] = "✅ Úspěšně přihlášen jako Admin User!";
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "❌ Nesprávné přihlašovací údaje! Zkuste: test/test nebo admin/admin";
                }
            } else {
                $error_message = "❌ Vyplňte všechna pole!";
            }
        }
    }
    
    $existing_users = [
        ['username' => 'test', 'full_name' => 'Test User', 'is_admin' => false],
        ['username' => 'admin', 'full_name' => 'Admin User', 'is_admin' => true]
    ];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Firemní objednávky (TEST)</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .test-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #856404;
        }

        .test-info h4 {
            color: #b45309;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🛒 Firemní objednávky</h1>
            <p>Přihlaste se pro správu objednávek (TEST MODE)</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="message success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?= $error_message ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label>Uživatelské jméno:</label>
                <input type="text" name="username" required placeholder="test nebo admin" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>

            <div class="form-group">
                <label>Heslo:</label>
                <input type="password" name="password" required placeholder="test nebo admin">
            </div>

            <button type="submit" class="btn btn-primary">
                🔓 Přihlásit se
            </button>
        </form>

        <div class="test-info">
            <h4>📋 Testovací účty:</h4>
            <p><strong>test / test</strong> - Základní uživatel</p>
            <p><strong>admin / admin</strong> - Administrátor</p>
        </div>
    </div>
</body>
</html>