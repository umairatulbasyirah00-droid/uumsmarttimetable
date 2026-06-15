<?php
session_start();
include 'config.php';

if (empty($_SESSION['student_id'])) {
    header("Location: dashboard.php?open=1");
    exit();
}
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

$selected_college = $_GET['college'] ?? '';
$search           = trim($_GET['search']  ?? '');
$filter_day       = $_GET['day']     ?? '';
$filter_time      = $_GET['time']    ?? '';
$filter_credit    = $_GET['credit']  ?? '';

$colleges = [
    'COB'    => ['name'=>'College of Business',                                        'malay'=>'Kolej Perniagaan'],
    'CAS'    => ['name'=>'College of Arts and Science',                                'malay'=>'Kolej Sastera Dan Sains'],
    'COLGIS' => ['name'=>'College of Law, Government & International Studies',         'malay'=>'Kolej Undang-Undang, Kerajaan & Pengajian Antarabangsa'],
];

// Count courses per college
$college_counts = [];
foreach (array_keys($colleges) as $col) {
    $c   = mysqli_real_escape_string($conn, $col);
    $r   = mysqli_query($conn, "SELECT COUNT(DISTINCT course_code) AS cnt FROM courses WHERE college='$c'");
    $college_counts[$col] = mysqli_fetch_assoc($r)['cnt'] ?? 0;
}

// Fetch courses when college selected
$courses = [];
if ($selected_college && isset($colleges[$selected_college])) {
    $col = mysqli_real_escape_string($conn, $selected_college);
    $sql = "SELECT c.course_code, c.course_name, c.credit_hours,
                   cg.group_label, UPPER(cg.day) AS day,
                   TIME_FORMAT(cg.start_time,'%h:%i %p') AS time_start,
                   TIME_FORMAT(cg.end_time,  '%h:%i %p') AS time_end,
                   cg.venue
            FROM courses c
            JOIN class_groups cg ON cg.course_code = c.course_code
            WHERE c.college = '$col'";
    if (!empty($search)) {
        $s = mysqli_real_escape_string($conn, $search);
        $sql .= " AND (c.course_code LIKE '%$s%' OR c.course_name LIKE '%$s%')";
    }
    if (!empty($filter_day)) {
        $d = mysqli_real_escape_string($conn, strtoupper($filter_day));
        $sql .= " AND UPPER(cg.day) = '$d'";
    }
    if ($filter_time === 'pagi')        $sql .= " AND cg.start_time < '12:00:00'";
    elseif ($filter_time === 'petang')  $sql .= " AND cg.start_time >= '12:00:00' AND cg.start_time < '17:00:00'";
    elseif ($filter_time === 'malam')   $sql .= " AND cg.start_time >= '17:00:00'";
    if (!empty($filter_credit))         $sql .= " AND c.credit_hours = ".(int)$filter_credit;
    $sql .= " ORDER BY c.course_code, cg.group_label";
    $res  = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $code = $row['course_code'];
        if (!isset($courses[$code])) {
            $courses[$code] = ['code'=>$row['course_code'],'name'=>$row['course_name'],
                               'credits'=>$row['credit_hours'],'groups'=>[]];
        }
        $courses[$code]['groups'][] = $row;
    }
}

$active_page = 'course_list';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course List — SmartTimetable UUM</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.wrap{max-width:1300px;margin:28px auto;padding:0 20px}

