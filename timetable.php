<?php
session_start();
include 'config.php';

if (empty($_SESSION['student_id'])) { header("Location: dashboard.php?open=1"); exit(); }

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['selected'])) {
    $_SESSION['final_schedule'] = $_POST['selected'];
}
if (empty($_SESSION['final_schedule'])) { header("Location: wishlist.php"); exit(); }

function timeToMinutes($t){ $p=explode(':',$t); return (int)$p[0]*60+(int)($p[1]??0); }

$day_map=['AHAD'=>'SUNDAY','SUNDAY'=>'SUNDAY','ISNIN'=>'MONDAY','MONDAY'=>'MONDAY',
          'SELASA'=>'TUESDAY','TUESDAY'=>'TUESDAY','RABU'=>'WEDNESDAY','WEDNESDAY'=>'WEDNESDAY',
          'KHAMIS'=>'THURSDAY','THURSDAY'=>'THURSDAY'];
$days      =['SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY'];
$time_slots=['08:30','09:30','10:30','11:30','12:30','13:30','14:30','15:30','16:30'];
$colors    =['#b5eaf7','#b5f7c5','#f7eab5','#f7b5c5','#d5b5f7','#f7d5b5','#b5c5f7','#f7f7b5'];

$grid=[]; foreach($days as $d) foreach($time_slots as $t) $grid[$d][$t]=null;
$ci=0; $course_colors=[]; $schedule_info=[];

foreach($_SESSION['final_schedule'] as $code=>$group_label){
    $c=mysqli_real_escape_string($conn,$code);
    $g=mysqli_real_escape_string($conn,$group_label);
    $nr=mysqli_query($conn,"SELECT course_name,credit_hours FROM courses WHERE course_code='$c'");
    $row=mysqli_fetch_assoc($nr);
    $cname=$row['course_name']??$code;
    $cr=$row['credit_hours']??'';
    $color=$colors[$ci++%count($colors)];
    $course_colors[$code]=$color;
    $schedule_info[$code]=['name'=>$cname,'group'=>$group_label,'color'=>$color,'credits'=>$cr];
    $res=mysqli_query($conn,"SELECT * FROM class_groups WHERE course_code='$c' AND group_label='$g'");
    while($s=mysqli_fetch_assoc($res)){
        $eng=$day_map[strtoupper(trim($s['day']))]??null;
        if(!$eng||!in_array($eng,$days)) continue;
        $sm=timeToMinutes($s['start_time']); $em=timeToMinutes($s['end_time']);
        foreach($time_slots as $t){
            $tm=timeToMinutes($t);
            if($tm>=$sm&&$tm<$em)
                $grid[$eng][$t]=['code'=>$code,'name'=>$cname,'group'=>$group_label,'venue'=>$s['venue'],'color'=>$color];
        }
    }
}

if(empty($_SESSION['share_token'])) $_SESSION['share_token']=bin2hex(random_bytes(5));
$share_url="https://smarttimetableuum.uum.edu.my/s/".$_SESSION['share_token'];
$active_page='timetable';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Final Timetable – SmartTimetable UUM</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}

.page{max-width:1300px;margin:28px auto;padding:0 20px;display:flex;gap:24px;align-items:flex-start}
.left{flex:1;min-width:0}
.right{display:flex;flex-direction:column;gap:12px;width:170px;flex-shrink:0;position:sticky;top:70px}

