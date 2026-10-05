/**
 * TENPOS 買取サイト → Google スプレッドシート 自動追記（Apps Script）
 *
 * 使い方（docs/gas/README.md を参照）
 *  1. 反映先のスプレッドシートで「拡張機能 → Apps Script」を開き、このコードを貼り付けて保存
 *  2. 関数「setup」を一度だけ実行（シートと見出し行を作成し、合言葉＝トークンを発行してログに表示）
 *  3. 「デプロイ → 新しいデプロイ → ウェブアプリ」
 *       次のユーザーとして実行：自分 ／ アクセスできるユーザー：全員
 *  4. 表示された「ウェブアプリの URL」と、手順2のトークンを
 *     WordPress の 外観 → カスタマイズ → スプレッドシート連携 に入力
 *
 * セキュリティ
 *  - トークンが一致しない送信は記録しない
 *  - 「=」「+」「-」「@」で始まる値は先頭に ' を付けて文字列として記録（数式として動かさない）
 *  - シートには個人情報が入るため、共有は必要な担当者だけに限定すること
 */

const SHEET_NAME = '問い合わせ';
const HEADERS = [
  '受信日時', 'フォーム', 'お名前', '電話番号', 'メールアドレス', '都道府県',
  '種類・ご相談内容', 'メーカー・型番', 'お問い合わせ内容', '写真', '送信ページ', '受付ID',
  '対応状況', '担当者', 'メモ',
];

/** 初回だけ実行：シートの用意とトークンの発行 */
function setup() {
  const sheet = getSheet_();
  const props = PropertiesService.getScriptProperties();
  let token = props.getProperty('TOKEN');
  if (!token) {
    token = Utilities.getUuid().replace(/-/g, '') + Utilities.getUuid().replace(/-/g, '');
    props.setProperty('TOKEN', token);
  }
  Logger.log('シート「%s」を用意しました。', sheet.getName());
  Logger.log('トークン（WordPress のカスタマイザーに入力）: %s', token);
}

/** トークンを作り直す（漏えいが疑われるとき）。実行後は WordPress 側も新しい値に変更 */
function resetToken() {
  PropertiesService.getScriptProperties().deleteProperty('TOKEN');
  setup();
}

function doPost(e) {
  let data;
  try {
    data = JSON.parse(e.postData.contents);
  } catch (err) {
    return json_({ ok: false, error: 'bad_request' });
  }
  const token = PropertiesService.getScriptProperties().getProperty('TOKEN');
  if (!token || !data || data.token !== token) {
    return json_({ ok: false, error: 'unauthorized' });
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    const sheet = getSheet_();
    // 同じ受付IDが既にあれば追記しない（再送対策）
    const idCol = HEADERS.indexOf('受付ID') + 1;
    const last = sheet.getLastRow();
    if (data.id && last > 1) {
      const ids = sheet.getRange(2, idCol, last - 1, 1).getValues().flat();
      if (ids.indexOf(data.id) !== -1) {
        return json_({ ok: true, duplicate: true });
      }
    }
    const row = [
      data.submitted_at ? new Date(data.submitted_at) : new Date(),
      safe_(data.form_label),
      safe_(data.name),
      safe_(data.tel),
      safe_(data.email),
      safe_(data.pref),
      safe_(data.kind),
      safe_(data.model),
      safe_(data.message),
      safe_(data.photo),
      safe_(data.page_url),
      safe_(data.id),
      '', '', '',
    ];
    sheet.appendRow(row);
    sheet.getRange(sheet.getLastRow(), 1).setNumberFormat('yyyy/mm/dd hh:mm');
    return json_({ ok: true });
  } finally {
    lock.releaseLock();
  }
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

function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
