<?php
session_start();
require_once 'db_connect.php';

// Генерация CSRF токена для защиты
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Секретный ключ Google reCAPTCHA (замените на свой)
$recaptcha_secret_key = '6LepmP0qAAAAADTwaSXRzXsksLpGtQHCMjaxEBwE';

// Функции для работы с БД (адаптированы под вашу структуру)
function getAdminByUsername($conn, $username) {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function verifyAdminLogin($conn, $username, $password) {
    $admin = getAdminByUsername($conn, $username);
    if ($admin && hash_equals(hash('sha256', $password), $admin['password']) && !$admin['is_locked']) {
        // Сброс счетчика неудачных попыток при успешном входе
        $stmt = $conn->prepare("UPDATE admins SET failed_attempts = 0 WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return true;
    } elseif ($admin && $admin['is_locked']) {
        return ['blocked' => true, 'unlock_time' => $admin['unlock_time']];
    }
    // Увеличение счетчика неудачных попыток
    if ($admin) {
        $new_attempts = $admin['failed_attempts'] + 1;
        if ($new_attempts >= 3) {
            $unlock_time = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $conn->prepare("UPDATE admins SET failed_attempts = ?, is_locked = 1, unlock_time = ? WHERE username = ?");
            $stmt->bind_param("iss", $new_attempts, $unlock_time, $username);
            $stmt->execute();
            return ['blocked' => true];
        } else {
            $stmt = $conn->prepare("UPDATE admins SET failed_attempts = ? WHERE username = ?");
            $stmt->bind_param("is", $new_attempts, $username);
            $stmt->execute();
        }
    }
    return false;
}

function getAccountStatus($conn, $username) {
    $admin = getAdminByUsername($conn, $username);
    if ($admin) {
        return [
            'failed_attempts' => $admin['failed_attempts'],
            'is_locked' => $admin['is_locked']
        ];
    }
    return null;
}

// Проверка при отправке формы логина
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Проверка CSRF токена
    if ($csrf_token !== $_SESSION['csrf_token']) {
        $error = 'Ошибка: Неверный CSRF токен';
    }
    // Проверка, заполнена ли reCAPTCHA
    elseif (empty($recaptcha_response)) {
        $error = 'Пожалуйста, подтвердите, что вы не робот';
    } else {
        // Проверка reCAPTCHA через API Google
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $recaptcha_secret_key,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            $error = 'Не удалось проверить reCAPTCHA. Попробуйте позже.';
        } else {
            $response = json_decode($result, true);
            if ($response['success'] !== true) {
                // Очищаем счетчик попыток для reCAPTCHA ошибок
                if (!empty($username)) {
                    $admin = getAdminByUsername($conn, $username);
                    if ($admin && !$admin['is_locked']) {
                        $error = 'Ошибка проверки безопасности. Попробуйте еще раз.';
                    } else {
                        $error = 'Неверная проверка reCAPTCHA';
                    }
                } else {
                    $error = 'Неверная проверка reCAPTCHA';
                }
            } else {
                // reCAPTCHA прошла успешно, проверяем логин и пароль
                $login_result = verifyAdminLogin($conn, $username, $password);
                
                if ($login_result === true) {
                    $_SESSION['logged_in'] = true;
                    $_SESSION['username'] = $username;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: index.php');
                    exit;
                } elseif (is_array($login_result) && isset($login_result['blocked'])) {
                    // Аккаунт заблокирован
                    if (isset($login_result['unlock_time'])) {
                        $unlock_time = new DateTime($login_result['unlock_time']);
                        $error = "Аккаунт заблокирован до " . $unlock_time->format('d.m.Y H:i:s') . ". Попробуйте позже.";
                    } else {
                        $error = "Аккаунт заблокирован после 3 неудачных попыток. Обратитесь к администратору.";
                    }
                } else {
                    // Проверка статуса аккаунта для отображения количества оставшихся попыток
                    $status = getAccountStatus($conn, $username);
                    if ($status && !$status['is_locked']) {
                        $error = "Неверный логин или пароль! Осталось попыток: " . (3 - $status['failed_attempts']);
                    } else {
                        $error = "Неверный логин или пароль!";
                    }
                }
            }
        }
    }
}

