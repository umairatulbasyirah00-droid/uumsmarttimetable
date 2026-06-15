<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header("Location: dashboard.php?error=" . urlencode("Please fill in all fields.") . "&form=login&open=1");
    exit();
}

$u = mysqli_real_escape_string($conn, $username);
$sql = "SELECT * FROM students WHERE username = '$u' LIMIT 1";
$res = mysqli_query($conn, $sql);
$student = mysqli_fetch_assoc($res);

if (!$student || !password_verify($password, $student['password'])) {
    header("Location: dashboard.php?error=" . urlencode("Invalid username or password.") . "&form=login&open=1");
    exit();
}

// ── Success: store student info in session ──
$_SESSION['student_id']   = $student['id'];
$_SESSION['matric_num']   = $student['matric_num'];
$_SESSION['username']     = $student['username'];
$_SESSION['full_name']    = $student['full_name'];
$_SESSION['email']        = $student['email'];
$_SESSION['programme']    = $student['programme'];
$_SESSION['current_sem']  = $student['current_sem'];

// Clear old wishlist/schedule on fresh login
$_SESSION['wishlist']        = [];
$_SESSION['final_schedule']  = [];
$_SESSION['share_token']     = null;

header("Location: index.php");
exit();