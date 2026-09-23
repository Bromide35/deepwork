'use strict';

const API  = DW.base + '/api';
const CSRF = DW.csrf;
const $ = (id) => document.getElementById(id);

const el = {
  digitL:$('digitL'), digitR:$('digitR'), keyL:$('keyL'), keyR:$('keyR'),
  wedge:$('wedge'), ticks:$('ticks'), handEnd:$('handEnd'),
  fuseLive:$('fuseLive'), spark:$('spark'), embers:$('embers'),
  playBtn:$('playBtn'), playIcon:$('playIcon'), pauseIcon:$('pauseIcon'),
  setup:$('setup'), live:$('live'), scoreVal:$('scoreVal'), blurVal:$('blurVal'),
  customMin:$('customMin'), customSec:$('customSec'),
  catSel:$('catSel'), soundOn:$('soundOn'),
  resetBtn:$('resetBtn'), againBtn:$('againBtn'),
  finish:$('finish'), finishTitle:$('finishTitle'),
  finishBody:$('finishBody'), finishEmoji:$('finishEmoji'), greet:$('greet'),
};

let S = { phase:'idle', sessionId:null, targetSec:50*60, endAtMs:null,
          leftMs:50*60*1000, awayMs:0, blurCount:0, categoryId:null };
let rafId=null, beatId=null, emberId=null, awayFrom=null, wakeLock=null, lastSec=-1;

const pad = (n) => String(Math.max(0,n)).padStart(2,'0');