.pg-title{font-size:20px;font-weight:800;color:#2c3e50;margin-bottom:16px}

/* Timetable card */
#tt-capture{background:white;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.08)}
table.tt{width:100%;border-collapse:collapse}
table.tt th{background:#2c3e50;color:white;padding:10px 6px;text-align:center;font-size:12px}
table.tt td{border:1px solid #eee;padding:6px 4px;text-align:center;vertical-align:middle;min-width:90px;font-size:11px}
.tc{background:#f8f9fa;font-size:11px;color:#777;font-weight:600;white-space:nowrap;min-width:76px}
.cc{border-radius:5px;padding:6px 3px;font-size:10px;font-weight:700;line-height:1.55}

/* Legend */
.legend{background:white;border-radius:12px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.07);margin-top:16px}
.legend h4{font-size:11px;font-weight:800;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.leg-item{display:flex;align-items:center;gap:8px;margin-bottom:7px;font-size:11px;color:#555}
.leg-dot{width:13px;height:13px;border-radius:3px;flex-shrink:0}

.btn-back{display:inline-block;margin-top:14px;background:#f0f2f5;color:#2c3e50;border:1.5px solid #dde;padding:8px 18px;border-radius:20px;text-decoration:none;font-size:13px;font-weight:600}
.btn-back:hover{background:#e5e8ec}

/* Action buttons */
.btn-action{
    display:flex;align-items:center;justify-content:center;gap:8px;
    background:white;border:2px solid #2c3e50;color:#2c3e50;
    border-radius:30px;padding:11px 18px;font-size:14px;font-weight:800;
    cursor:pointer;width:100%;transition:.15s;
}
.btn-action:hover{background:#2c3e50;color:white}
.btn-action svg{width:18px;height:18px;flex-shrink:0}

/* Modals */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
.modal-bg.active{display:flex}
.modal{background:white;border-radius:14px;padding:28px 26px;width:400px;max-width:94vw;box-shadow:0 16px 50px rgba(0,0,0,.25);animation:pop .2s ease}
@keyframes pop{from{transform:scale(.92);opacity:0}to{transform:scale(1);opacity:1}}
.modal h3{font-size:18px;font-weight:800;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #f0f0f0;color:#2c3e50}

/* Download modal */
.field-lbl{font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px}
.radio-group{display:flex;flex-direction:column;gap:9px;margin-bottom:18px}
.radio-group label{display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;color:#333}
.radio-group input[type=radio]{width:17px;height:17px;accent-color:#2c3e50}
.sel-quality{width:100%;padding:9px 11px;border:1.5px solid #ddd;border-radius:8px;font-size:13px}
.btn-dl{width:100%;background:#2c3e50;color:white;border:none;padding:12px;border-radius:8px;font-size:14px;font-weight:800;cursor:pointer;margin-top:16px}
.btn-dl:hover{background:#1a252f}

/* Share modal */
.share-link-row{display:flex;gap:8px;margin-bottom:18px}
.share-input{flex:1;border:1.5px solid #ddd;border-radius:8px;padding:9px 12px;font-size:12px;color:#666;background:#f9f9f9}
.btn-copy{background:#2c3e50;color:white;border:none;border-radius:8px;padding:9px 13px;cursor:pointer;font-size:12px;font-weight:700;white-space:nowrap}
.share-via-lbl{font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px}
.share-icons{display:flex;gap:14px;justify-content:center;margin-bottom:18px}
.share-icon-btn{display:flex;flex-direction:column;align-items:center;gap:5px;font-size:11px;color:#555;cursor:pointer;background:none;border:none;font-family:'Segoe UI',sans-serif}
.s-circle{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.btn-done{width:100%;background:white;border:1.5px solid #ddd;color:#555;padding:11px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer}
.btn-done:hover{background:#f5f5f5}
.copy-toast{display:none;color:#27ae60;font-size:12px;text-align:center;margin-top:4px}

@media(max-width:900px){.page{flex-direction:column}.right{width:100%;flex-direction:row;position:static}}
footer{text-align:center;margin:32px 0 20px;color:#bbb;font-size:12px}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page">
    <div class="left">
        <div class="pg-title">🗓️ Final Timetable</div>

        <div id="tt-capture">
            <table class="tt">
                <thead><tr><th>Time / Day</th><?php foreach($days as $d) echo "<th>$d</th>"; ?></tr></thead>
                <tbody>
                <?php foreach($time_slots as $t):
                    $nx=date('H:i',strtotime($t)+3600); ?>
                <tr>
                    <td class="tc"><?=$t?><br><span style="color:#bbb;font-size:9px"><?=$nx?></span></td>
                    <?php foreach($days as $d):
                        $cell=$grid[$d][$t]??null; ?>
                        <?php if($cell): ?>
                            <td style="background:<?=$cell['color']?>">
                                <div class="cc"><?=htmlspecialchars($cell['code'])?><br>Grp <?=htmlspecialchars($cell['group'])?><br><?=htmlspecialchars($cell['venue'])?></div>
                            </td>
                        <?php else: ?><td></td><?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div class="legend">
            <h4>Courses</h4>
            <?php foreach($schedule_info as $code=>$info): ?>
            <div class="leg-item">
                <div class="leg-dot" style="background:<?=$info['color']?>"></div>
                <span><strong><?=htmlspecialchars($code)?></strong> — <?=htmlspecialchars($info['name'])?> | Grp <?=htmlspecialchars($info['group'])?> | <?=$info['credits']?> cr</span>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="generate.php" class="btn-back">← Back to Schedules</a>
    </div>

    <!-- Action buttons -->
    <div class="right">
        <button class="btn-action" onclick="document.getElementById('dl-modal').classList.add('active')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>DOWNLOAD
        </button>
        <button class="btn-action" onclick="document.getElementById('sh-modal').classList.add('active')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
            </svg>SHARE
        </button>
    </div>
</div>

<!-- Download Modal -->
<div class="modal-bg" id="dl-modal">
    <div class="modal">
        <h3>Download Timetable</h3>
        <div class="field-lbl">File Type</div>
        <div class="radio-group">
            <label><input type="radio" name="ft" value="pdf" checked> PDF Document (.pdf)</label>
            <label><input type="radio" name="ft" value="png"> Image File (.png)</label>
            <label><input type="radio" name="ft" value="jpg"> Image File (.jpg)</label>
        </div>
        <div class="field-lbl">Quality</div>
        <select class="sel-quality" id="ql-sel">
            <option value="high">High (Best for printing)</option>
            <option value="medium">Medium (Balanced)</option>
            <option value="low">Low (Smaller file)</option>
        </select>
        <button class="btn-dl" id="btn-dl">Download</button>
    </div>
</div>

<!-- Share Modal -->
<div class="modal-bg" id="sh-modal">
    <div class="modal">
        <h3>Share Timetable</h3>
        <div class="field-lbl">Share Link</div>
        <div class="share-link-row">
            <input class="share-input" id="sl-input" type="text" value="<?=htmlspecialchars($share_url)?>" readonly>
            <button class="btn-copy" onclick="copyLink()">Copy<br>Link</button>
        </div>
        <div class="copy-toast" id="copy-toast">✓ Copied!</div>
        <div class="share-via-lbl">Share Via</div>
        <div class="share-icons">
            <button class="share-icon-btn" onclick="shareVia('whatsapp')">
                <div class="s-circle" style="background:#25D366">
                    <svg width="26" height="26" viewBox="0 0 32 32" fill="white"><path d="M16 3C9.373 3 4 8.373 4 15c0 2.385.668 4.61 1.832 6.504L4 29l7.695-1.805A12.94 12.94 0 0 0 16 27c6.627 0 12-5.373 12-12S22.627 3 16 3zm6.406 16.594c-.273.766-1.348 1.406-2.219 1.594-.594.125-1.367.227-3.969-.855-3.336-1.367-5.484-4.762-5.652-4.984-.164-.223-1.348-1.797-1.348-3.43s.852-2.43 1.156-2.762a1.22 1.22 0 0 1 .883-.414c.219 0 .438.004.629.012.203.008.473-.078.742.566.273.656.93 2.273.961 2.438.164.328.109.711-.109 1.02-.219.313-.328.5-.547.766-.219.273-.461.57-.219.957.242.383 1.078 1.773 2.313 2.875 1.594 1.414 2.938 1.852 3.352 2.07.414.219.656.188.898-.109.242-.297 1.043-1.219 1.32-1.633.273-.414.547-.344.922-.211.375.133 2.383 1.125 2.793 1.328.414.211.688.313.789.484.105.18.105 1.027-.168 1.793z"/></svg>
                </div>WhatsApp
            </button>
            <button class="share-icon-btn" onclick="shareVia('telegram')">
                <div class="s-circle" style="background:#2CA5E0">
                    <svg width="26" height="26" viewBox="0 0 32 32" fill="white"><path d="M16 3C9.373 3 4 8.373 4 15s5.373 12 12 12 12-5.373 12-12S22.627 3 16 3zm5.93 8.291-2.027 9.555c-.148.658-.537.818-1.09.508l-3-2.21-1.449 1.394c-.16.16-.295.295-.605.295l.215-3.054 5.56-5.023c.242-.215-.053-.334-.375-.119l-6.871 4.326-2.96-.924c-.644-.201-.658-.644.134-.953l11.562-4.458c.536-.194 1.005.131.906.663z"/></svg>
                </div>Telegram
            </button>
            <button class="share-icon-btn" onclick="shareVia('email')">
                <div class="s-circle" style="background:#e0e0e0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#444" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
                </div>Email
            </button>
            <button class="share-icon-btn" onclick="shareVia('other')">
                <div class="s-circle" style="background:#e0e0e0">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#555"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                </div>Others
            </button>
        </div>
        <button class="btn-done" onclick="document.getElementById('sh-modal').classList.remove('active')">Done</button>
    </div>
</div>

<footer>SmartTimetable UUM | Plan Smarter, Register Easier</footer>

<script>
const shareUrl=<?=json_encode($share_url)?>;
document.querySelectorAll('.modal-bg').forEach(b=>b.addEventListener('click',e=>{if(e.target===b)b.classList.remove('active')}));
function copyLink(){navigator.clipboard.writeText(shareUrl).then(()=>{const t=document.getElementById('copy-toast');t.style.display='block';setTimeout(()=>t.style.display='none',2500)})}
function shareVia(p){
    const msg=encodeURIComponent("Check out my UUM timetable! "+shareUrl);
    const urls={whatsapp:"https://wa.me/?text="+msg,telegram:"https://t.me/share/url?url="+encodeURIComponent(shareUrl),email:"mailto:?subject=My SmartTimetable UUM&body="+msg};
    if(p==='other'){navigator.share?navigator.share({title:'SmartTimetable UUM',url:shareUrl}):copyLink();return;}
    window.open(urls[p],'_blank');
}
document.getElementById('btn-dl').addEventListener('click',async()=>{
    const ft=document.querySelector('input[name=ft]:checked').value;
    const ql=document.getElementById('ql-sel').value;
    const scale=ql==='high'?3:ql==='medium'?2:1;
    const btn=document.getElementById('btn-dl');
    btn.textContent='Preparing...'; btn.disabled=true;
    try{
        const canvas=await html2canvas(document.getElementById('tt-capture'),{scale,useCORS:true,backgroundColor:'#ffffff'});
        if(ft==='pdf'){
            const{jsPDF}=window.jspdf;
            const pdf=new jsPDF({orientation:'landscape',unit:'px',format:[canvas.width,canvas.height]});
            pdf.addImage(canvas.toDataURL('image/jpeg',.95),'JPEG',0,0,canvas.width,canvas.height);
            pdf.save('SmartTimetable_UUM.pdf');
        } else {
            const a=document.createElement('a');
            a.download='SmartTimetable_UUM.'+ft;
            a.href=canvas.toDataURL(ft==='png'?'image/png':'image/jpeg',ql==='high'?.95:ql==='medium'?.8:.6);
            a.click();
        }
    } catch(e){alert('Download failed. Try again.');}
    btn.textContent='Download'; btn.disabled=false;
    document.getElementById('dl-modal').classList.remove('active');
});
</script>
</body>
</html>