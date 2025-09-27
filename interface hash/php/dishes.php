<?php
include 'db_connect.php';

$conn->set_charset("utf8mb4");

// Добавление нового блюда
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_dish'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = $_POST['price'];

    // Добавление нового блюда (ID автоинкрементируется)
    $sql = "INSERT INTO Dishes (Name, Category, Price) VALUES ('$name', '$category', '$price')";
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
    $sql = "DELETE FROM Dishes WHERE ID = $delete_id";
    if ($conn->query($sql) === TRUE) {
        // Сброс автоинкремента после удаления записи
        $conn->query("ALTER TABLE Dishes AUTO_INCREMENT = 1");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

// Получение списка блюд
$conn->set_charset("utf8mb4");
$sql = "SELECT * FROM Dishes";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Изменить блюдо</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
<div class="back-button-container">
    <a href="index.php" class="back-button">Назад</a>
</div>

<h1>Изменить блюдо</h1>
<form method="POST">
    <input type="text" name="name" placeholder="Имя" required>
    <input type="text" name="category" placeholder="Категория" required>
    <input type="number" name="price" placeholder="Цена" required>
    <button type="submit" name="add_dish">Добавить блюдо</button>
</form>

<h2>Список блюд</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Имя</th>
        <th>Цена</th>
        <th>Категория</th>
        <th>Редактировать</th>
        <th>Удалить</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['ID']; ?></td>
        <td><?php echo htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Price'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
            <a href="edit_dishes.php?id=<?php echo $row['ID']; ?>" class="edit-button">Редактировать</a>
        </td>
        <td>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="id" value="<?php echo $row['ID']; ?>">
                <button type="submit" name="delete_dish" class="delete-button" onclick="return confirm('Вы уверены, что хотите удалить это блюдо?');">Удалить</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>