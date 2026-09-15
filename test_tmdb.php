<?php
require '.env/config.php';
require 'v1/models/model.php';
require 'v1/controllers/controller.php';
$res = fetchTmdbApi('discover/movie', ['with_genres' => '10402']);
echo count($res['results']);
$res2 = fetchTmdbApi('discover/tv', ['with_genres' => '10759']);
echo " - ".count($res2['results']);
?>
