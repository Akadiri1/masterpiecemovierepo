<?php
// ==========================================
// 0. SETUP
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off display errors for JSON output
header('Content-Type: application/json');

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if (session_status() === PHP_SESSION_NONE) session_start();

// ==========================================
// 1. CONFIGURATION
// ==========================================

// Ensure your TMDB Key is defined
if (!defined('TMDB_API_KEY')) define('TMDB_API_KEY', 'YOUR_TMDB_API_KEY_HERE'); 
$geminiApiKey = "AIzaSyDE0XCHuKLjG9IVGaLsXCx0QrrYW_lgxFw"; 

// ==========================================
// 2. HELPER: TMDB API FETCH
// ==========================================
if (!function_exists('fetchTmdbApi')) {
    function fetchTmdbApi(string $endpoint, array $params = [], int $cacheDuration = 86400): ?array {
        $cacheDir = __DIR__ . '/../cache/tmdb/';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
        
        $cacheKey = md5($endpoint . http_build_query($params));
        $cacheFile = $cacheDir . $cacheKey . '.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheDuration) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        $baseUrl = 'https://api.themoviedb.org/3/';
        $defaultParams = ['api_key' => TMDB_API_KEY, 'language' => 'en-US'];
        $queryParams = http_build_query(array_merge($defaultParams, $params));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . $endpoint . '?' . $queryParams,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4 // Force IPv4
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        
        // Only cache valid responses
        if (json_last_error() === JSON_ERROR_NONE && !empty($data) && !isset($data['status_code'])) {
            if (is_dir($cacheDir) && is_writable($cacheDir)) file_put_contents($cacheFile, $response);
            return $data;
        }
        return null;
    }
}

// ==========================================
// 3. MAIN LOGIC
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get Input
    // Handle both raw JSON POST and standard Form Data
    $inputJSON = file_get_contents('php://input');
    $inputData = json_decode($inputJSON, true);
    $userQuery = trim($_POST['query'] ?? $inputData['query'] ?? '');

    if (empty($userQuery)) {
        echo json_encode(['status' => 'error', 'message' => 'Input is empty']);
        exit;
    }

    // 2. Build History (Session Context)
    if (!isset($_SESSION['chat_history'])) $_SESSION['chat_history'] = [];
    $geminiHistory = [];
    foreach ($_SESSION['chat_history'] as $turn) {
        if (!empty($turn['user']) && !empty($turn['ai_text'])) {
            $geminiHistory[] = ['role' => 'user', 'parts' => [['text' => $turn['user']]]];
            $geminiHistory[] = ['role' => 'model', 'parts' => [['text' => $turn['ai_text']]]];
        }
    }
    $geminiHistory[] = ['role' => 'user', 'parts' => [['text' => $userQuery]]];

    // 3. Prepare Prompt
    $systemInstruction = "
    You are CineBot. 
    1. Be friendly and concise.
    2. If the user describes a movie plot, actor, or genre, provide exactly 5 real movie/tv titles.
    3. IMPORTANT: Output strict JSON. Do not use Markdown blocks.
    Format:
    {
        \"reply\": \"Your conversational response here\",
        \"search_candidates\": [\"Movie 1\", \"Movie 2\"]
    }";

    $payload = [
        "contents" => $geminiHistory,
        "systemInstruction" => ["parts" => [["text" => $systemInstruction]]],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 800,
            "response_mime_type" => "application/json"
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $geminiApiKey;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 4. PARSE RESPONSE & HANDLE FALLBACK
    $cleanReply = "I'm having trouble connecting to my brain, but I'll search for that directly.";
    $suggestions = [];
    $aiFailed = false;

    if ($curlError) {
        $aiFailed = true;
    } else {
        $aiResult = json_decode($response, true);
        
        if (!empty($aiResult['candidates'][0]['content']['parts'][0]['text'])) {
            $rawText = $aiResult['candidates'][0]['content']['parts'][0]['text'];
            
            // Remove Markdown formatting if present
            $cleanText = preg_replace('/^```json\s*/i', '', $rawText);
            $cleanText = preg_replace('/^```\s*/i', '', $cleanText);
            $cleanText = preg_replace('/\s*```$/', '', $cleanText);
            
            $decoded = json_decode($cleanText, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $cleanReply = $decoded['reply'] ?? "Here is what I found.";
                $suggestions = $decoded['search_candidates'] ?? [];
            } else {
                // If JSON parsing fails, assume it's just text
                $cleanReply = strip_tags($cleanText);
            }
        } elseif (isset($aiResult['promptFeedback']['blockReason'])) {
            $cleanReply = "I cannot answer that because it triggered my safety filters.";
            $aiFailed = true; // Treat block as failure so we don't search blindly
        } else {
            $aiFailed = true;
        }
    }

    // --- CRITICAL FALLBACK LOGIC ---
    // If AI failed OR returned 0 suggestions, use the raw user query
    if (empty($suggestions) && !$aiFailed) {
        // AI worked but found nothing? Fallback search just in case.
        $cleanReply = "I couldn't think of specific titles, but let me try a direct search.";
    }

    if (empty($suggestions)) {
        // Strip conversational filler to make the search keyword-focused
        $stopwords = [' i ', ' want ', ' to ', ' watch ', ' a ', ' movie ', ' about ', ' looking ', ' for ', ' film ', ' show ', ' is ', ' that ', ' the '];
        $keywords = str_ireplace($stopwords, ' ', " " . $userQuery . " ");
        $keywords = trim(preg_replace('/\s+/', ' ', $keywords));
        
        // Add the cleaned query as the suggestion to search
        $suggestions[] = $keywords;
    }

    // Update History
    $_SESSION['chat_history'][] = ['user' => $userQuery, 'ai_text' => $cleanReply];
    if (count($_SESSION['chat_history']) > 10) array_shift($_SESSION['chat_history']);

    // 5. TMDB SEARCH (Iterate through suggestions)
    $finalMovies = [];
    $seenIds = [];
    $isKidsMode = isset($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'] === true;

    foreach ($suggestions as $title) {
        if (count($finalMovies) >= 5) break;
        if (empty($title)) continue;

        $searchData = fetchTmdbApi("search/multi", ['query' => $title]);

        if ($searchData && !empty($searchData['results'])) {
            foreach ($searchData['results'] as $item) {
                
                // Validate Media Type
                if (!isset($item['media_type']) || !in_array($item['media_type'], ['movie', 'tv'])) continue;
                
                // No Duplicates
                if (in_array($item['id'], $seenIds)) continue;

                // Kids Mode Filter
                if ($isKidsMode) {
                    $gids = $item['genre_ids'] ?? [];
                    // Must have Animation (16) or Family (10751)
                    if (!array_intersect([16, 10751], $gids)) continue;
                }

                $poster = !empty($item['poster_path']) 
                    ? "https://image.tmdb.org/t/p/w500" . $item['poster_path'] 
                    : "assets/images/media/robert.webp"; // Ensure this fallback exists in your JS or Path

                $finalMovies[] = [
                    'id' => $item['id'],
                    'type' => $item['media_type'],
                    'title' => $item['title'] ?? $item['name'],
                    'poster_path' => $poster,
                    'release_date' => $item['release_date'] ?? $item['first_air_date'] ?? 'N/A',
                    'rating' => $item['vote_average'] ?? 0
                ];

                $seenIds[] = $item['id'];
                break; // Take top result per suggestion to ensure variety
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'reply' => $cleanReply,
        'movies' => $finalMovies,
        'fallback_used' => empty($suggestions) || $aiFailed // Debug flag
    ]);
}
?>