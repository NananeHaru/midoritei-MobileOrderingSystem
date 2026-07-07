<?php
declare(strict_types=1);

/*
 * 独自例外を先に読み込む
 */
require_once dirname(__DIR__)
    . '/src/Exceptions/FatalSystemException.php';

/*
 * グローバルエラーハンドラを読み込む
 */
require_once dirname(__DIR__)
    . '/src/Lib/GlobalErrorHandler.php';

use App\Lib\GlobalErrorHandler;

/*
 * DB接続やController実行よりも前に登録する
 */
GlobalErrorHandler::register();


/**
 * regi/public/index.php
 * XAMPP(Apache) 前提の軽量フロントコントローラ + 動的ルーティング対応版
 *
 * 使い方:
 * - public/.htaccess で全リクエストをこの index.php に集約
 * - ルート定義は src/Routes/web.php で行う（['GET','/history/{billId}','HistoryController@detail'] 形式）
 */

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// 0) 環境設定（開発中は表示、本番はログ）
// =====================================================
ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Tokyo');

// =====================================================
// 1) パス定義
// =====================================================
$ROOT = dirname(__DIR__);   // regi/
$PUBLIC_DIR = __DIR__;      // regi/public/

// このアプリが置かれているURLの「ベースパス」
// 例: http://localhost/regi/public/... の場合は "/regi/public"
$BASE_PATH = '/regi/public';

// =====================================================
// 2) オートロード（composerがあるなら使用）
// =====================================================
$autoload = $ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(function (string $class) use ($ROOT) {
        $prefix = 'App\\';
        $baseDir = $ROOT . '/src/';

        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// =====================================================
// 3) 便利関数
// =====================================================
/** 安全な文字列エスケープ（Viewで使う用） */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSONレスポンス */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** リダイレクト（BASE_PATHを考慮） */
function redirect(string $path, string $basePath): void
{
    $to = rtrim($basePath, '/') . $path;
    header('Location: ' . $to);
    exit;
}

/** 生のリクエストボディ(JSON等) */
function read_raw_body(): string
{
    return file_get_contents('php://input') ?: '';
}

// =====================================================
// 4) リクエスト情報の取得
// =====================================================
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// クエリを除いたパスだけにする
$path = parse_url($uri, PHP_URL_PATH) ?? '/';

// BASE_PATH を取り除いて、アプリ内の相対パスにする
if ($BASE_PATH !== '' && str_starts_with($path, $BASE_PATH)) {
    $path = substr($path, strlen($BASE_PATH));
}
$path = $path === '' ? '/' : $path;

// /index.php を / と同じにする
if ($path === '/index.php') {
    $path = '/';
}

// POSTで _method=PUT 等の擬似メソッドに対応したい場合（任意）
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper((string)$_POST['_method']);
    if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
        $method = $override;
    }
}

// =====================================================
// 4.5) 認証ガード
// =====================================================
// ログイン済み判定
$isLoggedIn = !empty($_SESSION['account']);

// 未ログインでもアクセスを許可するルート
$publicRoutes = [
    'GET:/login',
    'POST:/login',
];

// ログイン済みでログイン画面に来たらホームへ
if ($isLoggedIn && $path === '/login' && $method === 'GET') {
    redirect('/home', $BASE_PATH);
}

// 未ログインで公開ルート以外に来たらログイン画面へ
$routeKey = $method . ':' . $path;
if (!$isLoggedIn && !in_array($routeKey, $publicRoutes, true)) {
    redirect('/login', $BASE_PATH);
}

// =====================================================
// 5) ルート定義の読み込み
// =====================================================
// 期待する形式: [
//   ['GET','/login','AuthController@showLogin'],
//   ['GET','/history/{billId}','HistoryController@detail'],
//   ...
// ]
$routesFile = $ROOT . '/src/Routes/web.php';
if (!file_exists($routesFile)) {
    http_response_code(500);
    echo "Routes file not found: " . h($routesFile);
    exit;
}
$routes = require $routesFile;

if (!is_array($routes)) {
    http_response_code(500);
    echo 'Routes file must return array.';
    exit;
}

// =====================================================
// 6) ルーティング（動的ルート対応）
// =====================================================
/**
 * ルートパスの例:
 *   /history/{billId}
 *   /history/{billId}/reprint/{type}
 *
 * これを正規表現に変換し、パラメータ名も抜き出す
 */
function compile_route(string $routePath): array
{
    // パラメータ名を抽出
    preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', $routePath, $m);
    $paramNames = $m[1] ?? [];

    // {xxx} を "([^/]+)" に置換して正規表現化
    $pattern = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';

    return [$pattern, $paramNames];
}

// 404のデフォルト
$matched = false;

foreach ($routes as $route) {
    if (!is_array($route) || count($route) < 3) {
        continue;
    }

    [$routeMethod, $routePath, $handler] = $route;

    $routeMethod = strtoupper((string)$routeMethod);
    if ($routeMethod !== $method) {
        continue;
    }

    $routePath = (string)$routePath;
    [$pattern, $paramNames] = compile_route($routePath);

    if (!preg_match($pattern, $path, $matches)) {
        continue;
    }

    // ここまで来たらマッチ
    $matched = true;

    // $matches[0]は全体一致なので捨てる
    array_shift($matches);

    // パラメータを連想配列化
    $params = [];
    foreach ($matches as $i => $value) {
        $name = $paramNames[$i] ?? (string)$i;
        $params[$name] = $value;
    }

    // =====================================================
    // 7) Controller呼び出し
    // =====================================================
    if (!is_string($handler) || !str_contains($handler, '@')) {
        http_response_code(500);
        echo 'Invalid handler definition.';
        exit;
    }

    [$class, $action] = explode('@', $handler, 2);
    $class = trim($class);
    $action = trim($action);

    $fullClass = "App\\Controllers\\{$class}";
    $controllerFile = $ROOT . "/src/Controllers/{$class}.php";

    // composerのautoloadが無い場合に備えてrequire
    if (!class_exists($fullClass)) {
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
        }
    }

    if (!class_exists($fullClass)) {
        http_response_code(500);
        echo 'Controller not found: ' . h($fullClass);
        exit;
    }

    $controller = new $fullClass();

    if (!method_exists($controller, $action)) {
        http_response_code(500);
        echo 'Action not found: ' . h("{$class}@{$action}");
        exit;
    }

    /**
     * Controllerに渡す引数
     * - ルートパラメータを定義順で渡す
     */
    $args = array_values($params);

    $controller->$action(...$args);
    exit;
}

// =====================================================
// 8) 404
// =====================================================
if (!$matched) {
    http_response_code(404);
    echo '404 Not Found: ' . h($method . ' ' . $path);
    exit;
}