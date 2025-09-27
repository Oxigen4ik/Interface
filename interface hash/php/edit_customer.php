<?php
include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

// Устанавливаем кодировку соединения с базой данных
$conn->set_charset("utf8mb4");

// Проверка, что передан параметр ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p style='color: red;'>Ошибка: Не указан ID клиента для редактирования.</p>";
    exit();
}

$id = $_GET['id'];

// Получаем данные клиента по ID
$sql = "SELECT * FROM Customer WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

// Если клиент не найден
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Ошибка: Клиент с таким ID не найден.</p>";
    exit();
}

$customer = $result->fetch_assoc();

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_customer'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];

    // Обновление данных повара
    $update_sql = "UPDATE Customer SET Name = ?, Phone = ? WHERE ID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssi", $name, $phone, $id);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>Данные клиента обновлены успешно.</p>";
    } else {
        echo "<p style='color: red;'>Ошибка: Не удалось обновить данные клиента. Детали ошибки: " . $stmt->error . "</p>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать клиента</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
    <h1>Редактировать данные клиента</h1>
    
    <form method="POST">
        <input type="number" name="id" value="<?php echo $customer['ID']; ?>" readonly>
        <input type="text" name="name" value="<?php echo htmlspecialchars($customer['Name'], ENT_QUOTES, 'UTF-8'); ?>" required>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($customer['Phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
        <button type="submit" name="edit_customer">Обновить данные</button>
    </form>

    <a href="customer.php" class="back-button">Назад к списку клиентов</a>
</body>
</html>
