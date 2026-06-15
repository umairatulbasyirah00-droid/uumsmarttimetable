<?php
session_start();
include 'config.php';

if (empty($_SESSION['student_id'])) { header("Location: dashboard.php?open=1"); exit(); }
if (empty($_SESSION['wishlist']))   { header("Location: wishlist.php"); exit(); }

function timeToMinutes($time) {
    $p = explode(':', $time);
    return ((int)$p[0]*60) + (int)($p[1]??0);
}
function timesOverlap($s1,$e1,$s2,$e2) {
    return (timeToMinutes($s1)<timeToMinutes($e2)) && (timeToMinutes($e1)>timeToMinutes($s2));
}

$courses_groups = [];
foreach ($_SESSION['wishlist'] as $code) {
    $c    = mysqli_real_escape_string($conn, $code);
    $nr   = mysqli_query($conn, "SELECT course_name FROM courses WHERE course_code='$c'");
    $cname= ($r=mysqli_fetch_assoc($nr)) ? $r['course_name'] : $code;
    $res  = mysqli_query($conn, "SELECT * FROM class_groups WHERE course_code='$c' ORDER BY group_label,day");
    $by_g = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['course_name'] = $cname;
        $by_g[$row['group_label']][] = $row;
    }
    if (!empty($by_g)) $courses_groups[$code] = array_values($by_g);
}

