<?php
include 'db_connect.php';

$conn->set_charset("utf8mb4");

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Получаем текущие данные официанта
    $sql = "SELECT * FROM Waiter WHERE ID = $id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_waiter'])) {
        $name = isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : '';
        $surname = isset($_POST['surname']) ? htmlspecialchars($_POST['surname'], ENT_QUOTES, 'UTF-8') : '';
        $status = isset($_POST['status']) ? htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8') : '';

        $sql = "UPDATE Waiter SET Name = '$name', Surname = '$surname', Status = '$status' WHERE ID = $id";
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>Официант успешно обновлён.</p>";
        } else {
            echo "Ошибка: " . $conn->error;
        }
    }
} else {
    echo "ID официанта не передан.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Редактирование официанта</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
</head>
<body>

<h1>Редактирование официанта</h1>

<form method="POST">
    <input type="text" name="name" value="<?php echo htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8'); ?>" required>
    <input type="text" name="surname" value="<?php echo htmlspecialchars($row['Surname'], ENT_QUOTES, 'UTF-8'); ?>" required>
    <input type="text" name="status" value="<?php echo htmlspecialchars($row['Status'], ENT_QUOTES, 'UTF-8'); ?>" required>
    <button type="submit" name="update_waiter">Сохранить изменения</button>
</form>

<div class="back-button-container">
    <a href="waiter.php" class="back-button">Вернуться к официантам</a>
</div>

</body>
</html>
