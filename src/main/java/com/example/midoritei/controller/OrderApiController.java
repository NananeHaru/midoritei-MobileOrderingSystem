package com.example.midoritei.controller;

import com.example.midoritei.exception.ApiException;
import com.example.midoritei.service.OrderHashCalculator;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.web.bind.annotation.*;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.*;

@RestController
@RequestMapping("/api/orders")
public class OrderApiController {

    private final JdbcTemplate jdbcTemplate;

    public OrderApiController(JdbcTemplate jdbcTemplate) {
        this.jdbcTemplate = jdbcTemplate;
    }

    private static final DateTimeFormatter ISO_FORMATTER = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ss");

    @PostMapping
    public ResponseEntity<?> handleRequest(@RequestBody Map<String, Object> body) {
        if (body == null || !body.containsKey("method")) {
            throw new ApiException("INVALID_REQUEST", "method パラメータは必須です。");
        }
        
        String method = (String) body.get("method");
        if ("getOrders".equals(method)) {
            return getOrders(body);
        } else if ("updateStatus".equals(method)) {
            return updateStatus(body);
        } else {
            throw new ApiException("INVALID_REQUEST", "無効な method です: " + method);
        }
    }

    private ResponseEntity<?> getOrders(Map<String, Object> body) {
        String customerId = (String) body.get("customerId");
        Object billStatusObj = body.get("billStatus");
        String fromTimeStr = (String) body.get("fromTime");
        String toTimeStr = (String) body.get("toTime");

        // 1. パラメータの検証
        if (customerId != null && !customerId.matches("^[0-9]{7}$")) {
            throw new ApiException("INVALID_PARAMETER", "customerId のフォーマットが不正です。7桁の数字を指定してください。");
        }

        List<Integer> targetBillStatuses = new ArrayList<>();
        if (billStatusObj != null) {
            if (billStatusObj instanceof Integer) {
                targetBillStatuses.add((Integer) billStatusObj);
            } else if (billStatusObj instanceof List) {
                for (Object status : (List<?>) billStatusObj) {
                    if (status instanceof Integer) {
                        targetBillStatuses.add((Integer) status);
                    } else {
                        throw new ApiException("INVALID_PARAMETER", "billStatus の型が不正です。");
                    }
                }
            } else {
                throw new ApiException("INVALID_PARAMETER", "billStatus の型が不正です。");
            }
        }

        LocalDateTime fromTime = null;
        LocalDateTime toTime = null;
        try {
            if (fromTimeStr != null) {
                fromTime = LocalDateTime.parse(fromTimeStr, ISO_FORMATTER);
            }
            if (toTimeStr != null) {
                toTime = LocalDateTime.parse(toTimeStr, ISO_FORMATTER);
            }
        } catch (Exception e) {
            throw new ApiException("INVALID_PARAMETER", "日時のフォーマットが不正です。yyyy-MM-dd'T'HH:mm:ss 形式で指定してください。");
        }

        // 2. クエリの構築と実行
        StringBuilder sql = new StringBuilder("SELECT customer_id, store_id, entry_time, checkout_status FROM 顧客テーブル WHERE 1=1 ");
        List<Object> params = new ArrayList<>();

        if (customerId != null) {
            String repId = getRepresentativeCustomerId(customerId);
            sql.append("AND customer_id = ? ");
            params.add(repId);
        } else {
            sql.append("AND parent_customer_id IS NULL "); // 代表顧客のみ
        }

        if (!targetBillStatuses.isEmpty()) {
            sql.append("AND checkout_status IN (");
            for (int i = 0; i < targetBillStatuses.size(); i++) {
                sql.append("?");
                params.add(targetBillStatuses.get(i));
                if (i < targetBillStatuses.size() - 1) {
                    sql.append(", ");
                }
            }
            sql.append(") ");
        }

        if (fromTime != null) {
            sql.append("AND entry_time >= ? ");
            params.add(fromTime);
        }
        if (toTime != null) {
            sql.append("AND entry_time <= ? ");
            params.add(toTime);
        }

        List<Map<String, Object>> customers = jdbcTemplate.queryForList(sql.toString(), params.toArray());
        List<Map<String, Object>> responseList = new ArrayList<>();

        for (Map<String, Object> customer : customers) {
            String cId = (String) customer.get("customer_id");
            String storeId = (String) customer.get("store_id");
            LocalDateTime entryTime = convertToLocalDateTime(customer.get("entry_time"));
            int checkoutStatus = ((Number) customer.get("checkout_status")).intValue();

            // 注文明細の取得
            String detailSql = "SELECT o.order_time, p.product_name, c.category_name, d.unit_price, d.option_add_price, d.quantity, d.serve_status, d.option_info " +
                    "FROM 注文テーブル o " +
                    "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                    "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                    "LEFT JOIN カテゴリーテーブル c ON p.category_id = c.category_id " +
                    "WHERE (o.customer_id = ? OR o.customer_id IN (SELECT customer_id FROM 顧客テーブル WHERE parent_customer_id = ?)) " +
                    "  AND d.serve_status != 9 " +
                    "ORDER BY o.order_time ASC, d.order_item_id ASC";
            
            List<Map<String, Object>> rawDetails = jdbcTemplate.queryForList(detailSql, cId, cId);
            List<Map<String, Object>> itemsList = new ArrayList<>();

            for (Map<String, Object> detail : rawDetails) {
                LocalDateTime orderTime = convertToLocalDateTime(detail.get("order_time"));
                String productName = (String) detail.get("product_name");
                String optionInfo = (String) detail.get("option_info");
                String categoryName = (String) detail.get("category_name");
                int unitPrice = ((Number) detail.get("unit_price")).intValue();
                int optionAddPrice = ((Number) detail.get("option_add_price")).intValue();
                int quantity = ((Number) detail.get("quantity")).intValue();
                int serveStatus = ((Number) detail.get("serve_status")).intValue();

                String menuName = productName;
                if (optionInfo != null && !optionInfo.trim().isEmpty() && !"なし".equals(optionInfo.trim())) {
                    menuName = productName + "（" + optionInfo.trim() + "）";
                }

                Map<String, Object> itemMap = new HashMap<>();
                itemMap.put("orderTime", orderTime.format(ISO_FORMATTER));
                itemMap.put("menuName", menuName);
                itemMap.put("categoryName", categoryName);
                itemMap.put("unitPrice", unitPrice + optionAddPrice);
                itemMap.put("taxRate", 10); // デフォルト10%
                itemMap.put("orderQty", quantity);
                itemMap.put("offerQty", serveStatus == 1 ? quantity : 0);

                itemsList.add(itemMap);
            }

            Map<String, Object> orderMap = new HashMap<>();
            orderMap.put("storeId", storeId);
            orderMap.put("customerId", cId);
            orderMap.put("entryTime", entryTime != null ? entryTime.format(ISO_FORMATTER) : "");
            orderMap.put("billStatus", checkoutStatus);
            orderMap.put("items", itemsList);

            // ハッシュ計算
            String hash = OrderHashCalculator.computeOrderHash(orderMap);
            orderMap.put("hash", hash);

            responseList.add(orderMap);
        }

        return ResponseEntity.ok(responseList);
    }