if (empty($courses_groups)) {
    // courses in wishlist but no class_groups data
    $active_page = 'timetable';
    include 'includes/navbar.php'; // will be echoed below
    die("<div style='max-width:700px;margin:40px auto;padding:20px;background:white;border-radius:12px;text-align:center'>
        <p style='font-size:2rem'>⚠️</p>
        <h2 style='color:#2c3e50;margin-bottom:8px'>No Class Group Data</h2>
        <p style='color:#888'>No timetable data found for your selected courses. Please add data to the database first.</p>
        <a href='wishlist.php' style='display:inline-block;margin-top:16px;background:#2c3e50;color:white;padding:10px 24px;border-radius:20px;text-decoration:none;font-weight:700'>← Back to Wishlist</a>
        </div>");
}

$course_codes = array_keys($courses_groups);

function buildSchedules($codes,$groups,$idx,$cur,&$valid,$max=6){
    if(count($valid)>=$max) return;
    if($idx>=count($codes)){ $valid[]=$cur; return; }
    $code=$codes[$idx];
    foreach($groups[$code] as $gsessions){
        $clash=false;
        foreach($cur as $sc=>$sses){
            foreach($sses as $sel){
                foreach($gsessions as $gs){
                    if(strtoupper($sel['day'])===strtoupper($gs['day'])){
                        if(timesOverlap($sel['start_time'],$sel['end_time'],$gs['start_time'],$gs['end_time'])){
                            $clash=true; break 3;
                        }
                    }
                }
            }
        }
        if(!$clash){ $nc=$cur; $nc[$code]=$gsessions; buildSchedules($codes,$groups,$idx+1,$nc,$valid,$max); }
    }
}

$valid_schedules=[];
buildSchedules($course_codes,$courses_groups,0,[],$valid_schedules,6);

$time_slots=['08:30','09:30','10:30','11:30','12:30','13:30','14:30','15:30','16:30'];
$days=['SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY'];
$day_map=['AHAD'=>'SUNDAY','SUNDAY'=>'SUNDAY','ISNIN'=>'MONDAY','MONDAY'=>'MONDAY',
          'SELASA'=>'TUESDAY','TUESDAY'=>'TUESDAY','RABU'=>'WEDNESDAY','WEDNESDAY'=>'WEDNESDAY',
          'KHAMIS'=>'THURSDAY','THURSDAY'=>'THURSDAY'];
$colors=['#b5eaf7','#b5f7c5','#f7eab5','#f7b5c5','#d5b5f7','#f7d5b5','#b5c5f7','#f7f7b5'];

function buildGrid($schedule,$time_slots,$days,$day_map,$colors){
    $grid=[]; foreach($days as $d) foreach($time_slots as $t) $grid[$d][$t]=null;
    $ci=0; $cc=[];
    foreach($schedule as $code=>$sessions){
        $cc[$code]=$colors[$ci++%count($colors)];
        foreach($sessions as $row){
            $eng=$day_map[strtoupper(trim($row['day']))]??null; if(!$eng||!in_array($eng,$days)) continue;
            $sm=((int)explode(':',$row['start_time'])[0])*60+(int)(explode(':',$row['start_time'])[1]??0);
            $em=((int)explode(':',$row['end_time'])[0])*60+(int)(explode(':',$row['end_time'])[1]??0);
            foreach($time_slots as $t){
                $tm=((int)explode(':',$t)[0])*60+(int)(explode(':',$t)[1]??0);
                if($tm>=$sm&&$tm<$em)
                    $grid[$eng][$t]=['code'=>$row['course_code'],'name'=>$row['course_name'],'group'=>$row['group_label'],'venue'=>$row['venue'],'color'=>$cc[$code]];
            }
        }
    }
    return $grid;
}

$clash_details=[];
if(empty($valid_schedules)){
    $all_sess=[];
    foreach($courses_groups as $code=>$opts) $all_sess[$code]=$opts[0];
    for($i=0;$i<count($course_codes);$i++)
        for($j=$i+1;$j<count($course_codes);$j++){
            $cA=$course_codes[$i]; $cB=$course_codes[$j];
            foreach($all_sess[$cA] as $sA) foreach($all_sess[$cB] as $sB){
                if(strtoupper($sA['day'])===strtoupper($sB['day']) &&
                   timesOverlap($sA['start_time'],$sA['end_time'],$sB['start_time'],$sB['end_time']))
                    $clash_details[]=['a_code'=>$cA,'b_code'=>$cB,'a_name'=>$sA['course_name'],
                        'b_name'=>$sB['course_name'],'day'=>strtoupper($sA['day']),
                        'a_start'=>substr($sA['start_time'],0,5),'a_end'=>substr($sA['end_time'],0,5),
                        'b_start'=>substr($sB['start_time'],0,5),'b_end'=>substr($sB['end_time'],0,5)];
            }
        }
}

function renderTT($grid,$time_slots,$days){
    echo '<table class="tt"><thead><tr><th>Time / Day</th>';
    foreach($days as $d) echo "<th>$d</th>";
    echo '</tr></thead><tbody>';
    foreach($time_slots as $t){
        $nx=date('H:i',strtotime($t)+3600);
        echo "<tr><td class='tc'>$t<br><span>$nx</span></td>";
        foreach($days as $d){
            $cell=$grid[$d][$t]??null;
            if($cell) echo "<td style='background:{$cell['color']}'><div class='cc'>".htmlspecialchars($cell['code'])."<br>Grp ".htmlspecialchars($cell['group'])."<br>".htmlspecialchars($cell['venue'])."</div></td>";
            else echo '<td></td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

$active_page = 'timetable';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Timetable Preview – UUM SmartTimetable</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.wrap{max-width:1300px;margin:28px auto;padding:0 20px}

/* Clash error */
.clash-banner{background:#e74c3c;color:white;padding:16px 22px;border-radius:12px;margin-bottom:16px;font-weight:800;font-size:16px;text-align:center;letter-spacing:.5px}
.clash-card{background:white;border:2px solid #e74c3c;border-radius:12px;padding:24px;margin-bottom:20px}
.clash-card p{font-size:14px;color:#555;margin-bottom:14px}
.clash-item{display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;font-size:13px;color:#c0392b;font-weight:600}
.clash-actions{display:flex;gap:12px;margin-top:20px;flex-wrap:wrap}
.btn-bk{display:inline-block;background:#6c757d;color:white;padding:10px 20px;border-radius:20px;text-decoration:none;font-size:13px;font-weight:700}
.btn-cl{display:inline-block;background:none;color:#007bff;padding:10px 0;font-size:13px;font-weight:600;text-decoration:underline}

/* Preview */
.pg-title{font-size:20px;font-weight:800;color:#2c3e50;margin-bottom:4px}
.pg-sub{font-size:13px;color:#999;margin-bottom:22px}
.sched-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px}
@media(max-width:860px){.sched-grid{grid-template-columns:1fr}}
.sched-card{background:white;border-radius:12px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,.08)}
.sched-label{font-size:13px;font-weight:700;color:#555;margin-bottom:12px}

/* Timetable */
.tt{width:100%;border-collapse:collapse;font-size:11px}
.tt th{background:#2c3e50;color:white;padding:7px 4px;text-align:center;font-size:11px}
.tt td{border:1px solid #eee;padding:4px 3px;text-align:center;vertical-align:middle;min-width:70px}
.tc{background:#f8f9fa;font-size:10px;color:#888;white-space:nowrap;font-weight:600;min-width:72px}
.tc span{color:#bbb}
.cc{border-radius:4px;padding:4px 2px;font-size:10px;font-weight:700;line-height:1.5}

/* Select button */
.btn-sel{display:block;margin:14px auto 0;background:#2c3e50;color:white;border:none;padding:9px 28px;border-radius:20px;cursor:pointer;font-size:13px;font-weight:800;letter-spacing:.5px}
.btn-sel:hover{background:#1a252f}
.single-card{background:white;border-radius:12px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.08)}

footer{text-align:center;margin:32px 0 20px;color:#bbb;font-size:12px}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="wrap">
<?php if(empty($valid_schedules)): ?>
    <div class="clash-banner">⚠️ CLASH ERROR — TIMETABLE COULD NOT BE GENERATED</div>
    <div class="clash-card">
        <p>The following courses have overlapping time slots across <strong>all available groups</strong> and cannot be scheduled together:</p>
        <?php if(!empty($clash_details)): foreach($clash_details as $cl): ?>
            <div class="clash-item">
                <span>●</span>
                <span><?= htmlspecialchars($cl['a_code']) ?> (<?= $cl['day'] ?> <?= $cl['a_start'] ?>–<?= $cl['a_end'] ?>)
                &nbsp;clashes with&nbsp;
                <?= htmlspecialchars($cl['b_code']) ?> (<?= $cl['day'] ?> <?= $cl['b_start'] ?>–<?= $cl['b_end'] ?>)</span>
            </div>
        <?php endforeach; else: ?>
            <div class="clash-item"><span>●</span><span>All group combinations result in time conflicts.</span></div>
        <?php endif; ?>
        <div class="clash-actions">
            <a href="wishlist.php" class="btn-bk">← Back to Wishlist</a>
            <a href="course_list.php" class="btn-cl">View All Courses</a>
        </div>
    </div>

<?php else: ?>
    <div class="pg-title">Timetable Preview</div>
    <div class="pg-sub"><?= count($valid_schedules) ?> conflict-free schedule<?= count($valid_schedules)!=1?'s':'' ?> found — select one to continue</div>

    <?php if(count($valid_schedules)===1): ?>
        <div class="single-card">
            <div class="sched-label">Schedule 1 — Only available option</div>
            <?php $grid=buildGrid($valid_schedules[0],$time_slots,$days,$day_map,$colors); renderTT($grid,$time_slots,$days); ?>
            <form method="post" action="timetable.php">
                <?php foreach($valid_schedules[0] as $code=>$sess): ?>
                    <input type="hidden" name="selected[<?= htmlspecialchars($code) ?>]" value="<?= htmlspecialchars($sess[0]['group_label']) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn-sel">SELECT THIS SCHEDULE →</button>
            </form>
        </div>
    <?php else: ?>
        <div class="sched-grid">
        <?php foreach($valid_schedules as $idx=>$schedule): ?>
            <?php $grid=buildGrid($schedule,$time_slots,$days,$day_map,$colors); ?>
            <div class="sched-card">
                <div class="sched-label">Schedule <?= $idx+1 ?></div>
                <?php renderTT($grid,$time_slots,$days); ?>
                <form method="post" action="timetable.php">
                    <?php foreach($schedule as $code=>$sess): ?>
                        <input type="hidden" name="selected[<?= htmlspecialchars($code) ?>]" value="<?= htmlspecialchars($sess[0]['group_label']) ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn-sel">SELECT</button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<footer>UUM SmartTimetable | Plan Smarter, Register Easier</footer>
</body>
</html>