/* テンポス 農機具・工具買取 — モック共通スクリプト（依存なし・軽量） */
(() => {
  'use strict';

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------------
     アイコンスプライト（file:// でも <use> が効くようインライン注入）
     ------------------------------------------------------------------ */
  const sprite = `
  <svg xmlns="http://www.w3.org/2000/svg" style="display:none">
    <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4M14 20h6"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></symbol>
    <symbol id="i-truck" viewBox="0 0 24 24"><path d="M3 6h11v9H3zM14 9h4l3 3v3h-7"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></symbol>
    <symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></symbol>
    <symbol id="i-doc" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></symbol>
  </svg>`;
  document.body.insertAdjacentHTML('afterbegin', sprite);

  /* ------------------------------------------------------------------
     スクロールリビール / メーター
     ------------------------------------------------------------------ */
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      e.target.classList.add('is-in');
      io.unobserve(e.target);
    });
  }, { rootMargin: '0px 0px -12% 0px' });
  $$('.reveal, .reveal-clip, .rank').forEach((el) => io.observe(el));

  /* カウントアップ */
  const countIO = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      const el = e.target;
      const to = parseFloat(el.dataset.count);
      const dur = reduced ? 0 : 1400;
      const t0 = performance.now();
      const tick = (t) => {
        const k = Math.min(1, (t - t0) / (dur || 1));
        const v = to * (1 - Math.pow(1 - k, 3));
        el.textContent = Math.round(v).toLocaleString();
        if (k < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
      countIO.unobserve(el);
    });
  }, { threshold: .6 });
  $$('[data-count]').forEach((el) => countIO.observe(el));

  /* ------------------------------------------------------------------
     セクション管理（アンカータブ / Dock チップ / シート共通）
     ------------------------------------------------------------------ */
  const sections = $$('[data-nav]').map((el, i) => ({
    el,
    id: el.id,
    label: el.dataset.nav,
    num: String(i + 1).padStart(2, '0'),
  }));
  let current = -1;
  let maxRead = -1;

  const tabs = $('.anchor-tabs');
  const tabLinks = tabs ? $$('a', tabs) : [];
  const pill = tabs ? $('.anchor-tabs__pill', tabs) : null;
  const track = tabs ? $('.anchor-tabs__track', tabs) : null;

  function movePill(idx) {
    if (!pill) return;
    const a = tabLinks[idx];
    if (!a) { pill.style.setProperty('--o', 0); return; }
    pill.style.setProperty('--x', a.offsetLeft + 'px');
    pill.style.setProperty('--w', a.offsetWidth + 'px');
    pill.style.setProperty('--o', 1);
    // アクティブタブを可視範囲へ
    const target = a.offsetLeft - (track.clientWidth - a.offsetWidth) / 2;
    track.scrollTo({ left: target, behavior: reduced ? 'auto' : 'smooth' });
  }

  /* ------------------------------------------------------------------
     Floating Dock
     ------------------------------------------------------------------ */
  const dock = $('[data-dock]');
  const orb = dock ? $('.dock__orb', dock) : null;
  const chipNum = dock ? $('.dock__chip-num', dock) : null;
  const chipLabel = dock ? $('.dock__chip-label', dock) : null;
  const sheet = $('#sheet');

  function setCurrent(idx) {
    if (idx === current) return;
    current = idx;
    if (idx > maxRead) maxRead = idx;
    tabLinks.forEach((a, i) => a.classList.toggle('is-active', i === idx));
    movePill(idx);

    if (dock) {
      const s = sections[idx];
      dock.classList.toggle('has-chip', !!s);
      if (s) {
        chipNum.textContent = s.num;
        chipLabel.textContent = s.label;
        chipLabel.classList.remove('is-swap');
        void chipLabel.offsetWidth;
        chipLabel.classList.add('is-swap');
      }
    }
    renderSheetState();
  }

  function onScroll() {
    const y = window.scrollY;
    const line = window.innerHeight * .38;
    let idx = -1;
    sections.forEach((s, i) => {
      if (s.el.getBoundingClientRect().top - line <= 0) idx = i;
    });
    // 最終セクションを過ぎたら（フッター域）チップを外す
    const last = sections[sections.length - 1];
    if (last && last.el.getBoundingClientRect().bottom < line) idx = -1;
    setCurrent(idx);

    // 読了リング
    const max = document.documentElement.scrollHeight - window.innerHeight;
    const p = max > 0 ? Math.min(100, (y / max) * 100) : 0;
    if (orb) orb.style.setProperty('--p', p.toFixed(1));
    if (sheetPct) sheetPct.textContent = Math.round(p) + '%';

    // 下スクロールでコンパクト、上スクロールで展開
    if (dock) {
      const dir = y > lastY ? 1 : -1;
      if (Math.abs(y - lastY) > 6) {
        dock.classList.toggle('is-compact', dir > 0 && y > 200);
        lastY = y;
      }
    }
  }
  let lastY = window.scrollY;
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => { onScroll(); ticking = false; });
  }, { passive: true });
  window.addEventListener('resize', () => movePill(current));

  /* シート（目次）構築 */
  const sheetList = sheet ? $('.sheet__list', sheet) : null;
  const sheetPct = sheet ? $('[data-sheet-pct]', sheet) : null;
  if (sheetList) {
    sheetList.innerHTML = sections.map((s, i) => `
      <li class="sheet__item" data-stagger style="--i:${i}">
        <a href="#${s.id}"><span class="n">${s.num}</span><span>${s.label}</span><span class="state"></span></a>
      </li>`).join('');
    // 後続要素のスタッガー順を振り直す
    $$('[data-stagger]', sheet).forEach((el, i) => el.style.setProperty('--i', i));
  }

  function renderSheetState() {
    if (!sheetList) return;
    $$('.sheet__item', sheetList).forEach((li, i) => {
      const state = $('.state', li);
      li.classList.toggle('is-current', i === current);
      li.classList.toggle('is-read', i < current || (i <= maxRead && i !== current));
      state.textContent = i === current ? '現在地' : (i <= maxRead ? '読んだ' : '');
      state.style.display = state.textContent ? '' : 'none';
    });
  }

  function openSheet() {
    const r = orb.getBoundingClientRect();
    sheet.style.setProperty('--ox', r.left + r.width / 2 + 'px');
    sheet.style.setProperty('--oy', r.top + r.height / 2 + 'px');
    sheet.classList.add('is-open');
    sheet.setAttribute('aria-hidden', 'false');
    dock.classList.add('is-open');
    orb.setAttribute('aria-expanded', 'true');
    orb.setAttribute('aria-label', 'メニューを閉じる');
    document.body.classList.add('is-locked');
    renderSheetState();
    const first = $('a', sheet);
    if (first) setTimeout(() => first.focus({ preventScroll: true }), 400);
  }
  function closeSheet(restoreFocus = true) {
    const r = orb.getBoundingClientRect();
    sheet.style.setProperty('--ox', r.left + r.width / 2 + 'px');
    sheet.style.setProperty('--oy', r.top + r.height / 2 + 'px');
    sheet.classList.remove('is-open');
    sheet.setAttribute('aria-hidden', 'true');
    dock.classList.remove('is-open');
    orb.setAttribute('aria-expanded', 'false');
    orb.setAttribute('aria-label', 'メニューを開く');
    document.body.classList.remove('is-locked');
    if (restoreFocus) orb.focus({ preventScroll: true });
  }
  if (orb && sheet) {
    orb.addEventListener('click', () => (sheet.classList.contains('is-open') ? closeSheet() : openSheet()));
    sheet.addEventListener('click', (e) => {
      const a = e.target.closest('a[href^="#"]');
      if (a) closeSheet(false);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sheet.classList.contains('is-open')) closeSheet();
    });
  }

  /* ------------------------------------------------------------------
     実績フィルター（農機具：稼働/不動）
     ------------------------------------------------------------------ */
  $$('[data-filter]').forEach((group) => {
    const cards = $$(group.dataset.filter);
    $$('button', group).forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('button', group).forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
        const v = btn.dataset.value;
        cards.forEach((c) => c.classList.toggle('is-hidden', v !== 'all' && c.dataset.state !== v));
      });
    });
  });

  /* ------------------------------------------------------------------
     買取方法タブ（工具）
     ------------------------------------------------------------------ */
  $$('[role="tablist"]').forEach((list) => {
    const btns = $$('[role="tab"]', list);
    const select = (btn) => {
      btns.forEach((b) => {
        const on = b === btn;
        b.setAttribute('aria-selected', String(on));
        b.tabIndex = on ? 0 : -1;
        const panel = document.getElementById(b.getAttribute('aria-controls'));
        panel.hidden = !on;
        if (on) { panel.classList.remove('is-anim'); void panel.offsetWidth; panel.classList.add('is-anim'); }
      });
    };
    btns.forEach((b, i) => {
      b.addEventListener('click', () => select(b));
      b.addEventListener('keydown', (e) => {
        const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (!d) return;
        const n = btns[(i + d + btns.length) % btns.length];
        n.focus(); select(n);
      });
    });
  });

  /* ------------------------------------------------------------------
     簡易フォーム（モック：送信せず完了表示）
     ------------------------------------------------------------------ */
  $$('.form-card form').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!form.reportValidity()) return;
      form.closest('.form-card').classList.add('is-done');
    });
  });

  onScroll();
})();
