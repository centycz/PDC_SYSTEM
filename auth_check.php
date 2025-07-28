<?php
session_start();

// Nastavení hesla
define('ADMIN_PASSWORD', 'diego');

// Funkce pro ověření přihlášení
function checkAuth() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Zpracování přihlášení
if ($_POST['password'] ?? false) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . $_POST['redirect_url']);
        exit;
    } else {
        $error = 'Nesprávné heslo!';
    }
}

// Zpracování odhlášení
if ($_GET['logout'] ?? false) {
    session_destroy();
    header('Location: index.html');
    exit;
}

// Pokud není přihlášen, zobraz formulář
if (!checkAuth()) {
    // Správně načti redirect URL z GET parametru
    $redirect_url = $_GET['redirect'] ?? $_SERVER['REQUEST_URI'] ?? 'protected_statistics.php';
    
    // Pokud je redirect_url prázdný nebo obsahuje auth_check.php, nastav výchozí
    if (empty($redirect_url) || strpos($redirect_url, 'auth_check.php') !== false) {
        $redirect_url = 'protected_statistics.php';
    }
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Přihlášení - Admin</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .login-container {
                background: white;
                padding: 2rem;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 400px;
            }
            .login-header {
                text-align: center;
                margin-bottom: 2rem;
                color: #333;
            }
            .form-group {
                margin-bottom: 1.5rem;
            }
            label {
                display: block;
                margin-bottom: 0.5rem;
                color: #555;
                font-weight: bold;
            }
            input[type="password"] {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 1rem;
                transition: border-color 0.3s;
                box-sizing: border-box;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            .login-btn {
                width: 100%;
                padding: 0.75rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 1rem;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .login-btn:hover {
                transform: translateY(-2px);
            }
            .error {
                color: #e74c3c;
                text-align: center;
                margin-bottom: 1rem;
                padding: 0.5rem;
                background: #ffeaea;
                border-radius: 5px;
            }
            .lock-icon {
                text-align: center;
                font-size: 3rem;
                margin-bottom: 1rem;
                color: #667eea;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="lock-icon">🔒</div>
            <div class="login-header">
                <h2>Administrátorské přihlášení</h2>
                <p>Vložte heslo pro přístup</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password">Heslo:</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($redirect_url) ?>">
                <button type="submit" class="login-btn">Přihlásit se</button>
            </form>
        </div>
        
        <script>
            // Automatické zaměření na input
            document.getElementById('password').focus();
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>