package com.example.midoritei.service;

import com.example.midoritei.CustomerIdGenerator;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import java.time.LocalDateTime;
import java.util.List;
import java.util.Map;

@Service
public class SeatingService {

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @Autowired
    private CustomerIdGenerator idGenerator;

    @Autowired
    private OrderService orderService;

    // 人数を指定してテーブルを開設するオーバーロードメソッド
    @Transactional
    public String openTable(String storeId, String seatId, String courseId, int numberOfMembers) {
        // 同一の座席で既に利用中のレコードがあればキャンセル扱いにしてリセットする
        String deleteSql = "UPDATE 顧客テーブル SET checkout_status = 9, seat_id = NULL WHERE store_id = ? AND seat_id = ? AND checkout_status = 1";
        jdbcTemplate.update(deleteSql, storeId, seatId);

        String customerId = idGenerator.generateNextId(storeId);
        
        String targetCourseId = courseId;
        if (targetCourseId == null || "none".equals(targetCourseId.trim())) {
            targetCourseId = "CS01";
        }

        String sql = "INSERT INTO 顧客テーブル " +
                    "(customer_id, store_id, seat_id, course_id, entry_time, checkout_status, total_amount, number_of_members) " +
                    "VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        jdbcTemplate.update(sql,
            customerId,
            storeId,
            seatId,
            targetCourseId, // 💡 三項演算子を消して、画面から来たコード（CS01, CS02, CS03）をそのまま保存！
            LocalDateTime.now(),
            1, // 1:受付中（食事中）
            0, // 初期合計金額は0円
            numberOfMembers
        );

