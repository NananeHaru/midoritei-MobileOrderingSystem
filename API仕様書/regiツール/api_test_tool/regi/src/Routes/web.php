<?php
return [

  // トップ
  ['GET',  '/',         'AuthController@showLogin'],
  ['GET',  '/login',    'AuthController@showLogin'],
  ['POST', '/login',    'AuthController@login'],
  ['POST', '/logout',   'AuthController@logout'],

  // SCR-02 ホーム
  ['GET',  '/home',                     'HomeController@index'],

  // 会計履歴
  ['GET',  '/history',                  'HistoryController@index'],
  ['GET',  '/history/receipt',          'HistoryController@receipt'],
  ['GET',  '/history/invoice',          'HistoryController@invoice'],
  ['GET',  '/history/detail',           'HistoryController@detail'],

  // SCR-03 客番号入力
  ['GET',  '/customer/select',          'CustomerController@showSelect'],
  ['POST', '/customer/select',          'CustomerController@select'],
  ['POST', '/customer/add',             'CustomerController@add'],

  ['POST', '/customer/search-orders', 'CustomerController@searchOrders'],
  ['POST', '/customer/select-order', 'CustomerController@selectOrder'],

  // 手入力会計
  ['POST', '/manual/checkout',          'ManualCheckoutController@checkout'],
  ['GET',  '/manual/settlement',        'ManualCheckoutController@settlement'],
  ['POST', '/manual/execute',           'ManualCheckoutController@execute'],
  ['GET',  '/manual/complete',          'ManualCheckoutController@complete'],
  ['POST', '/manual/finish',            'ManualCheckoutController@finish'],
  ['POST', '/manual/back',              'ManualCheckoutController@back'],

  // SCR-04 注文一覧（会計）
  ['GET',  '/checkout',                 'CheckoutController@show'],
  ['GET', '/settlement', 'CheckoutController@settlement'],
  ['GET', '/checkout/order-back', 'CheckoutController@orderBack'],

  // SCR-05 割引・会計
  ['POST', '/checkout/fetch',           'CheckoutController@fetchOrders'],
  ['GET',  '/checkout/settlement',      'CheckoutController@settlement'],
  ['POST', '/checkout/execute',         'CheckoutController@execute'],
  ['GET',  '/checkout/complete',        'CheckoutController@complete'],
  ['POST', '/checkout/finish',          'CheckoutController@finish'],
  ['GET',  '/checkout/back',            'CheckoutController@back'],
  ['POST', '/checkout/item-split/execute', 'CheckoutController@executeItemSplit'],

  // 割引
  ['POST', '/discount/apply',           'DiscountController@apply'],
  ['POST', '/discount/clear',           'DiscountController@clear'],

  // SCR-08 会計分割
  ['POST', '/checkout/split-mode',      'CheckoutController@setSplitMode'],
  ['POST', '/checkout/split/add',       'CheckoutController@addSplitPayment'],
  ['POST', '/checkout/split/remove',    'CheckoutController@removeSplitPayment'],
  ['GET', '/checkout/split/payment-complete', 'CheckoutController@splitPaymentComplete'],

  // API会計確定
  ['POST', '/billing/checkout',         'BillingController@checkout'],

  // 印刷
  ['GET',  '/receipt',                  'PrintController@receipt'],
  ['GET',  '/invoice',                  'PrintController@invoice'],

  // SCR-XX レジ締め（Close）
  ['GET',  '/close',                    'ClosingController@show'],
  ['POST', '/close/check',              'ClosingController@check'],
  ['POST', '/close/store',              'ClosingController@store'],

  // SCR-XX 売上レポート
  ['GET',  '/sales/report',             'SalesReportController@show'],
  ['GET',  '/sales/report/data',        'SalesReportController@data'],
  ['GET',  '/sales/report/export',      'SalesReportController@export'],

  /*
  // SCR-XX システム設定（マスター）
  ['GET',  '/settings',                 'SettingsController@index'],
  ['GET',  '/settings/accounts',        'SettingsController@accounts'],
  ['GET',  '/settings/stores',          'SettingsController@stores'],
  ['GET',  '/settings/backup',          'SettingsController@backup'],
  ['GET',  '/settings/system',          'SettingsController@systemInfo'],

  // アカウント操作
  ['POST', '/settings/accounts/store',  'SettingsController@storeAccount'],
  ['POST', '/settings/accounts/update', 'SettingsController@updateAccount'],
  ['POST', '/settings/accounts/delete', 'SettingsController@deleteAccount'],

  // 店舗操作
  ['POST', '/settings/stores/store',    'SettingsController@storeStore'],
  ['POST', '/settings/stores/update',   'SettingsController@updateStore'],
  ['POST', '/settings/stores/delete',   'SettingsController@deleteStore'],

  // バックアップ操作
  ['POST', '/settings/backup/run',      'SettingsController@runBackup'],
*/
  ['GET', '/settings/master', 'SettingsController@master'],
  ['GET', '/settings/master/data', 'SettingsController@masterData'],
  ['GET', '/settings/accounts', 'SettingsController@accounts'],
  ['GET', '/settings/stores', 'SettingsController@stores'],
  ['GET', '/settings/backup-histories', 'SettingsController@backupHistories'],
  ['GET', '/settings/restore-histories', 'SettingsController@restoreHistories'],
  ['GET', '/settings/system-info', 'SettingsController@systemInfo'],
  ['GET', '/settings/current-billing-store', 'SettingsController@currentBillingStore'],
  ['POST', '/settings/stores/create', 'SettingsController@createStore'],
  ['GET', '/settings/stores/detail', 'SettingsController@storeDetail'],
  ['POST', '/settings/stores/update', 'SettingsController@updateStore'],
  ['POST', '/settings/accounts/create', 'SettingsController@createAccount'],
  ['GET', '/settings/accounts/detail', 'SettingsController@accountDetail'],
  ['POST', '/settings/accounts/update', 'SettingsController@updateAccount'],
  ['GET',  '/settings',                 'SettingsController@index'],
  ['POST', '/settings/backup/create',   'SettingsController@createBackup'],

  ['GET', '/error/404', 'ErrorController@notFound'],
  ['GET', '/error/403', 'ErrorController@forbidden'],
  ['GET', '/error/system', 'ErrorController@system'],
  ['GET', '/error/mos-unavailable', 'ErrorController@mosUnavailable'],
  ['GET', '/error/session-expired', 'ErrorController@sessionExpired'],
];