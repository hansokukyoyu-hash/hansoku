/**
 * TENPOS 買取サイト → Google スプレッドシート 自動追記（Apps Script）
 *
 * 方式：この Apps Script が 5 分ごとにサイトへ取りに行く（サイトから Google へは送らない）
 *   → ウェブアプリのデプロイは不要。Google Workspace の「組織内のみ」設定でも動く
 *
 * 使い方（docs/gas/README.md を参照）
 *  1. 反映先のスプレッドシートで「拡張機能 → Apps Script」を開き、このコードを貼り付けて保存
 *  2. 関数「setup」を実行（シートと見出し行の作成、トークンの発行、5 分ごとのトリガーの登録）
 *  3. ログに表示されたトークンを WordPress の 外観 → カスタマイズ → スプレッドシート連携 に入力
 *
 * セキュリティ
 *  - サイトはトークンが一致しない取得を拒否する
 *  - 「=」「+」「-」「@」で始まる値は先頭に ' を付けて文字列として記録（数式として動かさない）
 *  - シートには個人情報が入るため、共有は必要な担当者だけに限定すること
 */

/** サイトの URL（末尾の / は付けない） */
const SITE = 'https://tenpos.online/kaitori';

const SHEET_NAME = '問い合わせ';
const HEADERS = [
  '受信日時', 'フォーム', 'お名前', '電話番号', 'メールアドレス', '都道府県',
  '種類・ご相談内容', 'メーカー・型番', 'お問い合わせ内容', '写真', '送信ページ', '受付ID',
  '対応状況', '担当者', 'メモ',
];

/** 初回に実行：シートの用意、トークンの発行、5 分ごとのトリガー登録（何度実行しても安全） */
function setup() {
  const sheet = getSheet_();
  const props = PropertiesService.getScriptProperties();
  let token = props.getProperty('TOKEN');
  if (!token) {
    token = Utilities.getUuid().replace(/-/g, '') + Utilities.getUuid().replace(/-/g, '');
    props.setProperty('TOKEN', token);
  }
  ScriptApp.getProjectTriggers()
    .filter(t => t.getHandlerFunction() === 'pull')
    .forEach(t => ScriptApp.deleteTrigger(t));
  ScriptApp.newTrigger('pull').timeBased().everyMinutes(5).create();

  Logger.log('シート「%s」を用意しました。5 分ごとに pull を実行するトリガーを登録しました。', sheet.getName());
  Logger.log('トークン（WordPress のカスタマイザーに入力）: %s', token);
}

/** トークンを作り直す（漏えいが疑われるとき）。実行後は WordPress 側も新しい値に変更 */
function resetToken() {
  PropertiesService.getScriptProperties().deleteProperty('TOKEN');
  setup();
}

/** サイトから未反映の問い合わせを取得してシートに追記（トリガーで 5 分ごと。手動実行も可） */
function pull() {
  const token = PropertiesService.getScriptProperties().getProperty('TOKEN');
  if (!token) throw new Error('先に setup を実行してください');

  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return; // 前回の実行中
  try {
    let added = 0;
    for (let round = 0; round < 10; round++) {
      const res = call_('pull', { token: token });
      const rows = res.rows || [];
      if (!rows.length) break;

      const sheet = getSheet_();
      // 同じ受付IDが既にあれば追記しない（取得のやり直し対策）
      const idCol = HEADERS.indexOf('受付ID') + 1;
      const last = sheet.getLastRow();
      const ids = new Set(last > 1 ? sheet.getRange(2, idCol, last - 1, 1).getValues().flat() : []);
      const out = [];
      rows.forEach(d => {
        if (d.id && ids.has(d.id)) return;
        ids.add(d.id);
        out.push(row_(d));
      });
      if (out.length) {
        const start = sheet.getLastRow() + 1;
        sheet.getRange(start, 1, out.length, HEADERS.length).setValues(out);
        sheet.getRange(start, 1, out.length, 1).setNumberFormat('yyyy/mm/dd hh:mm');
        SpreadsheetApp.flush();
        added += out.length;
      }
      // シートに書けたものをサイト側の一時保管から削除
      call_('ack', { token: token, ids: rows.map(d => d.qid) });
      if (!res.more) break;
    }
    if (added) Logger.log('%s 件追記しました', added);
  } finally {
    lock.releaseLock();
  }
}

function call_(action, body) {
  const res = UrlFetchApp.fetch(SITE + '/wp-json/tk/v1/sheets/' + action, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(body),
    muteHttpExceptions: true,
  });
  const code = res.getResponseCode();
  const text = res.getContentText();
  let data = null;
  try {
    data = JSON.parse(text);
  } catch (e) {
    // 下でエラーにする
  }
  if (code !== 200 || !data || data.ok !== true) {
    // 例外にすると Apps Script から所有者にエラー通知メールが届く
    throw new Error(action + ' に失敗しました（HTTP ' + code + '）: ' + text.slice(0, 200));
  }
  return data;
}

function row_(d) {
  return [
    d.submitted_at ? new Date(d.submitted_at) : new Date(),
    safe_(d.form_label),
    safe_(d.name),
    safe_(d.tel),
    safe_(d.email),
    safe_(d.pref),
    safe_(d.kind),
    safe_(d.model),
    safe_(d.message),
    safe_(d.photo),
    safe_(d.page_url),
    safe_(d.id),
    '', '', '',
  ];
}

function getSheet_() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(SHEET_NAME);
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
  }
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(HEADERS);
    sheet.setFrozenRows(1);
    sheet.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold').setBackground('#f1ece0');
  }
  return sheet;
}

/** 数式・関数として解釈されないよう文字列化 */
function safe_(v) {
  if (v === null || v === undefined) return '';
  const s = String(v);
  return /^[=+\-@\t\r]/.test(s) ? "'" + s : s;
}
