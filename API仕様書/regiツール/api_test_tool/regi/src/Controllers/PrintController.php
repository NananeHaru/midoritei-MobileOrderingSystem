<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StoreModel;
use App\Repositories\BillPaymentRepository;

final class PrintController
{
    private BillPaymentRepository $billPaymentRepository;
    private StoreModel $storeModel;

    public function __construct()
    {
        $this->billPaymentRepository = new BillPaymentRepository();
        $this->storeModel = new StoreModel();
    }

    /**
     * レシートを表示する。
     */
    public function receipt(): void
    {
        $this->startSession();

        /*
         * 人数分割・金額分割の1支払い完了画面では、
         * 支払いごとの商品明細が特定できないため、
         * 途中支払いのレシート発行を禁止する。
         */
        if ($this->isReceiptBlockedForCurrentSplitPayment()) {
            echo '人数分割・金額分割の途中支払いでは、'
                . 'レシートは発行できません。領収書を発行してください。';
            return;
        }

        $data = $this->resolvePrintData();

        if (empty($data)) {
            echo '印刷対象データがありません。';
            return;
        }

        require dirname(__DIR__) . '/Views/print/receipt.php';
    }

    /**
     * 領収書を表示する。
     */
    public function invoice(): void
    {
        $this->startSession();

        $data = $this->resolvePrintData();

        if (empty($data)) {
            echo '印刷対象データがありません。';
            return;
        }

        require dirname(__DIR__) . '/Views/print/invoice.php';
    }

    /**
     * セッションを開始する。
     */
    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * レシート・領収書へ渡す印刷データを取得する。
     */
    private function resolvePrintData(): ?array
    {
        /*
         * 優先順位
         *
         * 1. last_print_result
         *    通常会計や分割会計の支払い直後に作成された印刷データ
         *
         * 2. last_checkout_result
         *    精算完了画面から印刷するときのデータ
         */
        $data = $_SESSION['last_print_result']
            ?? $_SESSION['last_checkout_result']
            ?? null;

        if (!is_array($data) || empty($data)) {
            return null;
        }

        $requestBillId = isset($_GET['bill_id'])
            ? (int)$_GET['bill_id']
            : null;

        $sessionBillId = (int)($data['bill']['bill_id'] ?? 0);

        /*
         * URLでbill_idが指定されており、
         * セッション内の会計番号と異なる場合は印刷させない。
         */
        if (
            $requestBillId !== null
            && $requestBillId > 0
            && $sessionBillId > 0
            && $requestBillId !== $sessionBillId
        ) {
            return null;
        }

        $billId = $requestBillId ?: $sessionBillId;

        /*
         * 旧形式ではpaymentが単体で入っている場合があるため、
         * payments配列へ変換する。
         */
        if (
            empty($data['payments'])
            && !empty($data['payment'])
            && is_array($data['payment'])
        ) {
            $data['payments'] = [$data['payment']];
        }

        /*
         * 支払い情報がセッションにない場合は、
         * BILL_PAYMENTテーブルから再取得する。
         */
        if ($billId > 0 && empty($data['payments'])) {
            $pdo = require dirname(__DIR__) . '/Database/db.php';

            $data['payments'] = $this->billPaymentRepository
                ->findByBillId($pdo, $billId);
        }

        /*
         * 店舗IDを特定し、storesテーブルから店舗情報を取得する。
         */
        $storeId = $this->resolveStoreId($data);

        if ($storeId !== '') {
            $dbStore = $this->storeModel->findByStoreId($storeId);

            if ($dbStore !== null) {
                /*
                 * すでに印刷データ内にstore_tnoなどが入っている場合は
                 * 消さないように既存データとDBデータを結合する。
                 */
                $existingStore = isset($data['store'])
                    && is_array($data['store'])
                    ? $data['store']
                    : [];

                $data['store'] = array_merge(
                    $existingStore,
                    $dbStore
                );
            }
        }

        /*
         * DBから取得できなかった場合も含め、
         * Viewが扱いやすい形式へ店舗情報を整える。
         */
        $data['store'] = $this->resolveStoreData($data);

        return $data;
    }

    /**
     * 印刷対象の店舗IDを取得する。
     *
     * 優先順位:
     * 1. 印刷データ内のstore
     * 2. bill内のstore_id
     * 3. セッション内の店舗情報
     */
    private function resolveStoreId(array $data): string
    {
        $store = isset($data['store']) && is_array($data['store'])
            ? $data['store']
            : [];

        $bill = isset($data['bill']) && is_array($data['bill'])
            ? $data['bill']
            : [];

        $sessionStore = isset($_SESSION['store'])
            && is_array($_SESSION['store'])
            ? $_SESSION['store']
            : [];

        $sessionUser = isset($_SESSION['user'])
            && is_array($_SESSION['user'])
            ? $_SESSION['user']
            : [];

        $sessionAccount = isset($_SESSION['account'])
            && is_array($_SESSION['account'])
            ? $_SESSION['account']
            : [];

        $storeId = $store['store_id']
            ?? $store['storeId']
            ?? $bill['store_id']
            ?? $bill['storeId']
            ?? $sessionStore['store_id']
            ?? $sessionStore['storeId']
            ?? $sessionUser['store_id']
            ?? $sessionUser['storeId']
            ?? $sessionAccount['store_id']
            ?? $sessionAccount['storeId']
            ?? $_SESSION['store_id']
            ?? '';

        return trim((string)$storeId);
    }

