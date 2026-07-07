<?php
declare(strict_types=1);

/** @var array $bills */
/** @var string $title */
/** @var array $pagination */

$title = $title ?? '会計履歴';
$BASE_URL = '/regi/public';

$bills = is_array($bills ?? null) ? $bills : [];

$pagination = $pagination ?? [
    'page'       => 1,
    'perPage'    => 20,
    'totalCount' => 0,
    'totalPages' => 1,
    'hasPrev'    => false,
    'hasNext'    => false,
    'prevPage'   => 1,
    'nextPage'   => 1,
];

$currentKeyword = trim((string)($_GET['keyword'] ?? ''));
$currentDate = trim((string)($_GET['date'] ?? ''));

/**
 * HTMLエスケープ
 */
function historyIndexH(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * 支払方法に対応するCSSクラス
 */
function historyIndexPayClass(string $payMethod): string
{
    return match (strtoupper(trim($payMethod))) {
        'CASH'             => 'cash',
        'CARD',
        'CREDIT_CARD'      => 'card',
        'ELECTRONIC_MONEY' => 'emoney',
        'QR',
        'QR_PAYMENT'       => 'qr',
        default            => '',
    };
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1"
  >

  <title><?= historyIndexH($title) ?></title>

  <link
    rel="stylesheet"
    href="<?= historyIndexH($BASE_URL) ?>/assets/css/app.css"
  >

  <link
    rel="stylesheet"
    href="<?= historyIndexH($BASE_URL) ?>/assets/css/history.css?v=8"
  >

  <script
    src="<?= historyIndexH($BASE_URL) ?>/assets/js/history.js?v=8"
    defer
  ></script>
</head>

<body>

<div class="history-page">

  <!-- ヘッダー -->
  <header class="app-header">

    <div class="app-header__left">

      <a
        class="app-back"
        href="<?= historyIndexH($BASE_URL) ?>/home"
        aria-label="戻る"
      >
        ←
      </a>

      <h1 class="app-title">
        会計履歴
      </h1>

    </div>

  </header>

  <!-- 検索フォーム -->
  <form
    class="history-filters"
    method="get"
    action="<?= historyIndexH($BASE_URL) ?>/history"
  >

    <div class="history-search">

      <input
        class="input"
        type="text"
        id="historySearchInput"
        name="keyword"
        value="<?= historyIndexH($currentKeyword) ?>"
        placeholder="客番号、商品名、会計ID、店舗名で検索..."
      >

    </div>

    <div class="history-date">

      <input
        class="input"
        type="date"
        id="historyDateInput"
        name="date"
        value="<?= historyIndexH($currentDate) ?>"
      >

    </div>

    <div class="history-filter-actions">

      <button
        class="btn btn-primary"
        type="submit"
      >
        検索
      </button>

      <a
        class="btn btn-outline"
        href="<?= historyIndexH($BASE_URL) ?>/history"
      >
        クリア
      </a>

    </div>

  </form>

  <!-- 件数 -->
  <div class="history-result-summary">
    <?= number_format((int)($pagination['totalCount'] ?? 0)) ?>件
  </div>

  <!-- 会計履歴一覧 -->
  <div
    class="history-list"
    id="historyList"
  >

    <?php if ($bills !== []): ?>

      <?php foreach ($bills as $bill): ?>

        <?php
        $billId = (string)($bill['billId'] ?? '');

        $payMethod = (string)($bill['payMethod'] ?? '');
        $payLabel = (string)($bill['payLabel'] ?? '');
        $payClass = historyIndexPayClass($payMethod);

        $discountAmount = (int)(
            $bill['discountAmount']
            ?? $bill['discount']
            ?? 0
        );

        $hasDiscount = $discountAmount > 0;

        $storeName = trim((string)($bill['storeName'] ?? ''));
        $storeId = trim((string)($bill['storeId'] ?? ''));

        $storeText = $storeName !== ''
            ? $storeName
            : $storeId;

        $customerIdText = (string)(
            $bill['customerIdText']
            ?? '手入力'
        );

        $datetime = (string)($bill['datetime'] ?? '');
        $total = (int)($bill['total'] ?? 0);

        $paymentCount = (int)(
            $bill['paymentCount']
            ?? $bill['payment_count']
            ?? 0
        );

        $detailUrl =
            $BASE_URL
            . '/history/detail?bill_id='
            . rawurlencode($billId);
        ?>

        <button
          type="button"
          class="history-card"
          data-history-detail-url="<?= historyIndexH($detailUrl) ?>"
          data-bill-id="<?= historyIndexH($billId) ?>"
          aria-label="会計ID <?= historyIndexH($billId) ?> の詳細を表示"
        >

          <div class="history-card-left">

            <div class="history-card-top">

              <div class="history-id">
                <?= historyIndexH($billId) ?>
              </div>

              <div class="history-chips">

                <?php if ($storeText !== ''): ?>

                  <span class="history-chip">
                    <?= historyIndexH($storeText) ?>
                  </span>

                <?php endif; ?>

                <span class="history-chip">
                  <?= historyIndexH($customerIdText) ?>
                </span>

                <?php if ($payLabel !== ''): ?>

                  <span
                    class="history-chip <?= historyIndexH($payClass) ?>"
                  >
                    <?= historyIndexH($payLabel) ?>
                  </span>

                <?php endif; ?>

                <?php if ($paymentCount > 1): ?>

                  <span class="history-chip multiple">
                    <?= $paymentCount ?>回払い
                  </span>

                <?php endif; ?>

              </div>

            </div>

            <div class="history-card-sub">
              <?= historyIndexH($datetime) ?>
            </div>

          </div>

          <div class="history-card-right">

            <div class="history-amount">
              ¥<?= number_format($total) ?>
            </div>

            <?php if ($hasDiscount): ?>

              <div class="history-discount">
                割引あり
              </div>

            <?php endif; ?>

          </div>

        </button>

      <?php endforeach; ?>

    <?php else: ?>

      <div class="history-empty">
        会計履歴がありません。
      </div>

    <?php endif; ?>

  </div>

  <!-- ページネーション -->
  <?php if ((int)($pagination['totalPages'] ?? 1) > 1): ?>

    <?php
    $pageQueryBase = [];

    if ($currentKeyword !== '') {
        $pageQueryBase['keyword'] = $currentKeyword;
    }

    if ($currentDate !== '') {
        $pageQueryBase['date'] = $currentDate;
    }

    $prevQuery = http_build_query(
        array_merge(
            $pageQueryBase,
            [
                'page' => (int)($pagination['prevPage'] ?? 1),
            ]
        )
    );

    $nextQuery = http_build_query(
        array_merge(
            $pageQueryBase,
            [
                'page' => (int)($pagination['nextPage'] ?? 1),
            ]
        )
    );
    ?>

    <nav
      class="history-pagination"
      aria-label="会計履歴のページ移動"
    >

      <div class="history-pagination-side">

        <?php if (!empty($pagination['hasPrev'])): ?>

          <a
            class="history-page-btn"
            href="<?= historyIndexH($BASE_URL) ?>/history?<?= historyIndexH($prevQuery) ?>"
          >
            ← 前の20件
          </a>

        <?php endif; ?>

      </div>

      <span class="history-page-current">
        <?= (int)($pagination['page'] ?? 1) ?>
        /
        <?= (int)($pagination['totalPages'] ?? 1) ?>
        ページ
      </span>

      <div class="history-pagination-side history-pagination-side--right">

        <?php if (!empty($pagination['hasNext'])): ?>

          <a
            class="history-page-btn"
            href="<?= historyIndexH($BASE_URL) ?>/history?<?= historyIndexH($nextQuery) ?>"
          >
            次の20件 →
          </a>

        <?php endif; ?>

      </div>

    </nav>

  <?php endif; ?>

  <!-- 会計詳細モーダル -->
  <div
    class="history-backdrop"
    id="historyModal"
    aria-hidden="true"
  >

    <div
      class="history-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="historyModalTitle"
    >

      <div class="history-modal-head">

        <div
          class="history-modal-title"
          id="historyModalTitle"
        >
          会計詳細
        </div>

        <button
          class="history-x"
          type="button"
          id="historyClose"
          aria-label="会計詳細を閉じる"
        >
          ×
        </button>

      </div>

      <!--
        /history/detail のレスポンスを
        history.jsがここへ読み込む
      -->
      <div
        class="history-modal-body"
        id="historyModalBody"
      >

        <div class="history-modal-loading">
          会計詳細を読み込んでいます。
        </div>

      </div>

    </div>

  </div>

</div>

</body>
</html>