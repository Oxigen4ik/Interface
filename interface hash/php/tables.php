<?php
include 'db_connect.php';

$conn->set_charset("utf8mb4");

// Добавление нового стола
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tables'])) {
    $numberOfSeats = isset($_POST['numberOfSeats']) ? (int)$_POST['numberOfSeats'] : 0;

    $sql = "INSERT INTO Tables (NumberOfSeats) VALUES ('$numberOfSeats')";
    if ($conn->query($sql) === TRUE) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Ошибка: " . $conn->error;
    }
}

// Удаление стола
if (isset($_POST['delete_tables']) && isset($_POST['id'])) {
    $delete_id = (int)$_POST['id'];
    $sql = "DELETE FROM Tables WHERE ID = $delete_id";
    if ($conn->query($sql) === TRUE) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Ошибка: " . $conn->error;
    }
}

// Получение списка столов
$sql = "SELECT * FROM Tables";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Управление столами</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
<div class="back-button-container">
    <a href="index.php" class="back-button">Назад</a>
</div>

<h1>Управление столами</h1>
<form method="POST">
    <input type="number" name="numberOfSeats" placeholder="Количество мест" required>
    <button type="submit" name="add_tables">Добавить стол</button>
</form>

<h2>Список столов</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Количество мест</th>
        <th>Редактировать</th>
        <th>Удалить</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['ID']; ?></td>
        <td><?php echo htmlspecialchars($row['NumberOfSeats'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
            <a href="edit_tables.php?id=<?php echo $row['ID']; ?>" class="edit-button">Редактировать</a>
        </td>
        <td>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="id" value="<?php echo $row['ID']; ?>">
                <button type="submit" name="delete_tables" class="delete-button" onclick="return confirm('Вы уверены, что хотите удалить этот стол?');">Удалить</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
