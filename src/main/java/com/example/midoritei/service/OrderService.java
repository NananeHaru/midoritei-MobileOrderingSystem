package com.example.midoritei.service;

import com.example.midoritei.entity.CartItem;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.support.GeneratedKeyHolder;
import org.springframework.jdbc.support.KeyHolder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import java.sql.PreparedStatement;
import java.sql.Statement;
import java.time.LocalDateTime;
import java.util.List;
import java.util.Map;

@Service
public class OrderService {

    @Autowired
    private JdbcTemplate jdbcTemplate;

    /**
     * カートの中身を注文テーブルおよび注文詳細テーブルに一括保存します。
     * 合わせて顧客テーブルの合計金額を更新します。
     */
    @Transactional
    public void submitOrder(String customerId, List<CartItem> cartItems) {
        if (cartItems == null || cartItems.isEmpty()) return;

        // 1. 注文テーブルに親レコードを1件インサート
        String orderSql = "INSERT INTO 注文テーブル (customer_id, order_time, order_status) VALUES (?, ?, ?)";
        KeyHolder keyHolder = new GeneratedKeyHolder();

        jdbcTemplate.update(connection -> {
            PreparedStatement ps = connection.prepareStatement(orderSql, Statement.RETURN_GENERATED_KEYS);
            ps.setString(1, customerId);
            ps.setObject(2, LocalDateTime.now());
            ps.setInt(3, 0); // 0: 調理中
            return ps;
        }, keyHolder);

        // 自動生成された order_id を取得
        int orderId = keyHolder.getKey().intValue();

        // 2. 注文詳細テーブルにカゴの中身をすべてインサート
        String detailSql = "INSERT INTO 注文詳細テーブル (order_id, product_id, quantity, option_info, option_add_price) VALUES (?, ?, ?, ?, ?)";
        for (CartItem item : cartItems) {
            jdbcTemplate.update(detailSql,
                orderId,
                item.getProduct().getProductId(),
                item.getQuantity(),
                item.getSelectedOption(),
                item.getOptionAddPrice()
            );
        }

        // 3. 顧客テーブルの「現在の合計金額」も更新
        int subtotal = cartItems.stream().mapToInt(CartItem::getSubtotal).sum();
        String updateCustomerSql = "UPDATE 顧客テーブル SET total_amount = total_amount + ? WHERE customer_id = ?";
        jdbcTemplate.update(updateCustomerSql, subtotal, customerId);
    }

    /**
     * 指定された顧客IDの過去の注文履歴を、注文テーブル、詳細テーブル、商品マスタを結合して取得します。
     */
    public List<Map<String, Object>> getOrderHistory(String customerId) {
        String sql = "SELECT " +
                     "  o.order_time, " +
                     "  o.order_status, " +
                     "  p.product_name, " +
                     "  d.quantity, " +
                     "  d.option_info, " +
                     "  p.price, " +
                     "  d.option_add_price " +
                     "FROM 注文テーブル o " +
                     "JOIN 注文詳細テーブル d ON o.order_id = d.order_id " +
                     "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                     "WHERE o.customer_id = ? " +
                     "ORDER BY o.order_time DESC"; // 新しい注文が上に来るようにソート

        return jdbcTemplate.queryForList(sql, customerId);
    }

    // （既存のクラスの中に追記してください）
    // OrderService.java の一番下にある getTotalBillingAmount を以下に差し替えます

    public int getTotalBillingAmount(String customerId) {
        String sql = "SELECT SUM((p.price + d.option_add_price) * d.quantity) " +
                    "FROM 注文テーブル o " +
                    "JOIN 注文詳細テーブル d ON o.order_id = d.order_id " +
                    "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                    "WHERE o.customer_id = ?";
        
        // queryForObjectで直接intに変換するのではなく、一度「Map」として1行だけ取得します
        try {
            java.util.Map<String, Object> result = jdbcTemplate.queryForMap(sql, customerId);
            // SUMの結果（最初のカラム）を取り出す
            Object totalObj = result.values().iterator().next();
            
            if (totalObj != null) {
                // H2データベースのSUM関数は通常 Long や BigDecimal で返ってくることがあるため、安全にintへ変換します
                return ((Number) totalObj).intValue();
            }
        } catch (Exception e) {
            // まだ注文データが1件もない場合などはここに入ります
            return 0;
        }
        return 0;
    }
}