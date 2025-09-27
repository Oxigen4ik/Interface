<?php
include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

$conn->set_charset("utf8mb4");

// Проверяем, передан ли ID бронирования
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p style='color: red;'>Ошибка: Не указан ID бронирования для редактирования.</p>";
    exit();
}

$id = (int)$_GET['id'];

// Получаем данные бронирования по ID
$sql = "SELECT * FROM Reservation WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Ошибка: Бронирование с таким ID не найдено.</p>";
    exit();
}

$reservation = $result->fetch_assoc();

// Обновление бронирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_reservation'])) {
    $customerID = (int)$_POST['customerID'];
    $tablesID = (int)$_POST['tablesID'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $waiterID = (int)$_POST['waiterID'];
    $ordersID = (int)$_POST['ordersID'];

    // Обновление бронирования
    $update_sql = "UPDATE Reservation SET CustomerID = ?, TablesID = ?, Date = ?, Time = ?, WaiterID = ?, OrdersID = ? WHERE ID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iissiii", $customerID, $tablesID, $date, $time, $waiterID, $ordersID, $id);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>Бронирование успешно обновлено.</p>";
    } else {
        echo "<p style='color: red;'>Ошибка: Не удалось обновить бронирование. " . $stmt->error . "</p>";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать бронирование</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
    <h1>Редактировать бронирование</h1>

    <form method="POST">
        <label>ID клиента:</label>
        <input type="number" name="customerID" value="<?php echo htmlspecialchars($reservation['CustomerID'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>ID стола:</label>
        <input type="number" name="tablesID" value="<?php echo htmlspecialchars($reservation['TablesID'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Дата:</label>
        <input type="date" name="date" value="<?php echo htmlspecialchars($reservation['Date'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Время:</label>
        <input type="time" name="time" value="<?php echo htmlspecialchars($reservation['Time'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>ID официанта:</label>
        <input type="number" name="waiterID" value="<?php echo htmlspecialchars($reservation['WaiterID'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>ID заказа:</label>
        <input type="number" name="ordersID" value="<?php echo htmlspecialchars($reservation['OrdersID'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <button type="submit" name="edit_reservation">Обновить бронирование</button>
    </form>

    <a href="reservation.php" class="back-button">Назад к списку бронирований</a>
</body>
</html>