<?php
// zen-history.php (PDO VERSION)

// 1. SESSION & HEADERS
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Clear any accidental HTML output before JSON
if (ob_get_length()) ob_clean(); 
header('Content-Type: application/json');

// Error handling (Hide from output, log to file)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. CHECK CONNECTION ($conn)
// We assume $conn is already included via your router/config files.
// If $conn is missing, the script will crash, so we check:
if (!isset($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Database variable $conn not found.']);
    exit;
}

// 3. AUTH CHECK
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { 
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']); 
    exit; 
}

$action = $_POST['zen_action'] ?? '';

try {

    // --- SAVE MESSAGE ---
    if ($action === 'save') {
        $q = trim($_POST['query'] ?? '');
        $cid = $_POST['conversation_id'] ?? '';
        
        if ($q && $cid) {
            $stmt = $conn->prepare("INSERT INTO zen_search_history (user_id, conversation_id, query, created_at, is_pinned) VALUES (?, ?, ?, NOW(), 0)");
            // PDO: pass parameters directly to execute()
            $stmt->execute([$userId, $cid, $q]);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- FETCH SIDEBAR (Latest message per conversation) ---
    if ($action === 'fetch_sidebar') {
        $sql = "
            SELECT * FROM zen_search_history 
            WHERE id IN (
                SELECT MAX(id) 
                FROM zen_search_history 
                WHERE user_id = ? 
                GROUP BY conversation_id
            ) 
            ORDER BY is_pinned DESC, id DESC 
            LIMIT 50
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // --- FETCH CHAT MESSAGES ---
    if ($action === 'fetch_chat') {
        $cid = $_POST['conversation_id'] ?? '';
        
        $stmt = $conn->prepare("SELECT * FROM zen_search_history WHERE conversation_id = ? AND user_id = ? ORDER BY id ASC");
        $stmt->execute([$cid, $userId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // --- PIN CONVERSATION ---
    if ($action === 'pin') {
        $cid = $_POST['conversation_id'] ?? '';
        
        $stmt = $conn->prepare("UPDATE zen_search_history SET is_pinned = NOT is_pinned WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$cid, $userId]);
        
        usleep(200000); // UX Delay
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- DELETE CONVERSATION ---
    if ($action === 'delete') {
        $cid = $_POST['conversation_id'] ?? '';
        
        $stmt = $conn->prepare("DELETE FROM zen_search_history WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$cid, $userId]);
        
        usleep(200000); // UX Delay
        echo json_encode(['status' => 'success']);
        exit;
    }

    // No valid action found
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);

} catch (PDOException $e) {
    // Catch database errors
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    exit;
}
?>