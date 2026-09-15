<?php
require 'v1/models/model.php';
$stmt = $conn->query("SELECT email, username, is_admin FROM users WHERE is_admin=1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
