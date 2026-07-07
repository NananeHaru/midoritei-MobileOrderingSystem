<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;

final class HomeController
{
    public function index(): void
    {
        Auth::requireLogin();

        $account = Auth::user();
        require dirname(__DIR__) . '/Views/home/index.php';
    }
}
