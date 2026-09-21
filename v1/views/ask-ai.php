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
    // Get User AI Token Limit & Admin Status
    $userStmt = $conn->prepare("SELECT is_admin, ai_tokens_limit FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    $isAdminUser = $userData ? (bool)$userData['is_admin'] : false;
    $aiTokensLimit = $userData ? (int)($userData['ai_tokens_limit'] ?? 10) : 10;

    $limitStmt = $conn->prepare("SELECT COUNT(id) FROM zen_search_history WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $limitStmt->execute([$userId]);
    $dailyCount = (int) $limitStmt->fetchColumn();
    
    // Admins and users with limit -1 have unlimited access
    if (!$isAdminUser && $aiTokensLimit !== -1) {
        if ($dailyCount >= $aiTokensLimit) {
            echo json_encode([
                'status' => 'error', 
                'message' => "You have reached your daily limit of {$aiTokensLimit} AI requests. Please try again tomorrow!"
            ]);
            exit;
        }
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
            // Address family is decided by app_apply_tls() below. Forcing IPv4
            // here made the connect hang on an IPv6-preferring host.
        ]);
        // Verification stays on; app_apply_tls supplies the CA bundle that
        // WAMP leaves unconfigured.
        require_once __DIR__ . '/../lib/tls.php';
        app_apply_tls($ch);

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
    
    $isKidsMode = isset($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'] === true;
    
    $kidsInstruction = $isKidsMode 
        ? "\n10. CRITICAL: The user is currently in KIDS MODE. You MUST adopt a child-friendly, safe, and positive tone. You MUST ONLY recommend movies and shows that are rated PG-13, TV-14, or lower. NEVER recommend any Horror, Crime, Thriller, War, R-rated, TV-MA, or explicit adult content. If the user asks for something inappropriate, politely steer them towards family-friendly or teen-safe alternatives."
        : "";

    // Ground the assistant in this site's own data: who is watching, and what
    // the platform actually holds files for. Both return an empty string when
    // there is nothing to say, so the prompt never asserts data that does not
    // exist. See v1/lib/ai_context.php for the privacy note on watch history.
    require_once __DIR__ . '/../lib/ai_context.php';
    $viewerContext  = zen_user_context($conn ?? null, (int) $userId);
    $catalogContext = zen_catalog_context($conn ?? null);

    // What the conversation was already about, so "how did it end?" is looked
    // up against the right title.
    $lastTitles = $_SESSION['chat_last_titles'][$cid] ?? [];
    $factContext = zen_query_facts($userQuery, $lastTitles);

    $kidsRule = $isKidsMode
        ? "\n- KIDS MODE IS ON. Only suggest titles rated PG-13 / TV-14 or lower, and never horror, crime, thriller, war, R or TV-MA. Keep the tone warm and simple. If asked for something unsuitable, offer a family-friendly alternative instead."
        : "";

    // System Instruction
    $messages[] = [
        'role' => 'system',
        'content' => "You are ZEN AI, the film and TV guide for a streaming site called ZEN. Today is "
            . date('l, j F Y') . ".

HOW TO ANSWER
- Talk like a person: warm, direct, no lists of rules, no repeating the question back. Two or three sentences is usually plenty.
- This is a conversation. Use what was said earlier; when the viewer says \"it\", \"that one\" or \"the second one\", they mean what you were just discussing.
- Answer the question that was actually asked. If they ask when something comes out, give the date. If they ask what it is about, describe it. Only recommend titles when they ask for recommendations.
- Use the TMDB facts below when they are relevant; they are current and your memory is not. If TMDB has nothing for a title and you do not know it either, say so plainly rather than inventing a plot, a cast or a date.
- Never invent a film, show, cast member or release date. If TMDB has nothing on a title but you know it well, answer from what you know and say the details may be out of date; if you do not know it either, say so plainly. When you are guessing, say that you are.
- If the viewer describes a film they cannot name, give your best two or three guesses, say they are guesses, and ask for one more detail.
- Refuse pornographic or sexually explicit requests in one short line, with no suggestions.

ABOUT THIS SITE
- ZEN shows where each title can be watched legally (Netflix, Prime Video and so on), plays its trailer, and plays some films in full for free where the rights allow it.
- Never say which service carries a title, or that ZEN plays it, unless the facts below say so. Say instead that the title's page on ZEN shows where to watch it. Never promise that a title will be added later.$kidsRule$catalogContext$viewerContext$factContext

WHAT TO PUT IN search_candidates
- The exact titles of films or shows the viewer should see cards for, newest-style spelling, no year and no extra words: \"Inception\", not \"Inception (2010)\".
- When they name a title, put that exact full title first, even if you are unsure it exists: \"Spider-Man: Brand New Day\", not \"Spider-Man\".
- Leave it as [] for chat, greetings, follow-up questions about something already shown, and refusals.

Reply with JSON only. No markdown, no code fences:
{\"reply\": \"what you say to the viewer\", \"search_candidates\": [\"Exact Title\"]}"
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
        // Configured in .env/config.php -- Groq retires models periodically and
        // a retired name returns HTTP 404 for every request.
        "model" => defined('AI_MODEL_CHAT') ? AI_MODEL_CHAT : 'openai/gpt-oss-120b',
        "messages" => $messages,
        "temperature" => 0.4, // facts over flourish
        // gpt-oss reasons before answering and bills that against max_tokens.
        // Without headroom the JSON contract comes back empty or truncated,
        // which silently drops the reply into the fallback path below.
        "reasoning_effort" => "medium",
        "max_tokens" => 1600
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
        // Forcing IPv4 here made every request to Groq time out at connect on
        // an IPv6-preferring host, so the chat always fell back. Address family
        // is now left to curl (override with HTTP_FORCE_IPV4 in config).
        //
        // Without a timeout a hung upstream pins an Apache worker until the
        // request is killed.
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    // This request carries the Groq API key, so peer verification must be on.
    require_once __DIR__ . '/../lib/tls.php';
    app_apply_tls($ch);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 4. PARSE RESPONSE & HANDLE FALLBACK
    $cleanReply = "I couldn't reach my brain just now, so here is what the search turned up. Ask me again in a moment.";
    $suggestions = [];
    $aiFailed = false;
    $aiExplicitEmpty = false;

    if ($curlError || empty($response)) {
        $aiFailed = true;
    } else {
        $aiResult = json_decode($response, true);
        
        if (!empty($aiResult['choices'][0]['message']['content'])) {
            $rawText = trim($aiResult['choices'][0]['message']['content']);
            
            // Fix: If LLM returned fields without outer braces, wrap them
            if (strpos($rawText, '{') !== 0 && (strpos($rawText, '"reply"') === 0 || strpos($rawText, '"search_candidates"') === 0)) {
                $rawText = '{' . $rawText . '}';
            }
            
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
                if (isset($decoded['search_candidates']) && is_array($decoded['search_candidates'])) {
                    $aiExplicitEmpty = true;
                }
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
    // If AI failed (meaning no valid JSON decoded), fallback to use raw user query keywords
    if (empty($suggestions) && !$aiExplicitEmpty) {
        $aiFailed = true;
    }

    if ($aiFailed) {
        $cleanReply = "I couldn't reach my brain just now, so here is what the search turned up. Ask me again in a moment.";
        
        // Strip conversational filler to make the search keyword-focused
        $stopwords = [' i ', ' want ', ' to ', ' watch ', ' a ', ' movie ', ' about ', ' looking ', ' for ', ' film ', ' show ', ' is ', ' that ', ' the '];
        $keywords = str_ireplace($stopwords, ' ', " " . $userQuery . " ");
        $keywords = trim(preg_replace('/\s+/', ' ', $keywords));
        
        // Add the cleaned query as the suggestion to search
        $suggestions[] = $keywords;
    }

    // Update History specifically for this conversation
    $_SESSION['chat_history'][$cid][] = ['user' => $userQuery, 'ai_text' => $cleanReply];
    if (!empty($suggestions) && !$aiFailed) {
        // What "it" or "the second one" refers to in the next question.
        $_SESSION['chat_last_titles'][$cid] = array_slice(array_values(array_filter(array_map('strval', $suggestions))), 0, 3);
    }
    while (count($_SESSION['chat_history'][$cid]) > 12) array_shift($_SESSION['chat_history'][$cid]);

    // 5. TMDB SEARCH (Iterate through suggestions)
    $finalMovies = [];
    $seenIds = [];
    $isKidsMode = isset($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'] === true;

    foreach ($suggestions as $title) {
        if (count($finalMovies) >= 10) break;
        if (empty($title)) continue;

        $searchData = fetchTmdbApi("search/multi", ['query' => $title, 'include_adult' => 'false']);

        if ($searchData && !empty($searchData['results'])) {
            $matchCount = 0;
            foreach ($searchData['results'] as $item) {
                if ($matchCount >= 1) break; // Take ONLY the top 1 result per suggestion to avoid parodies/duplicates
                if (count($finalMovies) >= 10) break;
                
                // Validate Media Type
                if (!isset($item['media_type']) || !in_array($item['media_type'], ['movie', 'tv'])) continue;
                
                // No Duplicates
                if (in_array($item['id'], $seenIds)) continue;

                // Kids Mode Filter
                if ($isKidsMode) {
                    if (isset($item['adult']) && $item['adult'] === true) continue;
                    $gids = $item['genre_ids'] ?? [];
                    // Block Horror(27), Crime(80), Thriller(53), War(10768)
                    if (array_intersect([27, 80, 53, 10768], $gids)) continue;
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

    // Mark which of these the platform genuinely holds a file for, so the card
    // can say so. One query for the whole result set rather than per card.
    $availability = zen_availability($conn ?? null, $finalMovies);
    foreach ($finalMovies as &$m) {
        $key = $m['id'] . '|' . $m['type'];
        $m['available'] = isset($availability[$key]);
        $m['qualities'] = $availability[$key]['qualities'] ?? '';
    }
    unset($m);

    echo json_encode([
        'status' => 'success',
        'reply' => $cleanReply,
        'movies' => $finalMovies,
        'fallback_used' => $aiFailed // true only when the model could not be reached or parsed
    ]);
}
?>