<?php

namespace App\Controllers;

class DiscountController
{
    public function apply(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $type = trim((string)($_POST['type'] ?? ''));
        $percent = (int)($_POST['percent'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));

        if ($type === 'percent') {
            $percent = max(0, min(100, $percent));

            if ($percent <= 0) {
                $_SESSION['flash_error'] = '割引率は1〜100の範囲で入力してください。';
                header('Location: /regi/public/checkout');
                exit;
            }

            $_SESSION['discount'] = [
                'type'    => 'percent',
                'percent' => $percent,
                'amount'  => 0,
                'note'    => $note,
            ];

            unset($_SESSION['discount_amount']);

            header('Location: /regi/public/checkout');
            exit;
        }

        if ($type === 'amount') {
            $amount = max(0, $amount);

            if ($amount <= 0) {
                $_SESSION['flash_error'] = '割引額は1円以上で入力してください。';
                header('Location: /regi/public/checkout');
                exit;
            }

            $_SESSION['discount'] = [
                'type'    => 'amount',
                'percent' => 0,
                'amount'  => $amount,
                'note'    => $note,
            ];

            unset($_SESSION['discount_amount']);

            header('Location: /regi/public/checkout');
            exit;
        }

        $_SESSION['flash_error'] = '割引種別が不正です。';
        header('Location: /regi/public/checkout');
        exit;
    }

    public function clear(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['discount']);
        unset($_SESSION['discount_amount']);

        header('Location: /regi/public/checkout');
        exit;
    }
}