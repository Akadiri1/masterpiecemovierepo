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

// Authentication Check
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Please login to use ZEN AI.']);
    exit;
}

// Daily Limit Check
if (isset($conn)) {
    $limitStmt = $conn->prepare("SELECT COUNT(id) FROM zen_search_history WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $limitStmt->execute([$userId]);
    $dailyCount = (int) $limitStmt->fetchColumn();
    
    // Set Limit: 10 per day
    if ($dailyCount >= 10) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'You have reached your daily limit of 10 AI requests. Please try again tomorrow!'
        ]);
        exit;
    }
}

// ==========================================
// 1. CONFIGURATION
// ==========================================

// Ensure your TMDB Key is defined
if (!defined('TMDB_API_KEY')) define('TMDB_API_KEY', 'YOUR_TMDB_API_KEY_HERE'); 

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

    $cid = $_POST['conversation_id'] ?? 'default_cid';

    // 2. Build History (Session Context keyed by Conversation ID)
    if (!isset($_SESSION['chat_history'])) $_SESSION['chat_history'] = [];
    if (!isset($_SESSION['chat_history'][$cid])) $_SESSION['chat_history'][$cid] = [];
    $messages = [];
    
    // System Instruction
    $messages[] = [
        'role' => 'system',
        'content' => "You are ZEN AI, the ultimate movie and TV show recommendation assistant for a streaming platform called Masterpiece Movie.

RULES:
1. Be friendly, helpful, and conversational. Keep your reply concise but engaging.
2. When the user asks for movie or TV show recommendations by genre (e.g. \"shooting movies\", \"scary movies\", \"psychology thrillers\"), provide 5 to 8 well-known, popular, REAL titles that match.
3. When the user describes a movie they forgot (e.g. \"a movie where a guy is stuck in a time loop\"), try to identify the exact title(s) they are thinking of.
4. When the user says \"hello\" or makes general conversation, respond naturally. Still include 3-5 popular trending movie suggestions in search_candidates.
5. ONLY suggest REAL movies and TV shows that actually exist. Never invent fake titles.
6. For \"shooting\" movies, think action/gun/war films like John Wick, Heat, The Departed, Sicario etc. NOT sports shooting.
7. Always prefer well-known English-language titles unless the user asks for a specific language.
8. The search_candidates array should contain ONLY the exact title of the movie or show (no year, no parentheses, no extra text). Example: \"Inception\" not \"Inception (2010)\".
9. DO NOT output your internal thought process. Provide only your final, clean answer.

You MUST respond with valid JSON only. No markdown. No code blocks.
{
    \"reply\": \"Your friendly conversational response explaining your picks.\",
    \"search_candidates\": [\"Movie Title 1\", \"Movie Title 2\", \"Movie Title 3\"]
}"
    ];

    foreach ($_SESSION['chat_history'][$cid] as $turn) {
        if (!empty($turn['user']) && !empty($turn['ai_text'])) {
            $messages[] = ['role' => 'user', 'content' => $turn['user']];
            $messages[] = ['role' => 'assistant', 'content' => $turn['ai_text']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userQuery];

    // 3. Prepare Prompt for Groq
    $groqApiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
    
    $payload = [
        "model" => "llama-3.3-70b-versatile",
        "messages" => $messages,
        "temperature" => 0.7,
        "max_tokens" => 800
    ];

    $url = "https://api.groq.com/openai/v1/chat/completions";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $groqApiKey
        ],
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

    if ($curlError || empty($response)) {
        $aiFailed = true;
    } else {
        $aiResult = json_decode($response, true);
        
        if (!empty($aiResult['choices'][0]['message']['content'])) {
            $rawText = trim($aiResult['choices'][0]['message']['content']);
            
            // Strategy 1: Try direct JSON decode
            $decoded = json_decode($rawText, true);
            
            // Strategy 2: Strip markdown code blocks and try again
            if (json_last_error() !== JSON_ERROR_NONE) {
                $stripped = preg_replace('/^```(?:json)?\s*/im', '', $rawText);
                $stripped = preg_replace('/\s*```\s*$/m', '', $stripped);
                $decoded = json_decode(trim($stripped), true);
            }
            
            // Strategy 3: Extract the first JSON object {...} from anywhere in the text
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (preg_match('/\{[\s\S]*"reply"[\s\S]*"search_candidates"[\s\S]*\}/U', $rawText, $jsonMatch)) {
                    // Find the full balanced JSON object
                    $start = strpos($rawText, '{');
                    if ($start !== false) {
                        $depth = 0;
                        $end = $start;
                        for ($i = $start; $i < strlen($rawText); $i++) {
                            if ($rawText[$i] === '{') $depth++;
                            if ($rawText[$i] === '}') $depth--;
                            if ($depth === 0) { $end = $i; break; }
                        }
                        $jsonStr = substr($rawText, $start, $end - $start + 1);
                        $decoded = json_decode($jsonStr, true);
                    }
                }
            }
            
            // Apply parsed result
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $cleanReply = $decoded['reply'] ?? "Here is what I found.";
                $suggestions = $decoded['search_candidates'] ?? [];
            } else {
                // Strategy 4: AI responded with plain text - use it as reply and extract titles
                $cleanReply = strip_tags($rawText);
                // Try to extract quoted titles from the text
                if (preg_match_all('/"([^"]{2,50})"/', $rawText, $titleMatches)) {
                    $suggestions = array_slice($titleMatches[1], 0, 8);
                }
                // Also try titles after numbered lists (1. Title, 2. Title)
                if (empty($suggestions) && preg_match_all('/\d+\.\s*\*{0,2}([A-Z][^\n\r*]{3,50})/', $rawText, $listMatches)) {
                    $suggestions = array_map('trim', array_slice($listMatches[1], 0, 8));
                }
            }
        } elseif (isset($aiResult['error'])) {
            $cleanReply = "API Error: " . ($aiResult['error']['message'] ?? 'Unknown Error');
            $aiFailed = true;
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

    // Update History specifically for this conversation
    $_SESSION['chat_history'][$cid][] = ['user' => $userQuery, 'ai_text' => $cleanReply];
    if (count($_SESSION['chat_history'][$cid]) > 10) array_shift($_SESSION['chat_history'][$cid]);

    // 5. TMDB SEARCH (Iterate through suggestions)
    $finalMovies = [];
    $seenIds = [];
    $isKidsMode = isset($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'] === true;

    foreach ($suggestions as $title) {
        if (count($finalMovies) >= 10) break;
        if (empty($title)) continue;

        $searchData = fetchTmdbApi("search/multi", ['query' => $title]);

        if ($searchData && !empty($searchData['results'])) {
            $matchCount = 0;
            foreach ($searchData['results'] as $item) {
                if ($matchCount >= 2) break; // Take top 2 results per suggestion for variety
                if (count($finalMovies) >= 10) break;
                
                // Validate Media Type
                if (!isset($item['media_type']) || !in_array($item['media_type'], ['movie', 'tv'])) continue;
                
                // No Duplicates
                if (in_array($item['id'], $seenIds)) continue;

                // Kids Mode Filter
                if ($isKidsMode) {
                    $gids = $item['genre_ids'] ?? [];
                    if (!array_intersect([16, 10751], $gids)) continue;
                }

                $poster = !empty($item['poster_path']) 
                    ? "https://image.tmdb.org/t/p/w500" . $item['poster_path'] 
                    : "assets/images/media/robert.webp";

                $finalMovies[] = [
                    'id' => $item['id'],
                    'type' => $item['media_type'],
                    'title' => $item['title'] ?? $item['name'],
                    'poster_path' => $poster,
                    'release_date' => $item['release_date'] ?? $item['first_air_date'] ?? 'N/A',
                    'rating' => $item['vote_average'] ?? 0
                ];

                $seenIds[] = $item['id'];
                $matchCount++;
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