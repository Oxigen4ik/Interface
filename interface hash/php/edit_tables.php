<?php
include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

$conn->set_charset("utf8mb4");

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p style='color: red;'>Ошибка: Не указан ID стола.</p>";
    exit();
}

$id = (int)$_GET['id'];

// Получаем данные стола
$sql = "SELECT * FROM Tables WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Ошибка: Стол с таким ID не найден.</p>";
    exit();
}

$table = $result->fetch_assoc();

// Обновление стола
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_table'])) {
    $numberOfSeats = (int)$_POST['numberOfSeats'];

    $update_sql = "UPDATE Tables SET NumberOfSeats = ? WHERE ID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ii", $numberOfSeats, $id);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>Стол успешно обновлён.</p>";
    } else {
        echo "<p style='color: red;'>Ошибка обновления: " . $stmt->error . "</p>";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать стол</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
    <h1>Редактировать стол</h1>

    <form method="POST">
        <label>Количество мест:</label>
        <input type="number" name="numberOfSeats" value="<?php echo htmlspecialchars($table['NumberOfSeats'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <button type="submit" name="edit_table">Обновить стол</button>
    </form>

    <a href="tables.php" class="back-button">Назад к списку столов</a>
</body>
</html>
