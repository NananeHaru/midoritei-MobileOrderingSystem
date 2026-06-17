-- =============================================
-- MariaDB用スキーマファイル
-- schema.sql (H2用) をMariaDB互換に変換したもの
-- =============================================

-- 1. 顧客採番テーブル
CREATE TABLE IF NOT EXISTS 顧客採番テーブル (current_number INT NOT NULL);

INSERT INTO
    顧客採番テーブル (current_number)
SELECT 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 顧客採番テーブル
    );

-- 2. 顧客テーブル
CREATE TABLE IF NOT EXISTS 顧客テーブル (
    customer_id CHAR(7) PRIMARY KEY,
    store_id CHAR(2),
    seat_id VARCHAR(20),
    course_id CHAR(4),
    entry_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    checkout_status INT,
    total_amount INT,
    number_of_members INT DEFAULT 0,
    parent_customer_id CHAR(7)
);

-- 3. 商品マスタテーブル
CREATE TABLE IF NOT EXISTS 商品マスタテーブル (
    product_id CHAR(6) PRIMARY KEY,
    category_id VARCHAR(20),
    product_name VARCHAR(100),
    price INT,
    course_id CHAR(4),
    sales_status INT,
    description VARCHAR(255),
    is_seasonal INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255)
);

-- 5. オプションマスタテーブル
CREATE TABLE IF NOT EXISTS オプションマスタテーブル (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    option_name VARCHAR(50) NOT NULL,
    option_add_price INT NOT NULL DEFAULT 0
);

-- 梅酒用などのサンプルデータ（ロック、ソーダ割りなど）
INSERT INTO
    オプションマスタテーブル (
        option_id,
        option_name,
        option_add_price
    )
SELECT 1, 'ロック', 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM オプションマスタテーブル
        WHERE
            option_id = 1
    );

INSERT INTO
    オプションマスタテーブル (
        option_id,
        option_name,
        option_add_price
    )
SELECT 2, 'ソーダ割り', 50
WHERE
    NOT EXISTS (
        SELECT 1
        FROM オプションマスタテーブル
        WHERE
            option_id = 2
    );

INSERT INTO
    オプションマスタテーブル (
        option_id,
        option_name,
        option_add_price
    )
SELECT 3, 'ストレート', 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM オプションマスタテーブル
        WHERE
            option_id = 3
    );

INSERT INTO
    オプションマスタテーブル (
        option_id,
        option_name,
        option_add_price
    )
SELECT 4, '水割り', 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM オプションマスタテーブル
        WHERE
            option_id = 4
    );

INSERT INTO
    オプションマスタテーブル (
        option_id,
        option_name,
        option_add_price
    )
SELECT 5, 'タレ', 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM オプションマスタテーブル
        WHERE
            option_id = 5
    );

INSERT INTO
    オプションマスタテーブル (
        option_id,
        option_name,
        option_add_price
    )
SELECT 6, '塩', 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM オプションマスタテーブル
        WHERE
            option_id = 6
    );

-- 6. 注文テーブル
CREATE TABLE IF NOT EXISTS 注文テーブル (
    order_id CHAR(15) PRIMARY KEY,
    customer_id CHAR(7) NOT NULL,
    order_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    option_info VARCHAR(100),
    option_add_price INT NOT NULL DEFAULT 0,
    serve_status INT NOT NULL DEFAULT 0 -- 0:未提供, 1:提供済, 9:キャンセル
);

-- 8. テスト用の顧客データおよび注文データ（検証用）
INSERT INTO
    顧客テーブル (
        customer_id,
        store_id,
        seat_id,
        course_id,
        entry_time,
        checkout_status,
        total_amount,
        number_of_members
    )
SELECT '9900001', '99', '1', 'CS01', CURRENT_TIMESTAMP, 0, 1630, 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 顧客テーブル
        WHERE
            customer_id = '9900001'
    );

INSERT INTO
    顧客テーブル (
        customer_id,
        store_id,
        seat_id,
        course_id,
        entry_time,
        checkout_status,
        total_amount,
        number_of_members
    )
SELECT '9900002', '99', '2', 'CS01', CURRENT_TIMESTAMP, 0, 780, 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 顧客テーブル
        WHERE
            customer_id = '9900002'
    );

