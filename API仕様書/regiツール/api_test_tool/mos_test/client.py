"""
MOS API クライアントモジュール

MOSが提供するAPIに対してリクエストを送信するための
APIクライアントを提供する

主な役割：
・MOSAPIへのHTTPリクエスト送信
・getOrders/updateStatusのラッパー提供
・レスポンスデータの保持
"""
from dataclasses import dataclass
from typing import Any, Dict, List, Optional, Union
import requests

Json = Union[Dict[str, Any], List[Any]]
DEFAULT_BASE_URL = "http://localhost:8080"

@dataclass
class MosResponse:
    """
    APIの応答をまとめて持つためのデータクラス

    status_code: HTTPステータスコード
    raw_text: レスポンス本文
    raw_json: JSONとして解釈できた場合はdict/listを入れ、JSON出なかった場合はNoneを格納
    headers: レスポンスヘッダ
    """
    status_code: int
    raw_text: str
    raw_json: Optional[Json]
    headers: Dict[str, str]

class MosClient:
    """
    MOS API クライアントクラス。

    /api/orders エンドポイントに対する
    getOrders / updateStatus リクエストを提供する。

    本クラスは API 通信のみを責務とし、
    レスポンス内容の検証や業務判断は行わない。
    """

    def __init__(self, base_url: Optional[str] = None, timeout_sec: float = 10.0) -> None:
        """
        コンストラクタ
        クライアント生成時にbase_urlとtimeout_secを受け取る

        :param base_url: URL
        :type base_url: str
        :param timeout_sec: タイムアウト秒数
        :type timeout_sec: float
        """

        if not base_url:
            base_url = DEFAULT_BASE_URL

        self.base_url = base_url.rstrip("/")
        self.timeout_sec = timeout_sec

    @property
    def endpoint(self) -> str:
        """
        URL文字列を返す関数

        :return: URL
        :rtype: str
        """

        return f"{self.base_url}/api/orders"

    def _post_json(self, body: Json) -> MosResponse:
        """
        JSONボディを受け取り、POSTして、MosResponseで返すための関数
        
        :param body: JSONボディ
        :type body: Json
        :return: 取得した情報
        :rtype: MosResponse
        """

        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

        #POSTする
        r = requests.post(
            self.endpoint,
            json=body,
            headers=headers,
            timeout=self.timeout_sec,
        )

        try:
            j = r.json()
        except Exception:       #JSONでない、または空ボディだった場合はNone
            j = None
            
        return MosResponse(
            status_code=r.status_code,
            raw_text=r.text,
            raw_json=j,
            headers=dict(r.headers),
        )

    def get_orders(
        self,
        customer_id: Optional[str],
        bill_status: Optional[Union[int, List[int]]],
        from_time: Optional[str],
        to_time: Optional[str],
    ) -> MosResponse:
        """
        get_orders の Docstring

        :param customer_id: 顧客ID
        :type customer_id: Optional[str]
        :param bill_status: 会計状況
        :type bill_status: Optional[Union[int, List[int]]]
        :param from_time: 取得対象の開始日時
        :type from_time: Optional[str]
        :param to_time: 取得対象の終了日時
        :type to_time: Optional[str]
        :return: レスポンスボディ
        :rtype: MosResponse
        """

        payload_obj: Dict[str, Any] = {
            "method": "getOrders",
            "customerId": customer_id,
            "billStatus": bill_status,
            "fromTime": from_time,
            "toTime": to_time,
        }
        return self._post_json(payload_obj)

    def update_status(
        self,
        customer_id: str,
        bill_status: int,
        hash_value: Optional[str],
    ) -> MosResponse:
        """
        update_status の Docstring
        
        :param self: 説明
        :param customer_id: 顧客ID
        :type customer_id: str
        :param bill_status: 会計状況
        :type bill_status: int
        :param hash_value: ハッシュ
        :type hash_value: Optional[str]
        :return: レスポンスボディ
        :rtype: MosResponse
        """
        
        payload_obj: Dict[str, Any] = {
            "method": "updateStatus",
            "customerId": customer_id,
            "hash": hash_value,
            "billStatus": bill_status,
        }
        return self._post_json(payload_obj)
    
    def _post_raw(self, raw_text: str, content_type: str = "application/json") -> MosResponse:
        """
        生の文字列をそのまま送信する
        """
        
        headers = {
            "Content-Type": content_type,
            "Accept": "application/json",
        }
        
        r = requests.post(
            self.endpoint,
            data=raw_text.encode("utf-8"),
            headers=headers,
            timeout=self.timeout_sec,
        )
        try:
            j = r.json()
        except Exception:
            j = None

        return MosResponse(
            status_code=r.status_code,
            raw_text=r.text,
            raw_json=j,
            headers=dict(r.headers),
        )
