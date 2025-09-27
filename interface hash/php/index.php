<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';

// Функция проверки на опасные команды
function isDangerousQuery($sql) {
    $sql = trim(strtoupper($sql));
    if (preg_match('/\bDROP\b/i', $sql)) return true;
    if (preg_match('/\bDELETE\b/i', $sql) && !preg_match('/\bWHERE\b/i', $sql)) return true;
    return false;
}

// Функция для получения списка пользователей с удаленного сервера
function getUserList() {
    $ssh_key = '/home/student-5/.ssh/id_rsa_redos';
    $remote_server = 'root@172.17.0.250';

    if (!file_exists($ssh_key) || !is_readable($ssh_key)) {
        return ['error' => "SSH-ключ недоступен по пути: $ssh_key"];
    }

    $command = "getent passwd | grep '/home' | cut -d: -f1";
    $ssh_command = "ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i " . escapeshellarg($ssh_key) . " " . escapeshellarg($remote_server) . " " . escapeshellarg($command);

    $output = [];
    $return_var = 0;
    exec($ssh_command . " 2>&1", $output, $return_var);

    if ($return_var === 0) {
        $users = array_filter($output, function($line) {
            $line = trim($line);
            return !empty($line) && !str_contains($line, 'Warning: Permanently added');
        });
        return ['users' => array_values($users)];
    } else {
        return ['error' => "Ошибка при получении списка пользователей: " . implode("\n", $output)];
    }
}

// Обработка AJAX-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_users':
            $response = getUserList();
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            
        case 'add_user':
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if (!empty($username) && !empty($password) && preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $ssh_key = '/home/student-5/.ssh/id_rsa_redos';
                $remote_server = 'root@172.17.0.250';
                
                if (file_exists($ssh_key) && is_readable($ssh_key)) {
                    $command = "useradd -m -s /bin/bash " . escapeshellarg($username) . " && echo " . escapeshellarg("$username:$password") . " | chpasswd";
                    $ssh_command = "ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i " . escapeshellarg($ssh_key) . " " . escapeshellarg($remote_server) . " " . escapeshellarg($command);

                    $output = [];
                    $return_var = 0;
                    exec($ssh_command . " 2>&1", $output, $return_var);

                    if ($return_var === 0) {
                        $response = ['status' => 'success', 'message' => "Пользователь $username успешно создан"];
                    } else {
                        $response['message'] = "Ошибка при создании: " . implode("\n", $output);
                    }
                } else {
                    $response['message'] = "SSH-ключ недоступен: $ssh_key";
                }
            } else {
                $response['message'] = 'Имя пользователя может содержать только буквы, цифры и подчеркивания';
            }
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            
        case 'delete_user':
            $username = trim($_POST['username'] ?? '');
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if (!empty($username) && preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $ssh_key = '/home/student-5/.ssh/id_rsa_redos';
                $remote_server = 'root@172.17.0.250';
                
                if (file_exists($ssh_key) && is_readable($ssh_key)) {
                    $command = "userdel -r " . escapeshellarg($username);
                    $ssh_command = "ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i " . escapeshellarg($ssh_key) . " " . escapeshellarg($remote_server) . " " . escapeshellarg($command);

                    $output = [];
                    $return_var = 0;
                    exec($ssh_command . " 2>&1", $output, $return_var);

                    if ($return_var === 0) {
                        $response = ['status' => 'success', 'message' => "Пользователь $username успешно удален"];
                    } else {
                        $response['message'] = "Ошибка при удалении: " . implode("\n", $output);
                    }
                } else {
                    $response['message'] = "SSH-ключ недоступен: $ssh_key";
                }
            } else {
                $response['message'] = 'Имя пользователя может содержать только буквы, цифры и подчеркивания';
            }
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
    }
}

