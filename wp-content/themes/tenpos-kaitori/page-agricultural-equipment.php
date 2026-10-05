<?php
/**
 * 農機具買取 LP（固定ページ slug: agricultural-equipment）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_africa = tk_africa_enabled();

get_header();
// 目次タブは本文の [data-nav] セクションと同じ順番・同じ数にすること（SPEC.md §5）.
get_template_part(
	'parts/anchor-tabs',
	null,
	array(
		'items' => ( $tk_africa ? array( 'africa' => 'アフリカで再活用' ) : array() ) + array(
			'reason'  => '買取できる理由',
			'items'   => '対応品目',
			'results' => '買取事例',
			'flow'    => '引き取りの流れ',
			'faq'     => 'FAQ',
			'form'    => '無料査定',
		),
	)
);
?>
<main id="main">
<?php while ( have_posts() ) : the_post(); ?>

  <!-- [1] ファーストビュー -->
  <section class="agri-hero">
    <div class="container agri-hero__grid">
      <div>
        <?php if ( $tk_africa && '' !== trim( tk_opt( 'tk_africa_badge' ) ) ) : ?>
        <a class="africa-badge" href="#africa"><span class="africa-badge__ico"><svg class="icon"><use href="#i-globe"/></svg></span><span><?php echo esc_html( tk_opt( 'tk_africa_badge' ) ); ?></span><svg class="icon africa-badge__arrow"><use href="#i-arrow"/></svg></a>
        <?php endif; ?>
        <p class="agri-hero__tag"><i></i>全国出張・本日もご予約受付中</p>
        <h1>
          <span class="line"><span style="--d:.05s">動かなくても、</span></span>
          <span class="line"><span style="--d:.15s"><span class="mark">サビていても</span>大丈夫。</span></span>
        </h1>
        <p class="agri-hero__sub">15年前のトラクター・農機具も、現地まで無料で引き取りに伺います。納屋に眠ったままの農機具、処分費用をかける前に、まずはテンポスへ。</p>
        <div class="trust-badges">
          <span class="trust-badge"><span>出張費<b>0</b>円</span></span>
          <span class="trust-badge"><span>査定・<br>キャンセル<b>0</b>円</span></span>
          <span class="trust-badge"><span>東証上場<br>グループ<br>運営</span></span>
        </div>
        <div class="agri-hero__ctas">
          <a class="btn btn--cta" href="#form"><svg class="icon"><use href="#i-edit"/></svg>無料査定フォームへ</a>
          <a class="btn btn--primary" href="<?php echo esc_url( tk_tel_href() ); ?>"><svg class="icon"><use href="#i-phone"/></svg>電話で相談</a>
        </div>
        <p class="micro"><span>入力は1分で完了</span><span>強引な営業は一切ありません</span></p>
      </div>
      <div class="agri-hero__visual">
        <?php if ( has_post_thumbnail() ) : ?>
        <div class="ph ph--img reveal-clip"><?php the_post_thumbnail( 'tk-wide', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></div>
        <?php else : ?>
        <div class="ph reveal-clip" data-label="PHOTO：田んぼとトラクター（AVIF）">
          <svg viewBox="0 0 120 80" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="34" cy="56" r="18"/><circle cx="34" cy="56" r="7"/><circle cx="94" cy="62" r="11"/><circle cx="94" cy="62" r="4"/><path d="M22 38V16h24l8 22M54 38h38l8 14M28 16V8M64 38V26h8v12M14 38h92"/></svg>
        </div>
        <?php endif; ?>
        <div class="agri-hero__float"><svg class="icon" style="color:var(--agri)"><use href="#i-truck"/></svg><span>大型搬出・名義変更<br>すべて代行</span></div>
        <div class="agri-hero__float agri-hero__float--r"><span>今月の買取</span><b data-count="1284">0</b>台</div>
      </div>
    </div>
  </section>

  <!-- [2] アフリカで再活用（当面の主訴求。カスタマイザーでオン／オフ） -->
  <?php if ( $tk_africa ) { get_template_part( 'parts/africa' ); } ?>

  <!-- [3] 悩み共感＆解決 -->
  <section class="section" id="reason" data-nav="買取できる理由" style="background:var(--surface)">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Worries → Solved</span>
        <h2 class="sec-title">こんな状態でも、<br>買い取れます。</h2>
        <p class="answer">エンジンがかからない・サビや凹みがある・長期間放置・型式が古い農機具も買取対象です。部品の再利用と海外輸出ルートがあるため、状態を問わず値段がつきます。</p>
      </div>
      <div class="worry-grid">
        <div class="worry reveal"><p class="worry__q">エンジンがかからない</p><p class="worry__a"><b>不動車OK</b>部品取りとして価値があります</p></div>
        <div class="worry reveal" style="--d:.08s"><p class="worry__q">サビ・凹みがある</p><p class="worry__a"><b>外装の傷みOK</b>整備して再販・輸出します</p></div>
        <div class="worry reveal" style="--d:.16s"><p class="worry__q">何年も放置している</p><p class="worry__a"><b>長期放置OK</b>現地でそのまま査定します</p></div>
        <div class="worry reveal" style="--d:.24s"><p class="worry__q">型式が古すぎる</p><p class="worry__a"><b>15年落ちもOK</b>海外では今も現役です</p></div>
      </div>
      <div class="reasons">
        <article class="reason reveal"><span class="ico"><svg class="icon"><use href="#i-gear"/></svg></span><h3>パーツ再利用ネットワーク</h3><p>動かない機械も、エンジン・ミッション・爪などの部品単位で価値を算出します。</p></article>
        <article class="reason reveal" style="--d:.08s"><span class="ico"><svg class="icon"><use href="#i-globe"/></svg></span><h3>海外輸出ルート</h3><p>日本製農機具は海外で高い需要があり、国内相場より高く評価できることがあります。</p></article>
        <article class="reason reveal" style="--d:.16s"><span class="ico"><svg class="icon"><use href="#i-truck"/></svg></span><h3>全国の自社引き取り網</h3><p>テンポスの全国網で、査定費・出張費・搬出費をいただかずに伺います。</p></article>
      </div>
    </div>
  </section>

  <!-- [4] 対応品目＆メーカー -->
  <section class="section" id="items" data-nav="対応品目">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Items &amp; Makers</span>
        <h2 class="sec-title">対応品目・メーカー</h2>
        <p class="answer">トラクター・コンバイン・田植機をはじめ、耕運機・草刈機・アタッチメントまで農機具全般を買い取ります。国内主要メーカーはすべて対応しています。</p>
      </div>
      <div class="items-grid">
        <a class="item-card reveal" href="#form"><span class="n">01</span><svg viewBox="0 0 120 80"><circle cx="34" cy="56" r="18"/><circle cx="94" cy="62" r="11"/><path d="M22 38V16h24l8 22M54 38h38l8 14M14 38h92"/></svg><b>トラクター</b></a>
        <a class="item-card reveal" href="#form" style="--d:.05s"><span class="n">02</span><svg viewBox="0 0 120 80"><circle cx="30" cy="60" r="12"/><circle cx="90" cy="60" r="12"/><path d="M18 48h84V28H40l-8 20M62 28V14M100 34l14-10"/></svg><b>コンバイン</b></a>
        <a class="item-card reveal" href="#form" style="--d:.1s"><span class="n">03</span><svg viewBox="0 0 120 80"><circle cx="40" cy="58" r="14"/><path d="M26 40h50l10 18H54M70 40V20M84 58h26M92 50v16M102 50v16"/></svg><b>田植機</b></a>
        <a class="item-card reveal" href="#form" style="--d:.15s"><span class="n">04</span><svg viewBox="0 0 120 80"><circle cx="50" cy="58" r="14"/><path d="M50 44 30 14M30 14h-12M64 58h30M70 52l6 12M82 52l6 12"/></svg><b>耕運機・管理機</b></a>
        <a class="item-card reveal" href="#form"><span class="n">05</span><svg viewBox="0 0 120 80"><path d="M20 20 80 60M80 60h20M74 56l12 12M40 34l-10 6"/><circle cx="96" cy="64" r="10"/></svg><b>草刈機・芝刈機</b></a>
        <a class="item-card reveal" href="#form" style="--d:.05s"><span class="n">06</span><svg viewBox="0 0 120 80"><path d="M14 30h92v18H14zM24 48v14M44 48v14M64 48v14M84 48v14M104 48v14"/></svg><b>ドライブハロー</b></a>
        <a class="item-card reveal" href="#form" style="--d:.1s"><span class="n">07</span><svg viewBox="0 0 120 80"><path d="M20 40h30l10-16h40M60 24v40M40 64h40"/><circle cx="60" cy="40" r="6"/></svg><b>アタッチメント全般</b></a>
        <a class="item-card reveal" href="#form" style="--d:.15s"><span class="n">08</span><svg viewBox="0 0 120 80"><path d="M30 40h60M60 10v60"/></svg><b>その他・まとめて</b></a>
      </div>
    </div>
    <div class="makers">
      <div class="marquee" aria-label="対応メーカー：クボタ、ヤンマー、イセキ、三菱、シバウラ、ヒノモト ほか">
        <div class="marquee__inner" aria-hidden="true"><span class="marquee__item">クボタ</span><span class="marquee__item">ヤンマー</span><span class="marquee__item">イセキ</span><span class="marquee__item">三菱</span><span class="marquee__item">シバウラ</span><span class="marquee__item">ヒノモト</span><span class="marquee__item">ほか国内全メーカー</span></div>
        <div class="marquee__inner" aria-hidden="true"><span class="marquee__item">クボタ</span><span class="marquee__item">ヤンマー</span><span class="marquee__item">イセキ</span><span class="marquee__item">三菱</span><span class="marquee__item">シバウラ</span><span class="marquee__item">ヒノモト</span><span class="marquee__item">ほか国内全メーカー</span></div>
      </div>
    </div>
  </section>

  <!-- [6] 買取実績 -->
  <section class="section" id="results" data-nav="買取事例" style="background:var(--surface)">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Results</span>
        <h2 class="sec-title">農機具の買取事例</h2>
        <p class="answer">直近の買取事例です。不動・故障の農機具にも買取価格がついています。</p>
      </div>
      <?php get_template_part( 'parts/results-latest', null, array( 'genre' => 'agri', 'count' => 4, 'filter' => true ) ); ?>
      <p style="margin-top:24px"><a class="btn btn--ghost" href="<?php echo esc_url( tk_results_url( 'agri' ) ); ?>">買取実績をもっと見る<svg class="icon arrow"><use href="#i-arrow"/></svg></a></p>
    </div>
  </section>

  <!-- [5] 出張買取の流れ -->
  <section class="section" id="flow" data-nav="引き取りの流れ">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Flow</span>
        <h2 class="sec-title">出張買取の流れ</h2>
        <p class="answer">お問い合わせから現金お支払いまで最短3ステップ。大型搬出や名義変更もテンポスが代行するので、立ち会うだけで完了します。</p>
      </div>
      <div class="stack">
        <article class="stack__card" style="--i:0"><p class="stack__no"><small>STEP</small>01</p><div><h3>お問い合わせ</h3><p>査定フォームに入力するだけ（1分）。型式がわからなくても大丈夫です。</p><div class="tags"><span>WEB査定フォーム</span><span>電話相談</span><span>年中無休</span></div></div><div class="ph" data-label="PHOTO：フォーム入力"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5"><use href="#i-edit"/></svg></div></article>
        <article class="stack__card" style="--i:1"><p class="stack__no"><small>STEP</small>02</p><div><h3>現地確認・査定</h3><p>専門スタッフがご自宅・納屋まで訪問。その場で金額をご提示します。</p><div class="tags"><span>出張費0円</span><span>査定料0円</span><span>キャンセル無料</span></div></div><div class="ph" data-label="PHOTO：査定スタッフ"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5"><use href="#i-doc"/></svg></div></article>
        <article class="stack__card" style="--i:2"><p class="stack__no"><small>STEP</small>03</p><div><h3>その場で現金お支払い＆引き取り</h3><p>大型の搬出・名義変更手続きもテンポスがすべて代行します。</p><div class="tags"><span>即日現金</span><span>搬出代行</span><span>名義変更代行</span></div></div><div class="ph" data-label="PHOTO：積み込み"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5"><use href="#i-truck"/></svg></div></article>
      </div>
    </div>
  </section>

  <!-- [7] FAQ -->
  <section class="section" id="faq" data-nav="よくある質問" style="background:var(--surface)">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">FAQ</span>
        <h2 class="sec-title">よくある質問</h2>
      </div>
      <?php get_template_part( 'parts/faq-list', null, array( 'genre' => 'agri', 'surface' => true ) ); ?>
    </div>
  </section>

  <!-- [8] 査定フォーム -->
  <?php tk_render_estimate_section( 'agri' ); ?>

<?php endwhile; ?>
</main>
<?php
get_footer();
