"""
ハッシュ生成に関するクラス
"""
import hashlib
import json
from typing import Any, Dict, List

def _canonical_dumps(obj: Any) -> str:
    """
    オブジェクトを一定ルールでJSON文字列化する関数
    
    :param obj: 変換対象のオブジェクト
    :type obj: Any
    :return: JSON文字列
    :rtype: str
    """
    
    return json.dumps(
        obj,
        ensure_ascii=False,
        separators=(",", ":"),
        sort_keys=True,
    )


def compute_order_hash(order: Dict[str, Any]) -> str:
    """
    ハッシュになにが含まれているかを定義する関数
    MOSのハッシュと一致することを保証しない
    
    :param order: ハッシュ計算対象
    :type order: Dict[str, Any]
    :return: ハッシュ文字列
    :rtype: str
    """

    #注文詳細リスト作成
    items: List[Dict[str, Any]] = []
    for it in order.get("items", []) or []:
        items.append(
            {
                "orderTime": it.get("orderTime"),
                "menuName": it.get("menuName"),
                "unitPrice": it.get("unitPrice"),
                "taxRate": it.get("taxRate"),
                "orderQty": it.get("orderQty"),
                "offerQty": it.get("offerQty"),
            }
        )

    #ハッシュ計算用のデータ構造
    material = {
        "storeId": order.get("storeId"),
        "customerId": order.get("customerId"),
        "entryTime": order.get("entryTime"),
        "items": items,
    }

    raw = _canonical_dumps(material).encode("utf-8")
    return hashlib.sha256(raw).hexdigest()
