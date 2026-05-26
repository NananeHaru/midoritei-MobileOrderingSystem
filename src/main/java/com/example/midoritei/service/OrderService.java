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

        // 1. 注文ID（15桁）を生成 (例: OR + yyMMddHHmmss + ランダム1桁)
        String orderId = "OR" + java.time.format.DateTimeFormatter.ofPattern("yyMMddHHmmss").format(java.time.LocalDateTime.now()) + (int)(Math.random() * 10);

        // 2. 注文テーブルに親レコードを1件インサート
        String orderSql = "INSERT INTO 注文テーブル (order_id, customer_id, order_time, status) VALUES (?, ?, ?, 0)";
        jdbcTemplate.update(orderSql, orderId, customerId, java.time.LocalDateTime.now());

        // 3. 注文明細テーブルにカゴの中身をすべてインサート
        String detailSql = "INSERT INTO 注文明細テーブル (order_id, product_id, quantity, unit_price, option_id, option_info, option_add_price, serve_status) VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
        for (CartItem item : cartItems) {
            // オプション名からオプションIDを逆引き取得
            Integer optionId = null;
            if (item.getSelectedOption() != null && !item.getSelectedOption().trim().isEmpty()) {
                try {
                    optionId = jdbcTemplate.queryForObject(
                        "SELECT option_id FROM オプションマスタテーブル WHERE option_name = ?",
                        Integer.class,
                        item.getSelectedOption()
                    );
                } catch (Exception e) {
                    // オプションIDが見つからない場合はnullのまま
                }
            }

            jdbcTemplate.update(detailSql,
                orderId,
                item.getProduct().getProductId(),
                item.getQuantity(),
                item.getProduct().getPrice(), // unit_price
                optionId,
                item.getSelectedOption(),
                item.getOptionAddPrice()
            );
        }

        // 4. 顧客テーブルの「現在の合計金額」も更新
        int subtotal = cartItems.stream().mapToInt(CartItem::getSubtotal).sum();
        String updateCustomerSql = "UPDATE 顧客テーブル SET total_amount = total_amount + ? WHERE customer_id = ?";
        jdbcTemplate.update(updateCustomerSql, subtotal, customerId);
    }

    /**
     * 指定された顧客IDの過去 of 注文履歴を、注文テーブル、詳細テーブル、商品マスタを結合して取得します。
     */
    public List<Map<String, Object>> getOrderHistory(String customerId) {
        String sql = "SELECT " +
                     "  o.order_time, " +
                     "  d.serve_status AS order_status, " + // 既存Thymeleafテンプレートの互換性のためのエイリアス
                     "  p.product_name, " +
                     "  d.quantity, " +
                     "  d.option_info, " +
                     "  p.price, " +
                     "  d.option_add_price " +
                     "FROM 注文テーブル o " +
                     "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                     "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                     "WHERE o.customer_id = ? " +
                     "ORDER BY o.order_time DESC";

        return jdbcTemplate.queryForList(sql, customerId);
    }

    // （既存のクラスの中に追記してください）
    // OrderService.java の一番下にある getTotalBillingAmount を以下に差し替えます

    public int getTotalBillingAmount(String customerId) {
        String sql = "SELECT SUM((p.price + d.option_add_price) * d.quantity) " +
                    "FROM 注文テーブル o " +
                    "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
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

    /**
     * 指定された日付（yyyy-MM-dd）のすべての注文詳細を取得します。
     * 日付指定がない場合は本日分を取得します。
     */
    public List<Map<String, Object>> getAllOrderDetails(String dateStr) {
        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = java.time.LocalDate.now().toString();
        }
        
        java.time.LocalDateTime start = java.time.LocalDate.parse(dateStr).atStartOfDay();
        java.time.LocalDateTime end = java.time.LocalDate.parse(dateStr).atTime(java.time.LocalTime.MAX);

        String sql = "SELECT " +
                     "  o.order_id, " +
                     "  c.seat_id, " +
                     "  p.product_name, " +
                     "  d.quantity, " +
                     "  d.option_info, " +
                     "  d.serve_status AS order_status, " + // 既存Thymeleafテンプレートの互換用のエイリアス
                     "  d.order_item_id AS order_detail_id, " + // 既存コントローラ・ビュー互換用のエイリアス
                     "  o.order_time " +
                     "FROM 注文テーブル o " +
                     "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                     "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                     "JOIN 顧客テーブル c ON o.customer_id = c.customer_id " +
                     "WHERE o.order_time >= ? AND o.order_time <= ? " +
                     "ORDER BY o.order_time DESC, d.order_item_id ASC";

        return jdbcTemplate.queryForList(sql, start, end);
    }

    /**
     * 注文の提供ステータスを更新します。
     */
    @Transactional
    public void updateOrderStatus(int orderItemId, int status) {
        String sql = "UPDATE 注文明細テーブル SET serve_status = ? WHERE order_item_id = ?";
        jdbcTemplate.update(sql, status, orderItemId);
    }

    /**
     * 指定された日付の本日の提供数・未提供数の統計を取得します。
     */
    public Map<String, Object> getOrderStats(String dateStr) {
        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = java.time.LocalDate.now().toString();
        }
        
        java.time.LocalDateTime start = java.time.LocalDate.parse(dateStr).atStartOfDay();
        java.time.LocalDateTime end = java.time.LocalDate.parse(dateStr).atTime(java.time.LocalTime.MAX);

        String sql = "SELECT " +
                     "  COALESCE(SUM(CASE WHEN d.serve_status = 0 THEN d.quantity ELSE 0 END), 0) AS unpaid_count, " +
                     "  COALESCE(SUM(CASE WHEN d.serve_status = 1 THEN d.quantity ELSE 0 END), 0) AS paid_count " +
                     "FROM 注文テーブル o " +
                     "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                     "WHERE o.order_time >= ? AND o.order_time <= ?";

        return jdbcTemplate.queryForMap(sql, start, end);
    }
}