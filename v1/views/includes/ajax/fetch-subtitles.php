<?php
header('Content-Type: application/json; charset=utf-8');

// Ensure error output doesn't corrupt JSON
error_reporting(0);
ini_set('display_errors', '0');

$imdbId = trim($_GET['imdb_id'] ?? '');
$tmdbId = trim($_GET['tmdb_id'] ?? '');
$mediaType = trim($_GET['type'] ?? 'movie');
$season = (int)($_GET['season'] ?? 1);
$episode = (int)($_GET['episode'] ?? 1);

// Helper function to resolve TMDB ID to IMDb ID if not supplied
if (empty($imdbId) && !empty($tmdbId)) {
    if (defined('TMDB_API_KEY')) {
        $endpoint = ($mediaType === 'tv') ? "tv/{$tmdbId}" : "movie/{$tmdbId}";
        $url = "https://api.themoviedb.org/3/{$endpoint}?api_key=" . TMDB_API_KEY . "&append_to_response=external_ids";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        if ($res) {
            $json = json_decode($res, true);
            $imdbId = $json['external_ids']['imdb_id'] ?? ($json['imdb_id'] ?? '');
        }
    }
}

if (empty($imdbId)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'IMDb ID not found for this title.',
        'subtitles' => []
    ]);
    exit;
}

// Stremio OpenSubtitles v3 endpoint
if ($mediaType === 'tv') {
    $stremioUrl = "https://opensubtitles-v3.strem.io/subtitles/series/{$imdbId}:{$season}:{$episode}.json";
} else {
    $stremioUrl = "https://opensubtitles-v3.strem.io/subtitles/movie/{$imdbId}.json";
}

$ch = curl_init($stremioUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'MasterpieceMovie/2.0');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (!$response || $httpCode !== 200) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to fetch subtitles from provider.',
        'subtitles' => []
    ]);
    exit;
}

$data = json_decode($response, true);
$rawList = $data['subtitles'] ?? [];

$langMap = [
    'eng' => 'English',
    'spa' => 'Spanish',
    'fre' => 'French',
    'fra' => 'French',
    'ger' => 'German',
    'deu' => 'German',
    'ita' => 'Italian',
    'por' => 'Portuguese',
    'pob' => 'Portuguese (Brazil)',
    'rus' => 'Russian',
    'ara' => 'Arabic',
    'hin' => 'Hindi',
    'jpn' => 'Japanese',
    'kor' => 'Korean',
    'chi' => 'Chinese',
    'zho' => 'Chinese',
    'tur' => 'Turkish',
    'pol' => 'Polish',
    'dut' => 'Dutch',
    'nld' => 'Dutch',
    'swe' => 'Swedish',
    'dan' => 'Danish',
    'fin' => 'Finnish',
    'nor' => 'Norwegian',
    'gre' => 'Greek',
    'ell' => 'Greek',
    'heb' => 'Hebrew',
    'ind' => 'Indonesian',
    'vie' => 'Vietnamese',
    'tha' => 'Thai',
    'cze' => 'Czech',
    'hun' => 'Hungarian',
    'rum' => 'Romanian',
    'ron' => 'Romanian',
    'ukr' => 'Ukrainian',
    'per' => 'Persian',
    'fas' => 'Persian',
    'ben' => 'Bengali',
    'tam' => 'Tamil',
    'tel' => 'Telugu',
    'mal' => 'Malayalam',
    'fil' => 'Filipino',
    'tgl' => 'Tagalog'
];

$subtitles = [];
foreach ($rawList as $item) {
    $code = strtolower($item['lang'] ?? 'unk');
    $name = $langMap[$code] ?? strtoupper($code);
    $release = $item['subtitleFileName'] ?? ($item['movieReleaseName'] ?? 'Subtitle');
    $downloadUrl = $item['url'] ?? '';

    if (empty($downloadUrl)) continue;

    // Detect if subtitle is for a WEB-DL / WEBRip release (best matches streaming servers)
    $isWeb = (bool) preg_match('/(web|web-?dl|web-?rip|amzn|netflix|nf|hulu|dsnp|disney|max|atvp|itunes)/i', $release);

    $subtitles[] = [
        'id'        => $item['id'] ?? uniqid(),
        'lang_code' => $code,
        'lang_name' => $name,
        'release'   => $release,
        'is_web'    => $isWeb,
        'proxy_url' => '/proxy-subtitle?url=' . urlencode($downloadUrl)
    ];
}

// Prioritize WEB-DL / streaming releases first within each language
usort($subtitles, function($a, $b) {
    if ($a['is_web'] !== $b['is_web']) {
        return $b['is_web'] ? 1 : -1;
    }
    return 0;
});

echo json_encode([
    'status'    => 'success',
    'imdb_id'   => $imdbId,
    'count'     => count($subtitles),
    'subtitles' => $subtitles
]);
