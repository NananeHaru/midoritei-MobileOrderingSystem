<?php

namespace App\Lib;

use InvalidArgumentException;

class OrderImportValidator
{
    /**
     * MOSレスポンス全体を検証
     *
     * @param array $orders
     */
    public function validateOrders(array $orders): void
    {
        if (!is_array($orders) || empty($orders)) {
            throw new InvalidArgumentException('注文データがありません。');
        }

        foreach ($orders as $index => $order) {
            if (!is_array($order)) {
                throw new InvalidArgumentException('注文データの形式が不正です。');
            }

            $this->validateOrder($order, $index + 1);
        }
    }

    /**
     * 単一注文を検証
     */
    public function validateOrder(array $order, int $rowNo = 1): void
    {
        $this->validateRequiredOrderFields($order, $rowNo);
        $this->validateStoreId($order['storeId'], $rowNo);
        $this->validateCustomerId($order['customerId'], $rowNo);
        $this->validateHash($order['hash'], $rowNo);
        $this->validateIsoDateTime($order['entryTime'], "注文{$rowNo}のentryTime");
        $this->validateOrderBillStatus($order['billStatus'], $rowNo);
        $this->validateItems($order['items'], $rowNo);
    }

    private function validateRequiredOrderFields(array $order, int $rowNo): void
    {
        $requiredKeys = ['storeId', 'entryTime', 'customerId', 'hash', 'billStatus', 'items'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $order)) {
                throw new InvalidArgumentException("注文{$rowNo}の{$key}がありません。");
            }
        }

        if (!is_array($order['items'])) {
            throw new InvalidArgumentException("注文{$rowNo}のitemsが配列ではありません。");
        }
    }

    private function validateItems(array $items, int $orderRowNo): void
    {
        $count = count($items);

        if ($count < 1 || $count > 100) {
            throw new InvalidArgumentException("注文{$orderRowNo}のitems件数が不正です。");
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException("注文{$orderRowNo} 明細" . ($index + 1) . 'の形式が不正です。');
            }

            $this->validateItem($item, $orderRowNo, $index + 1);
        }
    }

    private function validateItem(array $item, int $orderRowNo, int $itemRowNo): void
    {
        $requiredKeys = [
            'orderTime',
            'menuName',
            'unitPrice',
            'taxRate',
            'orderQty',
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $item)) {
                throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}の{$key}がありません。");
            }
        }

        $this->validateIsoDateTime($item['orderTime'], "注文{$orderRowNo} 明細{$itemRowNo}のorderTime");

        if (!is_string($item['menuName']) || trim($item['menuName']) === '') {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のmenuNameが不正です。");
        }

        if (!is_numeric($item['unitPrice'])) {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のunitPriceが不正です。");
        }
        $unitPrice = (int)$item['unitPrice'];
        if ($unitPrice < 0 || $unitPrice > 99999999) {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のunitPriceが範囲外です。");
        }

        if (!is_numeric($item['taxRate'])) {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のtaxRateが不正です。");
        }
        $taxRate = (int)$item['taxRate'];
        if ($taxRate < 0 || $taxRate > 100) {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のtaxRateは0〜100で入力してください。");
        }

        if (!is_numeric($item['orderQty'])) {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のorderQtyが不正です。");
        }
        $orderQty = (int)$item['orderQty'];
        if ($orderQty < 1 || $orderQty > 9999) {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のorderQtyは1〜9999で入力してください。");
        }

        // offerQty は任意。あれば検証する
        if (array_key_exists('offerQty', $item) && $item['offerQty'] !== null && $item['offerQty'] !== '') {
            if (!is_numeric($item['offerQty'])) {
                throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のofferQtyが不正です。");
            }

            $offerQty = (int)$item['offerQty'];
            if ($offerQty < 0 || $offerQty > 9999) {
                throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のofferQtyは0〜9999で入力してください。");
            }

            if ($offerQty > $orderQty) {
                throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のofferQtyはorderQty以下である必要があります。");
            }
        }

        if (
            array_key_exists('categoryName', $item) &&
            $item['categoryName'] !== null &&
            !is_string($item['categoryName'])
        ) {
            throw new InvalidArgumentException("注文{$orderRowNo} 明細{$itemRowNo}のcategoryNameが不正です。");
        }
    }

    private function validateStoreId(mixed $storeId, int $rowNo): void
    {
        if (!is_string($storeId)) {
            throw new InvalidArgumentException("注文{$rowNo}のstoreIdが不正です。");
        }

        $storeId = trim($storeId);

        if (!preg_match('/^[A-Z]{2}$|^[0-9A-Za-z_-]{1,10}$/', $storeId)) {
            throw new InvalidArgumentException("注文{$rowNo}のstoreIdが不正です。");
        }
    }

    private function validateCustomerId(mixed $customerId, int $rowNo): void
    {
        if (!is_string($customerId) || !preg_match('/^[0-9]{7}$/', $customerId)) {
            throw new InvalidArgumentException("注文{$rowNo}のcustomerIdが不正です。");
        }
    }

    private function validateHash(mixed $hash, int $rowNo): void
    {
        if (!is_string($hash) || !preg_match('/^[0-9a-f]{8,64}$/', $hash)) {
            throw new InvalidArgumentException("注文{$rowNo}のhashが不正です。");
        }
    }

    private function validateOrderBillStatus(mixed $billStatus, int $rowNo): void
    {
        if (!is_numeric($billStatus)) {
            throw new InvalidArgumentException("注文{$rowNo}のbillStatusが不正です。");
        }

        $billStatus = (int)$billStatus;

        if ($billStatus < 1 || $billStatus > 15) {
            throw new InvalidArgumentException("注文{$rowNo}のbillStatusが範囲外です。");
        }

        if (($billStatus & ~0b1111) !== 0) {
            throw new InvalidArgumentException("注文{$rowNo}のbillStatusが不正です。");
        }
    }

    private function validateIsoDateTime(mixed $value, string $label): void
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
            throw new InvalidArgumentException("{$label}が不正です。");
        }
    }
}