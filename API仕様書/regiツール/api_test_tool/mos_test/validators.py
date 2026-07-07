"""
バリデーションに関するクラス
"""
from typing import Any, Dict, List, Union

Json = Union[Dict[str, Any], List[Any]]

def _is_hex(s: str) -> bool:
    """
    文字列が16進数として解釈できるかを判定する関数
    
    :param s: 検証対象
    :type s: str
    :return: true→変換できた, false→変換に失敗
    :rtype: bool
    """

    try:
        int(s, 16)
        return True
    except Exception:
        return False


def validate_error_response(body: Any) -> None:
    """
    エラー時レスポンスの形式を検証する関数
    
    :param body: 検証対象のJSON
    :type body: Any
    """

    assert isinstance(body, dict), f"error body must be object, got={type(body)}"   #JSONオブジェクトであるかを確認
    assert "errorCode" in body, "missing errorCode"                                 #"errorCode"があるかを確認
    assert "message" in body, "missing message"                                     #"message"があるかを確認
    assert isinstance(body["errorCode"], str), "errorCode must be string"           #"errorCode"の型が文字列であるかを確認
    assert isinstance(body["message"], str), "message must be string"               #"message"の型が文字列であるかを確認


def validate_get_orders_response(body: Any) -> None:
    """
    getOrdersの正常系レスポンスを検証する関数
    
    :param body: 検証対象のJSON
    :type body: Any
    """

    assert isinstance(body, list), f"getOrders response must be array, got={type(body)}"    #JSON配列であるかを確認

    #注文を1件ずつ検証
    for order in body:
        assert isinstance(order, dict), "each order must be object"                         #JSONオブジェクトであるかを確認

        #注文の必須キーを検証
        for k in ["hash", "storeId", "entryTime", "customerId", "billStatus", "items"]:
            assert k in order, f"missing {k} in order"                                      #必須キーがあるかを確認

        #hashの検証
        h = order["hash"]
        assert isinstance(h, str), "hash must be string"                                    #"hash"が文字列であるかを確認
        assert 8 <= len(h) <= 64, "hash length must be 8..64"                               #"hash"の文字数を確認
        assert _is_hex(h), "hash must be hex"                                               #"hash"が16進数であるかを確認

        assert isinstance(order["storeId"], str) and len(order["storeId"]) == 2             #"storeId"が2文字の文字列であるかを確認             
        assert isinstance(order["customerId"], str) and len(order["customerId"]) == 7       #"customerId"が7文字の文字列であるかを確認
        assert isinstance(order["entryTime"], str)                                          #"entryTime"が文字列であるかを確認
        assert isinstance(order["billStatus"], int)                                         #"billStatus"が数値であるかを確認
        assert isinstance(order["items"], list)                                             #"items"が配列であるかを確認

        #注文明細の検証
        for item in order["items"]:
            assert isinstance(item, dict), "each item must be object"                       #JSONオブジェクトであるかを確認

            #注文明細の必須キーを検証
            for k in ["orderTime", "unitPrice", "taxRate", 
                      "orderQty", "offerQty","menuName","categoryName"]:
                assert k in item, f"missing {k} in item"                                    #必須キーがあるかを確認

            assert isinstance(item["orderTime"], str)                                       #"orderTime"が文字列であるかを確認
            assert isinstance(item["unitPrice"], int)                                       #"utitPrice"が数値であるかを確認
            assert isinstance(item["taxRate"], int)                                         #"taxRate"が数値であるかを確認
            assert isinstance(item["orderQty"], int)                                        #"orderQty"が数値であるかを確認
            assert isinstance(item["offerQty"], int)                                        #"offerQty"が数値であるかを確認
            assert isinstance(item["menuName"], str)                                        #"menuName"が文字列であるかを確認
            assert item["categoryName"] is None or isinstance(item["categoryName"], str)    #"categoryName"が文字列またはnullであるかを確認
