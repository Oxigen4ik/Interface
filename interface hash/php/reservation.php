<?php
include 'db_connect.php';

$conn->set_charset("utf8mb4");

// Добавление нового бронирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_reservation'])) {
    $customerID = isset($_POST['customerID']) ? (int)$_POST['customerID'] : 0;
    $tablesID = isset($_POST['tablesID']) ? (int)$_POST['tablesID'] : 0;
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $time = isset($_POST['time']) ? $_POST['time'] : '';
    $waiterID = isset($_POST['waiterID']) ? (int)$_POST['waiterID'] : 0;
    $ordersID = isset($_POST['ordersID']) ? (int)$_POST['ordersID'] : 0;

    // Добавление новой брони (ID автоинкрементируется)
    $sql = "INSERT INTO Reservation (CustomerID, TablesID, Date, Time, WaiterID, OrdersID) VALUES ('$customerID', '$tablesID', '$date', '$time', '$waiterID', '$ordersID')";
    if ($conn->query($sql) === TRUE) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Удаление бронирования
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $sql = "DELETE FROM Reservation WHERE ID = $delete_id";
    if ($conn->query($sql) === TRUE) {
        $conn->query("ALTER TABLE Reservation AUTO_INCREMENT = 1");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

// Получение списка бронирований
$sql = "SELECT * FROM Reservation";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Управление бронированием</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
<div class="back-button-container">
    <a href="index.php" class="back-button">Назад</a>
</div>

<h1>Управление бронированием</h1>
<form method="POST">
    <input type="number" name="customerID" placeholder="CustomerID" required>
    <input type="number" name="tablesID" placeholder="TablesID" required>
    <input type="date" name="date" placeholder="Дата" required>
    <input type="time" name="time" placeholder="Время" required>
    <input type="number" name="waiterID" placeholder="WaiterID" required>
    <input type="number" name="ordersID" placeholder="OrdersID" required>
    <button type="submit" name="add_reservation">Добавить бронирование</button>
</form>

<h2>Список бронирований</h2>
<table>
    <tr>
        <th>ID</th>
        <th>CustomerID</th>
        <th>TablesID</th>
        <th>Дата</th>
        <th>Время</th>
        <th>WaiterID</th>
        <th>OrdersID</th>
        <th>Редактировать</th>
        <th>Удалить</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['ID']; ?></td>
        <td><?php echo htmlspecialchars($row['CustomerID'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['TablesID'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Date'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Time'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['WaiterID'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['OrdersID'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
            <a href="edit_reservation.php?id=<?php echo $row['ID']; ?>" class="edit-button">Редактировать</a>
        </td>
        <td>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="id" value="<?php echo $row['ID']; ?>">
                <button type="submit" name="delete_reservation" class="delete-button" onclick="return confirm('Вы уверены, что хотите удалить это блюдо?');">Удалить</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
