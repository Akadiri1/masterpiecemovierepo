<?php 
session_start(); 
$_SESSION["user_id"] = 1; 
$_SERVER["REQUEST_URI"] = "/update-history"; 
$_SERVER["REQUEST_METHOD"] = "POST"; 
$_POST["media_id"] = 555; 
$_POST["media_type"] = "movie"; 
$_POST["current_time"] = 10; 
$_POST["duration"] = 120; 
chdir("www"); 
include "index.php";
