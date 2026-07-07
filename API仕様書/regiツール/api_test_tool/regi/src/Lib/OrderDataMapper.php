<?php

namespace App\Lib;

class OrderDataMapper
{
    public function mapOrders(array $orders): array
    {
        $mappedOrders = [];

        foreach ($orders as $order) {
            $mappedOrders[] = $this->mapOrder($order);
        }

        return $mappedOrders;
    }

    /**
     * 単一注文をレジ内部形式へ変換
     */
    public function mapOrder(array $order): array
    {
        return [
            'store_id'    => (string)($order['storeId'] ?? ''),
            'entry_time'  => (string)($order['entryTime'] ?? ''),
            'customer_id' => (string)($order['customerId'] ?? ''),
            'hash'        => (string)($order['hash'] ?? ''),
            'bill_status' => $this->toInt($order['billStatus'] ?? 0),
            'details'     => $this->mapItems($order['items'] ?? []),
        ];
    }

    /**
     * 明細一覧をレジ内部形式へ変換
     */
    public function mapItems(array $items): array
    {
        $mappedItems = [];

        foreach ($items as $item) {
            $mappedItems[] = $this->mapItem($item);
        }

        return $mappedItems;
    }

    /**
     * 単一明細をレジ内部形式へ変換
     *
     * 今の新設計では qty を会計対象数量として扱う。
     * MOSの offerQty があれば優先し、なければ orderQty を使う。
     */
    public function mapItem(array $item): array
    {
        $orderQty = $this->toInt($item['orderQty'] ?? 0);
        $offerQty = array_key_exists('offerQty', $item)
            ? $this->toInt($item['offerQty'])
            : $orderQty;

        $unitPrice = $this->toInt($item['unitPrice'] ?? 0);

        return [
            'menu_name'     => (string)($item['menuName'] ?? ''),
            'category_name' => isset($item['categoryName']) && $item['categoryName'] !== ''
                ? (string)$item['categoryName']
                : null,
            'unit_price'    => $unitPrice,
            'tax_rate'      => $this->toInt($item['taxRate'] ?? 0),
            'qty'           => $offerQty,
        ];
    }

    /**
     * 単一注文を会計入力用へ整形
     *
     * BillingValidator / BillingCalculator / BillingService に渡しやすい形
     */
    public function mapOrderToBillingInput(array $order, array $overrides = []): array
    {
        $mapped = $this->mapOrder($order);

        $result = [
            'is_manual'       => false,
            'customer_id'     => $mapped['customer_id'],
            'store_id'        => $mapped['store_id'],
            'entry_time'      => $mapped['entry_time'],
            'hash'            => $mapped['hash'],
            'bill_status'     => $mapped['bill_status'],
            'discount_amount' => $this->toInt($overrides['discount_amount'] ?? 0),
            'pay_method'      => (string)($overrides['pay_method'] ?? ''),
            'received_amount' => array_key_exists('received_amount', $overrides)
                ? $this->toNullableInt($overrides['received_amount'])
                : null,
            'provider'        => array_key_exists('provider', $overrides) && $overrides['provider'] !== ''
                ? (string)$overrides['provider']
                : null,
            'details'         => $mapped['details'],
        ];

        if (!empty($overrides['order_header_ids']) && is_array($overrides['order_header_ids'])) {
            $result['order_header_ids'] = array_values(
                array_unique(array_map('intval', $overrides['order_header_ids']))
            );
        }

        return $result;
    }

    /**
     * 複数注文を1つの会計入力へまとめる
     *
     * 同一 customer_id / store_id 前提の集約向け
     */
    public function mergeOrdersToBillingInput(array $orders, array $overrides = []): array
    {
        $mappedOrders = $this->mapOrders($orders);

        if (empty($mappedOrders)) {
            $result = [
                'is_manual'       => false,
                'customer_id'     => '',
                'store_id'        => '',
                'entry_time'      => '',
                'hash'            => '',
                'bill_status'     => 0,
                'discount_amount' => $this->toInt($overrides['discount_amount'] ?? 0),
                'pay_method'      => (string)($overrides['pay_method'] ?? ''),
                'received_amount' => array_key_exists('received_amount', $overrides)
                    ? $this->toNullableInt($overrides['received_amount'])
                    : null,
                'provider'        => array_key_exists('provider', $overrides) && $overrides['provider'] !== ''
                    ? (string)$overrides['provider']
                    : null,
                'details'         => [],
            ];

            if (!empty($overrides['order_header_ids']) && is_array($overrides['order_header_ids'])) {
                $result['order_header_ids'] = array_values(
                    array_unique(array_map('intval', $overrides['order_header_ids']))
                );
            }

            return $result;
        }

        $first = $mappedOrders[0];
        $allDetails = [];

        foreach ($mappedOrders as $mappedOrder) {
            foreach ($mappedOrder['details'] as $detail) {
                $allDetails[] = $detail;
            }
        }

        $result = [
            'is_manual'       => false,
            'customer_id'     => $first['customer_id'],
            'store_id'        => $first['store_id'],
            'entry_time'      => $first['entry_time'],
            'hash'            => $first['hash'],
            'bill_status'     => $first['bill_status'],
            'discount_amount' => $this->toInt($overrides['discount_amount'] ?? 0),
            'pay_method'      => (string)($overrides['pay_method'] ?? ''),
            'received_amount' => array_key_exists('received_amount', $overrides)
                ? $this->toNullableInt($overrides['received_amount'])
                : null,
            'provider'        => array_key_exists('provider', $overrides) && $overrides['provider'] !== ''
                ? (string)$overrides['provider']
                : null,
            'details'         => $allDetails,
        ];

        if (!empty($overrides['order_header_ids']) && is_array($overrides['order_header_ids'])) {
            $result['order_header_ids'] = array_values(
                array_unique(array_map('intval', $overrides['order_header_ids']))
            );
        }

        return $result;
    }

    private function toInt(mixed $value): int
    {
        return (int)$value;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }
}