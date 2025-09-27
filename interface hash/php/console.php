<?php
include('db_connect.php'); // Подключение к базе данных

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    $sql = $_POST['sql'];

    // Выполнение SQL-запроса
    $result = mysqli_query($conn, $sql);

    if ($result) {
        // Если результат запроса — выборка (SELECT, SHOW, DESCRIBE и т.п.)
        if ($result instanceof mysqli_result) {
            // Формируем таблицу без встроенных стилей, указываем только класс:
            echo "<table class='styled-table'>";

            // Получаем названия столбцов
            echo "<thead><tr>";
            $fields = mysqli_fetch_fields($result);
            foreach ($fields as $field) {
                echo "<th>" . htmlentities($field->name) . "</th>";
            }
            echo "</tr></thead><tbody>";

            // Перемещаем указатель на первую строку результата (если нужно)
            mysqli_data_seek($result, 0);

            // Выводим строки
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlentities($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            // Если запрос не вернул строк (например, UPDATE, INSERT, DELETE)
            echo "Запрос выполнен успешно, затронуто строк: " . mysqli_affected_rows($conn);
        }
    } else {
        // Если возникла ошибка при выполнении запроса
        echo "Ошибка выполнения запроса: " . mysqli_error($conn);
    }
}
?>
