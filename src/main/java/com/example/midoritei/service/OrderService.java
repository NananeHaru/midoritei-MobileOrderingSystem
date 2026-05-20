package com.example.midoritei.service;

import com.example.midoritei.entity.CartItem;
import org.springframework.stereotype.Service;
import java.util.List;

/**
 * 注文を登録・合計金額を更新するクラス
 */
@Service
public class OrderService {
    
    /**
     * 注文を登録します
     * @param customerId 顧客ID
     * @param cartItems 注文かご内のアイテム
     * @return 合計金額
     */
    public int registerOrder(String customerId, List<CartItem> cartItems) {
        // TODO: 注文を登録してDBに保存
        return 0;
    }
    
    /**
     * 合計金額を計算します
     * @param cartItems 注文かご内のアイテム
     * @return 合計金額
     */
    public int calculateTotal(List<CartItem> cartItems) {
        // TODO: 合計金額を計算
        return 0;
    }
}
