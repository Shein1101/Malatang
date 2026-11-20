// assets/js/admin-notify.js — 新しい注文のお知らせ (admin の全ページ) [Myat]
// admin_header.php が読み込む。どの管理ページを見ていても:
//   ・新しい注文 (調理待ち) が来たら「ピッ」と鳴らす
//   ・画面の上に「新しい注文が入りました」のお知らせを出す (クリックで注文一覧へ)
//   ・タブのタイトルと「注文」メニューに 調理待ちの数 を出す
// 「まだ確認していない注文」の id は localStorage に残すので、ページを移動しても消えない。
// orders.php はそれを使って NEW のカードを目立たせる。
(function () {
  const API      = '../api/orders.php?status=all';
  const EVERY_MS = 15000;                  // 15秒ごとに見る
  const KEY_LAST = 'chilli.admin.lastOrderId';  // ここまでの id は「もう知っている」
  const KEY_NEW  = 'chilli.admin.newOrderIds';  // まだ確認していない新しい注文の id

  const baseTitle = document.title;

  // localStorage は使えないこともある (プライベートモードなど) → 失敗しても動くように
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* 使えなくても続ける */ } },
  };

  // ---- 音 (ファイルなし。WebAudio で「ピッ」) ----
  // ブラウザの決まりで、ページを開いてから1回どこかをクリックするまで音が出ないことがある。
  // → 音が止められているときは ヘッダーに「🔇 音をONにする」ボタンを出す。押せば鳴るようになる。
  //   止められている間に注文が来たら 覚えておいて、押したときに鳴らす。
  let audioCtx = null;
  let missedBeep = false;
  try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { /* 音なし */ }
  const soundOn = () => !!audioCtx && audioCtx.state === 'running';

  async function unlock() {
    if (!audioCtx) return;
    try { if (audioCtx.state !== 'running') await audioCtx.resume(); } catch (e) { /* 次のクリックでまた */ }
    renderSoundBtn();
    if (soundOn() && missedBeep) { missedBeep = false; playBeep(); }
  }
  document.addEventListener('click', unlock);
  document.addEventListener('keydown', unlock);
  document.addEventListener('touchend', unlock);
  if (audioCtx) audioCtx.onstatechange = renderSoundBtn;

  // ヘッダーの 音ボタン (🔇 = 止められている / 🔔 = ON)
  function renderSoundBtn() {
    const box = document.querySelector('.admin-header .header-icons');
    if (!box || !audioCtx) return;
    let b = document.getElementById('soundBtn');
    if (!b) {
      b = document.createElement('button');
      b.id = 'soundBtn';
      b.type = 'button';
      b.className = 'sound-btn';
      b.addEventListener('click', async () => { await unlock(); if (soundOn()) playBeep(); });  // 押したら テストで1回鳴らす
      box.prepend(b);
    }
    const on = soundOn();
    b.classList.toggle('is-off', !on);
    b.textContent = on ? '🔔 音ON' : '🔇 音をONにする';
    b.title = on ? '新しい注文が来たら音が鳴ります (押すとテスト)' : 'ブラウザが音を止めています。押すと鳴るようになります';
  }

  function playBeep() {
    if (!soundOn()) { missedBeep = true; renderSoundBtn(); return; }  // 止められている → あとで鳴らす
    try {
      const t = audioCtx.currentTime;
      // 2回「ピッ・ピッ」(880Hz) — 1回より気づきやすい
      [0, 0.25].forEach(d => {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        gain.gain.setValueAtTime(0.001, t + d);
        gain.gain.exponentialRampToValueAtTime(0.3, t + d + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, t + d + 0.2);
        osc.start(t + d);
        osc.stop(t + d + 0.2);
      });
    } catch (e) { /* 音が出せなくても注文の処理は続ける */ }
  }

  // ---- 画面のお知らせ (どのページでも上に出る) ----
  function showBanner(count) {
    let el = document.getElementById('newOrderBanner');
    if (!el) {
      el = document.createElement('a');
      el.id = 'newOrderBanner';
      el.className = 'new-order-banner';
      el.href = 'orders.php';
      el.setAttribute('role', 'alert');
      document.body.appendChild(el);
    }
    el.innerHTML = `🔔 新しい注文が <b>${count}件</b> 入りました <span class="nob-go">確認する →</span>`;
    el.hidden = false;
  }
  function hideBanner() {
    const el = document.getElementById('newOrderBanner');
    if (el) el.hidden = true;
  }

  // 「注文」メニューのリンクに 調理待ちの数 を出す
  function setNavCount(n) {
    const link = document.querySelector('.main-nav a[href="orders.php"]');
    if (!link) return;
    let b = link.querySelector('.nav-count');
    if (!n) { if (b) b.remove(); return; }
    if (!b) { b = document.createElement('span'); b.className = 'nav-count'; link.appendChild(b); }
    b.textContent = n;
  }

  const onOrdersPage = /\/orders\.php$/.test(location.pathname);

  async function check() {
    let res;
    try {
      const r = await fetch(API, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      res = await r.json();
    } catch (e) { return; }          // 通信エラーは次の回にまた見る
    if (!res || !res.success) return;

    const orders  = res.data || [];
    const pending = orders.filter(o => o.status === 'pending');
    const maxId   = orders.reduce((m, o) => Math.max(m, Number(o.id)), 0);

    let lastId = store.get(KEY_LAST, null);
    let newIds = store.get(KEY_NEW, []).map(Number);

    if (lastId === null) {
      // はじめて使うとき: 今ある注文は「古い注文」として扱う (鳴らさない)
      lastId = maxId;
    } else {
      const arrived = pending.filter(o => Number(o.id) > lastId).map(o => Number(o.id));
      if (arrived.length) {
        newIds = [...new Set([...newIds, ...arrived])];
        playBeep();
      }
      lastId = Math.max(lastId, maxId);
    }
    // 調理待ちでなくなった注文 (出来上がり・キャンセル・削除) は NEW から外す
    const pendingIds = new Set(pending.map(o => Number(o.id)));
    newIds = newIds.filter(id => pendingIds.has(id));

    store.set(KEY_LAST, lastId);
    store.set(KEY_NEW, newIds);

    // 注文一覧のページでは カードそのものが目立つので お知らせバーは出さない
    if (newIds.length && !onOrdersPage) showBanner(newIds.length); else hideBanner();
    setNavCount(pending.length);
    document.title = pending.length ? `(${pending.length}) ${baseTitle}` : baseTitle;

    // orders.php に「新しい情報があるよ」と知らせる (すぐ一覧を作り直せるように)
    document.dispatchEvent(new CustomEvent('admin:orders', { detail: { orders, newIds } }));
  }

  // orders.php から「この注文は確認した」と言われたら NEW から外す
  window.adminNotify = {
    newIds: () => store.get(KEY_NEW, []).map(Number),
    ack(id) {
      store.set(KEY_NEW, store.get(KEY_NEW, []).map(Number).filter(x => x !== Number(id)));
    },
    check,
  };

  // 別のタブで確認したら、こちらのタブも合わせる
  window.addEventListener('storage', (e) => {
    if (e.key === KEY_NEW && !store.get(KEY_NEW, []).length) hideBanner();
  });

  renderSoundBtn();
  check();
  setInterval(check, EVERY_MS);
})();
