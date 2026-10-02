// 主要ページの表示確認（ステータス・PHP エラー表示・JS エラー・テーマ要素）とスクリーンショット
// 使い方:
//   node tools/check-pages.mjs http://localhost:8090/kaitori            … ローカル
//   node tools/check-pages.mjs https://tenpos.online/kaitori            … 本番（閲覧のみ）
//   ※ 本番確認時、作業環境にプロキシがあれば自動でプロキシ経由（CA は SPKI 指定で信頼）。ローカル確認は直接接続
//   オプション: --shots=<dir> スクリーンショット保存先（既定: なし）
// 本番に対しては GET のみ。フォーム送信・ログインは行わない。
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

const base = (process.argv[2] || '').replace(/\/$/, '');
if (!base) { console.error('URL を指定してください'); process.exit(2); }
const useProxy = process.argv.includes('--proxy');
const shots = (process.argv.find(a => a.startsWith('--shots=')) || '').slice(8);
if (shots) fs.mkdirSync(shots, { recursive: true });

const PAGES = [
  ['top', '/', 200], ['agri', '/agricultural-equipment/', 200], ['tool', '/tool/', 200],
  ['results', '/results/', 200], ['agri-results', '/agricultural-equipment/results/', 200], ['tool-results', '/tool/results/', 200],
  ['column', '/column/', 200], ['series', '/column/series/rinou/', 200], ['article', '/column/rinou-01/', 200],
  ['glossary', '/glossary/', 200], ['faq', '/faq/', 200], ['contact', '/contact/', 200],
  ['area', '/area/', 200], ['privacy', '/privacy-policy/', 200], ['notfound', '/zz-not-found/', 404],
];
// ページごとに「あるべき要素」
const EXPECT = {
  top: ['.split', '.dock'], agri: ['.anchor-tabs', '#form', '.dock'], tool: ['.anchor-tabs', '#form', '.dock'],
  column: ['.series-card'], series: ['.route-v'], article: ['.prose', '.toc'], glossary: ['.g-term'], faq: ['.faq details'],
};

const launch = { executablePath: fs.existsSync('/opt/pw-browsers/chromium-1194/chrome-linux/chrome') ? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' : undefined };
const proxyServer = process.env.HTTPS_PROXY || process.env.https_proxy;
const isLocal = /^https?:\/\/(localhost|127\.0\.0\.1)/.test(base);
// ローカル確認はプロキシを使わない（Playwright は localhost もプロキシに送るため）。外部フォントの読込失敗は無視する
if (!isLocal && (useProxy || proxyServer) && fs.existsSync('/root/.ccr/ca-bundle.crt') && proxyServer) {
  // 作業環境のプロキシ CA だけを SPKI で信頼（TLS 検証自体は有効のまま）
  const bundle = fs.readFileSync('/root/.ccr/ca-bundle.crt', 'utf8').split(/(?=-----BEGIN CERTIFICATE-----)/);
  const spki = bundle.filter(c => /Anthropic/.test(execSync('openssl x509 -noout -subject', { input: c }).toString()))
    .map(c => execSync('openssl x509 -pubkey -noout | openssl pkey -pubin -outform der | openssl dgst -sha256 -binary | base64', { input: c, shell: '/bin/bash' }).toString().trim());
  launch.proxy = { server: proxyServer };
  launch.args = [`--ignore-certificate-errors-spki-list=${[...new Set(spki)].join(',')}`];
}

const b = await chromium.launch(launch);
const problems = [];
for (const [name, path, status] of PAGES) {
  for (const [dev, vp] of [['sp', { width: 390, height: 844 }], ['pc', { width: 1440, height: 900 }]]) {
    const p = await b.newPage({ viewport: vp });
    p.on('pageerror', e => problems.push(`${name}-${dev} JSエラー: ${e.message}`));
    p.on('console', m => { if (m.type() === 'error' && !/favicon|status of 404/.test(m.text()) && !(isLocal && /fonts\.g|ERR_CERT/.test(m.text() + (m.location().url || '')))) problems.push(`${name}-${dev} console: ${m.text()}`); });
    const r = await p.goto(base + path, { waitUntil: 'networkidle', timeout: 60000 });
    if (r.status() !== status) problems.push(`${name}: HTTP ${r.status()}（期待 ${status}）`);
    const html = await p.content();
    for (const w of ['Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Parse error']) if (html.includes(w)) problems.push(`${name}: PHP「${w}」表示あり`);
    if (dev === 'sp') for (const sel of EXPECT[name] || []) if (!(await p.$(sel))) problems.push(`${name}: 要素 ${sel} がない`);
    if (shots) {
      await p.evaluate(() => { document.documentElement.style.scrollBehavior = 'auto'; document.querySelectorAll('.reveal,.reveal-clip').forEach(e => e.classList.add('is-in')); });
      await p.waitForTimeout(1000);
      await p.screenshot({ path: `${shots}/${name}-${dev}.png` });
    }
    await p.close();
  }
  process.stdout.write('.');
}
await b.close();
console.log('\n' + (problems.length ? problems.join('\n') : 'OK: 問題なし'));
process.exit(problems.length ? 1 : 0);
