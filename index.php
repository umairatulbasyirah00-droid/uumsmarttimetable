<?php
session_start();
include 'config.php';

if (empty($_SESSION['student_id'])) {
    header("Location: dashboard.php?open=1"); exit();
}
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// Add to wishlist via GET (from course_list redirect)
if (isset($_GET['add'])) {
    $code = mysqli_real_escape_string($conn, $_GET['add']);
    if (!in_array($code, $_SESSION['wishlist'])) $_SESSION['wishlist'][] = $code;
    $back = $_GET['from_college'] ? "course_list.php?college=".urlencode($_GET['from_college']) : "index.php";
    header("Location: $back"); exit();
}

$student_id = (int)$_SESSION['student_id'];
$prog       = mysqli_real_escape_string($conn, $_SESSION['programme']);
$sem        = (int)$_SESSION['current_sem'];

// Programme progress
$done_res = mysqli_query($conn,"SELECT course_code FROM student_completed_courses WHERE student_id=$student_id");
$completed = [];
while($r = mysqli_fetch_assoc($done_res)) $completed[] = $r['course_code'];

$total_res = mysqli_query($conn,"SELECT COUNT(*) as cnt FROM proposed_courses WHERE programme='$prog'");
$total_courses = (int)(mysqli_fetch_assoc($total_res)['cnt'] ?? 0);

// Total credit hours required for the programme (BSc Multimedia = 122)
$TOTAL_CREDIT_HOURS_REQUIRED = 122;

// Sum credit hours of completed courses
$completed_credit_hours = 0;
if (!empty($completed)) {
    $codes_list = implode("','", array_map(fn($c)=>mysqli_real_escape_string($conn,$c), $completed));
    $ch_res = mysqli_query($conn, "SELECT SUM(credit_hours) as total_ch FROM courses WHERE course_code IN ('$codes_list')");
    $completed_credit_hours = (int)(mysqli_fetch_assoc($ch_res)['total_ch'] ?? 0);
}

$progress_pct = $TOTAL_CREDIT_HOURS_REQUIRED > 0
    ? round(($completed_credit_hours / $TOTAL_CREDIT_HOURS_REQUIRED) * 100)
    : 0;

// Remaining by category
$excl = !empty($completed)
    ? "AND course_code NOT IN ('".implode("','",array_map(fn($c)=>mysqli_real_escape_string($conn,$c),$completed))."')"
    : '';
$cat_res = mysqli_query($conn,
    "SELECT category, COUNT(*) as cnt FROM proposed_courses
     WHERE programme='$prog' $excl
     GROUP BY category
     ORDER BY FIELD(category,'University Core','English Language Core','Programme Core','Programme Elective','Free Elective')");
$cat_counts = [];
while($r = mysqli_fetch_assoc($cat_res)) $cat_counts[$r['category']] = (int)$r['cnt'];

$cat_meta = [
    'University Core'       => ['color'=>'#1e3a8a','bg'=>'#eef2ff','icon'=>'🏛️'],
    'English Language Core' => ['color'=>'#166534','bg'=>'#f0fdf4','icon'=>'🗣️'],
    'Programme Core'        => ['color'=>'#7f1d1d','bg'=>'#fef2f2','icon'=>'📘'],
    'Programme Elective'    => ['color'=>'#78350f','bg'=>'#fffbeb','icon'=>'⭐'],
    'Free Elective'         => ['color'=>'#581c87','bg'=>'#faf5ff','icon'=>'🎯'],
];

$wishlist = $_SESSION['wishlist'];
$active_page = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Home – UUM SmartTimetable</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}