async function api(path, body, method='POST') {
  const res = await fetch(API + path, {
    method,
    headers:{ 'Content-Type':'application/json', 'X-CSRF-Token':CSRF },
    body: method==='GET' ? undefined : JSON.stringify(body || {}),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

function calcScore(total, away, blur) {
  if (total < 60) return 100;
  const hours = total/3600;
  const ratio = Math.max(0,(total-away)/total);
  const penalty = Math.min(0.5, 0.04*(blur/Math.max(hours,0.25)));
  return Math.round(100*ratio*(1-penalty));
}

/* ★ for loop วาดขีดบนหน้าปัด */
(function drawTicks(){
  const CX=110, CY=118, R1=74, R2=62;
  let out='';
  for (let i=0; i<12; i++) {
    const a = (i/12)*Math.PI*2 - Math.PI/2;
    const wob = (i%3)*1.6 - 1.4;
    const x1 = CX+R1*Math.cos(a), y1 = CY+R1*Math.sin(a);
    const x2 = CX+(R2+wob)*Math.cos(a), y2 = CY+(R2+wob)*Math.sin(a);
    out += `<path d="M${x1.toFixed(1)} ${y1.toFixed(1)} L${x2.toFixed(1)} ${y2.toFixed(1)}"/>`;
  }
  el.ticks.innerHTML = out;
})();

function wedgePath(frac) {
  const CX=110, CY=118, R=66;
  if (frac <= 0) return '';
  const f = Math.min(frac, 0.9999);
  const a = -Math.PI/2 + Math.PI*2*f;
  const x = CX + R*Math.cos(a), y = CY + R*Math.sin(a);
  return `M ${CX} ${CY} L ${CX} ${CY-R} A ${R} ${R} 0 ${f>0.5?1:0} 1 ${x.toFixed(2)} ${y.toFixed(2)} Z`;
}

const fusePath = el.fuseLive;
const fuseLen  = fusePath.getTotalLength();

function paintFuse(frac) {
  fusePath.style.strokeDasharray = `${Math.max(0,Math.min(100,frac*100))} 100`;
  const pt = fusePath.getPointAtLength(fuseLen * Math.max(frac, 0.001));
  el.spark.setAttribute('transform', `translate(${pt.x} ${pt.y})`);
  return pt;
}

function spawnEmber(pt) {
  const svg = fusePath.ownerSVGElement.getBoundingClientRect();
  const d = document.createElement('span');
  d.className = 'ember';
  d.style.left = (pt.x/800)*svg.width + 'px';
  d.style.top  = (pt.y/90)*svg.height + 'px';
  d.style.setProperty('--dx', (Math.random()*40-20).toFixed(0)+'px');
  el.embers.appendChild(d);
  setTimeout(() => d.remove(), 1500);
}

function render() {
  const leftMs  = S.phase === 'running' ? Math.max(0, S.endAtMs - Date.now()) : S.leftMs;
  const leftSec = Math.ceil(leftMs/1000);
  const frac    = S.targetSec > 0 ? leftMs/(S.targetSec*1000) : 0;

  let L, R, kL, kR;
  if (leftSec >= 3600) {
    L = Math.floor(leftSec/3600); R = Math.floor((leftSec%3600)/60);
    kL = 'ชั่วโมง'; kR = 'นาที';
  } else {
    L = Math.floor(leftSec/60); R = leftSec%60;
    kL = 'นาที'; kR = 'วินาที';
  }
  el.digitL.textContent = pad(L);
  el.digitR.textContent = pad(R);
  el.keyL.textContent = kL;
  el.keyR.textContent = kR;

  if (leftSec !== lastSec) {
    lastSec = leftSec;
    if (S.phase === 'running') {
      el.digitR.classList.remove('pop');
      void el.digitR.offsetWidth;
      el.digitR.classList.add('pop');
    }
  }

  el.wedge.setAttribute('d', wedgePath(frac));
  el.handEnd.style.transform = `rotate(${(frac*360).toFixed(2)}deg)`;
  paintFuse(frac);

  document.body.classList.toggle('danger', S.phase==='running' && frac <= 0.1);

  const used = S.targetSec - leftSec;
  el.scoreVal.textContent = calcScore(used, Math.floor(S.awayMs/1000), S.blurCount);
  el.blurVal.textContent  = S.blurCount;

  if (S.phase === 'running') {
    if (leftMs <= 0) { finishSession(true); return; }
    rafId = requestAnimationFrame(render);
  }
}

/* ★ switch เปลี่ยนสถานะ */
function setPhase(next) {
  S.phase = next;
  document.body.dataset.state = next;
  switch (next) {
    case 'idle':
      el.setup.hidden = false; el.live.hidden = true;
      el.playIcon.hidden = false; el.pauseIcon.hidden = true;
      el.greet.textContent = 'เลือกเวลาแล้วกดเริ่มได้เลย';
      document.body.classList.remove('danger');
      break;
    case 'running':
      el.setup.hidden = true; el.live.hidden = false;
      el.playIcon.hidden = true; el.pauseIcon.hidden = false;
      el.greet.textContent = 'กำลังโฟกัส… อย่าเพิ่งออกไปไหนนะ';
      break;
    case 'paused':
      el.playIcon.hidden = false; el.pauseIcon.hidden = true;
      el.greet.textContent = 'หยุดชั่วคราว — เชือกยังไม่ดับ';
      break;
    case 'finished':
      el.playIcon.hidden = false; el.pauseIcon.hidden = true;
      el.greet.textContent = 'จบรอบแล้ว!';
      break;
  }
}


function selectedSeconds() {
  const v = document.querySelector('input[name="preset"]:checked')?.value;
  if (v !== 'custom') return (Number(v) || 25) * 60;

  const m = Math.max(0, Math.min(480, Number(el.customMin.value) || 0));
  const s = Math.max(0, Math.min(59,  Number(el.customSec.value) || 0));
  return Math.max(10, m * 60 + s);   // ขั้นต่ำ 10 วินาที
}


function syncTarget() {
  S.targetSec = selectedSeconds();
  S.leftMs    = S.targetSec * 1000;
  render();
}

async function startSession() {
  S.targetSec  = selectedSeconds();
  S.categoryId = el.catSel.value ? Number(el.catSel.value) : null;
  S.awayMs = 0; S.blurCount = 0;

  const r = await api('/session_start.php', {
    category_id:S.categoryId, mode:'countdown', target_seconds:S.targetSec,
  });
  const skew = Date.now() - new Date(r.server_now).getTime();
  S.sessionId = r.session_id;
  S.endAtMs   = new Date(r.started_at).getTime() + skew + S.targetSec*1000;

  setPhase('running');
  requestWakeLock();
  loopStart();
}

function loopStart() {
  cancelAnimationFrame(rafId);
  render();
  clearInterval(beatId); beatId = setInterval(heartbeat, 30000);
  clearInterval(emberId);
  emberId = setInterval(() => {
    if (S.phase==='running' && !document.hidden) {
      const frac = Math.max(0, (S.endAtMs-Date.now())/(S.targetSec*1000));
      spawnEmber(fusePath.getPointAtLength(fuseLen*Math.max(frac,0.001)));
    }
  }, 240);
}

function pauseSession() {
  S.leftMs = Math.max(0, S.endAtMs - Date.now());
  cancelAnimationFrame(rafId); clearInterval(emberId);
  setPhase('paused'); render();
}
function resumeSession() {
  S.endAtMs = Date.now() + S.leftMs;
  setPhase('running'); loopStart();
}

async function finishSession(completed) {
  cancelAnimationFrame(rafId); clearInterval(beatId); clearInterval(emberId);
  releaseWakeLock();
  if (awayFrom) { S.awayMs += Date.now()-awayFrom; awayFrom = null; }

  setPhase('finished');
  S.leftMs = 0;
  render();

  let data = null;
  try {
    data = await api('/session_stop.php', {
      session_id:S.sessionId,
      away_seconds:Math.floor(S.awayMs/1000),
      blur_count:S.blurCount,
      completed: completed ? 1 : 0,
    });
  } catch(_) {}

  if (completed && el.soundOn.checked) playChime();
  showFinish(completed, data);
}

/* ★ switch เลือกข้อความผลลัพธ์ */
function showFinish(completed, data) {
  const score = data?.focus_score ?? 0;
  const mins  = Math.round((data?.focus_seconds ?? 0)/60);
  let emoji, title;
  switch (true) {
    case (!completed):  emoji='🌤'; title='ยกเลิกรอบนี้แล้ว'; break;
    case (score>=90):   emoji='🔥'; title='Deep Work เต็มสิบ!'; break;
    case (score>=75):   emoji='✅'; title='โฟกัสได้ดีมาก'; break;
    case (score>=55):   emoji='⚠️'; title='ยังหลุดอยู่บ้างนะ'; break;
    default:            emoji='💤'; title='รอบนี้สมาธิกระจัดกระจาย';
  }
  el.finishEmoji.textContent = emoji;
  el.finishTitle.textContent = title;
  el.finishBody.innerHTML = completed
    ? `โฟกัสจริง <b>${mins} นาที</b> · Focus Score <b>${score}</b><br>หลุดโฟกัส ${S.blurCount} ครั้ง`
    : `บันทึกเวลาที่ทำไปแล้ว <b>${mins} นาที</b> ไว้เรียบร้อย`;
  el.finish.hidden = false;
}

function playChime() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    [880,1174,1568].forEach((f,i) => {
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.type='sine'; o.frequency.value=f;
      o.connect(g); g.connect(ctx.destination);
      const t = ctx.currentTime + i*0.16;
      g.gain.setValueAtTime(0.0001,t);
      g.gain.exponentialRampToValueAtTime(0.25,t+0.02);
      g.gain.exponentialRampToValueAtTime(0.0001,t+0.7);
      o.start(t); o.stop(t+0.75);
    });
  } catch(_) {}
}

function heartbeat() {
  if (S.phase!=='running' || !S.sessionId) return;
  const payload = JSON.stringify({
    session_id:S.sessionId,
    away_seconds:Math.floor(S.awayMs/1000),
    blur_count:S.blurCount,
  });
  if (document.hidden && navigator.sendBeacon) {
    navigator.sendBeacon(API+'/session_heartbeat.php', new Blob([payload],{type:'application/json'}));
  } else {
    fetch(API+'/session_heartbeat.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body:payload, keepalive:true,
    }).catch(()=>{});
  }
}

