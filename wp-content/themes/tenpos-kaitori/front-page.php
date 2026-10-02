<?php
/**
 * トップページ（軽量ハブ）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main">

  <!-- スプリットナビゲーション -->
  <section class="split" id="select" aria-label="買取ジャンルを選ぶ">
    <a class="split__panel split__panel--agri" href="<?php echo esc_url( tk_page_url( 'agricultural-equipment' ) ); ?>">
      <span class="split__en" aria-hidden="true">FARM<br>MACHINE</span>
      <svg class="split__art" viewBox="0 0 120 80" fill="none" stroke="rgba(255,255,255,.85)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="34" cy="56" r="18"/><circle cx="34" cy="56" r="7"/><circle cx="94" cy="62" r="11"/><circle cx="94" cy="62" r="4"/>
        <path d="M22 38V16h24l8 22M54 38h38l8 14M28 16V8M64 38V26h8v12M14 38h92"/>
      </svg>
      <span class="split__label">農家・離農・ご遺族の方</span>
      <h2 class="split__title">農機具を<br>売りたい・処分したい方へ</h2>
      <p class="split__desc">トラクター・コンバイン・田植機。動かなくても現地まで無料で引き取ります。</p>
      <span class="btn split__cta">農機具買取ページへ <small>出張費0円</small><svg class="icon arrow"><use href="#i-arrow"/></svg></span>
    </a>
    <a class="split__panel split__panel--tool" href="<?php echo esc_url( tk_page_url( 'tool' ) ); ?>">
      <span class="split__en" aria-hidden="true">PRO<br>TOOLS</span>
      <svg class="split__art" viewBox="0 0 120 100" fill="none" stroke="#ffd400" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M18 18h62a10 10 0 0 1 10 10v10a10 10 0 0 1-10 10H18z"/><path d="M90 33h12M102 27v12l12-6z"/>
        <path d="M40 48l-6 30h22l6-30"/><rect x="26" y="78" width="38" height="12" rx="3"/><path d="M28 33h34"/>
      </svg>
      <span class="split__label">職人・工務店・建設業の方</span>
      <h2 class="split__title">電動工具・作業工具を<br>売りたい方へ</h2>
      <p class="split__desc">マキタ・ハイコーキ高価買取。現場帰りの持ち込みで即日現金化。</p>
      <span class="btn split__cta">工具買取ページへ <small>即日現金化</small><svg class="icon arrow"><use href="#i-arrow"/></svg></span>
    </a>
  </section>
  <p class="hub-hero-note">査定料・出張費・キャンセル料 すべて0円｜強引な営業は一切ありません</p>

  <div class="marquee" aria-hidden="true" style="margin-top:28px">
    <div class="marquee__inner">
      <span class="marquee__item">出張費0円</span><span class="marquee__item">不動車OK</span><span class="marquee__item">サビ・型落ちOK</span><span class="marquee__item">箱なし・本体のみOK</span><span class="marquee__item">全国対応</span><span class="marquee__item">即日現金化</span><span class="marquee__item">まとめて一括査定</span>
    </div>
    <div class="marquee__inner">
      <span class="marquee__item">出張費0円</span><span class="marquee__item">不動車OK</span><span class="marquee__item">サビ・型落ちOK</span><span class="marquee__item">箱なし・本体のみOK</span><span class="marquee__item">全国対応</span><span class="marquee__item">即日現金化</span><span class="marquee__item">まとめて一括査定</span>
    </div>
  </div>

  <!-- 3大強み -->
  <section class="section" id="strength" data-nav="テンポスの強み">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Why TEMPOS</span>
        <h2 class="sec-title">テンポスが選ばれる<br>3つの理由</h2>
      </div>
      <div class="bento">
        <article class="bento__card bento__card--dark reveal">
          <h3>東証上場グループの<br>信頼感</h3>
          <p>厨房機器買取で培った査定ノウハウと全国ネットワーク。買取価格・手続きともに明朗会計でご案内します。</p>
          <div class="bento__stat"><b data-count="40">0</b><span>年以上の買取実績</span></div>
          <span class="num">01</span>
        </article>
        <article class="bento__card reveal" style="--d:.1s">
          <h3>動かない・サビ・型落ちも歓迎</h3>
          <p>パーツ再利用・海外輸出ルートがあるから、他社で断られた品も値段がつきます。</p>
          <span class="num">02</span>
        </article>
        <article class="bento__card reveal" style="--d:.2s">
          <h3>倉庫・現場の<br>まとめ買い・一括査定</h3>
          <p>納屋や資材置き場を丸ごと整理。大型搬出も私たちが行います。</p>
          <span class="num">03</span>
        </article>
      </div>
    </div>
  </section>

  <!-- 最新実績 -->
  <section class="section section--tight" id="results" data-nav="最新の買取実績" style="background:var(--surface)">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Latest Results</span>
        <h2 class="sec-title">最新の買取実績</h2>
      </div>
      <?php get_template_part( 'parts/results-latest', null, array( 'count' => 4 ) ); ?>
      <p style="margin-top:24px"><a class="btn btn--ghost" href="<?php echo esc_url( tk_results_url() ); ?>">買取実績をもっと見る<?php echo tk_icon( 'arrow', 'icon arrow' ); // phpcs:ignore ?></a></p>
    </div>
  </section>

  <section class="section section--tight" id="media" data-nav="コラム・用語集">
    <div class="container">
      <div class="media-links">
        <a class="media-link reveal" href="<?php echo esc_url( get_post_type_archive_link( 'column' ) ); ?>"><span class="eyebrow">Column</span><strong>お役立ちコラム</strong><span>離農時の農機具整理、工具の売り時など、連載でお届けします。</span></a>
        <a class="media-link reveal" style="--d:.08s" href="<?php echo esc_url( get_post_type_archive_link( 'glossary' ) ); ?>"><span class="eyebrow">Glossary</span><strong>用語集</strong><span>アワーメーター、名義変更、ジャンク品……買取でよく出てくることば。</span></a>
      </div>
    </div>
  </section>
</main>
<?php
get_footer();
