MOS API Test Tool

    本リポジトリは、MOSAPI 実装の動作確認・契約検証を行うためのテストツールです。
    API仕様どおりに動作しているかを自動テストで確認できます。

対象API

    getOrders
    updateStatus

このテストツールが検証すること

    API
        リクエスト形式（構造・必須項目）
        レスポンス形式（構造・必須項目・型・null可否）

    エラー仕様
        INVALID_JSON_FORMAT
        INVALID_REQUEST
        INVALID_PARAMETER
        INVALID_BILL_STATUS
        ORDER_NOT_FOUND

    hash仕様
        同一内容 → 同一hash
        注文内容が変化 → hashも変化
        hash 不一致時はupdateStatusを拒否

ディレクトリ構成

        mos_test/
        ├─ client.py        # MOS API クライアント
        ├─ validators.py    # レスポンス構造・必須項目の検証
        ├─ hash_util.py     # hash計算ロジック
        └─ suites/
        └─ smoke_cases.json

        tests/
        ├─ test_smoke.py                 # スモークテスト（疎通・A系）
        ├─ test_contract_updateStatus.py # updateStatus
        └─ test_hash_property.py         # hash性質テスト
    
前提条件

    Python 3.11 以上
    MOS が HTTP で起動していること

    依存ライブラリ：
        pip install pytest requests

MOS側で実装が必要な仕様

    エンドポイント
        POST /api/orders

    method による分岐
        method	        処理内容
        getOrders	    注文取得
        updateStatus	状態更新
        
    updateStatus 正常時レスポンス
        HTTP 200
        レスポンスボディは空で可

getOrders レスポンスの必須構造

    レスポンスは 配列
    各要素は 注文オブジェクト
    必須キー：
        storeId
        customerId
        entryTime
        billStatus
        hash
        items

    items 配列の必須キー
        menuName：必須・null不可
        categoryName：必須キー・null可
        数値項目は数値型
        ※ 具体的な値は MOS 側で任意に生成して構いません。

実行方法
    
    1. MOS を起動
    例：http://localhost:8080

    2. テスト実行
    スモーク
    python -m pytest -s -q tests/test_smoke.py

    updateStatus テスト
    python -m pytest -q tests/test_contract_updateStatus.py

    hash 性質テスト
    python -m pytest -q tests/test_hash_property.py

テスト失敗時の見方
    
    hash
        FAILED test_hash_changes_when_each_included_field_changes[items[0].menuName]
        → menuName が hash 計算に含まれていない可能性あり。

    レスポンス構造
        AssertionError: items[0].menuName must exist and not be null
        → getOrders レスポンスが仕様に違反しています。