function onHide(){ if (S.phase==='running' && !awayFrom) awayFrom = Date.now(); }
function onShow(){
  if (!awayFrom) return;
  const gap = Date.now()-awayFrom; awayFrom = null;
  if (gap > 3000) { S.awayMs += gap; S.blurCount += 1; heartbeat(); }
}
async function requestWakeLock(){ try { wakeLock = await navigator.wakeLock?.request('screen'); } catch(_){} }
function releaseWakeLock(){ wakeLock?.release?.(); wakeLock = null; }

/* ===== events ===== */
el.playBtn.addEventListener('click', () => {
  el.playBtn.disabled = true;
  const act = S.phase==='running' ? pauseSession
            : S.phase==='paused'  ? resumeSession
            : startSession;
  Promise.resolve(act())
    .catch(err => alert('เกิดข้อผิดพลาด: ' + err.message))
    .finally(() => { el.playBtn.disabled = false; });
});

el.resetBtn.addEventListener('click', () => {
  if (confirm('ยกเลิกรอบนี้? เวลาที่ทำไปแล้วจะถูกบันทึกไว้')) finishSession(false);
});

el.againBtn.addEventListener('click', () => {
  el.finish.hidden = true;
  S.leftMs = S.targetSec*1000;
  S.awayMs = 0; S.blurCount = 0; S.sessionId = null;
  setPhase('idle'); render();
});


