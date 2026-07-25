<?php
require 'c:/wamp64/www/masterpiecemovie/.env/config.php';
require 'c:/wamp64/www/masterpiecemovie/v1/models/model.php';
print_r($conn->query('SHOW CREATE TABLE watch_history')->fetchAll(PDO::FETCH_ASSOC));
?>
