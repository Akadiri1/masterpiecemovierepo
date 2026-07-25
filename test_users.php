<?php require 'c:/wamp64/www/masterpiecemovie/v1/models/model.php'; print_r($conn->query('SELECT user_id, count(*) FROM watch_history GROUP BY user_id')->fetchAll(PDO::FETCH_ASSOC)); ?>
