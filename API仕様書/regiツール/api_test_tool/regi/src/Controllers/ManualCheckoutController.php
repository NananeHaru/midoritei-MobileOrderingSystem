<?php

namespace App\Controllers;

use App\Services\BillingService;
use Throwable;

class ManualCheckoutController
{
    private BillingService $billingService;

    public function __construct()
    {
        $this->billingService = new BillingService();
    }

    /**
     * 手動入力画面から送られた明細をセッション保存して会計画面へ
     */
    public function checkout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'POSTでアクセスしてください。';
            return;
        }

        $itemsJson = $_POST['items_json'] ?? '[]';
        $items = json_decode($itemsJson, true);

        if (!is_array($items)) {
            $items = [];
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            $name = trim((string)($item['name'] ?? ''));
            $price = (int)($item['price'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            $taxRate = (int)($item['tax_rate'] ?? 10);
            $categoryName = isset($item['category_name']) && $item['category_name'] !== ''
                ? (string)$item['category_name']
                : '手入力';

            if ($name === '') {
                continue;
            }

            if ($price <= 0 || $qty <= 0) {
                continue;
            }

            $normalizedItems[] = [
                'name' => $name,
                'price' => $price,
                'qty' => $qty,
                'tax_rate' => $taxRate,
                'category_name' => $categoryName,
            ];
        }

        if (empty($normalizedItems)) {
            $_SESSION['flash_error'] = '明細がありません。商品を追加してください。';
            header('Location: /regi/public/customer/select?tab=manual');
            exit;
        }

        $_SESSION['manual_checkout_items'] = $normalizedItems;

        if (!isset($_SESSION['manual_started_at'])) {
            $_SESSION['manual_started_at'] = date('Y-m-d H:i:s');
        }

        unset($_SESSION['discount']);
        unset($_SESSION['discount_amount']);

        header('Location: /regi/public/checkout');
        exit;
    }

    /**
     * 手動会計の会計画面表示
     */
    public function settlement(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $manualItems = $_SESSION['manual_checkout_items'] ?? [];

        if (empty($manualItems)) {
            echo '手動入力データがありません。';
            return;
        }

        $items = [];
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($manualItems as $item) {
            $price = (int)($item['price'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            $taxRate = (int)($item['tax_rate'] ?? 10);

            $lineAmount = $price * $qty;
            $lineTax = (int) floor($lineAmount * $taxRate / 100);

            $items[] = [
                'name' => (string)($item['name'] ?? ''),
                'qty' => $qty,
                'price' => $price,
                'tax_rate' => $taxRate,
                'category_name' => $item['category_name'] ?? '手入力',
            ];

            $subtotal += $lineAmount;
            $taxAmount += $lineTax;
        }

        $discount = $_SESSION['discount'] ?? null;
        $discountAmount = 0;

        if (!empty($discount) && !empty($discount['type'])) {
            if ($discount['type'] === 'percent') {
                $percent = max(0, min(100, (int)($discount['percent'] ?? 0)));
                $discountAmount = (int) floor($subtotal * ($percent / 100));
            } elseif ($discount['type'] === 'amount') {
                $amount = max(0, (int)($discount['amount'] ?? 0));
                $discountAmount = min($subtotal, $amount);
            }
        }

        $_SESSION['discount_amount'] = $discountAmount;

        $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount);

        $isManualCheckout = true;
        $manualStartedAt = $_SESSION['manual_started_at'] ?? null;

        require dirname(__DIR__) . '/Views/checkout/settlement.php';
    }

    /**
     * 手動会計の確定
     */
    public function execute(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo 'POSTでアクセスしてください。';
                return;
            }

            $manualItems = $_SESSION['manual_checkout_items'] ?? [];

            if (empty($manualItems)) {
                throw new \RuntimeException('手動入力データがありません。');
            }

            $details = [];

            foreach ($manualItems as $item) {
                $qty = (int)($item['qty'] ?? 0);
                $unitPrice = (int)($item['price'] ?? 0);
                $menuName = trim((string)($item['name'] ?? ''));
                $categoryName = (string)($item['category_name'] ?? '手入力');
                $taxRate = (int)($item['tax_rate'] ?? 10);

                if ($menuName === '' || $qty <= 0 || $unitPrice <= 0) {
                    continue;
                }

                $details[] = [
                    'menu_name' => $menuName,
                    'category_name' => $categoryName,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                ];
            }

            if (empty($details)) {
                throw new \RuntimeException('会計対象の明細がありません。');
            }

            $payMethod = strtoupper(trim((string)($_POST['pay_method'] ?? '')));
            $receivedAmount = ($_POST['received_amount'] ?? '') !== ''
                ? (int)$_POST['received_amount']
                : null;

            $provider = ($_POST['provider'] ?? '') !== ''
                ? trim((string)$_POST['provider'])
                : null;

            $discountAmount = (int)($_SESSION['discount_amount'] ?? $_POST['discount_amount'] ?? 0);

            $billingInput = [
                'is_manual' => true,
                'store_id' => $_SESSION['store_id'] ?? 'AA',
                'discount_amount' => $discountAmount,
                'pay_method' => $payMethod,
                'received_amount' => $receivedAmount,
                'provider' => $provider,
                'details' => $details,
            ];

            $result = $this->billingService->checkout($billingInput);

            $_SESSION['last_checkout_result'] = $result;

            $this->clearManualCheckoutSession(false);

            header('Location: /regi/public/manual/complete');
            exit;
        } catch (Throwable $e) {
            http_response_code(400);
            echo '手動会計の確定に失敗しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * 完了画面
     */
    public function complete(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $result = $_SESSION['last_checkout_result'] ?? null;
        require dirname(__DIR__) . '/Views/checkout/complete.php';
    }

    /**
     * 完了後終了
     */
    public function finish(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['last_checkout_result']);
        $this->clearManualCheckoutSession();

        header('Location: /regi/public/customer/select');
        exit;
    }

    /**
     * 戻る
     * 手入力会計だけは内容を保持したまま会計入力へ戻す
     */
    public function back(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        header('Location: /regi/public/customer/select?tab=manual');
        exit;
    }

    /**
     * 手動会計関連のセッションをクリア
     */
    private function clearManualCheckoutSession(bool $withResult = true): void
    {
        unset($_SESSION['manual_checkout_items']);
        unset($_SESSION['manual_started_at']);
        unset($_SESSION['discount']);
        unset($_SESSION['discount_amount']);

        if ($withResult) {
            unset($_SESSION['last_checkout_result']);
        }
    }
}