<?php
$conn = new PDO("mysql:host=localhost;dbname=masterpiecemovie;charset=utf8mb4", "root", "");
$stmt = $conn->query("DESCRIBE reviews");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $conn->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