INSERT INTO
    注文テーブル (
        order_id,
        customer_id,
        order_time,
        status
    )
SELECT '1', '9900001', CURRENT_TIMESTAMP, 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 注文テーブル
        WHERE
            order_id = '1'
    );

INSERT INTO
    注文テーブル (
        order_id,
        customer_id,
        order_time,
        status
    )
SELECT '2', '9900001', CURRENT_TIMESTAMP, 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 注文テーブル
        WHERE
            order_id = '2'
    );

INSERT INTO
    注文テーブル (
        order_id,
        customer_id,
        order_time,
        status
    )
SELECT '3', '9900002', CURRENT_TIMESTAMP, 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 注文テーブル
        WHERE
            order_id = '3'
    );

INSERT INTO
    注文明細テーブル (
        order_item_id,
        order_id,
        product_id,
        quantity,
        unit_price,
        option_id,
        option_info,
        option_add_price,
        serve_status
    )
SELECT 1, '1', 'PR0001', 2, 450, 1, 'ロック', 0, 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 注文明細テーブル
        WHERE
            order_item_id = 1
    );

INSERT INTO
    注文明細テーブル (
        order_item_id,
        order_id,
        product_id,
        quantity,
        unit_price,
        option_id,
        option_info,
        option_add_price,
        serve_status
    )
SELECT 2, '1', 'FP0002', 1, 520, NULL, '', 0, 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 注文明細テーブル
        WHERE
            order_item_id = 2
    );

INSERT INTO
    注文明細テーブル (
        order_item_id,
        order_id,
        product_id,
        quantity,
        unit_price,
        option_id,
        option_info,
        option_add_price,
        serve_status
    )
SELECT 3, '2', 'PR0003', 1, 250, NULL, '', 0, 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 注文明細テーブル
        WHERE
            order_item_id = 3
    );

INSERT INTO
    注文明細テーブル (
        order_item_id,
        order_id,
        product_id,
        quantity,
        unit_price,
        option_id,
        option_info,
        option_add_price,
        serve_status
    )
SELECT 4, '3', 'PR0002', 2, 380, 2, 'ソーダ割り', 50, 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 注文明細テーブル
        WHERE
            order_item_id = 4
    );

-- 自動採番カウンタを初期データの最大値の次(5)にリセット（MariaDB互換）
ALTER TABLE 注文明細テーブル AUTO_INCREMENT = 5;

-- 9. カテゴリーテーブル
CREATE TABLE IF NOT EXISTS カテゴリーテーブル (
    category_id VARCHAR(20) PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    display_order INT NOT NULL
);

-- カテゴリー初期データ
INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT01', 'お酒', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT01'
    );

INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT02', 'スピードメニュー', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT02'
    );

INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT03', 'メイン・揚げ物', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT03'
    );

INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT04', '串物', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT04'
    );

INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT05', '一品', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT05'
    );

INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT06', '早出し', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT06'
    );

INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT07', 'おつまみ', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT07'
    );

INSERT INTO
    カテゴリーテーブル (
        category_id,
        category_name,
        display_order
    )
SELECT 'CT08', 'ソフトドリンク', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM カテゴリーテーブル
        WHERE
            category_id = 'CT08'
    );

-- 10. 分類・オプション紐づけテーブル（★ダブルクォートをバッククォートに変更）
CREATE TABLE IF NOT EXISTS `分類・オプション紐づけテーブル` (
    category_id VARCHAR(20) NOT NULL,
    option_id INT NOT NULL,
    PRIMARY KEY (category_id, option_id)
);

-- 分類・オプション紐づけデータ
-- ドリンク/お酒（CT01）にロック(1)、ソーダ割り(2)、ストレート(3)、水割り(4)を紐づけ
INSERT INTO
    `分類・オプション紐づけテーブル` (category_id, option_id)
SELECT 'CT01', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `分類・オプション紐づけテーブル`
        WHERE
            category_id = 'CT01'
            AND option_id = 1
    );

INSERT INTO
    `分類・オプション紐づけテーブル` (category_id, option_id)
