-- 1. 顧客採番テーブル
CREATE TABLE IF NOT EXISTS 顧客採番テーブル (
    current_number INT NOT NULL
);
INSERT INTO 顧客採番テーブル (current_number)
SELECT 0 WHERE NOT EXISTS (SELECT 1 FROM 顧客採番テーブル);

-- 2. 顧客テーブル
CREATE TABLE IF NOT EXISTS 顧客テーブル (
    customer_id CHAR(7) PRIMARY KEY,
    store_id CHAR(2),
    seat_id VARCHAR(20),
    course_id CHAR(7),
    entry_time TIMESTAMP,
    checkout_status INT,
    total_amount INT
);

-- 3. 商品マスタテーブル（★今回足りなかったのはこれ！）
CREATE TABLE IF NOT EXISTS 商品マスタテーブル (
    product_id CHAR(6) PRIMARY KEY,
    category_id VARCHAR(20),
    product_name VARCHAR(100),
    price INT,
    course_id CHAR(7),
    sales_status INT
);

-- 4. テスト用のメニューデータを何件か入れておく（テーブルが空のときだけ実行）
INSERT INTO 商品マスタテーブル (product_id, category_id, product_name, price, course_id, sales_status)
SELECT 'PR0001', 'CT01', '生ビール（中）', 450, NULL, 0 WHERE NOT EXISTS (SELECT 1 FROM 商品マスタテーブル WHERE product_id = 'PR0001');
INSERT INTO 商品マスタテーブル (product_id, category_id, product_name, price, course_id, sales_status)
SELECT 'PR0002', 'CT01', 'レモンサワー', 380, NULL, 0 WHERE NOT EXISTS (SELECT 1 FROM 商品マスタテーブル WHERE product_id = 'PR0002');
INSERT INTO 商品マスタテーブル (product_id, category_id, product_name, price, course_id, sales_status)
SELECT 'PR0003', 'CT01', 'ウーロン茶', 250, NULL, 0 WHERE NOT EXISTS (SELECT 1 FROM 商品マスタテーブル WHERE product_id = 'PR0003');
INSERT INTO 商品マスタテーブル (product_id, category_id, product_name, price, course_id, sales_status)
SELECT 'FP0001', 'CT02', '枝豆', 300, NULL, 0 WHERE NOT EXISTS (SELECT 1 FROM 商品マスタテーブル WHERE product_id = 'FP0001');
INSERT INTO 商品マスタテーブル (product_id, category_id, product_name, price, course_id, sales_status)
SELECT 'FP0002', 'CT02', '自家製から揚げ', 520, NULL, 0 WHERE NOT EXISTS (SELECT 1 FROM 商品マスタテーブル WHERE product_id = 'FP0002');
INSERT INTO 商品マスタテーブル (product_id, category_id, product_name, price, course_id, sales_status)
SELECT 'FP0003', 'CT02', 'フライドポテト', 400, NULL, 0 WHERE NOT EXISTS (SELECT 1 FROM 商品マスタテーブル WHERE product_id = 'FP0003');

-- 5. オプションマスタテーブル
CREATE TABLE IF NOT EXISTS オプションマスタテーブル (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    option_name VARCHAR(50) NOT NULL,
    option_add_price INT NOT NULL DEFAULT 0
);

-- 梅酒用などのサンプルデータ（ロック、ソーダ割りなど）
INSERT INTO オプションマスタテーブル (option_id, option_name, option_add_price)
SELECT 1, 'ロック', 0 WHERE NOT EXISTS (SELECT 1 FROM オプションマスタテーブル WHERE option_id = 1);
INSERT INTO オプションマスタテーブル (option_id, option_name, option_add_price)
SELECT 2, 'ソーダ割り', 50 WHERE NOT EXISTS (SELECT 1 FROM オプションマスタテーブル WHERE option_id = 2);
INSERT INTO オプションマスタテーブル (option_id, option_name, option_add_price)
SELECT 3, 'ストレート', 0 WHERE NOT EXISTS (SELECT 1 FROM オプションマスタテーブル WHERE option_id = 3);
INSERT INTO オプションマスタテーブル (option_id, option_name, option_add_price)
SELECT 4, '水割り', 0 WHERE NOT EXISTS (SELECT 1 FROM オプションマスタテーブル WHERE option_id = 4);

