from __future__ import annotations

import json
from pathlib import Path
from typing import Any, Dict, List


def load_smoke_cases(filename: str = "smoke_cases.json") -> List[Dict[str, Any]]:
    """
    スモークテストケースを JSON ファイルから読み込む。

    想定配置:
      mos_test/suites/smoke_cases.json

    JSON形式:
      [
        {
          "id": "case_id",
          "request": {...},
          "expect": {...}
        },
        ...
      ]
    """
    base_dir = Path(__file__).resolve().parent
    path = base_dir / filename

    if not path.exists():
        raise FileNotFoundError(f"smoke cases file not found: {path}")

    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as e:
        raise ValueError(f"invalid JSON in smoke cases file: {path}: {e}") from e

    if not isinstance(data, list):
        raise ValueError("smoke cases must be a JSON array")

    # 最低限のバリデーション
    seen_ids = set()
    for i, case in enumerate(data):
        if not isinstance(case, dict):
            raise ValueError(f"case[{i}] must be an object")
        if "id" not in case or not isinstance(case["id"], str) or not case["id"].strip():
            raise ValueError(f"case[{i}] missing/invalid 'id'")
        if case["id"] in seen_ids:
            raise ValueError(f"duplicate case id: {case['id']}")
        seen_ids.add(case["id"])

        if "request" not in case or not isinstance(case["request"], dict):
            raise ValueError(f"case[{i}] missing/invalid 'request'")
        if "expect" not in case or not isinstance(case["expect"], dict):
            raise ValueError(f"case[{i}] missing/invalid 'expect'")
        if "api" not in case["request"] or not isinstance(case["request"]["api"], str):
            raise ValueError(f"case[{i}] request missing/invalid 'api'")

    return data

