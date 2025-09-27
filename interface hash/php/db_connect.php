<?php
$host = '134.90.167.42'; // Замените на предоставленный IP-адрес
$port = '10306'; // Замените на предоставленный порт
$db = 'project_Shchegolkov';
$user = 'Shchegolkov'; // Логин
$pass = 'n1_PQo'; // Пароль

$conn = new mysqli($host, $user, $pass, $db, $port); // Добавляем порт в конструктор

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
