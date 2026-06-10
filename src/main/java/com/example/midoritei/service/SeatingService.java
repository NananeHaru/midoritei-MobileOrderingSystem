package com.example.midoritei.service;

import com.example.midoritei.CustomerIdGenerator;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import java.time.LocalDateTime;

@Service
public class SeatingService {

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @Autowired
    private CustomerIdGenerator idGenerator;

    // 人数を指定してテーブルを開設するオーバーロードメソッド
    @Transactional
    public String openTable(String storeId, String seatId, String courseId, int numberOfMembers) {
        // 同一の座席で既に利用中のレコードがあれば削除してリセットする
        String deleteSql = "DELETE FROM 顧客テーブル WHERE seat_id = ? AND checkout_status = 0";
        jdbcTemplate.update(deleteSql, seatId);

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
            0, // 0:利用中
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
        String sql = "SELECT seat_id, customer_id FROM 顧客テーブル WHERE checkout_status = 0 ";
        if (storeId != null && !storeId.trim().isEmpty()) {
            sql += "AND store_id = ? ";
            sql += "ORDER BY seat_id ASC";
            return jdbcTemplate.queryForList(sql, storeId);
        }
        sql += "ORDER BY seat_id ASC";
        return jdbcTemplate.queryForList(sql);
    }

    public java.util.List<java.util.Map<String, Object>> getActiveSeatsDetail() {
        return getActiveSeatsDetail(null);
    }

    public java.util.List<java.util.Map<String, Object>> getActiveSeatsDetail(String storeId) {
        String sql = "SELECT c.seat_id, c.customer_id, c.number_of_members, c.course_id, cm.course_name, c.entry_time, c.parent_customer_id " +
                     "FROM 顧客テーブル c " +
                     "LEFT JOIN コースマスタテーブル cm ON TRIM(c.course_id) = TRIM(cm.course_id) " +
                     "WHERE c.checkout_status = 0 ";
        if (storeId != null && !storeId.trim().isEmpty()) {
            sql += "AND c.store_id = ? ";
            sql += "ORDER BY c.seat_id ASC";
            return jdbcTemplate.queryForList(sql, storeId);
        }
        sql += "ORDER BY c.seat_id ASC";
        return jdbcTemplate.queryForList(sql);
    }

    /**
     * 座席を変更（席替え）します。
     */
    @Transactional
    public void changeSeat(String currentSeatId, String newSeatId) {
        String sql = "UPDATE 顧客テーブル SET seat_id = ? WHERE seat_id = ? AND checkout_status = 0";
        jdbcTemplate.update(sql, newSeatId, currentSeatId);
    }

    /**
     * 複数の座席を統合します。
     */
    @Transactional
    public void mergeSeats(String parentSeatId, java.util.List<String> childSeatIds) {
        String parentSql = "SELECT customer_id FROM 顧客テーブル WHERE seat_id = ? AND checkout_status = 0";
        String parentCustomerId = jdbcTemplate.queryForObject(parentSql, String.class, parentSeatId);

        for (String childSeatId : childSeatIds) {
            String childSql = "SELECT customer_id FROM 顧客テーブル WHERE seat_id = ? AND checkout_status = 0";
            java.util.List<String> childCustomerIds = jdbcTemplate.queryForList(childSql, String.class, childSeatId);
            
            if (!childCustomerIds.isEmpty()) {
                String childCustomerId = childCustomerIds.get(0);
                
                // 注文の顧客IDを親に統合
                String updateOrdersSql = "UPDATE 注文テーブル SET customer_id = ? WHERE customer_id = ?";
                jdbcTemplate.update(updateOrdersSql, parentCustomerId, childCustomerId);

                // 子の顧客レコードに親の顧客IDをセット（統合関係の保持）
                String updateParentIdSql = "UPDATE 顧客テーブル SET parent_customer_id = ? WHERE customer_id = ?";
                jdbcTemplate.update(updateParentIdSql, parentCustomerId, childCustomerId);
            }
        }
    }

    /**
     * 座席を空席にします。
     */
    @Transactional
    public void closeTable(String seatId) {
        String sql = "DELETE FROM 顧客テーブル WHERE seat_id = ? AND checkout_status = 0";
        jdbcTemplate.update(sql, seatId);
    }

    /**
     * テーブル結合（統合）を解除します。
     */
    @Transactional
    public void unmergeSeat(String seatId) {
        String selectSql = "SELECT customer_id, parent_customer_id FROM 顧客テーブル WHERE seat_id = ? AND checkout_status = 0";
        java.util.List<java.util.Map<String, Object>> seats = jdbcTemplate.queryForList(selectSql, seatId);
        if (seats.isEmpty()) return;
        
        String customerId = (String) seats.get(0).get("customer_id");
        String parentCustomerId = (String) seats.get(0).get("parent_customer_id");
        
        if (parentCustomerId != null && !parentCustomerId.trim().isEmpty()) {
            // 子席の場合: 自身の親ID連携をクリア
            String sql = "UPDATE 顧客テーブル SET parent_customer_id = NULL WHERE seat_id = ? AND checkout_status = 0";
            jdbcTemplate.update(sql, seatId);
        } else {
            // 親席の場合: 自身を親としている子席の連携をすべてクリア
            String sql = "UPDATE 顧客テーブル SET parent_customer_id = NULL WHERE parent_customer_id = ? AND checkout_status = 0";
            jdbcTemplate.update(sql, customerId);
        }
    }
}