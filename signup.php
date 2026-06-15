<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$full_name   = trim($_POST['full_name']   ?? '');
$matric_num  = trim($_POST['matric_num']  ?? '');
$username    = trim($_POST['username']    ?? '');
$current_sem = (int)($_POST['current_sem'] ?? 0);
$password    = $_POST['password'] ?? '';

// Basic validation
if (!$full_name || !$matric_num || !$username || !$password || $current_sem < 1 || $current_sem > 7) {
    header("Location: dashboard.php?error=" . urlencode("Please fill in all fields correctly.") . "&form=signup&open=1");
    exit();
}
if (strlen($password) < 6) {
    header("Location: dashboard.php?error=" . urlencode("Password must be at least 6 characters.") . "&form=signup&open=1");
    exit();
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    header("Location: dashboard.php?error=" . urlencode("Username can only contain letters, numbers and underscores.") . "&form=signup&open=1");
    exit();
}

$u  = mysqli_real_escape_string($conn, $username);
$mn = mysqli_real_escape_string($conn, $matric_num);

// Check duplicates
$check = mysqli_query($conn, "SELECT id FROM students WHERE username='$u' OR matric_num='$mn'");
if (mysqli_num_rows($check) > 0) {
    header("Location: dashboard.php?error=" . urlencode("Username or matric number already registered.") . "&form=signup&open=1");
    exit();
}

$hashed   = password_hash($password, PASSWORD_DEFAULT);
$fn       = mysqli_real_escape_string($conn, $full_name);
$email    = $u . '@smmtc.uum.edu.my';
$prog     = 'Bachelor of Science with Honours (Multimedia)';
$prog_esc = mysqli_real_escape_string($conn, $prog);

$sql = "INSERT INTO students (matric_num, username, password, full_name, email, programme, current_sem)
        VALUES ('$mn', '$u', '$hashed', '$fn', '$email', '$prog_esc', $current_sem)";

if (mysqli_query($conn, $sql)) {
    header("Location: dashboard.php?success=" . urlencode("Account created! Please log in.") . "&open=1");
} else {
    header("Location: dashboard.php?error=" . urlencode("Registration failed. Please try again.") . "&form=signup&open=1");
}
exit();