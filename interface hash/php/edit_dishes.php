<?php
include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

// Устанавливаем кодировку соединения с базой данных
$conn->set_charset("utf8mb4");

// Проверка, что передан параметр ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p style='color: red;'>Ошибка: Не указан ID блюда для редактирования.</p>";
    exit();
}

$id = $_GET['id'];

// Получаем данные блюда по ID
$sql = "SELECT * FROM Dishes WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

// Если блюдо не найдено
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Ошибка: Блюдо с таким ID не найдено.</p>";
    exit();
}

$dish = $result->fetch_assoc();

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_dish'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = $_POST['price'];

    // Обновление данных блюда
    $update_sql = "UPDATE Dishes SET Name = ?, Category = ?, Price = ? WHERE ID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssdi", $name, $category, $price, $id);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>Данные блюда обновлены успешно.</p>";
    } else {
        echo "<p style='color: red;'>Ошибка: Не удалось обновить данные блюда. Детали ошибки: " . $stmt->error . "</p>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать блюдо</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
    <h1>Редактировать блюдо</h1>
    
    <form method="POST">
        <input type="number" name="id" value="<?php echo $dish['ID']; ?>" readonly>
        <input type="text" name="name" value="<?php echo htmlspecialchars($dish['Name'], ENT_QUOTES, 'UTF-8'); ?>" required>
        <input type="text" name="category" value="<?php echo htmlspecialchars($dish['Category'], ENT_QUOTES, 'UTF-8'); ?>" required>
        <input type="number" name="price" value="<?php echo htmlspecialchars($dish['Price'], ENT_QUOTES, 'UTF-8'); ?>" required>
        <button type="submit" name="edit_dish">Обновить блюдо</button>
    </form>

    <a href="index.php" class="back-button">Назад к списку блюд</a>
</body>
</html>