/* Breadcrumb */
.bc{font-size:13px;color:#888;margin-bottom:16px;display:flex;align-items:center;gap:6px}
.bc a{color:#2c3e50;text-decoration:none;font-weight:600}
.bc a:hover{color:#efcb33}

/* Page title */
.pg-title{font-size:22px;font-weight:800;color:#2c3e50;margin-bottom:4px}
.pg-sub{font-size:13px;color:#999;margin-bottom:24px}

/* College cards grid */
.col-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:24px;
    margin-bottom:28px
}
.col-grid{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:24px;
    margin-top:80px;
    margin-bottom:28px;
    flex-wrap:wrap;
}
.col-card{
    background:#eef2ff;
    border-radius:20px;
    border:2px solid #cbd5e1;
    padding:32px 16px;
    cursor:pointer;
    transition:.2s;
    text-decoration:none;
    display:block;
    text-align:center;
    width:280px;
}
.col-icon{
    width:70px;
    height:70px;
    border-radius:50%;
    background:#efcb33;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:32px;
    margin:0 auto 20px auto   /* icon di tengah */
}
.col-code{
    font-size:32px;         /* lebih besar */
    font-weight:800;
    color:#1e3a8a;          /* biru gelap, kontras tinggi */
    letter-spacing:1px;
    margin-bottom:8px
}
.col-name{
    font-size:15px;         /* lebih kecil dari COB */
    font-weight:600;
    color:#334155;
    line-height:1.3;
    margin-bottom:12px
}
/* BUANG BAHASA MALAYSIA – jangan papar */
.col-malay{
    display:none
}
.col-foot{
    display:flex;
    align-items:center;
    justify-content:center;  /* center */
    margin-top:12px
}
.col-count{
    background:#1e3a8a;
    color:white;
    font-size:13px;
    font-weight:700;
    padding:5px 16px;
    border-radius:30px
}
.col-arrow{
    display:none   /* buang arrow */
}

/* View header */
.vh{background:white;border-radius:12px;padding:18px 22px;margin-bottom:14px;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
    display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.vh-tag{font-size:11px;font-weight:800;color:#efcb33;letter-spacing:1.5px;text-transform:uppercase}
.vh-title{font-size:18px;font-weight:800;color:#2c3e50;margin:4px 0 2px}
.vh-sub{font-size:13px;color:#999}
.btn-change{padding:8px 18px;border:1.5px solid #ddd;border-radius:20px;
            background:white;font-size:13px;font-weight:600;cursor:pointer;
            color:#2c3e50;text-decoration:none;display:inline-block;transition:.15s}
.btn-change:hover{border-color:#efcb33;background:#fffdf0}

/* Toolbar */
.toolbar{background:white;border-radius:12px;padding:14px 18px;margin-bottom:14px;
         box-shadow:0 2px 8px rgba(0,0,0,.06);
         display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tb-search{display:flex;align-items:center;gap:8px;border:1.5px solid #eee;
           border-radius:8px;padding:8px 13px;flex:1;min-width:180px;background:#fafafa}
.tb-search:focus-within{border-color:#efcb33;background:white}
.tb-search input{border:none;background:transparent;outline:none;
                 font-size:14px;color:#333;flex:1;font-family:'Segoe UI',sans-serif}
.tb-sel{padding:9px 11px;border:1.5px solid #eee;border-radius:8px;
        font-size:13px;color:#333;background:#fafafa;
        font-family:'Segoe UI',sans-serif;cursor:pointer}
.tb-sel:focus{outline:none;border-color:#efcb33}
.btn-apply{padding:9px 18px;background:#efcb33;border:none;border-radius:8px;
           font-size:13px;font-weight:700;cursor:pointer;color:#1a1a1a}
.btn-apply:hover{background:#d4b22c}
.btn-reset{padding:9px 12px;background:#f5f5f5;border:1.5px solid #eee;
           border-radius:8px;font-size:13px;color:#666;cursor:pointer;text-decoration:none}
.btn-reset:hover{background:#eee}

/* Stats */
.stats{display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:center}
.chip{background:white;border-radius:20px;padding:5px 13px;font-size:12px;
      color:#555;font-weight:600;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.chip strong{color:#2c3e50}
.chip-green{background:#d4edda;color:#155724}
.chip-yellow{background:#fff3cd;color:#856404}

/* Course accordion */
.c-block{background:white;border-radius:10px;margin-bottom:8px;
         box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:hidden}
.c-block.is-added{border-left:3px solid #28a745}
.c-head{display:flex;align-items:center;justify-content:space-between;
        padding:14px 18px;cursor:pointer;border:none;background:white;
        width:100%;text-align:left;font-family:'Segoe UI',sans-serif;
        transition:.15s;gap:12px}
.c-head:hover{background:#fafafa}
.c-left{display:flex;align-items:center;gap:12px;flex:1;min-width:0}
.c-code{font-size:14px;font-weight:800;color:#2c3e50;min-width:95px;flex-shrink:0}
.c-name{font-size:13px;color:#555;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.c-cr{background:#f0f2f5;color:#555;font-size:11px;padding:3px 8px;
      border-radius:10px;white-space:nowrap;flex-shrink:0}
.c-gcount{font-size:11px;color:#aaa;white-space:nowrap;flex-shrink:0}
.c-added-tag{background:#d4edda;color:#155724;font-size:11px;padding:3px 10px;
             border-radius:10px;font-weight:700;white-space:nowrap;flex-shrink:0}
.c-arr{color:#bbb;font-size:13px;transition:.25s;flex-shrink:0}
.c-arr.open{transform:rotate(180deg);color:#efcb33}

/* Groups table */
.g-wrap{display:none;border-top:1px solid #f0f0f0}
.g-wrap.open{display:block}
table.g-table{width:100%;border-collapse:collapse;font-size:13px}
table.g-table thead th{background:#2c3e50;color:white;padding:10px 14px;
                        text-align:left;font-size:12px;font-weight:700}
table.g-table tbody td{padding:11px 14px;border-bottom:1px solid #f5f5f5;color:#333}
table.g-table tbody tr:last-child td{border-bottom:none}
table.g-table tbody tr:hover{background:#fafafa}
.grp-lbl{font-weight:800;color:#2c3e50;font-size:14px}
.day-b{display:inline-block;padding:3px 9px;border-radius:10px;font-size:11px;font-weight:700}
.d-ahad{background:#fff3e0;color:#e65100}
.d-isnin{background:#e3f2fd;color:#1565c0}
.d-selasa{background:#f3e5f5;color:#6a1b9a}
.d-rabu{background:#e8f5e9;color:#2e7d32}
.d-khamis{background:#fce4ec;color:#880e4f}
.d-other{background:#f5f5f5;color:#555}
.venue-t{font-size:12px;color:#888}
.btn-add-r{padding:6px 14px;background:#28a745;color:white;border:none;
           border-radius:16px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}
.btn-add-r:hover{background:#218838}
.btn-done-r{padding:6px 14px;background:#6c757d;color:white;border:none;
            border-radius:16px;font-size:12px;font-weight:700;cursor:default}

/* Empty */
.empty{text-align:center;padding:60px 20px;background:white;border-radius:12px;
       box-shadow:0 2px 8px rgba(0,0,0,.06)}
.empty-ico{font-size:48px;margin-bottom:12px}
.empty-t{font-size:16px;font-weight:700;color:#2c3e50;margin-bottom:6px}
.empty-s{font-size:13px;color:#aaa}

footer{text-align:center;margin:32px 0 20px;color:#bbb;font-size:12px}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="wrap">

<?php if (!$selected_college): ?>
  <!-- ═══ LANDING — pick college ═══ -->
   <div class="col-grid">
  <?php
  $icons = ['COB'=>'💼','CAS'=>'🔬','COLGIS'=>'⚖️'];
  foreach ($colleges as $code => $info):
  ?>
    <a href="course_list.php?college=<?= $code ?>" class="col-card">
      <div class="col-icon"><?= $icons[$code] ?? '🎓' ?></div>
      <div class="col-code"><?= $code ?></div>
      <div class="col-name"><?= htmlspecialchars($info['name']) ?></div>
      <div class="col-foot">
        <span class="col-count"><?= $college_counts[$code] ?> courses</span>
        <span class="col-arrow">›</span>
      </div>
    </a>
  <?php endforeach; ?>
  </div>

<?php else: ?>
  <!-- ═══ COURSE TABLE VIEW ═══ -->

  <!-- College header -->
  <div class="vh">
    <div>
      <div class="vh-tag"><?= htmlspecialchars($selected_college) ?></div>
      <div class="vh-title"><?= htmlspecialchars($colleges[$selected_college]['name']) ?></div>
    </div>
    <a href="course_list.php" class="btn-change">← Change College</a>
  </div>

  <!-- Toolbar -->
  <form method="GET" action="course_list.php">
    <input type="hidden" name="college" value="<?= htmlspecialchars($selected_college) ?>">
    <div class="toolbar">
      <div class="tb-search">
        <span style="color:#aaa">🔍</span>
        <input type="text" name="search" placeholder="Search course code or name..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="day" class="tb-sel">
        <option value="">All Days</option>
        <?php foreach (['AHAD'=>'Ahad','ISNIN'=>'Isnin','SELASA'=>'Selasa','RABU'=>'Rabu','KHAMIS'=>'Khamis'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $filter_day===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <select name="time" class="tb-sel">
        <option value="">All Times</option>
        <option value="pagi"   <?= $filter_time==='pagi'   ?'selected':''?>>Morning (before 12pm)</option>
        <option value="petang" <?= $filter_time==='petang' ?'selected':''?>>Afternoon (12–5pm)</option>
        <option value="malam"  <?= $filter_time==='malam'  ?'selected':''?>>Evening (after 5pm)</option>
      </select>
      <select name="credit" class="tb-sel">
        <option value="">All Credits</option>
        <option value="2" <?= $filter_credit==='2'?'selected':''?>>2 Credits</option>
        <option value="3" <?= $filter_credit==='3'?'selected':''?>>3 Credits</option>
        <option value="4" <?= $filter_credit==='4'?'selected':''?>>4 Credits</option>
      </select>
      <button type="submit" class="btn-apply">Apply</button>
      <a href="course_list.php?college=<?= htmlspecialchars($selected_college) ?>" class="btn-reset">Reset</a>
    </div>
  </form>

  <!-- Stats chips -->
  <?php
    $tc = count($courses);
    $tg = array_sum(array_map(fn($c)=>count($c['groups']), $courses));
    $ac = count(array_filter($courses, fn($c)=>in_array($c['code'],$_SESSION['wishlist'])));
  ?>
  <div class="stats">
    <div class="chip"><strong><?= $tc ?></strong> course<?= $tc!==1?'s':'' ?> found</div>
    <div class="chip"><strong><?= $tg ?></strong> groups total</div>
    <?php if ($ac>0): ?><div class="chip chip-green"><strong><?= $ac ?></strong> in wishlist</div><?php endif; ?>
    <?php if ($search||$filter_day||$filter_time||$filter_credit): ?>
      <div class="chip chip-yellow">🔍 Filters active</div>
    <?php endif; ?>
  </div>

  <!-- Courses -->
  <?php if (empty($courses)): ?>
    <div class="empty">
      <div class="empty-ico">🔎</div>
      <div class="empty-t">No courses found</div>
      <div class="empty-s">Try adjusting your search or filters.</div>
    </div>
  <?php else: ?>
    <?php foreach ($courses as $c):
      $inWl = in_array($c['code'], $_SESSION['wishlist']);
    ?>
    <div class="c-block <?= $inWl?'is-added':'' ?>" id="block-<?= htmlspecialchars($c['code']) ?>">
      <button class="c-head" onclick="toggle('<?= htmlspecialchars($c['code']) ?>')">
        <div class="c-left">
          <span class="c-code"><?= htmlspecialchars($c['code']) ?></span>
          <span class="c-name"><?= htmlspecialchars($c['name']) ?></span>
        </div>
        <span class="c-cr"><?= $c['credits'] ?> cr</span>
        <span class="c-gcount"><?= count($c['groups']) ?> grp<?= count($c['groups'])!==1?'s':'' ?></span>
        <?php if ($inWl): ?><span class="c-added-tag">✓ Wishlist</span><?php endif; ?>
        <span class="c-arr" id="arr-<?= htmlspecialchars($c['code']) ?>">▼</span>
      </button>
      <div class="g-wrap" id="g-<?= htmlspecialchars($c['code']) ?>">
        <table class="g-table">
          <thead>
            <tr><th>Group</th><th>Day</th><th>Time</th><th>Venue</th><th style="text-align:center">Add to Wishlist</th></tr>
          </thead>
          <tbody>
          <?php foreach ($c['groups'] as $g):
            $dc = match($g['day']) {
              'AHAD'=>'d-ahad','ISNIN'=>'d-isnin','SELASA'=>'d-selasa',
              'RABU'=>'d-rabu','KHAMIS'=>'d-khamis',default=>'d-other'
            };
            $dl = match($g['day']) {
              'AHAD'=>'Ahad','ISNIN'=>'Isnin','SELASA'=>'Selasa',
              'RABU'=>'Rabu','KHAMIS'=>'Khamis',default=>$g['day']
            };
          ?>
          <tr>
            <td><span class="grp-lbl"><?= htmlspecialchars($g['group_label']) ?></span></td>
            <td><span class="day-b <?= $dc ?>"><?= $dl ?></span></td>
            <td><?= htmlspecialchars($g['time_start']) ?> – <?= htmlspecialchars($g['time_end']) ?></td>
            <td><span class="venue-t">📍 <?= htmlspecialchars($g['venue'] ?? '–') ?></span></td>
            <td style="text-align:center">
              <?php if (!$inWl): ?>
                <a href="index.php?add=<?= urlencode($c['code']) ?>&from_college=<?= urlencode($selected_college) ?>">
                  <button class="btn-add-r">+ Add</button>
                </a>
              <?php else: ?>
                <button class="btn-done-r" disabled>✓ Added</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>
</div>

<footer>SmartTimetable UUM | Plan Smarter, Register Easier</footer>

<script>
function toggle(code) {
    document.getElementById('g-'+code).classList.toggle('open');
    document.getElementById('arr-'+code).classList.toggle('open');
}
// Auto-open if only 1 result
<?php if (count($courses)===1): ?>
toggle(<?= json_encode(array_key_first($courses)) ?>);
<?php endif; ?>
</script>
</body>
</html>
