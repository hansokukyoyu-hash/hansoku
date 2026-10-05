// docs/gas/Code.gs を Node 上で擬似実行する（ローカル再現環境でのスプレッドシート連携の確認用）
//   node tools/gas-sim.cjs <サイトURL> <トークン> [関数名=pull] [回数=1]
// SpreadsheetApp などは最小限のスタブ（シートはメモリ上）。UrlFetchApp は curl で実際にサイトへ POST する。
// 追記された行を標準出力に JSON で出す。ローカル専用（本番で動かすと ack で本番の未反映データが消えるため）
const fs = require('fs');
const vm = require('vm');
const { execFileSync } = require('child_process');

const [site, token, fn = 'pull', times = '1'] = process.argv.slice(2);
if (!site || !token) {
  console.error('usage: node tools/gas-sim.cjs <サイトURL> <トークン> [関数名] [回数]');
  process.exit(2);
}
if (!/^http:\/\/localhost[:/]/.test(site)) {
  console.error('ローカル（http://localhost）専用です');
  process.exit(2);
}

const rows = [];
const sheet = {
  getName: () => '問い合わせ',
  getLastRow: () => rows.length,
  appendRow: r => rows.push(r),
  setFrozenRows() {},
  getRange: (r, c, nr = 1, nc = 1) => ({
    setFontWeight() { return this; },
    setBackground() { return this; },
    setNumberFormat() { return this; },
    getValues: () => rows.slice(r - 1, r - 1 + nr).map(x => x.slice(c - 1, c - 1 + nc)),
    setValues: v => v.forEach((x, i) => { rows[r - 1 + i] = x; }),
  }),
};
const props = { TOKEN: token };
const ctx = {
  console,
  SpreadsheetApp: { getActiveSpreadsheet: () => ({ getSheetByName: () => sheet, insertSheet: () => sheet }), flush() {} },
  PropertiesService: { getScriptProperties: () => ({ getProperty: k => props[k] || null, setProperty: (k, v) => (props[k] = v), deleteProperty: k => delete props[k] }) },
  ScriptApp: { getProjectTriggers: () => [], deleteTrigger() {}, newTrigger: () => ({ timeBased: () => ({ everyMinutes: () => ({ create() {} }) }) }) },
  Utilities: { getUuid: () => require('crypto').randomUUID() },
  Logger: { log: (f, ...a) => console.log('[Logger] ' + a.reduce((s, x) => s.replace('%s', x), f)) },
  LockService: { getScriptLock: () => ({ tryLock: () => true, waitLock() {}, releaseLock() {} }) },
  UrlFetchApp: {
    fetch(url, o) {
      const out = execFileSync('curl', ['-s', '-X', 'POST', '-H', 'Content-Type: ' + o.contentType, '--data-binary', '@-', '-w', '\n%{http_code}', url], { input: o.payload }).toString();
      const i = out.lastIndexOf('\n');
      const body = out.slice(0, i);
      const code = +out.slice(i + 1);
      return { getResponseCode: () => code, getContentText: () => body };
    },
  },
};
vm.createContext(ctx);
const src = fs.readFileSync(__dirname + '/../wp-content/themes/tenpos-kaitori/docs/gas/Code.gs', 'utf8')
  .replace(/^const SITE = .*$/m, `const SITE = ${JSON.stringify(site)};`);
vm.runInContext(src, ctx);
ctx.getSheet_();
try {
  for (let i = 0; i < +times; i++) ctx[fn]();
  console.log(JSON.stringify({ ok: true, rows: rows.slice(1).map(r => r.map(v => (v instanceof Date ? v.toISOString() : v))) }, null, 1));
} catch (e) {
  console.log(JSON.stringify({ ok: false, error: e.message, rows: rows.length - 1 }));
  process.exitCode = 1;
}
