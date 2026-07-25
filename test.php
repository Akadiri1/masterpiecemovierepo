<?php require 'c:/wamp64/www/masterpiecemovie/v1/models/model.php'; print_r($conn->query('SELECT * FROM watch_history')->fetchAll(PDO::FETCH_ASSOC)); ?>
