<?php
require '.env/config.php';
require 'v1/models/model.php';
require 'v1/controllers/controller.php';

$endpoint = 'discover/movie';
$params = ['with_genres' => '10402', 'page' => 1];
$data = fetchTmdbApi($endpoint, $params);
echo "Music: " . count($data['results']) . " results\n";

$endpoint2 = 'discover/tv';
$params2 = ['with_genres' => '10759', 'page' => 1];
$data2 = fetchTmdbApi($endpoint2, $params2);
echo "Manga(TV Action): " . count($data2['results']) . " results\n";
?>
