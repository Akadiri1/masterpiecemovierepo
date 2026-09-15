<?php
require 'c:\wamp64\www\masterpiecemovie\v1\models\model.php';
$stmt = $conn->query('SHOW COLUMNS FROM users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