SELECT 'CT01', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `分類・オプション紐づけテーブル`
        WHERE
            category_id = 'CT01'
            AND option_id = 2
    );

INSERT INTO
    `分類・オプション紐づけテーブル` (category_id, option_id)
SELECT 'CT01', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `分類・オプション紐づけテーブル`
        WHERE
            category_id = 'CT01'
            AND option_id = 3
    );

INSERT INTO
    `分類・オプション紐づけテーブル` (category_id, option_id)
SELECT 'CT01', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `分類・オプション紐づけテーブル`
        WHERE
            category_id = 'CT01'
            AND option_id = 4
    );

-- 串物（CT04）にタレ(5)、塩(6)を紐づけ
INSERT INTO
    `分類・オプション紐づけテーブル` (category_id, option_id)
SELECT 'CT04', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `分類・オプション紐づけテーブル`
        WHERE
            category_id = 'CT04'
            AND option_id = 5
    );

INSERT INTO
    `分類・オプション紐づけテーブル` (category_id, option_id)
SELECT 'CT04', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `分類・オプション紐づけテーブル`
        WHERE
            category_id = 'CT04'
            AND option_id = 6
    );

-- 11. 商品オプション紐づけテーブル（★ダブルクォートをバッククォートに変更）
CREATE TABLE IF NOT EXISTS `商品オプション紐づけテーブル` (
    product_id CHAR(6) NOT NULL,
    option_id INT NOT NULL,
    PRIMARY KEY (product_id, option_id),
    FOREIGN KEY (product_id) REFERENCES 商品マスタテーブル (product_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES オプションマスタテーブル (option_id) ON DELETE CASCADE
);

-- 12. 店舗マスタテーブル（設計書に準拠）
CREATE TABLE IF NOT EXISTS 店舗マスタテーブル (
    store_id CHAR(2) NOT NULL,
    store_name VARCHAR(50) NOT NULL,
    phone_number VARCHAR(15),
    address VARCHAR(100),
    PRIMARY KEY (store_id)
);

-- 各店舗の初期データを登録
INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'MH', '緑橋店', '06-XXXX-XXXX', '大阪府大阪市東成区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'MH'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'MN', '森ノ宮店', '06-YYYY-YYYY', '大阪府大阪市中央区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'MN'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'TM', '玉造店', '06-ZZZZ-ZZZZ', '大阪府大阪市中央区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'TM'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'TH', '鶴橋店', '06-AAAA-AAAA', '大阪府大阪市天王寺区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'TH'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'IM', '今里店', '06-BBBB-BBBB', '大阪府大阪市東成区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'IM'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'FB', '深江橋店', '06-CCCC-CCCC', '大阪府大阪市城東区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'FB'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'TY', '谷町四丁目店', '06-DDDD-DDDD', '大阪府大阪市中央区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'TY'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'HM', '本町店', '06-EEEE-EEEE', '大阪府大阪市中央区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'HM'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'KB', '京橋店', '06-FFFF-FFFF', '大阪府大阪市都島区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'KB'
    );

INSERT INTO
    店舗マスタテーブル (
        store_id,
        store_name,
        phone_number,
        address
    )
SELECT 'NB', 'なんば店', '06-GGGG-GGGG', '大阪府大阪市中央区...'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 店舗マスタテーブル
        WHERE
            store_id = 'NB'
    );

--テストデータ
-- 🍺 ドリンク（カテゴリー: CT01）

-- ウーロン茶：どのコース（CS02, CS03）でも0円になる（スタンダード以上対象）
INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'PR0003', 'CT01', 'ウーロン茶', 250, 'CS02', 0, 'さっぱりとしたウーロン茶です。お口直しにもどうぞ。', 0, '/images/PR0003.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'PR0003'
    );

-- オレンジジュース：どのコース（CS02, CS03）でも0円になる（スタンダード以上対象）
INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'PR0004', 'CT01', 'オレンジジュース', 250, 'CS02', 0, '果汁100%のみずみずしいオレンジジュースです。', 0, '/images/PR0004.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'PR0004'
    );

-- レモンサワー：どのコース（CS02, CS03）でも0円になる（スタンダード以上対象）
INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'PR0002', 'CT01', 'レモンサワー', 380, 'CS02', 0, 'すっきりレモン果汁がたっぷり入った人気のレモンサワーです。', 0, '/images/PR0002.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'PR0002'
    );

