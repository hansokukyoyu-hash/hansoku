#!/usr/bin/env bash
# 本番の読み取り確認（GET のみ。変更は一切行わない）
# 使い方: tools/check-live.sh [ベースURL]
set -uo pipefail
B="${1:-https://tenpos.online/kaitori}"
TMP="$(mktemp -d)"
printf "%-34s %-5s %-6s %-6s %s\n" PATH HTTP PHPerr CF7 JSON-LD
for p in / /agricultural-equipment/ /tool/ /results/ /agricultural-equipment/results/ /tool/results/ /column/ /column/series/rinou/ /column/rinou-01/ /glossary/ /faq/ /contact/ /area/ /privacy-policy/; do
  f="$TMP/page.html"
  code=$(curl -sS -m 30 -o "$f" -w "%{http_code}" "$B$p")
  err=$(grep -cE "Fatal error|Warning:|Notice:|Deprecated:|Parse error" "$f")
  cf7=$(grep -c 'class="wpcf7 ' "$f")
  ld=$(grep -o '"@type":"[A-Za-z]*"' "$f" | sort -u | sed 's/"@type"://;s/"//g' | tr '\n' ' ')
  printf "%-34s %-5s %-6s %-6s %s\n" "$p" "$code" "$err" "$cf7" "$ld"
done
echo "--- 設定値（仮の値が残っていないか）"
curl -sS -m 30 "$B/" -o "$TMP/top.html"
grep -o 'header-tel__num">[^<]*' "$TMP/top.html" | sed 's/.*">/電話番号: /'
grep -o '古物商許可番号：[^<]*' "$TMP/top.html"
echo "--- お問い合わせフォーム"
curl -sS -m 30 "$B/contact/" | grep -q 'form-fallback' && echo "/contact/ は「準備中」（tk_cf7_contact 未設定）" || echo "/contact/ フォーム表示あり"
echo "--- メール認証 DNS"
for n in tenpos.online _dmarc.tenpos.online; do printf "%s TXT: " "$n"; curl -sS -m 15 "https://dns.google/resolve?name=$n&type=TXT" | grep -o '"data":"v=[^"]*"' | tr '\n' ' '; echo; done
