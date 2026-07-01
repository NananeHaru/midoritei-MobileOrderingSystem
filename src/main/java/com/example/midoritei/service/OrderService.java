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

        String resolvedCustomerId = getRepresentativeCustomerId(customerId);

        // 顧客のコースIDを取得（CS01: コースなし, CS02: スタンダード, CS03: プレミアム）
        String courseId = "CS01";
        try {
            courseId = jdbcTemplate.queryForObject(
                "SELECT course_id FROM 顧客テーブル WHERE customer_id = ?",
                String.class,
                resolvedCustomerId
            );
        } catch (Exception e) {
            // 例外時はデフォルト値とする
        }
        if (courseId == null) {
            courseId = "CS01";
        }
        String trimmedCourseId = courseId.trim();

        // 親コースID（parent_course_id）を引き当て
        String parentCourseId = trimmedCourseId;
        if (!trimmedCourseId.isEmpty()) {
            try {
                parentCourseId = jdbcTemplate.queryForObject(
                    "SELECT COALESCE(parent_course_id, course_id) FROM コースマスタテーブル WHERE TRIM(course_id) = ?",
                    String.class,
                    trimmedCourseId
                );
                if (parentCourseId != null) {
                    parentCourseId = parentCourseId.trim();
                }
            } catch (Exception e) {
                parentCourseId = trimmedCourseId;
            }
        }

        // コースに応じてカート内商品の単価を０円に変更
        for (CartItem item : cartItems) {
            com.example.midoritei.entity.Product product = item.getProduct();
            if (product != null) {
                String prodCourseId = (product.getCourseId() != null) ? product.getCourseId().trim() : "";
                if ("CS03".equals(parentCourseId)) { // premium
                    if ("CS02".equals(prodCourseId) || "CS03".equals(prodCourseId)) {
                        product.setPrice(0);
                    }
                } else if ("CS02".equals(parentCourseId)) { // standard
                    if ("CS02".equals(prodCourseId)) {
                        product.setPrice(0);
                    }
                }
            }
        }

        // 1. 🛠️ 【本番仕様・毎日リセット型】注文IDの自動生成
        // 基準となる日付プレフィックスを作成 (例: OR260630)
        String datePrefix = "OR" + java.time.format.DateTimeFormatter.ofPattern("yyMMdd").format(java.time.LocalDateTime.now());

        // 今日のこのプレフィックスから始まる注文が何件あるか（最大連番）を高速に取得
        // ※末尾4桁を数値化して最大値を取ります。データがなければ 0 が返ります。
        int currentMaxSeq = 0;
        try {
            String maxSeqSql = "SELECT COALESCE(MAX(CAST(RIGHT(order_id, 4) AS UNSIGNED)), 0) " +
                               "FROM 注文テーブル WHERE order_id LIKE ?";
            currentMaxSeq = jdbcTemplate.queryForObject(maxSeqSql, Integer.class, datePrefix + "%");
        } catch (Exception e) {
            // 万が一の例外時は0スタートとする安全ガード
            currentMaxSeq = 0;
        }

        // 次の連番を4桁のゼロ埋め文字列にする (例: 1 -> "0001", 15 -> "0015")
        String seqPart = String.format("%04d", currentMaxSeq + 1);

        // ガッチャンコして新しい12桁の注文IDを確定 (例: "OR2606300001")
        String orderId = datePrefix + seqPart;

        
        // 2. 注文テーブルに親レコードを1件インサート
        String orderSql = "INSERT INTO 注文テーブル (order_id, customer_id, order_time, status) VALUES (?, ?, ?, 0)";
        jdbcTemplate.update(orderSql, orderId, resolvedCustomerId, java.time.LocalDateTime.now());

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
                item.getProduct().getPrice(), // unit_price (すでに0円調整済み)
                optionId,
                item.getSelectedOption(),
                item.getOptionAddPrice()
            );
        }

        // 4. 顧客テーブルの「現在の合計金額」も更新
        int subtotal = cartItems.stream().mapToInt(CartItem::getSubtotal).sum();
        String updateCustomerSql = "UPDATE 顧客テーブル SET total_amount = total_amount + ? WHERE customer_id = ?";
        jdbcTemplate.update(updateCustomerSql, subtotal, resolvedCustomerId);
    }

    /**
     * 指定された顧客IDの過去 of 注文履歴を、注文テーブル、詳細テーブル、商品マスタを結合して取得します。
     */
    public List<Map<String, Object>> getOrderHistory(String customerId) {
        String resolvedCustomerId = getRepresentativeCustomerId(customerId);
        String sql = "SELECT " +
                     "  o.order_time, " +
                     "  d.serve_status AS order_status, " + // 既存Thymeleafテンプレートの互換性のためのエイリアス
                     "  p.product_name, " +
                     "  d.quantity, " +
                     "  d.option_info, " +
                     "  d.unit_price AS price, " + // p.price から d.unit_price に変更
                     "  d.option_add_price " +
                     "FROM 注文テーブル o " +
                     "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                     "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                     "WHERE o.customer_id = ? AND d.serve_status != 9 " +
                     "ORDER BY o.order_time DESC";

        return jdbcTemplate.queryForList(sql, resolvedCustomerId);
    }

    // -------------------------------------------------------------
    // 💰 【修正版】DB（顧客テーブル）に保存されている合計金額をそのまま返す
    // -------------------------------------------------------------
    public int getTotalBillingAmount(String customerId) {
        String resolvedCustomerId = getRepresentativeCustomerId(customerId);
        String sql = "SELECT total_amount FROM 顧客テーブル WHERE customer_id = ?";
        try {
            return jdbcTemplate.queryForObject(sql, Integer.class, resolvedCustomerId);
        } catch (Exception e) {
            System.out.println("⚠️ 顧客テーブルからの合計金額取得に失敗しました: " + e.getMessage());
            return 0;
        }
    }

    /**
     * 指定された日付（yyyy-MM-dd）のすべての注文詳細を取得します。
     * 日付指定がない場合は本日分を取得します。
     */
    public List<Map<String, Object>> getAllOrderDetails(String dateStr) {
        return getAllOrderDetails(dateStr, "all", null, null);
    }

    /**
     * 指定された日付（yyyy-MM-dd）の注文詳細を取得し、提供状況で絞り込みます。
     */
    public List<Map<String, Object>> getAllOrderDetails(String dateStr, String statusFilter) {
        return getAllOrderDetails(dateStr, statusFilter, null, null);
    }

    /**
     * 指定された日付（yyyy-MM-dd）および店舗IDの注文詳細を取得し、提供状況で絞り込みます。
     */
    public List<Map<String, Object>> getAllOrderDetails(String dateStr, String statusFilter, String storeId) {
        return getAllOrderDetails(dateStr, statusFilter, storeId, null);
    }

    /**
     * 指定された日付（yyyy-MM-dd）、店舗ID、および座席番号の注文詳細を取得し、提供状況で絞り込みます。
     */
    public List<Map<String, Object>> getAllOrderDetails(String dateStr, String statusFilter, String storeId, String seatId) {
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
                     "  d.serve_status AS order_status, " +
                     "  d.order_item_id AS order_detail_id, " +
                     "  d.product_id, " +
                     "  d.unit_price, " +
                     "  d.option_add_price, " +
                     "  o.order_time " +
                     "FROM 注文テーブル o " +
                     "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                     "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                     "JOIN 顧客テーブル c ON o.customer_id = c.customer_id " +
                     "WHERE o.order_time >= ? AND o.order_time <= ? AND d.serve_status != 9 ";

        java.util.List<Object> params = new java.util.ArrayList<>();
        params.add(start);
        params.add(end);

        if (storeId != null && !storeId.trim().isEmpty()) {
            sql += "AND c.store_id = ? ";
            params.add(storeId);
        }

        if (seatId != null && !seatId.trim().isEmpty()) {
            sql += "AND c.seat_id = ? ";
            params.add(seatId);
        }

        if ("undelivered".equals(statusFilter)) {
            sql += "AND d.serve_status = 0 ";
        } else if ("delivered".equals(statusFilter)) {
            sql += "AND d.serve_status = 1 ";
        }

        sql += "ORDER BY o.order_time DESC, d.order_item_id ASC";

        return jdbcTemplate.queryForList(sql, params.toArray());
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
     * 注文明細をキャンセル（削除）します。
     * 部分キャンセルの場合は、元の数量を減らし、キャンセル分を新規レコードとしてserve_status = 9で挿入します。
     */
    @Transactional
    public void cancelOrderItem(int orderItemId, int cancelQuantity) {
        // 1. 注文明細情報を取得
        String selectSql = "SELECT order_id, product_id, quantity, unit_price, option_id, option_info, option_add_price, serve_status " +
                           "FROM 注文明細テーブル WHERE order_item_id = ?";
        List<Map<String, Object>> details = jdbcTemplate.queryForList(selectSql, orderItemId);
        if (details.isEmpty()) {
            return;
        }
        Map<String, Object> detail = details.get(0);
        int currentQuantity = ((Number) detail.get("quantity")).intValue();
        int serveStatus = ((Number) detail.get("serve_status")).intValue();
        if (serveStatus == 9 || cancelQuantity <= 0 || cancelQuantity > currentQuantity) {
            return; // すでにキャンセル済み、または数量不正
        }

        String orderId = (String) detail.get("order_id");
        String productId = (String) detail.get("product_id");
        int unitPrice = ((Number) detail.get("unit_price")).intValue();
        Integer optionId = (Integer) detail.get("option_id");
        String optionInfo = (String) detail.get("option_info");
        int optionAddPrice = ((Number) detail.get("option_add_price")).intValue();

        // 2. 数量の判定による更新・挿入
        if (cancelQuantity == currentQuantity) {
            // 全数キャンセルの場合: serve_status を 9 に更新
            String updateStatusSql = "UPDATE 注文明細テーブル SET serve_status = 9 WHERE order_item_id = ?";
            jdbcTemplate.update(updateStatusSql, orderItemId);
        } else {
            // 部分キャンセルの場合: 元のレコードの数量を減算
            String updateQtySql = "UPDATE 注文明細テーブル SET quantity = ? WHERE order_item_id = ?";
            jdbcTemplate.update(updateQtySql, currentQuantity - cancelQuantity, orderItemId);

            // キャンセルされた分のレコードを新規挿入
            String insertCancelSql = "INSERT INTO 注文明細テーブル " +
                                     "(order_id, product_id, quantity, unit_price, option_id, option_info, option_add_price, serve_status) " +
                                     "VALUES (?, ?, ?, ?, ?, ?, ?, 9)";
            jdbcTemplate.update(insertCancelSql, orderId, productId, cancelQuantity, unitPrice, optionId, optionInfo, optionAddPrice);
        }

        // 3. 顧客テーブルの total_amount を減算
        String selectCustomerSql = "SELECT customer_id FROM 注文テーブル WHERE order_id = ?";
        String customerId = jdbcTemplate.queryForObject(selectCustomerSql, String.class, orderId);
        
        if (customerId != null) {
            int cancelAmount = (unitPrice + optionAddPrice) * cancelQuantity;
            String updateCustomerSql = "UPDATE 顧客テーブル SET total_amount = total_amount - ? WHERE customer_id = ?";
            jdbcTemplate.update(updateCustomerSql, cancelAmount, customerId);
        }

        // 4. 親注文テーブルのstatus更新判定
        // 同一注文IDに紐づくすべての明細がserve_status = 9（キャンセル）になった場合、親のステータスも9にする
        String countAllSql = "SELECT COUNT(*) FROM 注文明細テーブル WHERE order_id = ?";
        String countCancelSql = "SELECT COUNT(*) FROM 注文明細テーブル WHERE order_id = ? AND serve_status = 9";
        
        int allCount = jdbcTemplate.queryForObject(countAllSql, Integer.class, orderId);
        int cancelCount = jdbcTemplate.queryForObject(countCancelSql, Integer.class, orderId);
        
        if (allCount > 0 && allCount == cancelCount) {
            String updateParentSql = "UPDATE 注文テーブル SET status = 9 WHERE order_id = ?";
            jdbcTemplate.update(updateParentSql, orderId);
        }
    }

    /**
     * 指定された日付の本日の提供数・未提供数の統計を取得します。
     */
    public Map<String, Object> getOrderStats(String dateStr) {
        return getOrderStats(dateStr, null);
    }

    /**
     * 指定された日付および店舗IDの本日の提供数・未提供数の統計を取得します。
     */
    public Map<String, Object> getOrderStats(String dateStr, String storeId) {
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
                     "JOIN 顧客テーブル c ON o.customer_id = c.customer_id " +
                     "WHERE o.order_time >= ? AND o.order_time <= ? ";

        if (storeId != null && !storeId.trim().isEmpty()) {
            sql += "AND c.store_id = ? ";
            return jdbcTemplate.queryForMap(sql, start, end, storeId);
        } else {
            return jdbcTemplate.queryForMap(sql, start, end);
        }
    }

    private String getRepresentativeCustomerId(String customerId) {
        if (customerId == null) return null;
        try {
            String sql = "SELECT COALESCE(parent_customer_id, customer_id) FROM 顧客テーブル WHERE customer_id = ?";
            return jdbcTemplate.queryForObject(sql, String.class, customerId);
        } catch (Exception e) {
            return customerId;
        }
    }
}