document.querySelectorAll('input[name="preset"]').forEach(r => {
  r.addEventListener('change', () => {
    const custom = r.value === 'custom';
    el.customMin.disabled = !custom;
    el.customSec.disabled = !custom;
    document.querySelectorAll('.spin-btn').forEach(b => b.disabled = !custom);
    if (!custom) { el.customMin.value = r.value; el.customSec.value = 0; }
    syncTarget();
  });
});


el.customMin.addEventListener('input', syncTarget);
el.customSec.addEventListener('input', syncTarget);


document.querySelectorAll('.spin-btn').forEach(btn => {
  btn.disabled = true;                       
  btn.addEventListener('click', () => {
    const step = Number(btn.dataset.step);
    if (btn.dataset.target === 'min') {
      el.customMin.value = Math.max(0, Math.min(480, (Number(el.customMin.value)||0) + step));
    } else {
      let sec = (Number(el.customSec.value)||0) + step;
      let min = Number(el.customMin.value)||0;
      /* ทดวินาที ↔ นาที ให้อัตโนมัติ */
      if (sec > 59) { sec -= 60; min++; }
      if (sec < 0)  { if (min > 0) { sec += 60; min--; } else sec = 0; }
      el.customSec.value = sec;
      el.customMin.value = Math.min(480, min);
    }
    syncTarget();
  });
});

const drawer = $('drawer'), scrim = $('scrim'), burger = $('burgerBtn');
function toggleDrawer(open){ drawer.hidden = !open; scrim.hidden = !open; }
burger.addEventListener('click', () => toggleDrawer(drawer.hidden));
scrim.addEventListener('click', () => toggleDrawer(false));

$('themeBtn').addEventListener('click', () => {
  const root = document.documentElement;
  const next = root.dataset.theme === 'paper' ? 'dark' : 'paper';
  root.dataset.theme = next;
  localStorage.setItem('dw_theme', next);
});
document.documentElement.dataset.theme = localStorage.getItem('dw_theme') || 'dark';

document.addEventListener('keydown', (ev) => {
  if (ev.code==='Space' && !['INPUT','SELECT','TEXTAREA'].includes(ev.target.tagName)) {
    ev.preventDefault(); el.playBtn.click();
  }
  if (ev.key === 'Escape') toggleDrawer(false);
});
document.addEventListener('visibilitychange', () => document.hidden ? onHide() : onShow());
window.addEventListener('blur', onHide);
window.addEventListener('focus', onShow);
window.addEventListener('beforeunload', heartbeat);


S.phase = 'idle';
S.leftMs = S.targetSec * 1000;
document.body.dataset.state = 'idle';

if ($('finish')) $('finish').hidden = true;

setPhase('idle');
render();