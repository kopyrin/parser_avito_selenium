<?php


$link = mysqli_connect("127.0.0.1", "root", "", "avito2");

if (!$link) {
    echo "Ошибка: Невозможно установить соединение с MySQL." . PHP_EOL;
    echo "Код ошибки errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Текст ошибки error: " . mysqli_connect_error() . PHP_EOL;
    exit;
}

echo "Соединение с MySQL установлено!" . PHP_EOL;
echo "Информация о сервере: " . mysqli_get_host_info($link) . PHP_EOL;



if (!$resourseId = mysqli_query($link, "SET NAMES UTF8"))
    throw new Exception("MySQL: Unable to execute SQL: " .  $sqlString . ". Error (" . mysqli_errno() . "): " .  @mysqli_error(), self::EXECUTE_ERROR);

$sql = file_get_contents(__DIR__. '/install.sql');

if (!$resourseId = mysqli_query($link, $sql))
    throw new Exception("MySQL: Unable to execute SQL: " .  $sqlString . ". Error (" . mysqli_errno() . "): " .  @mysqli_error(), self::EXECUTE_ERROR);
    echo 'Ok'.PHP_EOL;

mysqli_close($link);