-- ハイボール：どのコース（CS02, CS03）でも0円になる（スタンダード以上対象）
INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'PR0005', 'CT01', '角ハイボール', 420, 'CS02', 0, '強炭酸でキレ味抜群の定番ハイボールです。', 0, '/images/PR0005.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'PR0005'
    );

-- 生ビール：どのコース（CS02, CS03）でも0円になる（スタンダード以上対象）
INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'PR0001', 'CT01', '生ビール（中）', 450, 'CS02', 0, 'のどごし爽快な、みどり亭定番の生ビールです。お食事によく合います。', 0, '/images/PR0001.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'PR0001'
    );

-- プレミアム生日本酒：プレミアム（CS03）の人のみ0円！スタンダード（CS02）やコースなしの人は通常価格
INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'PR0006', 'CT01', '特選プレミアム日本酒', 850, 'CS03', 0, 'プレミアムコース限定で飲み放題になる、芳醇な香りの限定日本酒です。', 0, '/images/PR0006.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'PR0006'
    );

-- 高級ウイスキー：プレミアム（CS03）の人のみ0円！スタンダードやコースなしは通常価格
INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'PR0007', 'CT01', '山崎プレミアムハイボール', 950, 'CS03', 0, 'プレミアムコースの方だけが飲み放題で楽しめる、贅沢な一杯です。', 0, '/images/PR0007.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'PR0007'
    );

-- 🍗 フード・スピードメニュー（カテゴリー: CT02、すべて通常価格＝NULL）
-- ※今回はフードは飲み放題（0円）の対象外、単品での注文（コースなし含む）を想定しています。

INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'FP0001', 'CT02', '枝豆', 300, NULL, 0, '塩味が効いた、おつまみの定番の枝豆です。すぐにお持ちします。', 0, '/images/FP0001.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'FP0001'
    );

INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'FP0003', 'CT02', 'フライドポテト', 400, NULL, 0, '外はカリッと、中はホクホクに揚げたフライドポテトです。', 0, '/images/FP0003.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'FP0003'
    );

INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'FP0002', 'CT02', '自家製から揚げ', 520, NULL, 0, '外はサクサク、中はジューシーに揚げた自家製のから揚げです。', 0, '/images/FP0002.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'FP0002'
    );

INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'FP0004', 'CT02', 'なんこつの唐揚げ', 450, NULL, 0, 'コリコリとした食感がクセになる、おつまみにぴったりの唐揚げです。', 0, '/images/FP0004.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'FP0004'
    );

INSERT INTO
    商品マスタテーブル (
        product_id,
        category_id,
        product_name,
        price,
        course_id,
        sales_status,
        description,
        is_seasonal,
        image_path
    )
SELECT 'FP0005', 'CT02', 'おつまみ塩キャベツ', 350, NULL, 0, '特製の塩ダレとごま油がキャベツの旨味を引き立てるスピードメニューです。', 0, '/images/FP0005.png'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 商品マスタテーブル
        WHERE
            product_id = 'FP0005'
    );

-- 商品オプション紐づけ初期データ（★ダブルクォートをバッククォートに変更）
-- 生ビール(PR0001)、レモンサワー(PR0002)にロック(1)・ソーダ割り(2)・ストレート(3)・水割り(4)を紐づけ
INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0001', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0001'
            AND option_id = 1
    );

INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0001', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0001'
            AND option_id = 2
    );

INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0001', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0001'
            AND option_id = 3
    );

INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0001', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0001'
            AND option_id = 4
    );

INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0002', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0002'
            AND option_id = 1
    );

INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0002', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0002'
            AND option_id = 2
    );

INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0002', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0002'
            AND option_id = 3
    );

INSERT INTO
    `商品オプション紐づけテーブル` (product_id, option_id)
SELECT 'PR0002', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM `商品オプション紐づけテーブル`
        WHERE
            product_id = 'PR0002'
            AND option_id = 4
    );

