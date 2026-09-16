<?php
// One season's episode list as HTML, for the season buttons in the Episodes
// section (includes/season-episodes.php).
// GET id=<TMDB show id>&season=<number>[&cs=<season playing>&ce=<episode playing>]

require_once APP_PATH . '/views/includes/season-episodes.php';
header('Content-Type: text/html; charset=utf-8');

$tvId = (int) ($_GET['id'] ?? 0);
$season = (int) ($_GET['season'] ?? -1);
if ($tvId <= 0 || $season < 0 || $season > 1000) {
    http_response_code(400);
    exit;
}

$data = function_exists('fetchTmdbApi') ? fetchTmdbApi("tv/{$tvId}/season/{$season}") : null;
if (!$data) {
    http_response_code(404);
    echo '<p class="se-empty">This season isn\'t available.</p>';
    exit;
}

$currentSeason = isset($_GET['cs']) ? (int) $_GET['cs'] : null;
$currentEpisode = isset($_GET['ce']) ? (int) $_GET['ce'] : null;
echo seasonEpisodeItems($tvId, $season, $data['episodes'] ?? [], $currentSeason, $currentEpisode);
