<?php
// TEMPORARY local test helper (deleted by the same command that created it).
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(404); exit; }
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=masterpiecemovie;charset=utf8mb4', 'root', '');
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([(int) ($_GET['uid'] ?? 0)]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) { http_response_code(404); exit('no such user'); }
$_SESSION['user_id'] = $u['id']; $_SESSION['username'] = $u['username']; $_SESSION['email'] = $u['email'];
$_SESSION['role'] = $u['role'] ?? 'user'; $_SESSION['avatar_url'] = $u['avatar_url'] ?? null; $_SESSION['logged_in'] = true;
$_SESSION['is_kids_mode'] = (int) $u['is_kids_mode'] === 1;
header('Location: ' . ($_GET['to'] ?? '/'));
