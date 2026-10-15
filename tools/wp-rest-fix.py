#!/usr/bin/env python3
"""本番の投稿（用語集・FAQ・コラム）の文面を REST API で修正する（2026-10-15 料金表示の修正用・1回限り）

  WP_APP_USER / WP_APP_PASSWORD（アプリケーションパスワード）を環境変数で渡す。チャットやファイルに書かない。
  python3 tools/wp-rest-fix.py            # 確認のみ（変更しない）：現在の本文と照合して差分を表示
  python3 tools/wp-rest-fix.py --apply    # 書き換え（直前の本文を --backup に保存）
  python3 tools/wp-rest-fix.py --restore <バックアップ.json>   # 元に戻す

- 対象は下の TARGETS の 12 件だけ。本文が想定どおりでない投稿は書き換えずに中止する
- 新しい文面は inc/sample-data.json（テーマ 1.5.3）と同じ。古い文面は 1.5.2 のサンプルデータ
"""
import base64, json, os, subprocess, sys, urllib.parse, urllib.request, datetime

SITE = os.environ.get('WP_SITE', 'https://tenpos.online/kaitori')
ROUTE_MODE = os.environ.get('WP_REST_MODE', 'wp-json')  # wp-json | rest_route（サーバーの国外アクセス制限を通らない経路。ユーザーの了承がある場合のみ）
REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SAMPLE = 'wp-content/themes/tenpos-kaitori/inc/sample-data.json'


def sample(rev):
    return json.loads(subprocess.check_output(['git', '-C', REPO, 'show', f'{rev}:{SAMPLE}']))


OLD, NEW = sample('06a5771'), sample('b85faf7')  # 1.5.2 / 1.5.3


def targets():
    t = []
    og = {g['slug']: g for g in OLD['glossary']}
    for g in NEW['glossary']:
        if og[g['slug']]['content'] != g['content']:
            t.append({'type': 'glossary', 'slug': g['slug'], 'label': '用語集：' + g['title'], 'old': og[g['slug']]['content'], 'new': g['content']})
    of = {f['q']: f for f in OLD['faqs']}
    for f in NEW['faqs']:
        if of[f['q']]['a'] != f['a']:
            t.append({'type': 'faq', 'title': f['q'], 'label': 'FAQ：' + f['q'], 'old': of[f['q']]['a'], 'new': f['a']})
    oc = {c['slug']: c for c in OLD['columns']}
    for c in NEW['columns']:
        if oc[c['slug']]['content'] != c['content']:
            t.append({'type': 'column', 'slug': c['slug'], 'label': 'コラム：' + c['title'], 'old': oc[c['slug']]['content'], 'new': c['content']})
    return t


def url(route, params=None):
    params = dict(params or {})
    if ROUTE_MODE == 'rest_route':
        params = {'rest_route': route, **params}
        return SITE + '/?' + urllib.parse.urlencode(params)
    return SITE + '/wp-json' + route + ('?' + urllib.parse.urlencode(params) if params else '')


def call(method, route, params=None, body=None):
    user, pw = os.environ.get('WP_APP_USER'), os.environ.get('WP_APP_PASSWORD')
    if not user or not pw:
        sys.exit('環境変数 WP_APP_USER / WP_APP_PASSWORD がありません')
    req = urllib.request.Request(url(route, params), method=method, data=json.dumps(body).encode() if body is not None else None)
    req.add_header('Authorization', 'Basic ' + base64.b64encode(f'{user}:{pw}'.encode()).decode())
    req.add_header('Content-Type', 'application/json')
    req.add_header('User-Agent', 'tenpos-kaitori-maintenance')
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return json.loads(r.read())
    except urllib.error.HTTPError as e:
        sys.exit(f'{method} {route} → HTTP {e.code}: {e.read()[:300].decode(errors="replace")}')


def find(t):
    q = {'context': 'edit', 'per_page': 100, 'status': 'publish,future,draft,private'}
    if 'slug' in t:
        q['slug'] = t['slug']
    else:
        q['search'] = t['title']
    hits = call('GET', f'/wp/v2/{t["type"]}', q)
    if 'title' in t:
        hits = [h for h in hits if h['title']['raw'] == t['title']]
    if len(hits) != 1:
        sys.exit(f'{t["label"]}：投稿が {len(hits)} 件見つかりました（1 件のはず）。中止します')
    return hits[0]


def norm(s):
    return s.replace('\r\n', '\n').strip()


def main():
    args = sys.argv[1:]
    if args[:1] == ['--restore']:
        data = json.load(open(args[1]))
        for b in data:
            call('POST', f'/wp/v2/{b["type"]}/{b["id"]}', body={'content': b['raw']})
            print('戻しました：', b['label'], b['id'])
        return
    apply = '--apply' in args
    plan, backup = [], []
    for t in targets():
        p = find(t)
        raw = p['content']['raw']
        if norm(raw) == norm(t['new']):
            print('修正済み：', t['label'])
            continue
        if norm(raw) == norm(t['old']):
            new = t['new']
        else:
            sys.exit(f'{t["label"]}（ID {p["id"]}）：本文が想定と違います（管理画面で編集済み？）。書き換えずに中止します')
        plan.append((t, p['id'], new))
        backup.append({'type': t['type'], 'id': p['id'], 'label': t['label'], 'raw': raw})
        print(f'{"書き換え" if apply else "書き換え予定"}：{t["label"]}（ID {p["id"]}）')
    if not plan:
        print('書き換える投稿はありません')
        return
    if not apply:
        print(f'\n確認のみ（{len(plan)} 件）。書き換えるには --apply')
        return
    out = os.environ.get('BACKUP_DIR', '.') + '/wp-rest-backup-' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S') + '.json'
    json.dump(backup, open(out, 'w'), ensure_ascii=False, indent=1)
    print('バックアップ：', out)
    for t, pid, new in plan:
        r = call('POST', f'/wp/v2/{t["type"]}/{pid}', body={'content': new})
        ok = norm(r['content']['raw']) == norm(new)
        print(('OK  ' if ok else 'NG  ') + t['label'])


if __name__ == '__main__':
    main()
