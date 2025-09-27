<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';

// Обработка разблокировки
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_username'])) {
    $username = trim($_POST['unlock_username']);
    
    if (!empty($username)) {
        // Проверяем, существует ли админ и заблокирован ли
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? AND is_locked = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Разблокируем
            $update_stmt = $conn->prepare("UPDATE admins SET is_locked = 0, failed_attempts = 0, unlock_time = NULL WHERE username = ?");
            $update_stmt->bind_param("s", $username);
            
            if ($update_stmt->execute()) {
                $success_message = "Администратор '$username' успешно разблокирован!";
            } else {
                $error_message = "Ошибка при разблокировке: " . $conn->error;
            }
        } else {
            $error_message = "Администратор '$username' не найден или не заблокирован.";
        }
    } else {
        $error_message = "Введите имя пользователя.";
    }
}

// Получаем список заблокированных админов
$blocked_admins = [];
$result = $conn->query("SELECT username, failed_attempts, unlock_time FROM admins WHERE is_locked = 1 ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $blocked_admins[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Разблокировка админа | Панель Админа</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0077ff;
            --secondary-color: #66b2ff;
            --bg-gradient-start: #00c6ff;
            --bg-gradient-end: #0072ff;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --primary-hover: #005bb5;
            --success-color: #10b981;
            --danger-color: #e74c3c;
            --warning-color: #f59e0b;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            transition: var(--transition);
        }

        .container {
            max-width: 800px;
            width: 100%;
            background: var(--light-bg);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }

        .form-section {
            background: var(--bg-secondary);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            font-size: 0.875rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 119, 255, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .table-section {
            margin-top: 2rem;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .blocked-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-primary);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
        }

        .blocked-table th,
        .blocked-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .blocked-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        .blocked-table tr:hover {
            background: rgba(0, 119, 255, 0.05);
        }

        .blocked-table tbody tr:last-child td {
            border-bottom: none;
        }

        .no-data {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem;
            font-style: italic;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-top: 1rem;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-hover);
            transform: translateX(-2px);
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
                margin: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .blocked-table {
                font-size: 0.875rem;
            }

            .blocked-table th,
            .blocked-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>

<body data-theme="light">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Разблокировка администратора</h1>
            <p class="page-subtitle">Разблокируйте заблокированные аккаунты администраторов</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message message-success">
                <span class="material-icons">check_circle</span>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message message-error">
                <span class="material-icons">error</span>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h2 class="form-title">Разблокировать по имени</h2>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="unlock_username">Имя пользователя</label>
                    <input 
                        type="text" 
                        id="unlock_username" 
                        name="unlock_username" 
                        class="form-input" 
                        placeholder="Введите логин админа (например, admin)" 
                        required 
                        autocomplete="off"
                        value="<?= htmlspecialchars($_POST['unlock_username'] ?? '') ?>"
                    >
                </div>
                <div class="form-actions">
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons">lock_open</span>
                        Разблокировать
                    </button>
                </div>
            </form>
        </div>

        <div class="table-section">
            <h2 class="table-title">Заблокированные админы</h2>
            <?php if (empty($blocked_admins)): ?>
                <div class="no-data">
                    <span class="material-icons" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;">lock_open</span>
                    <p>Все администраторы разблокированы. Нет заблокированных аккаунтов.</p>
                </div>
            <?php else: ?>
                <table class="blocked-table">
                    <thead>
                        <tr>
                            <th>Логин</th>
                            <th>Неудачные попытки</th>
                            <th>Время разблокировки</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocked_admins as $admin): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($admin['username']) ?></strong></td>
                                <td><?= $admin['failed_attempts'] ?></td>
                                <td><?= $admin['unlock_time'] ? date('d.m.Y H:i', strtotime($admin['unlock_time'])) : 'Постоянно' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="index.php" class="back-link">
                <span class="material-icons">arrow_back</span>
                Вернуться в панель
            </a>
        </div>
    </div>

    <script>
        // Инициализация темы (синхронизирована с index.php)
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        // Автофокус на поле ввода
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('unlock_username');
            if (input) {
                input.focus();
            }
        });
    </script>
</body>
</html>