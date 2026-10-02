/* テンポス 農機具・工具買取 — モック共通スクリプト（依存なし・軽量） */
(() => {
  'use strict';

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------------
     アイコンスプライト（file:// でも <use> が効くようインライン注入）
     ------------------------------------------------------------------ */
  /* アイコンスプライトはテーマ（parts/icons.php）が出力 */

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
    if (!a) { pill.style.setProperty('--o', 0); if (track) track.scrollTo({ left: 0, behavior: reduced ? 'auto' : 'smooth' }); return; }
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

  /* ------------------------------------------------------------------
     ロゴ画像が未配置のときは仮マークを表示
     ------------------------------------------------------------------ */
  $$('.brand__logo').forEach((img) => {
    const miss = () => img.classList.add('is-missing');
    if (img.complete && !img.naturalWidth) miss();
    img.addEventListener('error', miss);
  });

  /* ------------------------------------------------------------------
     用語集：検索＋カテゴリ絞り込み
     ------------------------------------------------------------------ */
  const gInput = $('[data-g-search]');
  if (gInput) {
    const toHira = (t) => t.replace(/[ァ-ヶ]/g, (c) => String.fromCharCode(c.charCodeAt(0) - 0x60)).toLowerCase();
    const terms = $$('.g-term');
    const groups = $$('.g-group');
    const countEl = $('[data-g-count]');
    const emptyEl = $('[data-g-empty]');
    let cat = 'all';
    const apply = () => {
      const q = toHira(gInput.value.trim());
      let total = 0;
      terms.forEach((t) => {
        const ok = (cat === 'all' || t.dataset.cat === cat) && (!q || toHira(t.dataset.search).includes(q));
        t.classList.toggle('is-hidden', !ok);
        if (ok) total++;
      });
      groups.forEach((g) => {
        const n = $$('.g-term:not(.is-hidden)', g).length;
        g.classList.toggle('is-empty', n === 0);
        const c = $('[data-group-count]', g);
        if (c) c.textContent = n;
      });
      countEl.textContent = total;
      emptyEl.classList.toggle('is-show', total === 0);
      onScroll();
    };
    gInput.addEventListener('input', apply);
    $$('[data-g-cats] button').forEach((b) => b.addEventListener('click', () => {
      $$('[data-g-cats] button').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      cat = b.dataset.value;
      apply();
    }));
    // 関連用語リンクで飛んだ先を一瞬ハイライト
    document.addEventListener('click', (e) => {
      const a = e.target.closest('.g-term__rel a');
      if (!a) return;
      const t = document.getElementById(a.getAttribute('href').slice(1));
      if (t && t.classList.contains('is-hidden')) { gInput.value = ''; cat = 'all'; $$('[data-g-cats] button').forEach((x) => x.setAttribute('aria-pressed', String(x.dataset.value === 'all'))); apply(); }
      if (t) { t.classList.remove('is-flash'); void t.offsetWidth; t.classList.add('is-flash'); setTimeout(() => t.classList.remove('is-flash'), 1600); }
    });
  }

  /* ------------------------------------------------------------------
     本文の用語ポップ（用語集の解説を表示）
     ------------------------------------------------------------------ */
  const termBtns = $$('button.term');
  if (termBtns.length) {
    const pop = document.createElement('div');
    pop.className = 'term-pop';
    pop.setAttribute('role', 'tooltip');
    pop.id = 'term-pop';
    document.body.appendChild(pop);
    let openBtn = null;
    const close = () => { pop.classList.remove('is-open'); if (openBtn) openBtn.setAttribute('aria-expanded', 'false'); openBtn = null; };
    termBtns.forEach((b) => {
      b.setAttribute('aria-expanded', 'false');
      b.setAttribute('aria-describedby', 'term-pop');
      b.addEventListener('click', (e) => {
        e.stopPropagation();
        if (openBtn === b) return close();
        pop.innerHTML = '';
        const t = document.createElement('b'); t.textContent = b.dataset.termName;
        const d = document.createElement('span'); d.textContent = b.dataset.def;
        const a = document.createElement('a'); a.href = b.dataset.href; a.textContent = '用語集で詳しく見る →';
        pop.append(t, d, document.createElement('br'), a);
        const r = b.getBoundingClientRect();
        const w = Math.min(320, window.innerWidth - 32);
        const left = Math.max(16, Math.min(window.scrollX + r.left + r.width / 2 - w / 2, window.scrollX + window.innerWidth - w - 16));
        pop.style.left = left + 'px';
        pop.style.top = (window.scrollY + r.bottom + 10) + 'px';
        pop.classList.add('is-open');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        openBtn = b; b.setAttribute('aria-expanded', 'true');
      });
    });
    document.addEventListener('click', (e) => { if (!pop.contains(e.target)) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', close);
  }

  /* ------------------------------------------------------------------
     連載の既読管理（この端末のみ・ブラウザ保存）
     ------------------------------------------------------------------ */
  const READ_KEY = 'tempos-column-read';
  const loadRead = () => { try { return JSON.parse(localStorage.getItem(READ_KEY)) || {}; } catch (_) { return {}; } };
  const saveRead = (v) => { try { localStorage.setItem(READ_KEY, JSON.stringify(v)); } catch (_) { /* 保存不可でも表示は継続 */ } };
  const paintRead = () => {
    const read = loadRead();
    $$('[data-series]').forEach((list) => {
      const done = read[list.dataset.series] || [];
      $$('li[data-ep]', list).forEach((li) => {
        const on = done.includes(Number(li.dataset.ep));
        li.classList.toggle('is-read', on);
        const badge = $('[data-read-badge]', li);
        if (badge) badge.hidden = !on;
      });
    });
    // 連載トップ：続きから読む
    const resume = $('[data-resume]');
    const list = $('.route-v[data-series]');
    if (resume && list) {
      const done = read[list.dataset.series] || [];
      const next = $$('li.is-pub[data-ep]', list).find((li) => !done.includes(Number(li.dataset.ep)));
      if (done.length && next) {
        const a = $('a.ep', next);
        if (a) { resume.href = a.getAttribute('href'); resume.firstChild.textContent = `続きから読む（第${next.dataset.ep}回）`; }
      }
    }
  };
  paintRead();
  const art = $('[data-read-series]');
  if (art) {
    const end = $('.ep-nav', art);
    const markIO = new IntersectionObserver((entries) => {
      if (!entries.some((e) => e.isIntersecting)) return;
      const read = loadRead();
      const sid = art.dataset.readSeries;
      const ep = Number(art.dataset.readEp);
      read[sid] = [...new Set([...(read[sid] || []), ep])];
      saveRead(read);
      paintRead();
      markIO.disconnect();
    });
    if (end) markIO.observe(end);
  }

  onScroll();
})();