-- 13.コースマスタテーブル
CREATE TABLE IF NOT EXISTS コースマスタテーブル (
    course_id char(4) PRIMARY KEY,
    course_name VARCHAR(50) NOT NULL,
    price INT NOT NULL,
    time_limit INT NOT NULL
);

-- 📄 コースマスタテーブルへの初期データ投入

-- CS01: コースなし（単品注文なので価格は0円、制限時間はなしとして0分）
INSERT INTO
    コースマスタテーブル (
        course_id,
        course_name,
        price,
        time_limit
    )
SELECT 'CS01', 'コースなし（単品注文）', 0, 0
WHERE
    NOT EXISTS (
        SELECT 1
        FROM コースマスタテーブル
        WHERE
            course_id = 'CS01'
    );

-- CS02: スタンダードコース（例: 2時間飲み放題 2000円）
INSERT INTO
    コースマスタテーブル (
        course_id,
        course_name,
        price,
        time_limit
    )
SELECT 'CS02', 'スタンダード2時間飲み放題', 2000, 120
WHERE
    NOT EXISTS (
        SELECT 1
        FROM コースマスタテーブル
        WHERE
            course_id = 'CS02'
    );

INSERT INTO コースマスタテーブル (course_id, course_name, price, time_limit)
SELECT 'CS20', 'スタンダード3時間飲み放題', 2500, 180
WHERE NOT EXISTS (SELECT 1 FROM コースマスタテーブル WHERE course_id = 'CS20');

-- CS03: プレミアムコース（例: 2時間プレミアム飲み放題 3500円）
INSERT INTO
    コースマスタテーブル (
        course_id,
        course_name,
        price,
        time_limit
    )
SELECT 'CS03', 'プレミアム2時間贅沢飲み放題', 3500, 120
WHERE
    NOT EXISTS (
        SELECT 1
        FROM コースマスタテーブル
        WHERE
            course_id = 'CS03'
    );

-- プレミアム3時間も同様に修正
INSERT INTO コースマスタテーブル (course_id, course_name, price, time_limit)
SELECT 'CS30', 'プレミアム3時間贅沢飲み放題', 4500, 180
WHERE NOT EXISTS (SELECT 1 FROM コースマスタテーブル WHERE course_id = 'CS30');

-- 14. 座席マスタテーブル
CREATE TABLE IF NOT EXISTS 座席マスタテーブル (
    store_id CHAR(2) NOT NULL,
    seat_id VARCHAR(20) NOT NULL,
    seat_type VARCHAR(20) NOT NULL,
    PRIMARY KEY (store_id, seat_id),
    FOREIGN KEY (store_id) REFERENCES 店舗マスタテーブル (store_id) ON DELETE CASCADE,
    seat_status int(3) NOT NULL DEFAULT 0
);

-- 各店舗の初期座席データ（1〜10番: counter, 11〜15番: table）
-- MH (緑橋店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MH', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MH'
            AND seat_id = '15'
    );

-- MN (森ノ宮店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'MN', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'MN'
            AND seat_id = '15'
    );

-- TM (玉造店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TM', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TM'
            AND seat_id = '15'
    );

-- TH (鶴橋店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TH', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TH'
            AND seat_id = '15'
    );

-- IM (今里店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'IM', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'IM'
            AND seat_id = '15'
    );

-- FB (深江橋店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'FB', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'FB'
            AND seat_id = '15'
    );

-- TY (谷町四丁目店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'TY', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'TY'
            AND seat_id = '15'
    );

-- HM (本町店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'HM', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'HM'
            AND seat_id = '15'
    );

-- KB (京橋店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'KB', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'KB'
            AND seat_id = '15'
    );

-- NB (なんば店)
INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '1', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '1'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '2', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '2'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '3', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '3'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '4', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '4'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '5', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '5'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '6', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '6'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '7', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '7'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '8', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '8'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '9', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '9'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '10', 'counter'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '10'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '11', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '11'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '12', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '12'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '13', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '13'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '14', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '14'
    );

INSERT INTO
    座席マスタテーブル (store_id, seat_id, seat_type)
SELECT 'NB', '15', 'table'
WHERE
    NOT EXISTS (
        SELECT 1
        FROM 座席マスタテーブル
        WHERE
            store_id = 'NB'
            AND seat_id = '15'
    );