<?php
/**
 * 工具買取 LP（固定ページ slug: tool）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part(
	'parts/anchor-tabs',
	null,
	array(
		'items' => array(
			'maker'    => '強化メーカー',
			'category' => 'カテゴリー',
			'rank'     => '状態ランク',
			'method'   => '買取方法',
			'results'  => '買取実績',
			'faq'     => 'FAQ',
			'form'    => '無料査定',
		),
	)
);
?>
<main id="main">
<?php while ( have_posts() ) : the_post(); ?>

  <!-- [1] ファーストビュー -->
  <section class="tool-hero">
    <div class="container tool-hero__grid">
      <div>
        <p class="tool-hero__kicker reveal">Cash<span>Today.</span></p>
        <h1 class="reveal" style="--d:.1s"><em>マキタ・ハイコーキ</em>高価買取！<br>買い替えで不要になった現場の工具、サクッと即日現金化。</h1>
        <p class="tool-hero__sub reveal" style="--d:.15s">動く道具はもちろん、型落ち・予備工具・ケースなしも歓迎。プロの道具を正当評価します。</p>
        <div class="tool-badges reveal" style="--d:.2s"><span>型番明確査定</span><span>現場帰り・持ち込み歓迎</span><span>即日現金化</span></div>
        <div class="tool-hero__ctas reveal" style="--d:.25s">
          <a class="btn btn--cta" href="#form"><svg class="icon"><use href="#i-edit"/></svg>型番で無料査定</a>
          <a class="btn btn--primary" href="<?php echo esc_url( tk_tel_href() ); ?>"><svg class="icon"><use href="#i-phone"/></svg>電話で相談</a>
        </div>
        <p class="micro"><span>型番を入れるだけ・1分で完了</span><span>査定料・キャンセル料0円</span></p>
      </div>
      <div class="tool-hero__visual">
        <?php if ( has_post_thumbnail() ) : ?>
        <div class="ph ph--img reveal-clip"><?php the_post_thumbnail( 'tk-wide', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></div>
        <?php else : ?>
        <div class="ph reveal-clip" data-label="PHOTO：インパクトドライバ／現場（AVIF）">
          <svg viewBox="0 0 120 100" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 18h62a10 10 0 0 1 10 10v10a10 10 0 0 1-10 10H18z"/><path d="M90 33h12M102 27v12l12-6z"/><path d="M40 48l-6 30h22l6-30"/><rect x="26" y="78" width="38" height="12" rx="3"/><path d="M28 33h34"/></svg>
        </div>
        <?php endif; ?>
        <p class="tool-hero__price"><small>インパクトドライバ 最大</small><b>¥<span data-count="25000">0</span></b></p>
      </div>
    </div>
  </section>
  <div class="hazard" aria-hidden="true"></div>

  <!-- [3] 強化メーカー -->
  <section class="section" id="maker" data-nav="強化メーカー">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Makers</span>
        <h2 class="sec-title">買取強化メーカー</h2>
        <p class="answer">マキタ・ハイコーキ（旧日立工機）をはじめ、MAX・パナソニック・ボッシュ・タジマ・京セラ（旧リョービ）など主要メーカーを型番ベースで明確に査定します。</p>
      </div>
      <div class="maker-grid reveal">
        <div class="maker"><span class="hot">強化中</span><span>makita<small>マキタ</small></span></div>
        <div class="maker"><span class="hot">強化中</span><span>HiKOKI<small>ハイコーキ／日立工機</small></span></div>
        <div class="maker"><span>MAX<small>マックス</small></span></div>
        <div class="maker"><span>Panasonic<small>パナソニック</small></span></div>
        <div class="maker"><span>BOSCH<small>ボッシュ</small></span></div>
        <div class="maker"><span>TAJIMA<small>タジマ</small></span></div>
        <div class="maker"><span>KYOCERA<small>京セラ／リョービ</small></span></div>
        <div class="maker"><span>OTHERS<small>その他メーカー</small></span></div>
      </div>
    </div>
  </section>

  <!-- [4] カテゴリー -->
  <section class="section" id="category" data-nav="カテゴリー" style="background:var(--surface)">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Category</span>
        <h2 class="sec-title">カテゴリー別 取扱品目</h2>
        <p class="answer">電動工具・エア工具・測量機器・手工具・エンジン工具まで、現場で使うプロの道具はほぼすべて買取対象です。</p>
      </div>
      <div class="cat-scroller">
        <article class="cat"><div class="ph" data-label="PHOTO"><svg viewBox="0 0 120 100" fill="none" stroke-width="3"><path d="M18 18h62a10 10 0 0 1 10 10v10a10 10 0 0 1-10 10H18z"/><path d="M40 48l-6 30h22l6-30"/></svg></div><div class="cat__body"><p class="cat__no">CAT.01</p><h3>電動工具</h3><ul><li>インパクトドライバ</li><li>丸ノコ</li><li>ハンマドリル</li><li>ディスクグラインダ</li><li>全ねじカッタ</li></ul></div></article>
        <article class="cat"><div class="ph" data-label="PHOTO"><svg viewBox="0 0 120 100" fill="none" stroke-width="3"><path d="M20 30h50v20H20zM70 40h30M40 50v30h14V50"/></svg></div><div class="cat__body"><p class="cat__no">CAT.02</p><h3>エア工具</h3><ul><li>釘打機</li><li>ねじ打機</li><li>エアコンプレッサー</li></ul></div></article>
        <article class="cat"><div class="ph" data-label="PHOTO"><svg viewBox="0 0 120 100" fill="none" stroke-width="3"><rect x="40" y="30" width="40" height="40" rx="4"/><path d="M60 10v20M60 70v20M10 50h30M80 50h30"/></svg></div><div class="cat__body"><p class="cat__no">CAT.03</p><h3>測量・レーザー機器</h3><ul><li>レーザー墨出し器</li><li>オートレベル</li></ul></div></article>
        <article class="cat"><div class="ph" data-label="PHOTO"><svg viewBox="0 0 120 100" fill="none" stroke-width="3"><path d="M30 80 70 40M70 40l10-20 20 10-10 20zM30 80l-10 10"/></svg></div><div class="cat__body"><p class="cat__no">CAT.04</p><h3>手工具・作業工具</h3><ul><li>圧着工具</li><li>配管工具</li><li>油圧工具</li></ul></div></article>
        <article class="cat"><div class="ph" data-label="PHOTO"><svg viewBox="0 0 120 100" fill="none" stroke-width="3"><rect x="20" y="30" width="80" height="44" rx="6"/><path d="M34 74v10M86 74v10M36 44h20M36 56h48"/></svg></div><div class="cat__body"><p class="cat__no">CAT.05</p><h3>エンジン工具・大型機器</h3><ul><li>発電機</li><li>溶接機</li><li>チェーンソー</li></ul></div></article>
      </div>
    </div>
  </section>

  <!-- [5] 状態ランク -->
  <section class="section" id="rank" data-nav="状態ランク">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Condition Rank</span>
        <h2 class="sec-title">査定基準・状態ランク</h2>
        <p class="answer">状態をS〜Cの4ランクで評価します。動作未確認のジャンク品でもCランクとして買い取ります。</p>
      </div>
      <div class="rank-list">
        <div class="rank" style="--c:#ffd400;--v:100%"><span class="rank__letter">S</span><div class="rank__body"><h3>新品・未使用</h3><p>未開封、または開封済みで未使用のもの</p></div><div class="rank__meter">査定目安<span class="bar"><i></i></span></div></div>
        <div class="rank" style="--c:#ffe14d;--v:78%"><span class="rank__letter">A</span><div class="rank__body"><h3>美品</h3><p>使用感が少なく、目立つ傷がないもの</p></div><div class="rank__meter">査定目安<span class="bar"><i></i></span></div></div>
        <div class="rank" style="--c:#f4f4f2;--v:55%"><span class="rank__letter">B</span><div class="rank__body"><h3>動作確認済 実用品</h3><p>使用感・傷はあるが正常に動作するもの</p></div><div class="rank__meter">査定目安<span class="bar"><i></i></span></div></div>
        <div class="rank" style="--c:#e60012;--v:28%"><span class="rank__letter" style="color:#fff">C</span><div class="rank__body"><h3>ジャンク・パーツ取り</h3><p>故障品・動作未確認・部品取り用</p></div><div class="rank__meter">査定目安<span class="bar"><i></i></span></div></div>
      </div>
      <div class="ok-strip reveal"><span>箱なしOK</span><span>本体のみOK</span><span>バッテリー劣化品OK</span><span>型落ちOK</span></div>
    </div>
  </section>

  <!-- [6] 買取方法 -->
  <section class="section" id="method" data-nav="買取方法" style="background:var(--surface)">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">How to Sell</span>
        <h2 class="sec-title">選べる3つの買取方法</h2>
      </div>
      <div class="method-tabs" role="tablist" aria-label="買取方法">
        <button role="tab" id="t-store" aria-controls="p-store" aria-selected="true">店頭<small>即日現金</small></button>
        <button role="tab" id="t-visit" aria-controls="p-visit" aria-selected="false" tabindex="-1">出張<small>大量一括</small></button>
        <button role="tab" id="t-mail" aria-controls="p-mail" aria-selected="false" tabindex="-1">宅配<small>全国対応</small></button>
      </div>
      <div class="method-panel" id="p-store" role="tabpanel" aria-labelledby="t-store">
        <div><h3>現場帰りに<em>持ち込み</em>、その場で現金。</h3><p>最寄りのテンポス店舗へ持ち込むだけ。査定から現金お渡しまで最短15分です。</p><ul><li>予約不要・作業着のままでOK</li><li>1点からでも歓迎</li><li>駐車場完備の店舗多数</li></ul></div>
        <div class="ph" data-label="PHOTO：店頭カウンター"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5"><use href="#i-doc"/></svg></div>
      </div>
      <div class="method-panel" id="p-visit" role="tabpanel" aria-labelledby="t-visit" hidden>
        <div><h3>現場・倉庫の工具を<em>まとめて</em>一括査定。</h3><p>廃業・事業縮小・倉庫整理などの大量買取に。スタッフが伺い、その場で査定します。</p><ul><li>出張費・査定料0円</li><li>重量物の搬出も対応</li><li>法人・工務店の大口歓迎</li></ul></div>
        <div class="ph" data-label="PHOTO：出張査定"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5"><use href="#i-truck"/></svg></div>
      </div>
      <div class="method-panel" id="p-mail" role="tabpanel" aria-labelledby="t-mail" hidden>
        <div><h3>送って<em>待つだけ</em>。全国どこからでも。</h3><p>梱包キットをお届けし、着払いで送るだけ。査定額にご納得いただければ振込みます。</p><ul><li>送料・梱包キット無料</li><li>査定後のキャンセル返送無料</li><li>最短翌日振込</li></ul></div>
        <div class="ph" data-label="PHOTO：梱包キット"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5"><use href="#i-globe"/></svg></div>
      </div>
    </div>
  </section>

  <!-- 買取実績 -->
  <section class="section" id="results" data-nav="買取実績">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">Results</span>
        <h2 class="sec-title">工具の買取実績</h2>
        <p class="answer">直近の買取実績です。型落ち・ジャンク品にも買取価格がついています。</p>
      </div>
      <?php get_template_part( 'parts/results-latest', null, array( 'genre' => 'tool', 'count' => 4 ) ); ?>
      <p style="margin-top:24px"><a class="btn btn--ghost" href="<?php echo esc_url( tk_results_url( 'tool' ) ); ?>">買取実績をもっと見る<?php echo tk_icon( 'arrow', 'icon arrow' ); // phpcs:ignore ?></a></p>
    </div>
  </section>

  <!-- [7] FAQ -->
  <section class="section" id="faq" data-nav="よくある質問" style="background:var(--surface)">
    <div class="container">
      <div class="sec-head reveal">
        <span class="eyebrow">FAQ</span>
        <h2 class="sec-title">よくある質問</h2>
      </div>
      <?php get_template_part( 'parts/faq-list', null, array( 'genre' => 'tool' ) ); ?>
    </div>
  </section>

  <!-- [8] 査定フォーム -->
  <?php tk_render_estimate_section( 'tool' ); ?>

<?php endwhile; ?>
</main>
<?php
get_footer();