// Проверка, если пользователь согласился с условиями
if (!isset($_SESSION['accepted_terms'])) {
    $_SESSION['accepted_terms'] = false;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Панель Админа</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <style>
        :root {
            --primary-color: #0077ff;
            --secondary-color: #66b2ff;
            --bg-gradient-start: #00c6ff;
            --bg-gradient-end: #0072ff;
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --primary-hover: #005bb5;
            --success-color: #10b981;
            --danger-color: #e74c3c;
            --warning-color: #f59e0b;
            --error-bg: rgba(239, 68, 68, 0.1);
            --warning-bg: rgba(245, 158, 11, 0.1);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius: 12px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --icon-size: 20px;
            --input-height: 48px;
            --light-bg: rgba(255, 255, 255, 0.95);
            --error-color: #e74c3c;
            --font-family: 'Inter', sans-serif;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --primary-color: #60a5fa;
            --primary-hover: #3b82f6;
            --success-color: #34d399;
            --danger-color: #f87171;
            --warning-color: #fbbf24;
            --error-bg: rgba(248, 113, 113, 0.2);
            --warning-bg: rgba(251, 191, 36, 0.2);
            --light-bg: rgba(30, 41, 59, 0.95);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(to right, var(--bg-gradient-start), var(--bg-gradient-end));
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: var(--transition);
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .page-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem 1rem;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: var(--light-bg);
            padding: 2.5rem 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
            box-shadow: var(--shadow-md);
        }

        .login-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 400;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            height: var(--input-height);
            background: var(--bg-secondary);
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            transition: var(--transition);
            overflow: hidden;
        }

        .input-wrapper:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 119, 255, 0.1);
            background: var(--bg-card);
        }

        .form-input {
            flex: 1;
            height: 100%;
            padding: 14px 3rem 14px 3rem;
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-size: 18px;
            font-family: inherit;
            outline: none;
        }

        .form-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: var(--icon-size);
            z-index: 1;
            pointer-events: none;
            transition: var(--transition);
        }

        .input-wrapper:focus-within .input-icon {
            color: var(--primary-color);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: var(--icon-size);
            padding: 0.25rem;
            border-radius: 50%;
            z-index: 2;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: var(--icon-size);
            height: var(--icon-size);
        }

        .password-toggle:hover {
            background: rgba(0, 119, 255, 0.1);
            color: var(--primary-color);
        }
        
        .btn-login {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            height: var(--input-height);
            padding: 0 2rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            margin: 0 auto;
        }

        .btn-login .material-icons {
            font-size: var(--icon-size);
            transition: var(--transition);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 16px;
            animation: slideDown 0.3s ease-out;
            min-height: var(--input-height);
        }

        .message .material-icons {
            font-size: var(--icon-size);
        }

        .message-error {
            background: var(--error-bg);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
            justify-content: flex-start;
        }

        .message-warning {
            background: var(--warning-bg);
            border: 1px solid var(--warning-color);
            color: var(--warning-color);
            justify-content: flex-start;
        }

        @keyframes slideDown {
            from { 
                opacity: 0; 
                transform: translateY(-10px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        .security-info {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            line-height: 1.4;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .security-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.75rem;
        }

        .security-item .material-icons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
            width: 44px;
            height: 44px;
            background: var(--primary-color);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .theme-toggle:hover {
            background: var(--primary-hover);
            transform: scale(1.05) rotate(180deg);
        }

        .theme-toggle .material-icons {
            font-size: 1.25rem;
            transition: var(--transition);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.2s ease-out;
        }

        .modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--bg-card);
            padding: 2.5rem;
            border-radius: var(--radius);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            transform: scale(0.9) translateY(20px);
            animation: modalSlideUp 0.3s ease-out forwards;
            position: relative;
        }

        @keyframes modalSlideUp {
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .modal-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
            box-shadow: var(--shadow-md);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .modal-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .modal-description {
            color: var(--text-secondary);
            line-height: 1.6;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .modal-text {
            margin: 1rem 0;
            line-height: 1.6;
            text-align: left;
        }

        .modal-text strong {
            color: var(--text-primary);
        }

        .contact-info {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1rem;
            margin: 1.5rem 0;
            font-size: 0.875rem;
        }

        .contact-info strong {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn-modal {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
            flex: 1;
            max-width: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            height: var(--input-height);
        }

        .btn-accept {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .btn-accept .material-icons {
            font-size: var(--icon-size);
        }

        .btn-accept:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-decline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-decline .material-icons {
            font-size: var(--icon-size);
        }

        .btn-decline:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-1px);
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Адаптивность */
        @media (max-width: 480px) {
            .page-container {
                padding: 1rem 0.5rem;
            }

            .login-card {
                padding: 2rem 1.5rem;
                margin: 0 1rem;
            }

            .modal-actions {
                flex-direction: column;
            }

            .btn-modal {
                max-width: none;
            }

            .recaptcha-container {
                padding: 0 1rem;
            }

            .login-title {
                font-size: 1.5rem;
            }

            .input-wrapper {
                height: 44px;
            }

            .form-input {
                padding: 0 2.5rem 0 2.5rem;
            }

            .input-icon,
            .password-toggle {
                font-size: 18px;
            }
        }

        /* Темная тема для reCAPTCHA */
        [data-theme="dark"] .g-recaptcha {
            --reaptcha-bg: #1e293b;
            --reaptcha-border: #334155;
        }

        /* Скрытие скроллбара */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--border-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--text-secondary);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-primary);
        }
    </style>
</head>

<body data-theme="light">
    <!-- Кнопка смены темы -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Переключить тему" aria-label="Переключить тему">
        <span class="material-icons" id="themeIcon">dark_mode</span>
    </button>

    <div class="page-container">
        <div class="login-card" id="loginCard">
            <div class="login-header">
                <div class="login-logo">
                    <span class="material-icons">lock</span>
                </div>
                <h1 class="login-title">Вход | Панель Админа</h1>
                <p class="login-subtitle">Добро пожаловать в систему управления</p>
            </div>

            <?php if (isset($error) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="message message-error">
                    <span class="material-icons">error</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="username">Логин</label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="Введите логин" 
                            required 
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            autocomplete="username"
                            aria-describedby="username-help"
                        >
                        <span class="input-icon material-icons">person</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Пароль</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Введите пароль" 
                            required 
                            autocomplete="current-password"
                            aria-describedby="password-help"
                        >
                        <button type="button" class="password-toggle material-icons" onclick="togglePassword()" title="Показать пароль" aria-label="Показать пароль">
                            visibility
                        </button>
                        <span class="input-icon material-icons">lock</span>
                    </div>
                </div>

                <div class="recaptcha-container" id="recaptcha-container"></div>

                <button type="submit" class="btn-login" id="loginBtn" name="submit" disabled>
                    <span class="material-icons">login</span>
                    Войти
                </button>

                <?php if (isset($_POST['username']) && !empty($_POST['username']) && !$error): ?>
                    <?php 
                    $status = getAccountStatus($conn, trim($_POST['username']));
                    if ($status && !$status['is_locked'] && $status['failed_attempts'] > 0): 
                    ?>
                        <div class="message message-warning">
                            <span class="material-icons">warning</span>
                            Осталось попыток: <strong><?= 3 - $status['failed_attempts'] ?></strong>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="security-info">
                    <div class="security-item">
                        <span class="material-icons">lock</span>
                        После 3 неудачных попыток аккаунт блокируется на 24 часа
                    </div>
                    <div class="security-item">
                        <span class="material-icons">security</span>
                        Все соединения защищены SSL и CSRF токенами
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно согласия -->
    <div id="termsModal" class="modal active">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">gavel</span>
                </div>
                <div>
                    <h3 class="modal-title">Условия использования</h3>
                    <p class="modal-subtitle">Лицензионное соглашение</p>
                </div>
            </div>

            <div class="modal-description">
                <div class="modal-text">
                    <strong>Проект:</strong> Панель администратора для системы управления кухней<br><br>
                    <strong>Разработчики:</strong><br>
                    • Семкин Иван (@nertoff)<br>
                    • Щегольков Максим (@Oxigen4ik)<br><br>
                    <em>Вся документация и программное обеспечение защищены авторским правом 
                    и могут использоваться только с письменного разрешения разработчиков.</em>
                </div>
                
                <div class="contact-info">
                    <strong>Контакты для связи:</strong>
                    📧 35313531as@gmail.com<br>
                    📧 q_bite@mail.ru<br>
                    📱 Telegram: <a href="https://t.me/nertoff" target="_blank">@nertoff</a> (Семкин Иван)<br>
                    📱 Telegram: <a href="https://t.me/Oxigen4ik" target="_blank">@Oxigen4ik</a> (Щегольков Максим)
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-modal btn-decline" onclick="declineTerms()" aria-label="Отказаться от условий">
                    <span class="material-icons">close</span>
                    Отказаться
                </button>
                <button class="btn-modal btn-accept" onclick="acceptTerms()" aria-label="Принять условия">
                    <span class="material-icons">check</span>
                    Принять и продолжить
                </button>
            </div>
        </div>
    </div>

    <script>
        // Инициализация темы
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.getElementById('themeIcon').textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            document.getElementById('themeIcon').textContent = newTheme === 'dark' ? 'light_mode' : 'dark_mode';
            
            // Обновляем reCAPTCHA при смене темы
            if (typeof grecaptcha !== 'undefined' && grecaptcha.getResponse().length === 0) {
                grecaptcha.reset();
            }
        }

        // Переключение видимости пароля
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.textContent = 'visibility_off';
                toggleBtn.setAttribute('aria-label', 'Скрыть пароль');
            } else {
                passwordField.type = 'password';
                toggleBtn.textContent = 'visibility';
                toggleBtn.setAttribute('aria-label', 'Показать пароль');
            }
        }

        // Callback для reCAPTCHA - разблокирует кнопку только при успешной проверке
        function onRecaptchaSuccess(token) {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = false;
            loginBtn.style.opacity = '1';
            loginBtn.style.cursor = 'pointer';
        }

        // Обработка формы входа
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('loginBtn');
            const recaptchaResponse = grecaptcha.getResponse();
            
            // Проверяем reCAPTCHA только при отправке формы
            if (!recaptchaResponse.length) {
                e.preventDefault();
                showMessage('Пожалуйста, пройдите проверку reCAPTCHA', 'error');
                return false;
            }

            // Показываем индикатор загрузки
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            submitBtn.innerHTML = `
                <div class="loading"></div>
                Проверка доступа...
            `;
        });

        function showMessage(text, type) {
            // Удаляем существующие сообщения
            const existingMessage = document.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = `message message-${type}`;
            messageDiv.innerHTML = `
                <span class="material-icons">${type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'check_circle'}</span>
                ${text}
            `;
            messageDiv.setAttribute('role', 'alert');
            messageDiv.setAttribute('aria-live', 'polite');
            
            const form = document.getElementById('loginForm');
            form.insertBefore(messageDiv, form.firstChild);
            
            // Автофокус на поле логина при ошибке
            if (type === 'error') {
                setTimeout(() => {
                    document.getElementById('username').focus();
                    document.getElementById('username').select();
                }, 100);
            }
        }

        // Модальное окно согласия
        function acceptTerms() {
            // Сохраняем согласие в сессии (через AJAX, но для простоты используем PHP)
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accept_terms=1'
            }).then(response => response.text()).then(() => {
                <?php $_SESSION['accepted_terms'] = true; ?>
            }).catch(() => {
                // Fallback: просто продолжаем
                <?php $_SESSION['accepted_terms'] = true; ?>
            });

            const modal = document.getElementById('termsModal');
            modal.style.opacity = '0';
            modal.style.transform = 'scale(0.9)';
            modal.style.transition = 'all 0.3s ease-out';
            
            setTimeout(() => {
                modal.classList.remove('active');
                modal.style.display = 'none';
                
                // Фокус на поле логина
                setTimeout(() => {
                    document.getElementById('username').focus();
                }, 300);
                
                // Инициализируем reCAPTCHA после закрытия модалки
                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.ready(function() {
                        grecaptcha.render('recaptcha-container', {
                            'sitekey': '6LepmP0qAAAAAJe27ickgNFe7iqwIdWwR7FGjw2f', // Замените на свой sitekey
                            'callback': onRecaptchaSuccess
                        });
                    });
                }
            }, 300);
        }

        function declineTerms() {
            // Показываем более информативное сообщение
            const modal = document.getElementById('termsModal');
            const acceptBtn = modal.querySelector('.btn-accept');
            const declineBtn = modal.querySelector('.btn-decline');
            
            declineBtn.innerHTML = '<span class="material-icons">refresh</span> Попробовать снова';
            acceptBtn.style.display = 'none';
            
            declineBtn.onclick = function() {
                acceptBtn.style.display = 'flex';
                declineBtn.innerHTML = '<span class="material-icons">close</span> Отказаться';
                declineBtn.onclick = declineTerms;
            };
        }

        // Закрытие модального окна по ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('termsModal');
                if (modal.classList.contains('active')) {
                    declineTerms();
                }
            }
        });

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            // Плавная анимация появления формы
            const loginCard = document.getElementById('loginCard');
            loginCard.style.opacity = '0';
            loginCard.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                loginCard.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                loginCard.style.opacity = '1';
                loginCard.style.transform = 'translateY(0)';
            }, 100);

            // Фокус на поле логина после загрузки
            setTimeout(() => {
                document.getElementById('username').focus();
            }, 500);

            // Инициализация reCAPTCHA только после принятия условий
            // (будет вызвана в acceptTerms())
        });

        // Предотвращение автозаполнения пароля в некоторых браузерах
        document.getElementById('password').addEventListener('animationstart', function(e) {
            if (e.animationName === 'onAutoFillStart') {
                e.target.type = 'password';
            }
        });
    </script>
</body>
</html>