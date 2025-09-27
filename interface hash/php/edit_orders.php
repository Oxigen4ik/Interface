<?php
include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

$conn->set_charset("utf8mb4");

// Проверяем, передан ли ID заказа
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p style='color: red;'>Ошибка: Не указан ID заказа для редактирования.</p>";
    exit();
}

$id = (int)$_GET['id'];

// Получаем данные заказа по ID
$sql = "SELECT * FROM Orders WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Ошибка: Заказ с таким ID не найден.</p>";
    exit();
}

$order = $result->fetch_assoc();

// Обновление заказа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_order'])) {
    $date = $_POST['date'];
    $time = $_POST['time'];
    $status = $_POST['status'];
    $reservationID = (int)$_POST['reservationID'];
    $ordersDetailsID = (int)$_POST['ordersDetailsID'];

    // Обновление заказа
    $update_sql = "UPDATE Orders SET Date = ?, Time = ?, Status = ?, ReservationID = ?, OrdersDetailsID = ? WHERE ID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssssii", $date, $time, $status, $reservationID, $ordersDetailsID, $id);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>Заказ успешно обновлён.</p>";
    } else {
        echo "<p style='color: red;'>Ошибка: Не удалось обновить заказ. " . $stmt->error . "</p>";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать заказ</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
    <h1>Редактировать заказ</h1>

    <form method="POST">
        <label>Дата:</label>
        <input type="date" name="date" value="<?php echo htmlspecialchars($order['Date'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Время:</label>
        <input type="time" name="time" value="<?php echo htmlspecialchars($order['Time'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Статус:</label>
        <input type="text" name="status" value="<?php echo htmlspecialchars($order['Status'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>ReservationID:</label>
        <input type="number" name="reservationID" value="<?php echo htmlspecialchars($order['ReservationID'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>OrdersDetailsID:</label>
        <input type="number" name="ordersDetailsID" value="<?php echo htmlspecialchars($order['OrdersDetailsID'], ENT_QUOTES, 'UTF-8'); ?>" required>

        <button type="submit" name="edit_order">Обновить заказ</button>
    </form>

    <a href="orders.php" class="back-button">Назад к списку заказов</a>
</body>
</html>
