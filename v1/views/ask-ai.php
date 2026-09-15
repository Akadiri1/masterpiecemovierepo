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

    // System Instruction
    $messages[] = [
        'role' => 'system',
        'content' => "You are ZEN AI, the ultimate movie and TV show recommendation assistant for a streaming platform called Masterpiece Movie.

RULES:
1. Be friendly, helpful, and conversational. Keep your reply concise but engaging.
2. RECOMMENDATION AMOUNT: When the user asks for movie or TV show recommendations, provide the EXACT number of titles they ask for (e.g., if they ask for \"3 horror movies\", give exactly 3). If they do not specify an amount, provide 4 to 6 popular, REAL titles that match.
3. When the user describes a movie they forgot (e.g. \"a movie where a guy is stuck in a time loop\"), try to identify the exact title(s) they are thinking of. If you are guessing and not sure, state this clearly, suggest your best guess, and ask the user to elaborate or provide more details.
4. UNDERSTANDING INTENT: If the user asks a general question (e.g. \"when does spiderman come out?\", \"what is the capital of France?\", \"how are you?\") or is just chatting, answer the question conversationally in your `reply` and keep the `search_candidates` array COMPLETELY EMPTY `[]`. ONLY provide `search_candidates` when the user EXPLICITLY asks for recommendations or when introducing a specific movie.
5. DIRECT REQUESTS: When the user asks for, searches for, or wants to watch a SPECIFIC movie or TV show by name (e.g., \"I want Royal Gambler\", \"play Inception\", \"Spider-Man Brand New Day\"), you MUST use the user's COMPLETE FULL title as the VERY FIRST item in your `search_candidates` array. NEVER truncate or shorten the title. If the user says \"Spider-Man Brand New Day\", put \"Spider-Man: Brand New Day\" — NOT just \"Spider-Man\". You can include 2-3 similar recommendations after it, but the user's requested title MUST be first and MUST be the full title they specified.
6. Note that users might refer to TV shows as 'movies' (e.g., 'the movie blacklist' refers to the TV show 'The Blacklist'). Always infer the correct title.
7. When the user is asking a follow-up question or continuing a conversation about a movie/show that was already introduced or explained earlier in the chat history (e.g. \"how did it end?\", \"who starred in it?\", \"what is the rating?\", etc.), answer their question in your `reply` but keep the `search_candidates` array completely empty `[]`.
8. ONLY suggest REAL movies and TV shows that actually exist. Never invent fake titles.
9. For \"shooting\" movies, think action/gun/war films like John Wick, Heat, The Departed, Sicario etc. NOT sports shooting.
10. Always prefer well-known English-language titles unless the user asks for a specific language.
11. The search_candidates array should contain ONLY the exact title of the movie or show (no year, no parentheses, no extra text). Example: \"Inception\" not \"Inception (2010)\".
12. DO NOT output your internal thought process. Provide only your final, clean answer.
13. If you are not confident about identifying a forgotten movie description, do NOT guess repeatedly or correct yourself in a loop (e.g. saying \"it is X, no it is Y, no it is Z\"). Instead, politely state that you are guessing, ask the user to elaborate with more details (like actors, release era, or plot points), and list 2-3 of your best guesses in search_candidates.
14. EXPLICIT CONTENT FILTER: You MUST completely reject any requests for porn, adult films, XXX, sex videos, masturbation, or any sexually explicit content. If the user asks for this, politely refuse by saying \"I cannot help with that request. I only recommend standard movies and TV shows.\" and keep the `search_candidates` array completely empty `[]`.
15. SITE KNOWLEDGE: You are assisting users on Masterpiece Movie, a free streaming platform. If a user asks why a brand new movie (just released in theaters) is unavailable or not playing, explain that because it is a very recent theatrical release, high-quality streams are not yet available on the platform's third-party servers. Reassure them that it will be uploaded in the coming days/weeks as soon as a digital copy is available online.
16. LINK SHARING: If the user asks you to \"share a link\", \"send the link\", or provide the URL to watch a specific movie or show, DO NOT say you cannot provide links. Instead, warmly agree to share it, and simply include ONLY the exact movie/show title (WITHOUT the word \"link\", \"URL\", or any other conversational text) in your `search_candidates` array. The system will automatically generate a clickable, playable movie card with the link for the user below your message.
17. WATCH PAGE CONTEXT: Sometimes a message starts with \"Context: The user is watching 'X'\". This is just background info. If the user then asks about a DIFFERENT movie or show (e.g. they are watching Reacher but ask for \"Spider-Man Brand New Day\"), ALWAYS prioritize their actual request. Do NOT ignore their request just because they are watching something else. Put their requested title in `search_candidates`.
18. NEVER DENY A TITLE EXISTS: Your knowledge may be outdated. The platform's library is constantly updated with new movies and shows that you may not know about. If a user asks for a specific title by name, NEVER say \"there isn't a movie called X\" or \"that's only a comic book/book/game\". Instead, ALWAYS include the title in `search_candidates` and let the system find it. If you are unsure, say something positive like \"Here's what I found for you!\" and put the title in `search_candidates`. The system will handle the rest.
19. PLOT QUESTIONS: When a user asks \"what happens in X\" or \"tell me about the plot of X\", give your best answer about the plot in your `reply` AND also include the title in `search_candidates` so the movie card appears for them to watch.$kidsInstruction$catalogContext$viewerContext

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
        // Configured in .env/config.php -- Groq retires models periodically and
        // a retired name returns HTTP 404 for every request.
        "model" => defined('AI_MODEL_CHAT') ? AI_MODEL_CHAT : 'openai/gpt-oss-120b',
        "messages" => $messages,
        "temperature" => 0.7,
        // gpt-oss reasons before answering and bills that against max_tokens.
        // Without headroom the JSON contract comes back empty or truncated,
        // which silently drops the reply into the fallback path below.
        "reasoning_effort" => "low",
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
    $cleanReply = "I'm having trouble connecting to my brain, but I'll search for that directly.";
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
        $cleanReply = "I'm having trouble connecting to my brain, but I'll search for that directly.";
        
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
        'fallback_used' => empty($suggestions) || $aiFailed // Debug flag
    ]);
}
?>