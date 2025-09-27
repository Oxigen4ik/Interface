<?php
include 'db_connect.php';

$conn->set_charset("utf8mb4");

// Добавление нового официанта
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_waiter'])) {
    $name = isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : '';
    $surname = isset($_POST['surname']) ? htmlspecialchars($_POST['surname'], ENT_QUOTES, 'UTF-8') : '';
    $status = isset($_POST['status']) ? htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8') : '';

    $sql = "INSERT INTO Waiter (Name, Surname, Status) VALUES ('$name', '$surname', '$status')";
    if ($conn->query($sql) === TRUE) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Ошибка: " . $conn->error;
    }
}

// Удаление официанта
if (isset($_POST['delete_waiter']) && isset($_POST['id'])) {
    $delete_id = (int)$_POST['id'];
    $sql = "DELETE FROM Waiter WHERE ID = $delete_id";
    if ($conn->query($sql) === TRUE) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Ошибка: " . $conn->error;
    }
}

// Получение списка официантов
$sql = "SELECT * FROM Waiter";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Управление официантами</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
<div class="back-button-container">
    <a href="index.php" class="back-button">Назад</a>
</div>

<h1>Управление официантами</h1>
<form method="POST">
    <input type="text" name="name" placeholder="Имя" required>
    <input type="text" name="surname" placeholder="Фамилия" required>
    <input type="text" name="status" placeholder="Статус" required>
    <button type="submit" name="add_waiter">Добавить официанта</button>
</form>

<h2>Список официантов</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Имя</th>
        <th>Фамилия</th>
        <th>Статус</th>
        <th>Редактировать</th>
        <th>Удалить</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['ID']; ?></td>
        <td><?php echo htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Surname'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Status'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
            <a href="edit_waiter.php?id=<?php echo $row['ID']; ?>" class="edit-button">Редактировать</a>
        </td>
        <td>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <input type="hidden" name="id" value="<?php echo $row['ID']; ?>">
            <button type="submit" name="delete_waiter" class="delete-button" onclick="return confirm('Вы уверены, что хотите удалить этого официанта?');">Удалить</button>
        </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
