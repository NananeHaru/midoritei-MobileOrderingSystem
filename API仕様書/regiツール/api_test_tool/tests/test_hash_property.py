from __future__ import annotations

from copy import deepcopy
import pytest

from mos_test.hash_util import compute_order_hash


def _base_order():
    """
    hash対象項目を全て含む最小の注文データ
    """
    return {
        "storeId": "AA",
        "customerId": "0000001",
        "entryTime": "2026-02-01T12:00:00",
        "items": [
            {
                "orderTime": "2026-02-01T12:00:00",
                "menuName": "生ビール",
                "categoryName": "ドリンク",
                "unitPrice": 600,
                "taxRate": 10,
                "orderQty": 2,
                "offerQty": 2,
            },
            {
                "orderTime": "2026-02-01T12:01:00",
                "menuName": "唐揚げ",
                "categoryName": None,
                "unitPrice": 300,
                "taxRate": 10,
                "orderQty": 1,
                "offerQty": 1,
            },
        ],
    }


def _set_path(obj, path, value):
    """
    pathで指定した位置にvalueをセットする。
    path例: ("items", 0, "menuName")
    """
    cur = obj
    for key in path[:-1]:
        cur = cur[key]
    cur[path[-1]] = value


def _path_to_str(path) -> str:
    """
    失敗メッセージ用にパスを読みやすい文字列に変換する。
    """
    s = []
    for p in path:
        if isinstance(p, int):
            s.append(f"[{p}]")
        else:
            if not s:
                s.append(str(p))
            else:
                # 直前が index なら . でつなぐ
                if s[-1].endswith("]"):
                    s.append(f".{p}")
                else:
                    s.append(f".{p}")
    return "".join(s)


# ここに「hashに含める項目」だけ列挙します
# 失敗時にどの項目が原因か、case idとメッセージで特定できます。
HASH_FIELDS_CASES = [
    (("storeId",), "AB"),
    (("customerId",), "0000002"),
    (("entryTime",), "2026-02-01T12:00:01"),
    (("items", 0, "orderTime"), "2026-02-01T12:00:01"),
    (("items", 0, "menuName"), "瓶ビール"),
    (("items", 0, "unitPrice"), 601),
    (("items", 0, "taxRate"), 8),
    (("items", 0, "orderQty"), 3),
    (("items", 0, "offerQty"), 1)
]

def test_hash_is_idempotent_same_input_same_hash():
    """
    同じデータなら常に同一ハッシュになるかを確認するテスト
    """
    o = _base_order()
    h1 = compute_order_hash(o)
    h2 = compute_order_hash(o)
    assert h1 == h2, "hash must be identical for the same input (idempotent)"

@pytest.mark.parametrize(
    "path,new_value",
    HASH_FIELDS_CASES,
    ids=lambda v: _path_to_str(v[0]) if isinstance(v, tuple) else str(v),
)
def test_hash_changes_when_each_included_field_changes(path, new_value):
    """
    hashに含めると決めた任意の項目が変われば、hashも変わるかを確認するテスト
    """
    base = _base_order()
    h0 = compute_order_hash(base)

    o = deepcopy(base)
    _set_path(o, path, new_value)
    h1 = compute_order_hash(o)

    assert h0 != h1, f"hash did not change when {_path_to_str(path)} changed"
