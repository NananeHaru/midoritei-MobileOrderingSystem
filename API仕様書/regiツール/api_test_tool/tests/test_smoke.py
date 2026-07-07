import os
import pytest
from mos_test.client import MosClient
from mos_test.validators import validate_error_response, validate_get_orders_response
from mos_test.suites import load_smoke_cases

BASE_URL = os.environ.get("MOS_BASE_URL", "http://localhost:8080")

@pytest.mark.parametrize("case", load_smoke_cases(), ids=lambda c: c["id"])
def test_smoke(case):
    """
    一つのスモークケースを受け取ってテストする関数
    
    :param case: テスト対象
    :type case: Any
    """

    #APIを呼び出すためのクライアント生成
    client = MosClient(BASE_URL)

    req = case["request"]                           #どのAPIをどう呼ぶかを指定
    exp = case["expect"]                            #正常/異常かを指定
    api = req["api"]

    if api == "getOrders":
        resp = client.get_orders(
            customer_id=req.get("customerId"),
            bill_status=req.get("billStatus"),
            from_time=req.get("fromTime"),
            to_time=req.get("toTime"),
        )

    elif api == "updateStatus":
        resp = client.update_status(
            customer_id=req["customerId"],
            bill_status=req["billStatus"],
            hash_value=req.get("hash"),
        )

    elif api == "raw":
        resp = client._post_raw(req["raw"], content_type=req.get("contentType", "application/json"))

    elif api == "updateStatus_tamper_hash":
        r = client.get_orders(customer_id=None, bill_status=None, from_time=None, to_time=None)
        assert r.status_code == 200
        assert r.raw_json is not None
        validate_get_orders_response(r.raw_json)
        assert len(r.raw_json) >= 1, "No orders exist. Prepare MOS test data first."

        order0 = r.raw_json[0]
        customer_id = order0["customerId"]
        h = order0["hash"]

        tampered = h[:-1] + ("0" if h[-1] != "0" else "1")

        resp = client.update_status(
            customer_id=customer_id,
            bill_status=req.get("billStatus", 2),
            hash_value=tampered,
    )

    else:
        raise AssertionError(f"unknown api in suite: {api}")

    if exp.get("is_error"):
        assert resp.raw_json is not None, f"expected JSON error body, got raw_text={resp.raw_text!r}"
        validate_error_response(resp.raw_json)

        if "status_code" in exp:
            assert resp.status_code == exp["status_code"]

        if "errorCode" in exp:
            assert resp.raw_json.get("errorCode") == exp["errorCode"]

    else:
        #正常系
        if "status_code" in exp:
            assert resp.status_code == exp["status_code"]
        else:
            assert resp.status_code == 200

        #getOrdersのときだけレスポンス構造も検証する
        if api == "getOrders":
            assert resp.raw_json is not None
            validate_get_orders_response(resp.raw_json)

        #updateStatus正常時は空ボディでもOK（raw_json None でもOK）
        if api == "updateStatus":
            assert (resp.raw_text.strip() == "") or (resp.raw_json is None) or (resp.raw_json == {})
