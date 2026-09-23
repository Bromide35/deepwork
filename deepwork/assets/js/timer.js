'use strict';

const API  = DW.base + '/api';
const CSRF = DW.csrf;
const $ = (id) => document.getElementById(id);

const els = {
  clock: $('clock'), score: $('scoreVal'), blur: $('blurVal'), away: $('awayVal'),
  btn: $('toggleBtn'), state: $('stateText'), result: $('result'),
  catRow: $('catRow'), addCat: $('addCat'),
};

let S = { sessionId:null, startedAtMs:null, awayMs:0, blurCount:0, running:false, categoryId:null };
let tickId = null, beatId = null, awayFrom = null, wakeLock = null;

const pad = (n) => String(Math.max(0,n)).padStart(2,'0');
const fmt = (s) => `${pad(Math.floor(s/3600))}:${pad(Math.floor(s/60)%60)}:${pad(s%60)}`;
const fmtShort = (s) => `${Math.floor(s/60)}:${pad(s%60)}`;

async function api(path, body, method='POST') {
  const res = await fetch(API + path, {
    method,
    headers: { 'Content-Type':'application/json', 'X-CSRF-Token':CSRF },
    body: method === 'GET' ? undefined : JSON.stringify(body || {}),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

function calcScore(total, away, blur) {
  if (total < 60) return 100;
  const hours = total/3600;
  const ratio = Math.max(0, (total-away)/total);
  const penalty = Math.min(0.5, 0.04*(blur/Math.max(hours,0.25)));
  return Math.round(100*ratio*(1-penalty));
}
function scoreClass(s){
  if (s>=90) return 'sc-great';
  if (s>=75) return 'sc-good';
  if (s>=55) return 'sc-mid';
  return 'sc-bad';
}

function render() {
  if (!S.running || !S.startedAtMs) return;
  const elapsed = Math.max(0, Math.floor((Date.now()-S.startedAtMs)/1000));
  const awaySec = Math.floor(S.awayMs/1000);
  const score = calcScore(elapsed, awaySec, S.blurCount);
  els.clock.textContent = fmt(Math.max(0, elapsed-awaySec));
  els.blur.textContent  = S.blurCount;
  els.away.textContent  = fmtShort(awaySec);
  els.score.textContent = score;
  els.score.className   = 'stat-val ' + scoreClass(score);
}

function setRunningUI(on) {
  els.btn.textContent = on ? 'หยุด' : 'เริ่มโฟกัส';
  els.state.textContent = on ? 'กำลังโฟกัส…' : 'พร้อมเริ่มโฟกัส';
  els.catRow.classList.toggle('locked', on);
}

async function requestWakeLock(){ try { wakeLock = await navigator.wakeLock?.request('screen'); } catch(_){} }
function releaseWakeLock(){ wakeLock?.release?.(); wakeLock = null; }

function onHide(){ if (S.running && !awayFrom) awayFrom = Date.now(); }
function onShow(){
  if (!awayFrom) return;
  const gap = Date.now()-awayFrom; awayFrom = null;
  if (gap > 3000) { S.awayMs += gap; S.blurCount += 1; heartbeat(); }
}

function heartbeat() {
  if (!S.running || !S.sessionId) return;
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

function currentMode() {
  return document.querySelector('input[name="mode"]:checked')?.value || 'stopwatch';
}

async function start() {
  const active = els.catRow.querySelector('.chip.active');
  S.categoryId = active ? Number(active.dataset.id) : null;

  const r = await api('/session_start.php', { category_id:S.categoryId, mode:currentMode() });
  const skew = Date.now() - new Date(r.server_now).getTime();

  S.sessionId = r.session_id;
  S.startedAtMs = new Date(r.started_at).getTime() + skew;
  S.awayMs = 0; S.blurCount = 0; S.running = true;

  setRunningUI(true);
  els.result.hidden = true;
  requestWakeLock();
  tickId = setInterval(render, 250);
  beatId = setInterval(heartbeat, 30000);
  render();
}

async function stop() {
  clearInterval(tickId); clearInterval(beatId);
  releaseWakeLock();
  if (awayFrom) { S.awayMs += Date.now()-awayFrom; awayFrom = null; }

  const r = await api('/session_stop.php', {
    session_id:S.sessionId,
    away_seconds:Math.floor(S.awayMs/1000),
    blur_count:S.blurCount,
    completed:1,
  });

  S = { sessionId:null, startedAtMs:null, awayMs:0, blurCount:0, running:false, categoryId:S.categoryId };
  setRunningUI(false);
  els.clock.textContent = '00:00:00';
  els.away.textContent  = '0:00';
  els.blur.textContent  = '0';
  els.score.textContent = '100';
  els.score.className   = 'stat-val sc-great';

  els.result.hidden = false;
  els.result.innerHTML =
    `<strong>บันทึกแล้ว</strong>
     <span>โฟกัสจริง ${fmt(r.focus_seconds)}</span>
     <span class="${scoreClass(r.focus_score)}">${r.label.emoji} ${r.label.text} · ${r.focus_score}</span>`;
}

async function resume() {
  try {
    const r = await api('/session_active.php', null, 'GET');
    if (!r.active) return;
    const skew = Date.now() - new Date(r.server_now).getTime();
    S.sessionId   = r.session_id;
    S.startedAtMs = new Date(r.started_at).getTime() + skew;
    S.awayMs      = r.away_seconds * 1000;
    S.blurCount   = r.blur_count;
    S.running     = true;
    if (r.category_id) {
      els.catRow.querySelectorAll('.chip').forEach(c =>
        c.classList.toggle('active', Number(c.dataset.id) === r.category_id));
    }
    setRunningUI(true);
    requestWakeLock();
    tickId = setInterval(render, 250);
    beatId = setInterval(heartbeat, 30000);
    render();
    els.state.textContent = 'กู้เซสชันที่ค้างอยู่กลับมาแล้ว';
  } catch(_) {}
}

els.btn.addEventListener('click', () => {
  els.btn.disabled = true;
  (S.running ? stop() : start())
    .catch(err => alert('เกิดข้อผิดพลาด: ' + err.message))
    .finally(() => { els.btn.disabled = false; });
});

els.catRow.addEventListener('click', (ev) => {
  const chip = ev.target.closest('.chip');
  if (!chip || chip.id === 'addCat' || S.running) return;
  els.catRow.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
});

els.addCat?.addEventListener('click', async () => {
  const name = prompt('ชื่อหมวดใหม่');
  if (!name) return;
  const r = await api('/categories.php', { name });
  const b = document.createElement('button');
  b.className = 'chip';
  b.dataset.id = r.id;
  b.style.setProperty('--chip', r.color);
  b.textContent = r.name;
  els.catRow.insertBefore(b, els.addCat);
});

document.addEventListener('visibilitychange', () => document.hidden ? onHide() : onShow());
window.addEventListener('blur', onHide);
window.addEventListener('focus', onShow);
window.addEventListener('beforeunload', heartbeat);

resume();