        return customerId; 
    }

    @Transactional
    public String openTable(String storeId, String seatId, String courseId) {
        return openTable(storeId, seatId, courseId, 0);
    }

    /**
     * 現在食事中（checkout_status = 0）の座席と顧客ID一覧を取得します。
     */
    public java.util.List<java.util.Map<String, Object>> getActiveSeats() {
        return getActiveSeats(null);
    }

    public java.util.List<java.util.Map<String, Object>> getActiveSeats(String storeId) {
        String sql = "SELECT seat_id, customer_id FROM 顧客テーブル WHERE checkout_status = 1 ";
        if (storeId != null && !storeId.trim().isEmpty()) {
            sql += "AND store_id = ? ";
            sql += "ORDER BY LENGTH(seat_id) ASC, seat_id ASC";
            return jdbcTemplate.queryForList(sql, storeId);
        }
        sql += "ORDER BY LENGTH(seat_id) ASC, seat_id ASC";
        return jdbcTemplate.queryForList(sql);
    }

    public java.util.List<java.util.Map<String, Object>> getActiveSeatsDetail() {
        return getActiveSeatsDetail(null);
    }

    public java.util.List<java.util.Map<String, Object>> getActiveSeatsDetail(String storeId) {
        String sql = "SELECT c.seat_id, c.customer_id, c.number_of_members, c.course_id, cm.course_name, c.entry_time, c.parent_customer_id, c.merged_at, c.checkout_status " +
                     "FROM 顧客テーブル c " +
                     "LEFT JOIN コースマスタテーブル cm ON TRIM(c.course_id) = TRIM(cm.course_id) " +
                     "WHERE c.checkout_status IN (1, 2, 8) ";
        if (storeId != null && !storeId.trim().isEmpty()) {
            sql += "AND c.store_id = ? ";
            sql += "ORDER BY LENGTH(c.seat_id) ASC, c.seat_id ASC";
            return jdbcTemplate.queryForList(sql, storeId);
        }
        sql += "ORDER BY LENGTH(c.seat_id) ASC, c.seat_id ASC";
        return jdbcTemplate.queryForList(sql);
    }

    /**
     * 座席を変更（席替え）します。
     */
    @Transactional
    public void changeSeat(String storeId, String currentSeatId, String newSeatId) {
        String sql = "UPDATE 顧客テーブル SET seat_id = ? WHERE store_id = ? AND seat_id = ? AND checkout_status = 1";
        jdbcTemplate.update(sql, newSeatId, storeId, currentSeatId);
    }

    /**
     * 複数の座席を統合します。
     */
    @Transactional
    public void mergeSeats(String storeId, String parentSeatId, java.util.List<String> childSeatIds) {
        // 親の情報を取得 (コースIDと終了時刻を含める)
        String parentSql = "SELECT customer_id, course_id, end_time FROM 顧客テーブル WHERE store_id = ? AND seat_id = ? AND checkout_status = 1";
        java.util.List<java.util.Map<String, Object>> parentList = jdbcTemplate.queryForList(parentSql, storeId, parentSeatId);
        if (parentList.isEmpty()) return;
        
        String parentCustomerId = (String) parentList.get(0).get("customer_id");
        String parentCourseId = parentList.get(0).get("course_id") != null ? ((String) parentList.get(0).get("course_id")).trim() : "CS01";
        java.sql.Timestamp parentEndTime = (java.sql.Timestamp) parentList.get(0).get("end_time");

        for (String childSeatId : childSeatIds) {
            String childSql = "SELECT customer_id, course_id, number_of_members FROM 顧客テーブル WHERE store_id = ? AND seat_id = ? AND checkout_status = 1";
            java.util.List<java.util.Map<String, Object>> childList = jdbcTemplate.queryForList(childSql, storeId, childSeatId);
            
            if (!childList.isEmpty()) {
                String childCustomerId = (String) childList.get(0).get("customer_id");
                String childCourseId = childList.get(0).get("course_id") != null ? ((String) childList.get(0).get("course_id")).trim() : "CS01";
                int childMembers = ((Number) childList.get(0).get("number_of_members")).intValue();

                // 親がコースありで、子がそのコースと異なる場合、親のコースに強制依存させる
                if (!"CS01".equals(parentCourseId) && !parentCourseId.equals(childCourseId)) {
                    int parentCoursePrice = 0;
                    int childOldCoursePrice = 0;
                    try {
                        parentCoursePrice = jdbcTemplate.queryForObject(
                            "SELECT price FROM コースマスタテーブル WHERE course_id = ?",
                            Integer.class,
                            parentCourseId
                        );
                        if (!"CS01".equals(childCourseId)) {
                            childOldCoursePrice = jdbcTemplate.queryForObject(
                                "SELECT price FROM コースマスタテーブル WHERE course_id = ?",
                                Integer.class,
                                childCourseId
                            );
                        }
                    } catch (Exception e) {
                        System.out.println("⚠️ コース価格の取得に失敗: " + e.getMessage());
                    }

                    // 1. 旧コース注文明細をキャンセル扱い (serve_status = 9) に更新する
                    if (!"CS01".equals(childCourseId)) {
                        String cancelOldCourseSql = 
                            "UPDATE 注文明細テーブル d " +
                            "JOIN 注文テーブル o ON d.order_id = o.order_id " +
                            "SET d.serve_status = 9 " +
                            "WHERE o.customer_id = ? AND d.product_id = ? AND d.serve_status = 1";
                        jdbcTemplate.update(cancelOldCourseSql, childCustomerId, childCourseId);
                    }

                    // 2. 金額のクリーンな調整
                    int oldAmount = childOldCoursePrice * childMembers;
                    int newAmount = parentCoursePrice * childMembers;

                    // 3. 親の新しいコース商品を新規注文（提供済）として追加登録
                    try {
                        orderService.createCourseOrder(childCustomerId, parentCourseId, childMembers);
                    } catch (Exception e) {
                        System.out.println("⚠️ 親コース商品の追加注文登録に失敗: " + e.getMessage());
                    }

                    // 4. 子の顧客レコードを更新 (コースID変更、金額の差し引き調整、制限時間を親に同期)
                    String updateChildCourseSql = 
                        "UPDATE 顧客テーブル " +
                        "SET course_id = ?, total_amount = total_amount - ? + ?, end_time = ? " +
                        "WHERE customer_id = ?";
                    jdbcTemplate.update(updateChildCourseSql, parentCourseId, oldAmount, newAmount, parentEndTime, childCustomerId);
                    
                    System.out.println("★子席(ID: " + childCustomerId + ")を親のコース(" + parentCourseId + ")に強制移行。金額調整: -" + oldAmount + "円 + " + newAmount + "円。終了時間を親と同期しました。");
                } else {
                    // 親と同じコース、もしくは親がコースなしの場合でも、終了時刻（制限時間）は親と同期させる
                    String updateChildTimeSql = "UPDATE 顧客テーブル SET end_time = ? WHERE customer_id = ?";
                    jdbcTemplate.update(updateChildTimeSql, parentEndTime, childCustomerId);
                }

                // 注文の顧客IDを親に統合
                String updateOrdersSql = "UPDATE 注文テーブル SET customer_id = ? WHERE customer_id = ?";
                jdbcTemplate.update(updateOrdersSql, parentCustomerId, childCustomerId);

                // 子の顧客レコードに親の顧客IDと統合日時をセット
                String updateParentIdSql = "UPDATE 顧客テーブル SET parent_customer_id = ?, merged_at = NOW() WHERE customer_id = ?";
                jdbcTemplate.update(updateParentIdSql, parentCustomerId, childCustomerId);
            }
        }
    }

    /**
     * 座席を空席にします。
     */
    @Transactional
    public void closeTable(String storeId, String seatId) {
        // 1. この座席（または統合されたグループ）に紐づく顧客IDを特定する
        String getCustomerSql = "SELECT customer_id FROM 顧客テーブル WHERE store_id = ? AND seat_id = ? AND checkout_status IN (1, 8)";
        List<Map<String, Object>> customers = jdbcTemplate.queryForList(getCustomerSql, storeId, seatId);
        
        if (!customers.isEmpty()) {
            String customerId = (String) customers.get(0).get("customer_id");
            
            // 2. その顧客（またはグループ全体）に、キャンセルされていない有効な注文があるかチェック！
            String checkOrderSql = "SELECT COUNT(*) FROM 注文テーブル o " +
                                   "JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
                                   "WHERE (o.customer_id = ? OR o.customer_id IN (SELECT customer_id FROM 顧客テーブル WHERE parent_customer_id = ?)) " +
                                   "  AND d.serve_status != 9";
            
            int orderCount = jdbcTemplate.queryForObject(checkOrderSql, Integer.class, customerId, customerId);
            
            // 3. 注文が1件でも残っている場合は、エラーメッセージを投げてクローズを阻止する
            if (orderCount > 0) {
                throw new IllegalStateException("エラー: 注文データが存在するため、この座席を空席にすることはできません。");
            }
        }

        // --- 以下、元のクローズ処理（そのまま残す部分） ---
        // 食事中(1)・会計中(8)のものはキャンセル(9)に更新し、座席との紐付けを解除
        String sql1 = "UPDATE 顧客テーブル SET checkout_status = 9, seat_id = NULL WHERE store_id = ? AND seat_id = ? AND checkout_status IN (1, 8)";
        jdbcTemplate.update(sql1, storeId, seatId);
        
        // 会計済み(2)のものはステータスは維持したまま、座席との紐付けのみを解除
        String sql2 = "UPDATE 顧客テーブル SET seat_id = NULL WHERE store_id = ? AND seat_id = ? AND checkout_status IN (2)";
        jdbcTemplate.update(sql2, storeId, seatId);
    }

    /**
     * テーブル結合（統合）を解除します。
     */
    @Transactional
    public void unmergeSeat(String storeId, String seatId) {
        String selectSql = "SELECT customer_id, parent_customer_id FROM 顧客テーブル WHERE store_id = ? AND seat_id = ? AND checkout_status = 1";
        java.util.List<java.util.Map<String, Object>> seats = jdbcTemplate.queryForList(selectSql, storeId, seatId);
        if (seats.isEmpty()) return;
        
        String customerId = (String) seats.get(0).get("customer_id");
        String parentCustomerId = (String) seats.get(0).get("parent_customer_id");
        
        if (parentCustomerId != null && !parentCustomerId.trim().isEmpty()) {
            // 子席の場合: 自身の親ID連携をクリア
            String sql = "UPDATE 顧客テーブル SET parent_customer_id = NULL WHERE store_id = ? AND seat_id = ? AND checkout_status = 1";
            jdbcTemplate.update(sql, storeId, seatId);
        } else {
            // 親席の場合: 自身を親としている子席 of this store の連携をすべてクリア
            String sql = "UPDATE 顧客テーブル SET parent_customer_id = NULL WHERE store_id = ? AND parent_customer_id = ? AND checkout_status = 1";
            jdbcTemplate.update(sql, storeId, customerId);
        }
    }
}