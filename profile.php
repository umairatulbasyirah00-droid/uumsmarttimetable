<?php
session_start();
include 'config.php';

if (empty($_SESSION['student_id'])) {
    header("Location: dashboard.php?open=1"); exit();
}

$student_id = (int)$_SESSION['student_id'];

// Fetch full student info from DB
$res = mysqli_query($conn,
    "SELECT full_name, matric_num, username, password, programme, current_sem
     FROM students WHERE id = $student_id LIMIT 1");
$student = mysqli_fetch_assoc($res);

// Fetch all courses in proposed_courses for this student's programme (if table exists)
$proposed = [];
$chk = mysqli_query($conn, "SHOW TABLES LIKE 'proposed_courses'");
if (mysqli_num_rows($chk) > 0) {
    $prog = mysqli_real_escape_string($conn, $student['programme']);
    $pr   = mysqli_query($conn,
        "SELECT pc.course_code, c.course_name, c.credit_hours, pc.category
         FROM proposed_courses pc
         LEFT JOIN courses c ON c.course_code = pc.course_code
         WHERE pc.programme = '$prog'
         ORDER BY pc.category, pc.course_code");
    while ($r = mysqli_fetch_assoc($pr)) $proposed[] = $r;
}

$email       = ($student['username'] ?? '') . '@smmtc.uum.edu.my';
$active_page = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile – UUM SmartTimetable</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}

.wrap{ max-width:900px; margin:30px auto; padding:0 20px 40px; }

/* Profile header card */
.profile-card{
    background:linear-gradient(135deg,#1a2a4a,#2c4a7c);
    border-radius:16px; padding:32px 36px;
    display:flex; align-items:center; gap:28px;
    margin-bottom:24px; color:white;
    box-shadow:0 4px 20px rgba(0,0,0,.15);
}
.profile-avatar{
    width:80px; height:80px; border-radius:50%;
    background:#efcb33; color:#1a2a4a;
    display:flex; align-items:center; justify-content:center;
    font-size:32px; font-weight:900; flex-shrink:0;
    box-shadow:0 0 0 4px rgba(239,203,51,.3);
}
.profile-info h2{ font-size:1.4rem; font-weight:800; margin-bottom:4px; }
.profile-info p { font-size:13px; color:#8ab0d4; }

/* Info grid */
.info-grid{
    display:grid; grid-template-columns:1fr 1fr;
    gap:14px; margin-bottom:24px;
}
.info-item{
    background:white; border-radius:12px; padding:18px 20px;
    box-shadow:0 1px 6px rgba(0,0,0,.06);
}
.info-item label{
    font-size:11px; font-weight:700; color:#8ab0d4;
    text-transform:uppercase; letter-spacing:.6px;
    display:block; margin-bottom:6px;
}
.info-item span{ font-size:15px; font-weight:600; color:#1a2a4a; }

/* Section */
.sec-title{
    font-size:13px; font-weight:800; color:#1a2a4a;
    text-transform:uppercase; letter-spacing:.6px;
    margin-bottom:14px;
}

/* Proposed courses table */
.table-wrap{
    background:white; border-radius:12px; overflow:hidden;
    box-shadow:0 1px 8px rgba(0,0,0,.07);
}
table{ width:100%; border-collapse:collapse; font-size:13px; }
thead th{
    background:#1a2a4a; color:white;
    padding:12px 16px; text-align:left; font-size:12px;
}
tbody td{ padding:11px 16px; border-bottom:1px solid #f0f0f0; color:#333; }
tbody tr:last-child td{ border-bottom:none; }
tbody tr:hover{ background:#f8f9fb; }
.cat-badge{
    display:inline-block; font-size:10px; font-weight:700;
    padding:3px 8px; border-radius:10px;
}
.cat-uc { background:#eef2ff; color:#1e3a8a; }
.cat-el { background:#f0fdf4; color:#166534; }
.cat-pc { background:#fef2f2; color:#7f1d1d; }
.cat-pe { background:#fffbeb; color:#78350f; }
.cat-fe { background:#faf5ff; color:#581c87; }

/* Back link */
.back-link{
    display:inline-flex; align-items:center; gap:6px;
    color:#1a2a4a; text-decoration:none; font-size:13px;
    font-weight:700; margin-bottom:20px;
    padding:8px 16px; background:white; border-radius:20px;
    box-shadow:0 1px 4px rgba(0,0,0,.08); transition:.15s;
}
.back-link:hover{ background:#efcb33; }

.no-data{ text-align:center; padding:30px; color:#aaa; font-size:13px; }
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="wrap">

    <a href="index.php" class="back-link">← Back to Home</a>

    <!-- Profile header -->
    <div class="profile-card">
        <div class="profile-avatar">
            <?= strtoupper(substr($student['full_name'] ?? 'S', 0, 1)) ?>
        </div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($student['full_name'] ?? '') ?></h2>
            <p><?= htmlspecialchars($student['programme'] ?? '') ?></p>
        </div>
    </div>

    <!-- Student details -->
    <div class="info-grid">
        <div class="info-item">
            <label>Full Name</label>
            <span><?= htmlspecialchars($student['full_name'] ?? '–') ?></span>
        </div>
        <div class="info-item">
            <label>Matric Number</label>
            <span><?= htmlspecialchars($student['matric_num'] ?? '–') ?></span>
        </div>
        <div class="info-item">
            <label>Email</label>
            <span><?= htmlspecialchars($email) ?></span>
        </div>
        <div class="info-item">
            <label>Current Semester</label>
            <span>Semester <?= (int)($student['current_sem'] ?? 0) ?></span>
        </div>
        <div class="info-item" style="grid-column:1/-1">
            <label>Programme</label>
            <span><?= htmlspecialchars($student['programme'] ?? '–') ?></span>
        </div>
    </div>

    <!-- Proposed courses -->
    <div class="sec-title">📋 Proposed Courses — <?= htmlspecialchars($student['programme'] ?? '') ?></div>

    <?php if (!empty($proposed)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th style="text-align:center">Credit Hours</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($proposed as $i => $p):
                $catClass = match($p['category'] ?? '') {
                    'University Core'       => 'cat-uc',
                    'English Language Core' => 'cat-el',
                    'Programme Core'        => 'cat-pc',
                    'Programme Elective'    => 'cat-pe',
                    'Free Elective'         => 'cat-fe',
                    default                 => 'cat-uc',
                };
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($p['course_code']) ?></strong></td>
                <td><?= htmlspecialchars($p['course_name'] ?? $p['course_code']) ?></td>
                <td style="text-align:center"><?= $p['credit_hours'] ?? '–' ?></td>
                <td><span class="cat-badge <?= $catClass ?>"><?= htmlspecialchars($p['category'] ?? '–') ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div class="table-wrap">
        <div class="no-data">
            📭 No proposed courses found.<br>
            <small>The proposed courses table may not be set up yet.</small>
        </div>
    </div>
    <?php endif; ?>

</div>

<footer style="text-align:center;margin:20px 0;color:#bbb;font-size:12px">
    UUM SmartTimetable | Plan Smarter, Register Easier
</footer>
</body>
</html>