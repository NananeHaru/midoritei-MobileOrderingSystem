import os
import pytest
from mos_test.client import MosClient
from mos_test.validators import validate_error_response, validate_get_orders_response

BASE_URL = os.environ.get("MOS_BASE_URL", "http://localhost:8080")

@pytest.mark.contract
def test_update_status_success_body_empty_or_null_json():
    """
    updateStatusの正常系レスポンスボディを検証する関数
    """

    #APIを呼び出すためのクライアント生成
    client = MosClient(BASE_URL)

    #注文が1件以上存在することを確認
    r = client.get_orders(customer_id=None, bill_status=None, from_time=None, to_time=None)
    assert r.status_code == 200
    assert r.raw_json is not None
    validate_get_orders_response(r.raw_json)
    assert len(r.raw_json) >= 1, "No orders exist. Prepare MOS test data first."

    #注文取得しupdateStatus実行
    order0 = r.raw_json[0]
    customer_id = order0["customerId"]
    u = client.update_status(customer_id=customer_id, bill_status=8, hash_value=None)

    assert u.status_code == 200
    #レスポンスボディが空文字、JSONとして解釈できない、空オブジェクトの場合、OK
    assert (u.raw_text.strip() == "") or (u.raw_json is None) or (u.raw_json == {})


@pytest.mark.contract
def test_update_status_invalid_hash_expect_order_not_found():
    """
    updateStatusの異常系レスポンスボディを検証する関数
    """

    #APIを呼び出すためのクライアント生成
    client = MosClient(BASE_URL)

    #注文が1件以上存在することを確認
    r = client.get_orders(customer_id=None, bill_status=None, from_time=None, to_time=None)
    assert r.status_code == 200
    assert r.raw_json is not None
    validate_get_orders_response(r.raw_json)
    assert len(r.raw_json) >= 1, "No orders exist. Prepare MOS test data first."

    #注文取得
    order0 = r.raw_json[0]
    customer_id = order0["customerId"]
    h = order0["hash"]

    #ハッシュの最後の一文字だけを変更する
    tampered = h[:-1] + ("0" if h[-1] != "0" else "1")

    #改ざんしたハッシュでupdateStatus実行
    u = client.update_status(customer_id=customer_id, bill_status=2, hash_value=tampered)

    assert u.status_code == 400                                     #HTTP400が返るかを確認
    assert u.raw_json is not None                                   #JSONであるかを確認
    validate_error_response(u.raw_json)                             #レスポンスボディの中身が正しいかを確認
    assert u.raw_json["errorCode"].strip() == "ORDER_NOT_FOUND"     #エラーコードが"ORDER_NOT_FOUND"かを確認