// Обработка скачивания файла
if (isset($_GET['download'])) {
    $sql = $_SESSION['last_sql_query'] ?? '';
    if ($sql && ($result = $conn->query($sql)) && $result !== true) {
        $fields = [];
        while ($field = $result->fetch_field()) {
            $fields[] = $field->name;
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        if ($_GET['download'] === 'csv') {
            $csv_content = implode(",", array_map(fn($field) => '"' . str_replace('"', '""', $field) . '"', $fields)) . "\n";
            foreach ($rows as $row) {
                $csv_content .= implode(",", array_map(fn($cell) => '"' . str_replace('"', '""', preg_replace('/\s*\n\s*/', '; ', trim($cell ?? ''))) . '"', $row)) . "\n";
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="query_result_' . date('Y-m-d_H-i-s') . '.csv"');
            echo "\xEF\xBB\xBF"; // BOM для UTF-8
            echo $csv_content;
        } elseif ($_GET['download'] === 'xlsx') {
            $html_content = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Query Result</title></head><body><table border='1' style='border-collapse:collapse;'>";
            $html_content .= "<tr>";
            foreach ($fields as $field) {
                $html_content .= "<th style='background:#f0f0f0;padding:8px;border:1px solid #ddd;'>" . htmlspecialchars($field) . "</th>";
            }
            $html_content .= "</tr>";
            foreach ($rows as $row) {
                $html_content .= "<tr>";
                foreach ($row as $cell) {
                    $html_content .= "<td style='padding:8px;border:1px solid #ddd;'>" . htmlspecialchars($cell ?? '') . "</td>";
                }
                $html_content .= "</tr>";
            }
            $html_content .= "</table></body></html>";
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="query_result_' . date('Y-m-d_H-i-s') . '.xls"');
            header('Cache-Control: max-age=0');
            echo $html_content;
        }
    }
    exit;
}

// Обработка выхода
if (isset($_GET['action']) && $_GET['action'] === 'logout' && $_GET['confirm'] === 'yes') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Обработка SQL-запроса
if (isset($_POST['sql_query'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sql = trim($_POST['sql_query']);
    $is_confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === 'true';  // ← Фикс warning
    $_SESSION['last_sql_query'] = $sql;
    
    if (empty($sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Пожалуйста, введите SQL-запрос']);
        exit;
    }
    
    if (isDangerousQuery($sql) && !$is_confirmed) {
        echo json_encode(['status' => 'warning', 'message' => 'Внимание: запрос содержит потенциально опасные команды. Подтвердите выполнение.']);
        exit;
    }
    
    if (preg_match('/^\s*CREATE\s+TABLE\s+\w+\s*(?:\(|$)/i', $sql) && !preg_match('/\(/', $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Некорректный синтаксис CREATE TABLE. Укажите структуру таблицы.']);
        exit;
    }
    
    try {
        if ($result = $conn->query($sql)) {
            if ($result === true) {
                echo json_encode(['status' => 'success', 'message' => 'Запрос успешно выполнен']);
                exit;
            } else {
                ob_start();
                echo '<div class="result-table">';
                echo '<table><thead><tr>';
                $fields = [];
                while ($field = $result->fetch_field()) {
                    $fields[] = $field->name;
                    echo '<th>' . htmlspecialchars($field->name) . '</th>';
                }
                echo '</tr></thead><tbody>';
                
                $row_count = 0;
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>' . htmlspecialchars($cell ?? '') . '</td>';
                    }
                    echo '</tr>';
                    $row_count++;
                    if ($row_count > 1000) break;
                }
                echo '</tbody></table>';
                
                if ($row_count > 1000) {
                    echo '<p class="table-note">Показаны первые 1000 строк. <a href="?download=csv">Скачать все данные</a></p>';
                } else {
                    echo '<div class="download-links">';
                    echo '<a href="?download=csv" class="download-btn">📥 CSV</a>';
                    echo '<a href="?download=xlsx" class="download-btn">📊 Excel</a>';
                    echo '</div>';
                }
                echo '</div>';
                
                $html_message = ob_get_clean();
                echo json_encode(['status' => 'success', 'message' => $html_message, 'rows' => $row_count]);
                exit;
            }
        } else {
            $error_msg = $conn->error;
            echo json_encode(['status' => 'error', 'message' => 'Ошибка SQL: ' . $error_msg]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка выполнения: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель Админа | Зал</title>
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
            --dark-bg: #333;
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
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: var(--light-bg);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
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

        .btn-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .btn-icon:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .main-content {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            padding: 2rem;
            min-height: calc(100vh - 80px);
        }

        .sidebar {
            background: var(--light-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            height: fit-content;
        }

        .nav-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-list {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--radius);
            transition: var(--transition);
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .nav-link i {
            width: 20px;
            font-size: 1.125rem;
            opacity: 0.7;
            transition: var(--transition);
        }

        .nav-link:hover i {
            opacity: 1;
        }

        .content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-panel {
            background: var(--light-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            line-height: 1.7;
        }

        .info-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .info-text {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .info-list {
            list-style: none;
        }

        .info-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item strong {
            color: var(--text-primary);
            min-width: 180px;
            font-weight: 500;
        }

        .console-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: var(--light-bg);
            border-top: 1px solid var(--border-color);
            padding: 1rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            display: none;
            max-height: 60vh;
            overflow-y: auto;
        }

        .console-panel.active {
            display: block;
            animation: slideUpFromBottom 0.3s ease-out;
        }

        @keyframes slideUpFromBottom {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .console-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .console-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .console-toggle {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .console-toggle:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .sql-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .sql-textarea {
            width: 100%;
            min-height: 120px;
            padding: 1rem;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            resize: vertical;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .sql-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 119, 255, 0.1);
        }

        .sql-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .btn-execute {
            background: var(--success-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-execute:hover:not(:disabled) {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-execute:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .result-container {
            margin-top: 1rem;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            border-radius: var(--radius);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .result-table {
            width: 100%;
            overflow-x: auto;
            border-radius: var(--radius);
            border-collapse: separate;
            border-spacing: 0;
            box-shadow: var(--shadow-sm);
        }

        .result-table th,
        .result-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .result-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .result-table tr:hover {
            background: rgba(0, 119, 255, 0.05);
        }

        .result-table tbody tr:last-child td {
            border-bottom: none;
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .styled-table th, .styled-table td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid var(--border-color);
        }
        .styled-table th {
            background: var(--primary-color);
            color: white;
        }
        .styled-table tr:hover {
            background: rgba(0, 119, 255, 0.05);
        }
        .table-note {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        } 
        .download-links {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .download-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .table-note {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: var(--radius);
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            transform: translateY(-20px);
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .modal-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .modal-warning .modal-icon {
            background: var(--warning-color);
        }

        .modal-danger .modal-icon {
            background: var(--danger-color);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-description {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            flex: 1;
            max-width: 120px;
        }

        .btn-confirm {
            background: var(--danger-color);
            color: white;
        }

        .btn-confirm:hover {
            background: #dc2626;
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-cancel:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
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
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 119, 255, 0.1);
        }

        .user-list {
            max-height: 200px;
            overflow-y: auto;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .user-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                padding: 1rem;
                gap: 1rem;
            }

            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .header-actions {
                order: -1;
                width: 100%;
                justify-content: center;
            }

            .sidebar {
                padding: 1rem;
            }

            .modal-actions {
                flex-direction: column;
            }

            .sql-buttons {
                flex-direction: column;
            }

            .download-links {
                flex-direction: column;
                align-items: center;
            }

            .console-panel {
                padding: 0.5rem;
            }

            .sql-textarea {
                min-height: 100px;
            }
        }

        /* Скрытие скроллбара */
        .user-list::-webkit-scrollbar {
            width: 4px;
        }

        .user-list::-webkit-scrollbar-track {
            background: var(--border-color);
            border-radius: 2px;
        }

        .user-list::-webkit-scrollbar-thumb {
            background: var(--text-secondary);
            border-radius: 2px;
        }

        .user-list::-webkit-scrollbar-thumb:hover {
            background: var(--text-primary);
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <span class="material-icons" style="font-size: 2rem; color: var(--primary-color);">event_seat</span>
            Панель Админа | Зал
        </div>
        
        <div class="header-actions">
            <a href="performance.php" class="btn btn-icon" title="Мониторинг">
                <span class="material-icons">speed</span>
            </a>
            <button class="btn btn-icon" onclick="toggleConsole()" title="SQL Консоль">
                <span class="material-icons">code</span>
            </button>
            <button class="btn btn-icon" onclick="toggleTheme()" title="Переключить тему">
                <span class="material-icons" id="themeIcon">dark_mode</span>
            </button>
            <button class="btn btn-secondary" onclick="showLogoutModal()">
                <span class="material-icons">logout</span>
                Выход
            </button>
        </div>
    </header>

    <main class="main-content">
        <aside class="sidebar">
            <h3 class="nav-title">Управление</h3>
            <nav class="nav-list">
                <a href="customer.php" class="nav-link">
                    <span class="material-icons">person</span>
                    Управление клиентами
                </a>
                <a href="dishes.php" class="nav-link">
                    <span class="material-icons">local_dining</span>
                    Управление блюдами
                </a>
                <a href="orders.php" class="nav-link">
                    <span class="material-icons">shopping_cart</span>
                    Управление заказами
                </a>
                <a href="reservation.php" class="nav-link">
                    <span class="material-icons">event</span>
                    Управление бронированиями
                </a>
                <a href="tables.php" class="nav-link">
                    <span class="material-icons">grid_view</span>
                    Управление столами
                </a>
                <a href="waiter.php" class="nav-link">
                    <span class="material-icons">room_service</span>
                    Управление официантами
                </a>
            </nav>

            <h3 class="nav-title" style="margin-top: 2rem;">Система</h3>
            <nav class="nav-list">
                <a href="#" class="nav-link" onclick="launchRemmina(); return false;">
                    <span class="material-icons">desktop_windows</span>
                    VNC Подключение
                </a>
                <a href="#" class="nav-link" onclick="showAddUserModal(); return false;">
                    <span class="material-icons">person_add</span>
                    Добавить пользователя
                </a>
                <a href="#" class="nav-link" onclick="showDeleteUserModal(); return false;">
                    <span class="material-icons">person_remove</span>
                    Удалить пользователя
                </a>
                <a href="unlock_admin.php" class="nav-link" target="_blank">
                    <span class="material-icons">security</span>
                    Разблокировать админа
                </a>
            </nav>
        </aside>

        <div class="content">
            <div class="info-panel">
                <h2 class="info-title">Добро пожаловать в панель управления</h2>
                <p class="info-text">Централизованное управление залом и системными ресурсами.</p>
                
                <ul class="info-list">
                    <li class="info-item">
                        <strong>👥 Клиенты</strong>
                        <span>Управление клиентами и их данными</span>
                    </li>
                    <li class="info-item">
                        <strong>🍽️ Блюда</strong>
                        <span>Каталог блюд и меню</span>
                    </li>
                    <li class="info-item">
                        <strong>📋 Заказы</strong>
                        <span>Обработка и аналитика заказов</span>
                    </li>
                    <li class="info-item">
                        <strong>📅 Бронирования</strong>
                        <span>Управление резервациями</span>
                    </li>
                    <li class="info-item">
                        <strong>🪑 Столы</strong>
                        <span>Расстановка и управление столами</span>
                    </li>
                    <li class="info-item">
                        <strong>👨‍🍳 Официанты</strong>
                        <span>Управление персоналом зала</span>
                    </li>
                    <li class="info-item">
                        <strong>🖥️ VNC</strong>
                        <span>Удаленное подключение к серверу</span>
                    </li>
                    <li class="info-item">
                        <strong>👥 Пользователи</strong>
                        <span>Управление системными учетными записями</span>
                    </li>
                    <li class="info-item">
                        <strong>🔐 Безопасность</strong>
                        <span>Разблокировка административных аккаунтов</span>
                    </li>
                </ul>
            </div>
        </div>
    </main>

    <!-- SQL Консоль (теперь fixed внизу) -->
    <div class="console-panel" id="consolePanel">
        <div class="console-header">
            <h3 class="console-title">SQL Консоль</h3>
            <button class="console-toggle" onclick="toggleConsole()">
                <span class="material-icons" id="consoleIcon">expand_less</span>
                <span id="consoleText">Скрыть</span>
            </button>
        </div>
        <form class="sql-form" id="sqlForm">
            <textarea 
                class="sql-textarea" 
                name="sql_query" 
                placeholder="SELECT * FROM users WHERE active = 1; -- Введите SQL запрос"
                rows="4"
            ></textarea>
            <div class="sql-buttons">
                <button type="submit" class="btn-execute" id="executeBtn">
                    <span class="material-icons">play_arrow</span>
                    Выполнить
                </button>
            </div>
        </form>
        <div class="result-container" id="resultContainer">
            <div style="color: var(--text-secondary);">Введите SQL запрос и нажмите "Выполнить"</div>
        </div>
    </div>

    <!-- Модальные окна -->
    <div id="warningModal" class="modal modal-warning">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">warning</span>
                </div>
                <div>
                    <h3 class="modal-title">Внимание!</h3>
                </div>
            </div>
            <p class="modal-description">Этот SQL-запрос содержит потенциально опасные команды (DROP, DELETE без WHERE). Вы уверены, что хотите продолжить выполнение?</p>
            <div class="modal-actions">
                <button class="btn-modal btn-confirm" id="confirmQuery">Выполнить</button>
                <button class="btn-modal btn-cancel" id="cancelQuery">Отмена</button>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal modal-danger">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">logout</span>
                </div>
                <div>
                    <h3 class="modal-title">Выход из системы</h3>
                </div>
            </div>
            <p class="modal-description">Вы действительно хотите завершить сеанс работы? Все несохраненные данные будут потеряны.</p>
            <div class="modal-actions">
                <button class="btn-modal btn-confirm" id="confirmLogout">Выйти</button>
                <button class="btn-modal btn-cancel" id="cancelLogout">Остаться</button>
            </div>
        </div>
    </div>

    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon" style="background: var(--success-color);">
                    <span class="material-icons">person_add</span>
                </div>
                <div>
                    <h3 class="modal-title">Добавить пользователя</h3>
                </div>
            </div>
            <div class="user-list" id="userList">
                <div style="color: var(--text-secondary); text-align: center; padding: 1rem;">
                    Загрузка существующих пользователей...
                </div>
            </div>
            <form id="addUserForm" class="sql-form">
                <div class="form-group">
                    <label class="form-label" for="username">Имя пользователя</label>
                    <input type="text" id="username" name="username" class="form-input" required 
                           placeholder="Введите имя пользователя" maxlength="32">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Пароль</label>
                    <input type="password" id="password" name="password" class="form-input" required 
                           placeholder="Введите пароль" minlength="6">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal('addUserModal')">Отмена</button>
                    <button type="submit" class="btn-modal btn-confirm">Создать</button>
                </div>
            </form>
            <div id="addUserResult"></div>
        </div>
    </div>

    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon" style="background: var(--danger-color);">
                    <span class="material-icons">person_remove</span>
                </div>
                <div>
                    <h3 class="modal-title">Удалить пользователя</h3>
                </div>
            </div>
            <div class="user-list" id="deleteUserList">
                <div style="color: var(--text-secondary); text-align: center; padding: 1rem;">
                    Загрузка существующих пользователей...
                </div>
            </div>
            <form id="deleteUserForm" class="sql-form">
                <div class="form-group">
                    <label class="form-label" for="delete-username">Имя пользователя</label>
                    <input type="text" id="delete-username" name="username" class="form-input" required 
                           placeholder="Введите имя пользователя для удаления" maxlength="32">
                </div>
                <div style="color: var(--danger-color); font-size: 0.875rem; margin-top: 0.5rem;">
                    ⚠️ Это действие необратимо и удалит пользователя с сервера
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal('deleteUserModal')">Отмена</button>
                    <button type="submit" class="btn-modal btn-confirm">Удалить</button>
                </div>
            </form>
            <div id="deleteUserResult"></div>
        </div>
    </div>

    <footer>
        <p>© 2025 Семкин Иван и Щегольков Максим. Все права защищены.</p>
    </footer>

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
    }

    // Автоматический выход при бездействии
    let inactivityTimer;
    const INACTIVITY_TIMEOUT = 10 * 60 * 1000; // 10 минут

    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            if (confirm('Сессия истекает из-за бездействия. Выйти из системы?')) {
                window.location.href = '?action=logout&confirm=yes';
            }
        }, INACTIVITY_TIMEOUT);
    }

    ['load', 'mousemove', 'mousedown', 'click', 'scroll', 'keypress'].forEach(event => {
        document.addEventListener(event, resetInactivityTimer, true);
    });

    // SQL Консоль (обновлённый обработчик с правильным параметром 'sql_query')
    const sqlForm = document.getElementById('sqlForm');
    const resultContainer = document.getElementById('resultContainer');
    const executeBtn = document.getElementById('executeBtn');
    let pendingQuery = null;

    sqlForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const sqlQuery = sqlForm.querySelector('textarea[name="sql_query"]').value.trim();
        
        if (!sqlQuery) {
            showMessage('Введите SQL запрос', 'error');
            return;
        }

        executeBtn.disabled = true;
        executeBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Выполняется...';

        try {
            const formData = new FormData();
            formData.append('sql_query', sqlQuery);  // ← Правильный параметр!

            const response = await fetch(window.location.href, {  // Отправка на index.php
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();  // Теперь парсим JSON

            if (data.status === 'warning') {
                pendingQuery = sqlQuery;
                document.getElementById('warningModal').style.display = 'flex';
            } else {
                showResult(data.message, data.status);
            }
        } catch (error) {
            showMessage(`Ошибка: ${error.message}`, 'error');
        } finally {
            executeBtn.disabled = false;
            executeBtn.innerHTML = '<span class="material-icons">play_arrow</span> Выполнить';
        }
    });

    function showResult(message, status) {
        resultContainer.innerHTML = message;
        resultContainer.className = `result-container ${status}`;
        resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showMessage(text, type) {
        resultContainer.innerHTML = `<div class="message message-${type}"><span class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</span>${text}</div>`;
    }

    // Подтверждение опасного запроса (с 'sql_query')
    document.getElementById('confirmQuery').addEventListener('click', async () => {
        if (!pendingQuery) return;

        executeBtn.disabled = true;
        executeBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Выполняется...';

        try {
            const formData = new FormData();
            formData.append('sql_query', pendingQuery);  // ← Правильный параметр!
            formData.append('confirmed', 'true');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            showResult(data.message, data.status);
            document.getElementById('warningModal').style.display = 'none';
            pendingQuery = null;
        } catch (error) {
            showMessage(`Ошибка: ${error.message}`, 'error');
            document.getElementById('warningModal').style.display = 'none';
        } finally {
            executeBtn.disabled = false;
            executeBtn.innerHTML = '<span class="material-icons">play_arrow</span> Выполнить';
        }
    });
    document.getElementById('cancelQuery').addEventListener('click', () => {
        document.getElementById('warningModal').style.display = 'none';
        pendingQuery = null;
        showMessage('Запрос отменен', 'warning');
    });

    // Переключение консоли (теперь для fixed panel)
    function toggleConsole() {
        const panel = document.getElementById('consolePanel');
        const icon = document.getElementById('consoleIcon');
        const text = document.getElementById('consoleText');
        
        if (panel.classList.contains('active')) {
            panel.classList.remove('active');
            icon.textContent = 'code';
            text.textContent = 'SQL Консоль';
        } else {
            panel.classList.add('active');
            icon.textContent = 'code_off';
            text.textContent = 'Закрыть';
            setTimeout(() => {
                document.querySelector('.sql-textarea').focus();
            }, 300);
        }
    }

        // Модальные окна
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }

        document.getElementById('confirmLogout').addEventListener('click', () => {
            window.location.href = '?action=logout&confirm=yes';
        });

        document.getElementById('cancelLogout').addEventListener('click', () => {
            closeModal('logoutModal');
        });

        // Закрытие модалок по клику вне
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Управление пользователями
        async function fetchUserList(containerId) {
            const container = document.getElementById(containerId);
            container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;"><span class="material-icons">hourglass_empty</span> Загрузка...</div>';

            try {
                const formData = new FormData();
                formData.append('action', 'get_users');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.error) {
                    container.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.error}</div>`;
                } else if (data.users && data.users.length > 0) {
                    container.innerHTML = data.users.map(user => 
                        `<div class="user-item"><span class="material-icons" style="font-size: 16px; opacity: 0.5;">person</span>${user}</div>`
                    ).join('');
                } else {
                    container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;">Пользователи не найдены</div>';
                }
            } catch (error) {
                container.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка загрузки: ${error.message}</div>`;
            }
        }

        function showAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
            fetchUserList('userList');
        }

        function showDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'flex';
            fetchUserList('deleteUserList');
        }

        // Формы пользователей
        document.getElementById('addUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add_user');
            
            const resultDiv = document.getElementById('addUserResult');
            resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Создание...</div>';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                    setTimeout(() => {
                        closeModal('addUserModal');
                        e.target.reset();
                        fetchUserList('userList');
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
            }
        });

        document.getElementById('deleteUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'delete_user');
            
            if (!confirm('Вы уверены? Это действие необратимо!')) return;
            
            const resultDiv = document.getElementById('deleteUserResult');
            resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Удаление...</div>';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                    setTimeout(() => {
                        closeModal('deleteUserModal');
                        e.target.reset();
                        fetchUserList('deleteUserList');
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
            }
        });

        // VNC подключение
        function launchRemmina() {
            window.open('vnc://172.17.0.250', '_blank');
            setTimeout(() => {
                alert('Если VNC не запустился автоматически:\n\n1. Установите Remmina: sudo apt install remmina remmina-plugin-vnc\n2. Или используйте TightVNC Viewer\n3. Адрес сервера: 172.17.0.250:5900');
            }, 500);
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
        resetInactivityTimer();
    });
</script>
</body>
</html>