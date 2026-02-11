<?php
// Вывод phpinfo()
phpinfo();

// Подключение к MySQL через PDO (для демонстрации)
try {
    $dsn = 'mysql:host=' . getenv('MYSQL_HOST') . ';dbname=' . getenv('MYSQL_DATABASE') . ';charset=utf8';
    $pdo = new PDO($dsn, getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h2>Подключение к БД успешно!</h2>";
    
    // Пример запроса: Создание таблицы (если нужно)
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_table (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))");
    echo "<p>Таблица создана или уже существует.</p>";
} catch (PDOException $e) {
    echo "<h2>Ошибка подключения: " . $e->getMessage() . "</h2>";
}
?>