    /**
     * Viewへ渡す店舗情報を統一する。
     *
     * storesテーブルから取得した値を優先しつつ、
     * 既存の印刷データやセッションもフォールバックとして使用する。
     */
    private function resolveStoreData(array $data): array
    {
        $store = isset($data['store']) && is_array($data['store'])
            ? $data['store']
            : [];

        $bill = isset($data['bill']) && is_array($data['bill'])
            ? $data['bill']
            : [];

        $sessionStore = isset($_SESSION['store'])
            && is_array($_SESSION['store'])
            ? $_SESSION['store']
            : [];

        $sessionUser = isset($_SESSION['user'])
            && is_array($_SESSION['user'])
            ? $_SESSION['user']
            : [];

        $sessionAccount = isset($_SESSION['account'])
            && is_array($_SESSION['account'])
            ? $_SESSION['account']
            : [];

        return [
            'store_id' => $store['store_id']
                ?? $store['storeId']
                ?? $bill['store_id']
                ?? $bill['storeId']
                ?? $sessionStore['store_id']
                ?? $sessionStore['storeId']
                ?? $sessionUser['store_id']
                ?? $sessionUser['storeId']
                ?? $sessionAccount['store_id']
                ?? $sessionAccount['storeId']
                ?? $_SESSION['store_id']
                ?? '',

            'store_name' => $store['store_name']
                ?? $store['storeName']
                ?? $bill['store_name']
                ?? $bill['storeName']
                ?? $sessionStore['store_name']
                ?? $sessionStore['storeName']
                ?? $sessionUser['store_name']
                ?? $sessionUser['storeName']
                ?? $sessionAccount['store_name']
                ?? $sessionAccount['storeName']
                ?? $_SESSION['store_name']
                ?? '店舗名未設定',

            'store_address' => $store['store_address']
                ?? $store['storeAddress']
                ?? $bill['store_address']
                ?? $bill['storeAddress']
                ?? $sessionStore['store_address']
                ?? $sessionStore['storeAddress']
                ?? $sessionUser['store_address']
                ?? $sessionUser['storeAddress']
                ?? $sessionAccount['store_address']
                ?? $sessionAccount['storeAddress']
                ?? $_SESSION['store_address']
                ?? '',

            'store_phone' => $store['store_phone']
                ?? $store['storePhone']
                ?? $bill['store_phone']
                ?? $bill['storePhone']
                ?? $sessionStore['store_phone']
                ?? $sessionStore['storePhone']
                ?? $sessionUser['store_phone']
                ?? $sessionUser['storePhone']
                ?? $sessionAccount['store_phone']
                ?? $sessionAccount['storePhone']
                ?? $_SESSION['store_phone']
                ?? '',

            'store_tno' => $store['store_tno']
                ?? $store['storeTno']
                ?? $bill['store_tno']
                ?? $bill['storeTno']
                ?? $sessionStore['store_tno']
                ?? $sessionStore['storeTno']
                ?? $sessionUser['store_tno']
                ?? $sessionUser['storeTno']
                ?? $sessionAccount['store_tno']
                ?? $sessionAccount['storeTno']
                ?? $_SESSION['store_tno']
                ?? '',
        ];
    }

    /**
     * 現在の分割支払いでレシート発行を禁止するか判定する。
     */
    private function isReceiptBlockedForCurrentSplitPayment(): bool
    {
        $splitResult = $_SESSION['last_split_payment_result'] ?? null;

        if (!is_array($splitResult) || empty($splitResult)) {
            return false;
        }

        $splitType = strtoupper(
            (string)($splitResult['split_type'] ?? '')
        );

        $printType = strtoupper(
            (string)($splitResult['print_type'] ?? '')
        );

        /*
         * 人数分割・金額分割の途中支払いでは、
         * 支払いごとの商品明細が特定できないため領収書のみとする。
         */
        if (in_array($splitType, ['PERSON', 'AMOUNT'], true)) {
            return true;
        }

        /*
         * 明示的に領収書のみと指定されている場合も禁止する。
         */
        if ($printType === 'INVOICE_ONLY') {
            return true;
        }

        return false;
    }
}