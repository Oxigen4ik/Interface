<?php
include 'db_connect.php';

$conn->set_charset("utf8mb4");

// Добавление нового заказа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_orders'])) {
    $date = isset($_POST['date']) ? $_POST['date'] : '';
        $time = isset($_POST['time']) ? $_POST['time'] : '';
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        $reservationID = isset($_POST['reservationID']) ? (int)$_POST['reservationID'] : 0;
        $ordersDetailsID = isset($_POST['ordersdetailsID']) ? (int)$_POST['ordersdetailsID'] : 0;

    // Добавление нового заказа (ID автоинкрементируется)
    $sql = "INSERT INTO Orders (Date, Time, status, ReservationID, OrdersDetailsID) VALUES ('$date', '$time', '$status', '$reservationID', '$ordersDetailsID')";
    if ($conn->query($sql) === TRUE) {
        // Редирект после успешного добавления блюда, чтобы избежать повторной отправки формы
        header("Location: " . $_SERVER['PHP_SELF']);
        exit(); // Завершаем выполнение скрипта
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Удаление блюда
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $sql = "DELETE FROM Orders WHERE ID = $delete_id";
    if ($conn->query($sql) === TRUE) {
        // Сброс автоинкремента после удаления записи
        $conn->query("ALTER TABLE Orders AUTO_INCREMENT = 1");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

// Получение списка заказов
$conn->set_charset("utf8mb4");
$sql = "SELECT * FROM Orders";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Изменить заказ</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
<div class="back-button-container">
    <a href="index.php" class="back-button">Назад</a>
</div>

<h1>Изменить заказ</h1>
<form method="POST">
    <input type="date" name="date" placeholder="Дата" required>
    <input type="time" name="time" placeholder="Время" required>
    <input type="text" name="statis" placeholder="Статус" required>
    <input type="number" name="reservationID" placeholder="ReservationID" required>
    <input type="number" name="ordersDetailsID" placeholder="OrdersDetailsID" required>
    <button type="submit" name="add_orders">Добавить заказ</button>
</form>

<h2>Список заказов</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Дата</th>
        <th>Время</th>
        <th>Статус</th>
        <th>ReservationID</th>
        <th>OrdersDetailsID</th>
        <th>Редактировать</th>
        <th>Удалить</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['ID']; ?></td>
        <td><?php echo htmlspecialchars($row['Date'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Time'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Status'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['ReservationID'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['OrdersDetailsID'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
            <a href="edit_orders.php?id=<?php echo $row['ID']; ?>" class="edit-button">Редактировать</a>
        </td>
        <td>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="id" value="<?php echo $row['ID']; ?>">
                <button type="submit" name="delete_orders" class="delete-button" onclick="return confirm('Вы уверены, что хотите удалить это блюдо?');">Удалить</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>