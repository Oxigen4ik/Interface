<?php
include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_customer'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];

    $check_sql = "SELECT ID FROM Customer WHERE ID = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<p style='color: red;'>Ошибка: Клиент с таким ID уже существует.</p>";
    } else {
        $sql = "INSERT INTO Customer (ID, Name, Phone) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $id, $name, $phone);

        if ($stmt->execute()) {
            header("Location: customer.php");
            exit();
        } else {
            echo "<p style='color: red;'>Ошибка: Не удалось добавить клиента. Детали ошибки: " . $stmt->error . "</p>";
        }
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_customer'])) {
    $id = $_POST['id'];
    $delete_sql = "DELETE FROM Customer WHERE ID = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: customer.php");
        exit();
    } else {
        echo "<p style='color: red;'>Ошибка: Не удалось удалить клиента. Детали ошибки: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

$sql = "SELECT * FROM Customer";
$result = $conn->query($sql);
if (!$result) {
    die("Ошибка получения данных клиентов: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Изменить клиентов</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>
    <div class="back-button-container">
        <a href="index.php" class="back-button">Назад</a>
    </div>

    <h1>Изменить клиентов</h1>

    <form method="POST">
        <input type="number" name="id" placeholder="ID" required>
        <input type="text" name="name" placeholder="Имя" required>
        <input type="text" name="phone" placeholder="Телефон" required>
        <button type="submit" name="add_customer">Добавить клиента</button>
    </form>

    <h2>Список клиентов</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Имя</th>
            <th>Телефон</th>
            <th>Изменить</th>
            <th>Удалить</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['ID']; ?></td>
            <td><?php echo htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($row['Phone'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><a href="edit_customer.php?id=<?php echo $row['ID']; ?>">Редактировать</a></td>
            <td>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="id" value="<?php echo $row['ID']; ?>">
                    <button type="submit" name="delete_customer" onclick="return confirm('Вы уверены, что хотите удалить этого клиента?');">Удалить</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
