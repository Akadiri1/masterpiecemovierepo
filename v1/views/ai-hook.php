<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$title = $_POST['title'] ?? '';
$mediaId = $_POST['media_id'] ?? '';

if (empty($title) || empty($mediaId)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing title or ID.']);
    exit;
}

// Global Cache System to save Groq API limits
$cacheFile = __DIR__ . '/../cache/ai_hooks.json';
if (!file_exists(dirname($cacheFile))) {
    mkdir(dirname($cacheFile), 0777, true);
}

$cacheData = [];
if (file_exists($cacheFile)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true) ?? [];
}

// Return instantly if cached
if (isset($cacheData[$mediaId])) {
    echo json_encode(['status' => 'success', 'hook' => $cacheData[$mediaId]]);
    exit;
}

// Include config to get GROQ_API_KEY
require_once __DIR__ . '/../../.env/config.php';

// Not cached, query Groq
$groqApiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
$url = "https://api.groq.com/openai/v1/chat/completions";

$messages = [
    [
        'role' => 'system',
        'content' => "You are an expert movie critic writing short, exciting pitches to convince users to watch a movie or show. Keep it exactly 1 or 2 sentences. Make it engaging, punchy, and highlight what makes it special (e.g. twist ending, amazing acting, intense action). Do not use the title of the movie in the pitch. No quotes."
    ],
    [
        'role' => 'user',
        'content' => "Write a pitch for: " . $title
    ]
];

$payload = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => $messages,
    "temperature" => 0.7,
    "max_tokens" => 150
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqApiKey
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
curl_close($ch);

$aiResult = json_decode($response, true);
$hook = '';

if (!empty($aiResult['choices'][0]['message']['content'])) {
    $hook = trim(strip_tags($aiResult['choices'][0]['message']['content']));
    // Clean up quotes if the AI added them
    $hook = trim($hook, "\"'");
}

if (!empty($hook)) {
    // Save to Cache
    $cacheData[$mediaId] = $hook;
    file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
    echo json_encode(['status' => 'success', 'hook' => $hook]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate hook.']);
}
?>
