<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'smarttimetable';
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) die("Sambungan gagal: " . mysqli_connect_error());
mysqli_set_charset($conn, "utf8");
?>