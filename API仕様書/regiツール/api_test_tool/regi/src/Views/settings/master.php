<?php
$BASE = '/regi/public';
$title = 'システム設定';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 仮対応: 設定画面開発中は強制でマスター扱い

$accounts = $accounts ?? [];
$stores = $stores ?? [];
$backupHistories = $backupHistories ?? [];
$restoreHistories = $restoreHistories ?? [];
$systemInfo = $systemInfo ?? [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fmt_datetime')) {
    function fmt_datetime($value): string
    {
        if (!$value) {
            return '—';
        }
        return date('Y/m/d H:i', strtotime((string)$value));
    }
}

if (!function_exists('fmt_file_size')) {
    function fmt_file_size($bytes): string
    {
        if ($bytes === null || $bytes === '') {
            return '—';
        }

        $bytes = (float)$bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}

if (!function_exists('backup_type_label')) {
    function backup_type_label($value): string
    {
        $map = [
            'MANUAL' => '手動',
            'AUTO'   => '自動',
        ];

        $value = (string)$value;
        return $map[$value] ?? $value;
    }
}

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="base-url" content="<?= h($BASE) ?>">

  <title><?= h($title) ?></title>

  <link rel="stylesheet" href="<?= h($BASE) ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= h($BASE) ?>/assets/css/settings-master.css">
  <script defer src="<?= h($BASE) ?>/assets/js/settings-master.js"></script>
</head>
<body>
  <header class="app-header">
    <div class="app-header__left">
      <a class="app-back" href="<?= h($BASE) ?>/home" aria-label="戻る">←</a>
      <h1 class="app-title">システム設定</h1>
    </div>
  </header>

  <main class="settings-page">
    <?php if ($flashSuccess): ?>
      <div class="flash flash-success"><?= h($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
      <div class="flash flash-error"><?= h($flashError) ?></div>
    <?php endif; ?>

    <nav class="settings-tabs" aria-label="設定タブ">
      <button class="settings-tab is-active" type="button" data-tab="accounts">アカウント管理</button>
      <button class="settings-tab" type="button" data-tab="stores">店舗管理</button>
      <button class="settings-tab" type="button" data-tab="backup">バックアップ</button>
      <button class="settings-tab" type="button" data-tab="system">システム情報</button>
    </nav>

    <section class="settings-body">
      <!-- アカウント管理 -->
      <section class="settings-panel is-active" data-panel="accounts">
        <div class="settings-card">
          <div class="settings-card__head">
            <h2 class="settings-card__title">アカウント管理</h2>
            <p class="settings-card__desc">全店舗のアカウントを追加・編集できます。</p>
          </div>

          <div class="settings-list">
            <?php foreach ($accounts as $account): ?>
              <?php
                $isMaster = (($account['role_type'] ?? '') === 'MASTER');
                $statusClass = !empty($account['is_active']) ? 'pill pill-on' : 'pill pill-off';
                $statusText = !empty($account['is_active']) ? '有効' : '無効';
                $accountStoreText = $isMaster
                    ? '全店舗'
                    : (($account['store_name'] ?? '') !== ''
                        ? ($account['store_id'] . ' / ' . $account['store_name'])
                        : ($account['store_id'] ?? '未設定'));
              ?>
              <div class="account-row">
                <div class="account-row__left">
                  <div class="account-row__name"><?= h($account['account_name'] ?? '') ?></div>
                  <div class="account-row__meta">
                    ID: <?= h($account['login_id'] ?? '') ?>
                    / 権限: <?= $isMaster ? 'マスター' : 'スタッフ' ?>
                    / 所属店舗: <?= h($accountStoreText) ?>
                    / 最終ログイン: <?= h(fmt_datetime($account['last_login_at'] ?? null)) ?>
                  </div>
                </div>

                <div class="account-row__right">
                  <span class="<?= h($statusClass) ?>"><?= h($statusText) ?></span>

                  <button
                    class="btnx btnx-ghost btn-account-detail"
                    type="button"
                    data-account-id="<?= h($account['account_id'] ?? '') ?>"
                    data-login-id="<?= h($account['login_id'] ?? '') ?>"
                    data-account-name="<?= h($account['account_name'] ?? '') ?>"
                    data-role-type="<?= h($account['role_type'] ?? '') ?>"
                    data-store-id="<?= h($account['store_id'] ?? '') ?>"
                    data-store-name="<?= h($account['store_name'] ?? '') ?>"
                    data-email="<?= h($account['email'] ?? '') ?>"
                    data-is-active="<?= (int)($account['is_active'] ?? 0) ?>"
                    data-last-login-at="<?= h(fmt_datetime($account['last_login_at'] ?? null)) ?>"
                  >詳細</button>

                  <button
                    class="btnx btnx-primary btn-account-edit"
                    type="button"
                    data-account-id="<?= h($account['account_id'] ?? '') ?>"
                    data-login-id="<?= h($account['login_id'] ?? '') ?>"
                    data-account-name="<?= h($account['account_name'] ?? '') ?>"
                    data-role-type="<?= h($account['role_type'] ?? '') ?>"
                    data-store-id="<?= h($account['store_id'] ?? '') ?>"
                    data-store-name="<?= h($account['store_name'] ?? '') ?>"
                    data-email="<?= h($account['email'] ?? '') ?>"
                    data-is-active="<?= (int)($account['is_active'] ?? 0) ?>"
                  >編集</button>
                </div>
              </div>
            <?php endforeach; ?>

            <button class="dashed-add" type="button" id="btnOpenAccountModal">＋ 新規アカウントを追加</button>
          </div>
        </div>
      </section>

      <!-- 店舗管理 -->
      <section class="settings-panel" data-panel="stores">
        <div class="settings-card">
          <div class="settings-card__head">
            <h2 class="settings-card__title">店舗管理</h2>
            <p class="settings-card__desc">店舗の追加・編集・無効化を行います。</p>
          </div>

          <div class="stores-grid">
            <?php foreach ($stores as $store): ?>
              <?php
                $statusClass = !empty($store['is_active']) ? 'pill pill-on' : 'pill pill-off';
                $statusText = !empty($store['is_active']) ? '有効' : '無効';
              ?>
              <div class="store-row">
                <div class="store-row__left">
                  <div class="store-row__name"><?= h($store['store_name'] ?? '') ?></div>
                  <div class="store-row__meta">
                    店舗ID: <?= h($store['store_id'] ?? '') ?>
                    / <?= h($store['store_address'] ?? '') ?>
                    / <?= h($store['store_phone'] ?? '') ?>
                  </div>
                </div>

                <div class="store-row__right">
                  <span class="<?= h($statusClass) ?>"><?= h($statusText) ?></span>

                  <button
                    class="btnx btnx-ghost btn-store-detail"
                    type="button"
                    data-store-id="<?= h($store['store_id'] ?? '') ?>"
                    data-store-name="<?= h($store['store_name'] ?? '') ?>"
                    data-store-address="<?= h($store['store_address'] ?? '') ?>"
                    data-store-phone="<?= h($store['store_phone'] ?? '') ?>"
                    data-store-active="<?= (int)($store['is_active'] ?? 0) ?>"
                  >詳細</button>

                  <button
                    class="btnx btnx-primary btn-store-edit"
                    type="button"
                    data-store-id="<?= h($store['store_id'] ?? '') ?>"
                    data-store-name="<?= h($store['store_name'] ?? '') ?>"
                    data-store-address="<?= h($store['store_address'] ?? '') ?>"
                    data-store-phone="<?= h($store['store_phone'] ?? '') ?>"
                    data-store-active="<?= (int)($store['is_active'] ?? 0) ?>"
                  >編集</button>
                </div>
              </div>
            <?php endforeach; ?>

            <button class="store-add" type="button" id="btnOpenStoreModal">＋ 店舗を追加</button>
          </div>
        </div>
      </section>

      <!-- バックアップ -->
      <section class="settings-panel" data-panel="backup">
        <div class="settings-card">
          <div class="settings-card__head">
            <h2 class="settings-card__title">バックアップ</h2>
            <p class="settings-card__desc">
              レジシステムのデータを保存します。システム更新前やデータ修正前に作成してください。
            </p>
          </div>

          <div class="backup-grid">
            <div class="backup-box">
              <div class="backup-box__title">手動バックアップ</div>
              <div class="backup-box__desc">
                現在の全データをバックアップします。<br>
                保存対象：会計履歴、売上、店舗、アカウント情報など
              </div>

              <form method="post" action="<?= h($BASE) ?>/settings/backup/create" class="backup-form">
                <input type="hidden" name="backup_type" value="MANUAL">

                <button class="btnx btnx-primary" type="submit" id="btnCreateBackup">
                  バックアップを作成する
                </button>
              </form>
            </div>

            <div class="backup-box backup-box--history">
              <div class="backup-box__title">バックアップ履歴</div>
              <div class="backup-box__desc">
                過去に作成したバックアップと復元の履歴を確認できます。
              </div>

              <div class="history-table-wrap">
                <h3 class="settings-subtitle">バックアップ履歴</h3>

                <table class="settings-table settings-table--backup">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>種別</th>
                      <th>バックアップファイル</th>
                      <th>サイズ</th>
                      <th>作成者</th>
                      <th>結果</th>
                      <th>作成日時</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$backupHistories): ?>
                      <tr>
                        <td colspan="7">バックアップ履歴はまだありません。</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($backupHistories as $row): ?>
                        <tr>
                          <td><?= h($row['backup_id'] ?? '') ?></td>
                          <td><?= h(backup_type_label($row['backup_type'] ?? '')) ?></td>
                          <td class="td-file"><?= h($row['file_name'] ?? '') ?></td>
                          <td><?= h(fmt_file_size($row['file_size'] ?? null)) ?></td>
                          <td><?= h($row['created_by_name'] ?? '—') ?></td>
                          <td>
                            <?php if (($row['status'] ?? '') === 'SUCCESS'): ?>
                              <span class="status-success">成功</span>
                            <?php elseif (($row['status'] ?? '') === 'FAILED'): ?>
                              <span class="status-failed">失敗</span>
                            <?php else: ?>
                              <span class="status-waiting"><?= h($row['status'] ?? '不明') ?></span>
                            <?php endif; ?>
                          </td>
                          <td><?= h(fmt_datetime($row['created_at'] ?? null)) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <div class="history-table-wrap">
                <h3 class="settings-subtitle">復元履歴</h3>

                <table class="settings-table settings-table--restore">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>バックアップID</th>
                      <th>実行者</th>
                      <th>理由</th>
                      <th>結果</th>
                      <th>実行日時</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$restoreHistories): ?>
                      <tr>
                        <td colspan="6">復元履歴はまだありません。</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($restoreHistories as $row): ?>
                        <tr>
                          <td><?= h($row['restore_id'] ?? '') ?></td>
                          <td><?= h($row['backup_id'] ?? '') ?></td>
                          <td><?= h($row['executed_by_name'] ?? '—') ?></td>
                          <td><?= h($row['reason'] ?? '—') ?></td>
                          <td>
                            <?php if (($row['status'] ?? '') === 'SUCCESS'): ?>
                              <span class="status-success">成功</span>
                            <?php elseif (($row['status'] ?? '') === 'FAILED'): ?>
                              <span class="status-failed">失敗</span>
                            <?php else: ?>
                              <span class="status-waiting"><?= h($row['status'] ?? '不明') ?></span>
                            <?php endif; ?>
                          </td>
                          <td><?= h(fmt_datetime($row['executed_at'] ?? null)) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- システム情報 -->
      <section class="settings-panel" data-panel="system">
        <div class="settings-card">
          <div class="settings-card__head">
            <h2 class="settings-card__title">システム情報</h2>
            <p class="settings-card__desc">現在のバージョンや稼働状況を確認できます。</p>
          </div>

          <div class="sys-kv">
            <div class="sys-row"><div class="k">バージョン</div><div class="v"><?= h($systemInfo['version'] ?? '—') ?></div></div>
            <div class="sys-row"><div class="k">環境</div><div class="v"><?= h($systemInfo['environment'] ?? '—') ?></div></div>
            <div class="sys-row"><div class="k">最終バックアップ</div><div class="v"><?= h(fmt_datetime($systemInfo['last_backup'] ?? null)) ?></div></div>
            <div class="sys-row"><div class="k">最終復元</div><div class="v"><?= h(fmt_datetime($systemInfo['last_restore'] ?? null)) ?></div></div>
            <div class="sys-row"><div class="k">マスター数</div><div class="v"><?= h((string)($systemInfo['master_count'] ?? 0)) ?></div></div>
            <div class="sys-row"><div class="k">有効スタッフ数</div><div class="v"><?= h((string)($systemInfo['active_staff_count'] ?? 0)) ?></div></div>
            <div class="sys-row"><div class="k">有効店舗数</div><div class="v"><?= h((string)($systemInfo['active_store_count'] ?? 0)) ?></div></div>
            <div class="sys-row"><div class="k">稼働状況</div><div class="v"><span class="pill pill-on"><?= h($systemInfo['health'] ?? '正常') ?></span></div></div>
          </div>
        </div>
      </section>
    </section>
  </main>

  <!-- 店舗追加モーダル -->
  <div class="modal-backdrop" id="storeModal" hidden>
    <div
      class="settings-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="storeModalTitle"
    >
      <div class="settings-modal__head">
        <h2 id="storeModalTitle">店舗を追加</h2>

        <button
          type="button"
          class="modal-close"
          id="btnCloseStoreModal"
          aria-label="閉じる"
        >
          ×
        </button>
      </div>

      <form
        method="post"
        action="<?= h($BASE) ?>/settings/stores/create"
        class="settings-form"
        autocomplete="off"
      >
        <div class="form-row">
          <label for="create_store_id">
            店舗ID
            <span style="font-weight:400; color:#6b7a8a;">
              （任意）
            </span>
          </label>

          <input
            id="create_store_id"
            name="store_id"
            type="text"
            maxlength="2"
            minlength="2"
            pattern="[A-Za-z]{2}"
            placeholder="例：MH（未入力なら自動採番）"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            oninput="
              this.value = this.value
                .toUpperCase()
                .replace(/[^A-Z]/g, '')
                .slice(0, 2);
            "
          >

          <small>
            半角英字2文字で指定できます。
            未入力の場合は、AA、AB、AC……の順で自動採番します。
          </small>
        </div>

        <div class="form-row">
          <label for="store_name">店舗名</label>

          <input
            id="store_name"
            name="store_name"
            type="text"
            maxlength="64"
            autocomplete="organization"
            required
          >
        </div>

        <div class="form-row">
          <label for="store_address">住所</label>

          <input
            id="store_address"
            name="store_address"
            type="text"
            maxlength="128"
            autocomplete="street-address"
            required
          >
        </div>

        <div class="form-row">
          <label for="store_phone">電話番号</label>

          <input
            id="store_phone"
            name="store_phone"
            type="tel"
            maxlength="13"
            placeholder="06-0000-0000"
            autocomplete="tel"
            required
          >
        </div>

        <div class="form-row">
          <label for="store_is_active">状態</label>

          <select
            id="store_is_active"
            name="is_active"
          >
            <option value="1" selected>
              有効
            </option>

            <option value="0">
              無効
            </option>
          </select>

          <small>
            追加する店舗を使用可能な状態にするか選択してください。
          </small>
        </div>

        <div class="settings-form__actions">
          <button
            type="submit"
            class="btnx btnx-primary"
          >
            追加する
          </button>

          <button
            type="button"
            class="btnx btnx-outline"
            id="btnCancelStoreModal"
          >
            キャンセル
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 店舗詳細モーダル -->
  <div class="modal-backdrop" id="storeDetailModal" hidden>
    <div class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="storeDetailTitle">
      <div class="settings-modal__head">
        <h2 id="storeDetailTitle">店舗詳細</h2>
        <button type="button" class="modal-close" id="btnCloseStoreDetailModal" aria-label="閉じる">×</button>
      </div>

      <div class="settings-form">
        <div class="form-row">
          <label>店舗ID</label>
          <input type="text" id="detail_store_id" disabled>
        </div>
        <div class="form-row">
          <label>店舗名</label>
          <input type="text" id="detail_store_name" disabled>
        </div>
        <div class="form-row">
          <label>住所</label>
          <input type="text" id="detail_store_address" disabled>
        </div>
        <div class="form-row">
          <label>電話番号</label>
          <input type="text" id="detail_store_phone" disabled>
        </div>
        <div class="form-row">
          <label>状態</label>
          <input type="text" id="detail_store_status" disabled>
        </div>

        <div class="settings-form__actions">
          <button type="button" class="btnx btnx-outline" id="btnCloseStoreDetailModalBottom">閉じる</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 店舗編集モーダル -->
  <div class="modal-backdrop" id="storeEditModal" hidden>
    <div class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="storeEditTitle">
      <div class="settings-modal__head">
        <h2 id="storeEditTitle">店舗編集</h2>
        <button type="button" class="modal-close" id="btnCloseStoreEditModal" aria-label="閉じる">×</button>
      </div>

      <form method="post" action="<?= h($BASE) ?>/settings/stores/update" class="settings-form">
        <div class="form-row">
          <label>店舗ID</label>
          <input type="text" id="edit_store_id_view" disabled>
          <input type="hidden" id="edit_store_id" name="store_id">
        </div>

        <div class="form-row">
          <label for="edit_store_name">店舗名</label>
          <input type="text" id="edit_store_name" name="store_name" maxlength="64" required>
        </div>

        <div class="form-row">
          <label for="edit_store_address">住所</label>
          <input type="text" id="edit_store_address" name="store_address" maxlength="128" required>
        </div>

        <div class="form-row">
          <label for="edit_store_phone">電話番号</label>
          <input type="text" id="edit_store_phone" name="store_phone" maxlength="13" required>
        </div>

        <div class="form-row">
          <label for="edit_is_active">状態</label>
          <select id="edit_is_active" name="is_active">
            <option value="1">有効</option>
            <option value="0">無効</option>
          </select>
        </div>

        <div class="settings-form__actions">
          <button type="submit" class="btnx btnx-primary">更新する</button>
          <button type="button" class="btnx btnx-outline" id="btnCancelStoreEditModal">キャンセル</button>
        </div>
      </form>
    </div>
  </div>

  <!-- アカウント追加モーダル -->
  <div class="modal-backdrop" id="accountModal" hidden>
    <div
      class="settings-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="accountModalTitle"
    >
      <div class="settings-modal__head">
        <h2 id="accountModalTitle">アカウントを追加</h2>

        <button
          type="button"
          class="modal-close"
          id="btnCloseAccountModal"
          aria-label="閉じる"
        >
          ×
        </button>
      </div>

      <form
        method="post"
        action="<?= h($BASE) ?>/settings/accounts/create"
        class="settings-form"
        autocomplete="off"
      >
        <div class="form-row">
          <label for="create_account_login_id">ログインID</label>

          <input
            id="create_account_login_id"
            name="login_id"
            type="text"
            maxlength="50"
            autocomplete="off"
            autocapitalize="none"
            spellcheck="false"
            required
          >

          <small>英数字・ハイフン・アンダーバーで4〜50文字</small>
        </div>

        <div class="form-row">
          <label for="create_account_name">アカウント名</label>

          <input
            id="create_account_name"
            name="account_name"
            type="text"
            maxlength="100"
            autocomplete="off"
            required
          >
        </div>

        <div class="form-row">
          <label for="create_account_password">パスワード</label>

          <input
            id="create_account_password"
            name="password"
            type="password"
            minlength="8"
            autocomplete="new-password"
            required
          >

          <small>8文字以上で入力してください</small>
        </div>

        <div class="form-row">
          <label for="create_account_store_id">所属店舗</label>

          <select
            id="create_account_store_id"
            name="store_id"
            autocomplete="off"
            required
          >
            <option value="">選択してください</option>

            <?php foreach ($stores as $store): ?>
              <?php if ((int)($store['is_active'] ?? 0) === 1): ?>
                <option value="<?= h($store['store_id']) ?>">
                  <?= h($store['store_id']) ?> / <?= h($store['store_name']) ?>
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label for="create_account_email">メールアドレス</label>

          <input
            id="create_account_email"
            name="email"
            type="email"
            maxlength="255"
            autocomplete="off"
          >
        </div>

        <div class="form-row">
          <label for="create_account_is_active">状態</label>

          <select
            id="create_account_is_active"
            name="is_active"
            autocomplete="off"
          >
            <option value="1" selected>有効</option>
            <option value="0">無効</option>
          </select>
        </div>

        <div class="settings-form__actions">
          <button type="submit" class="btnx btnx-primary">
            追加する
          </button>

          <button
            type="button"
            class="btnx btnx-outline"
            id="btnCancelAccountModal"
          >
            キャンセル
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- アカウント詳細モーダル -->
  <div class="modal-backdrop" id="accountDetailModal" hidden>
    <div class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="accountDetailModalTitle">
      <div class="settings-modal__head">
        <h2 id="accountDetailModalTitle">アカウント詳細</h2>
        <button type="button" class="modal-close" id="btnCloseAccountDetailModal" aria-label="閉じる">×</button>
      </div>

      <div class="settings-form">
        <div class="form-row">
          <label>アカウントID</label>
          <input type="text" id="detail_account_id" disabled>
        </div>

        <div class="form-row">
          <label>ログインID</label>
          <input type="text" id="detail_login_id" disabled>
        </div>

        <div class="form-row">
          <label>アカウント名</label>
          <input type="text" id="detail_account_name" disabled>
        </div>

        <div class="form-row">
          <label>権限</label>
          <input type="text" id="detail_role_type" disabled>
        </div>

        <div class="form-row">
          <label>所属店舗</label>
          <input type="text" id="detail_account_store" disabled>
        </div>

        <div class="form-row">
          <label>メールアドレス</label>
          <input type="text" id="detail_account_email" disabled>
        </div>

        <div class="form-row">
          <label>状態</label>
          <input type="text" id="detail_account_status" disabled>
        </div>

        <div class="form-row">
          <label>最終ログイン</label>
          <input type="text" id="detail_account_last_login" disabled>
        </div>

        <div class="settings-form__actions">
          <button type="button" class="btnx btnx-outline" id="btnCloseAccountDetailModalBottom">閉じる</button>
        </div>
      </div>
    </div>
  </div>

  <!-- アカウント編集モーダル -->
  <div class="modal-backdrop" id="accountEditModal" hidden>
    <div class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="accountEditModalTitle">
      <div class="settings-modal__head">
        <h2 id="accountEditModalTitle">アカウント編集</h2>
        <button type="button" class="modal-close" id="btnCloseAccountEditModal" aria-label="閉じる">×</button>
      </div>

      <form method="post" action="<?= h($BASE) ?>/settings/accounts/update" class="settings-form">
        <input type="hidden" id="edit_account_id" name="account_id">

        <div class="form-row">
          <label>アカウントID</label>
          <input type="text" id="edit_account_id_view" disabled>
        </div>

        <div class="form-row">
          <label for="edit_login_id">ログインID</label>
          <input type="text" id="edit_login_id" name="login_id" maxlength="50" required>
        </div>

        <div class="form-row">
          <label for="edit_account_name">アカウント名</label>
          <input type="text" id="edit_account_name" name="account_name" maxlength="100" required>
        </div>

        <div class="form-row">
          <label for="edit_account_password">パスワード</label>
          <input type="password" id="edit_account_password" name="password" minlength="8">
          <small>変更しない場合は空欄のままにしてください</small>
        </div>

        <div class="form-row">
          <label>権限</label>
          <input type="text" id="edit_role_type_view" value="" disabled>
          <small>権限はこの画面では変更できません。</small>
        </div>

        <div class="form-row">
          <label for="edit_account_store_id">所属店舗</label>
          <select id="edit_account_store_id" name="store_id">
            <option value="">選択してください</option>
            <?php foreach ($stores as $store): ?>
              <?php if ((int)($store['is_active'] ?? 0) === 1): ?>
                <option value="<?= h($store['store_id']) ?>">
                  <?= h($store['store_id']) ?> / <?= h($store['store_name']) ?>
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <small id="edit_account_store_help">スタッフの場合は所属店舗を選択してください。</small>
        </div>

        <div class="form-row">
          <label for="edit_account_email">メールアドレス</label>
          <input type="email" id="edit_account_email" name="email" maxlength="255">
        </div>

        <div class="form-row">
          <label for="edit_account_is_active">状態</label>

          <select id="edit_account_is_active">
            <option value="1">有効</option>
            <option value="0">無効</option>
          </select>

          <input type="hidden" id="edit_account_is_active_hidden" name="is_active" value="1">

          <small id="edit_account_status_help">
            アカウントの有効・無効を設定できます。
          </small>
        </div>

        <div class="settings-form__actions">
          <button type="submit" class="btnx btnx-primary">更新する</button>
          <button type="button" class="btnx btnx-outline" id="btnCancelAccountEditModal">キャンセル</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>