-- 6. 注文テーブル
CREATE TABLE IF NOT EXISTS 注文テーブル (
    order_id CHAR(15) PRIMARY KEY,
    customer_id CHAR(7) NOT NULL,
    order_time TIMESTAMP NOT NULL,
    status INT NOT NULL DEFAULT 0 -- 0:受付中, 1:提供済, 9:キャンセル
);

-- 7. 注文明細テーブル
CREATE TABLE IF NOT EXISTS 注文明細テーブル (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id CHAR(15) NOT NULL,
    product_id CHAR(6) NOT NULL,
    quantity INT NOT NULL,
    unit_price INT NOT NULL,
    option_id INT,
    option_info VARCHAR(100), -- 既存コードとの互換性のために維持
    option_add_price INT NOT NULL DEFAULT 0, -- 既存コードとの互換性のために維持
    serve_status INT NOT NULL DEFAULT 0 -- 0:未提供, 1:提供済, 9:キャンセル
);

-- 8. テスト用の顧客データおよび注文データ（検証用）
INSERT INTO 顧客テーブル (customer_id, store_id, seat_id, course_id, entry_time, checkout_status, total_amount)
SELECT '9900001', '99', '1', 'none', CURRENT_TIMESTAMP, 0, 1630 WHERE NOT EXISTS (SELECT 1 FROM 顧客テーブル WHERE customer_id = '9900001');
INSERT INTO 顧客テーブル (customer_id, store_id, seat_id, course_id, entry_time, checkout_status, total_amount)
SELECT '9900002', '99', '2', 'none', CURRENT_TIMESTAMP, 0, 780 WHERE NOT EXISTS (SELECT 1 FROM 顧客テーブル WHERE customer_id = '9900002');

INSERT INTO 注文テーブル (order_id, customer_id, order_time, status)
SELECT '1', '9900001', CURRENT_TIMESTAMP, 0 WHERE NOT EXISTS (SELECT 1 FROM 注文テーブル WHERE order_id = '1');
INSERT INTO 注文テーブル (order_id, customer_id, order_time, status)
SELECT '2', '9900001', CURRENT_TIMESTAMP, 1 WHERE NOT EXISTS (SELECT 1 FROM 注文テーブル WHERE order_id = '2');
INSERT INTO 注文テーブル (order_id, customer_id, order_time, status)
SELECT '3', '9900002', CURRENT_TIMESTAMP, 0 WHERE NOT EXISTS (SELECT 1 FROM 注文テーブル WHERE order_id = '3');

INSERT INTO 注文明細テーブル (order_item_id, order_id, product_id, quantity, unit_price, option_id, option_info, option_add_price, serve_status)
SELECT 1, '1', 'PR0001', 2, 450, 1, 'ロック', 0, 0 WHERE NOT EXISTS (SELECT 1 FROM 注文明細テーブル WHERE order_item_id = 1);
INSERT INTO 注文明細テーブル (order_item_id, order_id, product_id, quantity, unit_price, option_id, option_info, option_add_price, serve_status)
SELECT 2, '1', 'FP0002', 1, 520, NULL, '', 0, 0 WHERE NOT EXISTS (SELECT 1 FROM 注文明細テーブル WHERE order_item_id = 2);
INSERT INTO 注文明細テーブル (order_item_id, order_id, product_id, quantity, unit_price, option_id, option_info, option_add_price, serve_status)
SELECT 3, '2', 'PR0003', 1, 250, NULL, '', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM 注文明細テーブル WHERE order_item_id = 3);
INSERT INTO 注文明細テーブル (order_item_id, order_id, product_id, quantity, unit_price, option_id, option_info, option_add_price, serve_status)
SELECT 4, '3', 'PR0002', 2, 380, 2, 'ソーダ割り', 50, 0 WHERE NOT EXISTS (SELECT 1 FROM 注文明細テーブル WHERE order_item_id = 4);