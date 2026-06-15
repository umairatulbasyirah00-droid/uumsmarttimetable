<?php
session_start();
include 'config.php';

if (empty($_SESSION['student_id'])) {
    header("Location: dashboard.php?open=1"); exit();
}
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

if (isset($_GET['delete'])) {
    $code = $_GET['delete'];
    $key  = array_search($code, $_SESSION['wishlist']);
    if ($key !== false) unset($_SESSION['wishlist'][$key]);
    header("Location: wishlist.php"); exit();
}

$courses_data  = [];
$total_credits = 0;
foreach ($_SESSION['wishlist'] as $code) {
    $c   = mysqli_real_escape_string($conn, $code);
    $res = mysqli_query($conn, "SELECT course_name, credit_hours FROM courses WHERE course_code='$c'");
    if ($row = mysqli_fetch_assoc($res)) {
        $courses_data[]  = ['code'=>$code,'name'=>$row['course_name'],'credits'=>$row['credit_hours']];
        $total_credits  += $row['credit_hours'];
    }
}

$active_page = 'wishlist';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wishlist – UUM SmartTimetable</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.wrap{max-width:900px;margin:30px auto;padding:0 20px}

.pg-title{font-size:22px;font-weight:800;color:#2c3e50;margin-bottom:4px}
.pg-sub{font-size:13px;color:#999;margin-bottom:24px}

/* Table card */
.table-card{background:white;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px}
table{width:100%;border-collapse:collapse}
thead th{background:#2c3e50;color:white;padding:13px 16px;text-align:left;font-size:13px;font-weight:700}
tbody td{padding:13px 16px;border-bottom:1px solid #f0f0f0;font-size:13px;color:#333;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:#fafafa}
.tfoot-row td{background:#f7f8fa;font-weight:700;font-size:14px;padding:14px 16px}

.code-badge{font-weight:800;color:#2c3e50;font-size:13px}
.cr-chip{background:#f0f2f5;color:#555;font-size:11px;padding:3px 10px;border-radius:10px;display:inline-block}

.btn-del{
    background:none; border:1.5px solid #e74c3c;
    color:#e74c3c; padding:5px 14px; border-radius:16px;
    font-size:12px; font-weight:700; cursor:pointer; transition:.15s;
}
.btn-del:hover{background:#e74c3c;color:white}

/* Empty state */
.empty{background:white;border-radius:14px;padding:60px 30px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.empty-ico{font-size:3rem;margin-bottom:12px}
.empty-t{font-size:16px;font-weight:700;color:#2c3e50;margin-bottom:6px}
.empty-s{font-size:13px;color:#aaa;margin-bottom:20px}
.btn-browse{display:inline-block;background:#efcb33;color:#1a1a1a;padding:10px 24px;border-radius:20px;font-size:14px;font-weight:700;text-decoration:none}
.btn-browse:hover{background:#d4b22c}

/* Generate button */
.btn-generate{
    display:block;width:100%;max-width:280px;margin:0 auto;
    background:#2c3e50;color:white;border:none;
    padding:14px;border-radius:30px;font-size:15px;font-weight:800;
    cursor:pointer;letter-spacing:.5px;transition:.15s;
    text-align:center;
}
.btn-generate:hover{background:#1a252f}

/* Credit warning */
.credit-warn{
    background:#fff3cd;border:1px solid #ffc107;
    border-radius:10px;padding:12px 16px;
    font-size:13px;color:#856404;margin-bottom:16px;
}
.credit-ok{background:#d4edda;border-color:#28a745;color:#155724}

footer{text-align:center;margin:32px 0 20px;color:#bbb;font-size:12px}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="wrap">
    <div class="pg-title">❤️ My Wishlist</div>
    <div class="pg-sub">Courses you've selected — generate your timetable when ready</div>

    <?php if (empty($courses_data)): ?>
        <div class="empty">
            <div class="empty-ico">📭</div>
            <div class="empty-t">Your wishlist is empty</div>
            <div class="empty-s">Browse courses and add them to your wishlist first.</div>
            <a href="course_list.php" class="btn-browse">📋 Browse Courses</a>
        </div>

    <?php else: ?>

        <!-- Credit info -->
        <?php $warn = $total_credits < 12 || $total_credits > 21; ?>
        <div class="credit-warn <?= !$warn?'credit-ok':'' ?>">
            <?php if(!$warn): ?>
                ✅ <strong><?= $total_credits ?> credit hours</strong> — within normal semester load (12–21 credits)
            <?php elseif($total_credits < 12): ?>
                ⚠️ <strong><?= $total_credits ?> credit hours</strong> — below minimum (12 credits recommended)
            <?php else: ?>
                ⚠️ <strong><?= $total_credits ?> credit hours</strong> — above maximum (21 credits limit)
            <?php endif; ?>
        </div>

        <form method="post" action="generate.php">
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px">No</th>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th style="text-align:center">Credit Hours</th>
                            <th style="text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i=1; foreach($courses_data as $c): ?>
                    <tr>
                        <td style="color:#aaa"><?= $i++ ?></td>
                        <td><span class="code-badge"><?= htmlspecialchars($c['code']) ?></span></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td style="text-align:center"><span class="cr-chip"><?= $c['credits'] ?> cr</span></td>
                        <td style="text-align:center">
                            <a href="wishlist.php?delete=<?= urlencode($c['code']) ?>"
                               onclick="return confirm('Remove <?= htmlspecialchars($c['code']) ?> from wishlist?')">
                                <button type="button" class="btn-del">✕ Remove</button>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="tfoot-row">
                            <td colspan="3" style="text-align:right">Total Credit Hours</td>
                            <td style="text-align:center"><?= $total_credits ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="submit" class="btn-generate">🗓️ GENERATE TIMETABLE</button>
        </form>
    <?php endif; ?>
</div>

<footer>UUM SmartTimetable | Plan Smarter, Register Easier</footer>
</body>
</html>