    private ResponseEntity<?> updateStatus(Map<String, Object> body) {
        String customerId = (String) body.get("customerId");
        Object billStatusObj = body.get("billStatus");
        String hash = (String) body.get("hash");

        // 1. パラメータの検証
        if (customerId == null || !customerId.matches("^[0-9]{7}$")) {
            throw new ApiException("INVALID_PARAMETER", "customerId のフォーマットが不正です。7桁の数字を指定してください。");
        }

        if (billStatusObj == null || !(billStatusObj instanceof Integer)) {
            throw new ApiException("INVALID_PARAMETER", "billStatus は必須であり、数値型でなければなりません。");
        }
        int billStatus = (Integer) billStatusObj;
        
        // 有効なステータス: 0 (初期化用), 1 (受付中), 2 (会計済み), 4 (未収金), 8 (会計中), 9 (キャンセル)
        if (billStatus != 0 && billStatus != 1 && billStatus != 2 && billStatus != 4 && billStatus != 8 && billStatus != 9) {
            throw new ApiException("INVALID_BILL_STATUS", "無効な billStatus です: " + billStatus);
        }

        // 代表IDの取得
        String repId = getRepresentativeCustomerId(customerId);

        // 顧客の存在確認
        String checkSql = "SELECT COUNT(*) FROM 顧客テーブル WHERE customer_id = ?";
        Integer count = jdbcTemplate.queryForObject(checkSql, Integer.class, repId);
        if (count == null || count == 0) {
            throw new ApiException("ORDER_NOT_FOUND", "指定された顧客が見つかりません。");
        }

        // 2. ハッシュ値の検証（hashが指定されている場合のみ）
        if (hash != null) {
            // 現在の注文ハッシュを計算して比較
            Map<String, Object> currentOrder = getSingleOrderMap(repId);
            if (currentOrder == null) {
                throw new ApiException("ORDER_NOT_FOUND", "注文データが見つかりません。");
            }
            String calculatedHash = OrderHashCalculator.computeOrderHash(currentOrder);
            if (!calculatedHash.equals(hash)) {
                throw new ApiException("ORDER_NOT_FOUND", "ハッシュ値が一致しません。注文内容が変更された可能性があります。");
            }
        }

        // 3. ステータスの更新
        String updateSql = "UPDATE 顧客テーブル SET checkout_status = ? WHERE customer_id = ? OR parent_customer_id = ?";
        jdbcTemplate.update(updateSql, billStatus, repId, repId);

        return ResponseEntity.ok(Collections.emptyMap());
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

    private Map<String, Object> getSingleOrderMap(String repId) {
        String sql = "SELECT customer_id, store_id, entry_time, checkout_status FROM 顧客テーブル WHERE customer_id = ?";
        List<Map<String, Object>> list = jdbcTemplate.queryForList(sql, repId);
        if (list.isEmpty()) {
            return null;
        }
        Map<String, Object> customer = list.get(0);
        String storeId = (String) customer.get("store_id");
        LocalDateTime entryTime = convertToLocalDateTime(customer.get("entry_time"));
        int checkoutStatus = ((Number) customer.get("checkout_status")).intValue();

        String detailSql = "SELECT o.order_time, p.product_name, c.category_name, d.unit_price, d.option_add_price, d.quantity, d.serve_status, d.option_info " +
                "FROM 注文テーブル o " +
                "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                "JOIN 商品マスタテーブル p ON d.product_id = p.product_id " +
                "LEFT JOIN カテゴリーテーブル c ON p.category_id = c.category_id " +
                "WHERE (o.customer_id = ? OR o.customer_id IN (SELECT customer_id FROM 顧客テーブル WHERE parent_customer_id = ?)) " +
                "  AND d.serve_status != 9 " +
                "ORDER BY o.order_time ASC, d.order_item_id ASC";
        
        List<Map<String, Object>> rawDetails = jdbcTemplate.queryForList(detailSql, repId, repId);
        List<Map<String, Object>> itemsList = new ArrayList<>();

        for (Map<String, Object> detail : rawDetails) {
            LocalDateTime orderTime = convertToLocalDateTime(detail.get("order_time"));
            String productName = (String) detail.get("product_name");
            String optionInfo = (String) detail.get("option_info");
            String categoryName = (String) detail.get("category_name");
            int unitPrice = ((Number) detail.get("unit_price")).intValue();
            int optionAddPrice = ((Number) detail.get("option_add_price")).intValue();
            int quantity = ((Number) detail.get("quantity")).intValue();
            int serveStatus = ((Number) detail.get("serve_status")).intValue();

            String menuName = productName;
            if (optionInfo != null && !optionInfo.trim().isEmpty() && !"なし".equals(optionInfo.trim())) {
                menuName = productName + "（" + optionInfo.trim() + "）";
            }

            Map<String, Object> itemMap = new HashMap<>();
            itemMap.put("orderTime", orderTime.format(ISO_FORMATTER));
            itemMap.put("menuName", menuName);
            itemMap.put("categoryName", categoryName);
            itemMap.put("unitPrice", unitPrice + optionAddPrice);
            itemMap.put("taxRate", 10);
            itemMap.put("orderQty", quantity);
            itemMap.put("offerQty", serveStatus == 1 ? quantity : 0);

            itemsList.add(itemMap);
        }

        Map<String, Object> orderMap = new HashMap<>();
        orderMap.put("storeId", storeId);
        orderMap.put("customerId", repId);
        orderMap.put("entryTime", entryTime != null ? entryTime.format(ISO_FORMATTER) : "");
        orderMap.put("billStatus", checkoutStatus);
        orderMap.put("items", itemsList);

        return orderMap;
    }

    private LocalDateTime convertToLocalDateTime(Object obj) {
        if (obj == null) return null;
        if (obj instanceof java.sql.Timestamp) {
            return ((java.sql.Timestamp) obj).toLocalDateTime();
        }
        if (obj instanceof LocalDateTime) {
            return (LocalDateTime) obj;
        }
        return null;
    }
}