/* Hero */
.hero{
    background:linear-gradient(135deg,#2c3e50 0%,#3d5572 100%);
    color:white; padding:36px 28px 32px; text-align:center;
}
.hero-sub{ font-size:11px; font-weight:700; color:#efcb33; letter-spacing:2px; text-transform:uppercase; margin-bottom:8px; }
.hero h1{ font-size:clamp(1.4rem,3vw,2rem); font-weight:800; margin-bottom:14px; }

/* Student info pills */
.info-row{
    display:inline-flex; flex-wrap:wrap; justify-content:center;
    gap:6px; margin-bottom:22px;
}
.info-pill{
    background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
    border-radius:20px; padding:6px 14px; font-size:12px; color:#cdd8e0;
}
.info-pill strong{ color:#efcb33; margin-right:4px; }

/* Progress bar */
.prog-wrap{ max-width:460px; margin:0 auto; }
.prog-labels{ display:flex; justify-content:space-between; font-size:11px; color:#ffffff; margin-bottom:6px; }
.prog-track{ height:8px; background:rgba(255,255,255,.15); border-radius:8px; overflow:hidden; }
.prog-fill{ height:100%; background:#ffffff; border-radius:8px; transition:width .6s ease; }

/* Page wrap */
.wrap{ max-width:1200px; margin:28px auto; padding:0 20px; }

/* Quick actions */
.actions{ display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:28px; }
.act-card{
    background:white; border-radius:14px; padding:22px 20px;
    box-shadow:0 1px 6px rgba(0,0,0,.07);
    text-decoration:none; color:inherit; display:block;
    border-top:4px solid #efcb33;
    transition:transform .15s, box-shadow .15s;
}
.act-card:hover{ transform:translateY(-4px); box-shadow:0 6px 20px rgba(0,0,0,.11); }
.act-icon{ font-size:2rem; margin-bottom:10px; }
.act-card h3{ font-size:.95rem; font-weight:700; color:#2c3e50; margin-bottom:4px; }
.act-card p{ font-size:.8rem; color:#999; line-height:1.55; margin-bottom:12px; }
.act-badge{
    display:inline-block; background:#efcb33; color:#1a1a1a;
    font-size:11px; font-weight:700; padding:3px 11px; border-radius:10px;
}

/* Section title */
.sec-title{ font-size:14px; font-weight:800; color:#2c3e50; margin-bottom:14px; text-transform:uppercase; letter-spacing:.5px; }

/* Programme structure grid */
.struct-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(195px,1fr)); gap:12px; margin-bottom:32px; }
.struct-card{
    border-radius:12px; padding:15px 16px;
    display:flex; align-items:center; gap:12px;
    text-decoration:none; transition:transform .12s, box-shadow .12s;
    box-shadow:0 1px 5px rgba(0,0,0,.06);
}
.struct-card:hover{ transform:translateY(-2px); box-shadow:0 4px 14px rgba(0,0,0,.1); }
.struct-icon{ font-size:1.5rem; flex-shrink:0; }
.struct-info h4{ font-size:12px; font-weight:700; margin-bottom:1px; }
.struct-info p{ font-size:11px; opacity:.65; }
.struct-count{
    margin-left:auto; font-size:1.3rem; font-weight:800;
    background:rgba(0,0,0,.1); padding:4px 10px; border-radius:8px; flex-shrink:0;
}

footer{ text-align:center; margin:32px 0 20px; color:#bbb; font-size:12px; }
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="hero">
    <div class="hero-sub">Welcome Back</div>
    <h1><?= htmlspecialchars($_SESSION['full_name']) ?></h1>
    <div class="prog-wrap">
        <div class="prog-labels">
            <span>Programme Progress</span>
            <span><?= $completed_credit_hours ?> / <?= $TOTAL_CREDIT_HOURS_REQUIRED ?> credit hours (<?= $progress_pct ?>%)</span>
        </div>
        <div class="prog-track">
            <div class="prog-fill" style="width:<?= $progress_pct ?>%"></div>
        </div>
    </div>
</div>

<div class="wrap">

    <!-- Quick actions -->
    <div class="actions">
        <a href="course_list.php" class="act-card">
            <div class="act-icon">📋</div>
            <h3>Course List</h3>
            <p>Browse courses by college — COB, CAS, or COLGIS</p>
            <span class="act-badge">Browse Courses</span>
        </a>
        <a href="wishlist.php" class="act-card">
            <div class="act-icon">❤️</div>
            <h3>My Wishlist</h3>
            <p>Courses you've picked. Generate timetable when ready.</p>
            <span class="act-badge"><?= count($wishlist) ?> course<?= count($wishlist)!=1?'s':'' ?></span>
        </a>
        <a href="<?= empty($_SESSION['final_schedule'])?'wishlist.php':'timetable.php' ?>" class="act-card">
            <div class="act-icon">🗓️</div>
            <h3>My Timetable</h3>
            <p>View, download, or share your conflict-free schedule.</p>
            <span class="act-badge"><?= empty($_SESSION['final_schedule'])?'Not generated yet':'View now' ?></span>
        </a>
    </div>

    <!-- Programme structure -->
    <?php if(!empty($cat_counts)): ?>
    <div class="sec-title">Programme Structure — Remaining Courses</div>
    <div class="struct-grid">
        <?php foreach($cat_meta as $cat => $meta):
            $cnt = $cat_counts[$cat] ?? 0;
            if(!$cnt) continue;
        ?>
        <a href="course_list.php" class="struct-card" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>;">
            <span class="struct-icon"><?= $meta['icon'] ?></span>
            <div class="struct-info">
                <h4><?= $cat ?></h4>
                <p>remaining</p>
            </div>
            <span class="struct-count"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<footer>UUM SmartTimetable | Plan Smarter, Register Easier</footer>
</body>
</html>