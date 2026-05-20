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
SELECT * FROM (
    SELECT 'PR0001', 'CT01', '生ビール（中）', 450, NULL, 0 UNION ALL
    SELECT 'PR0002', 'CT01', 'レモンサワー', 380, NULL, 0 UNION ALL
    SELECT 'PR0003', 'CT01', 'ウーロン茶', 250, NULL, 0 UNION ALL
    SELECT 'FP0001', 'CT02', '枝豆', 300, NULL, 0 UNION ALL
    SELECT 'FP0002', 'CT02', '自家製から揚げ', 520, NULL, 0 UNION ALL
    SELECT 'FP0003', 'CT02', 'フライドポテト', 400, NULL, 0
) th WHERE NOT EXISTS (SELECT 1 FROM 商品マスタテーブル);

-- 5. オプションマスタテーブル
CREATE TABLE IF NOT EXISTS オプションマスタテーブル (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    option_name VARCHAR(50) NOT NULL,
    option_add_price INT NOT NULL DEFAULT 0
);

-- 梅酒用などのサンプルデータ（ロック、ソーダ割りなど）
INSERT INTO オプションマスタテーブル (option_id, option_name, option_add_price)
SELECT * FROM (
    SELECT 1, 'ロック', 0 UNION ALL
    SELECT 2, 'ソーダ割り', 50 UNION ALL
    SELECT 3, 'ストレート', 0 UNION ALL
    SELECT 4, '水割り', 0
) th WHERE NOT EXISTS (SELECT 1 FROM オプションマスタテーブル);