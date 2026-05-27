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
        String customerId = idGenerator.generateNextId(storeId);
        
        String sql = "INSERT INTO 顧客テーブル " +
                     "(customer_id, store_id, seat_id, course_id, entry_time, checkout_status, total_amount, number_of_members) " +
                     "VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        jdbcTemplate.update(sql,
            customerId,
            storeId,
            seatId,
            "none".equals(courseId) ? null : courseId,
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
        String sql = "SELECT seat_id, customer_id FROM 顧客テーブル WHERE checkout_status = 0 ORDER BY seat_id ASC";
        return jdbcTemplate.queryForList(sql);
    }

    /**
     * 現在食事中（checkout_status = 0）の座席詳細一覧を取得します。
     */
    public java.util.List<java.util.Map<String, Object>> getActiveSeatsDetail() {
        String sql = "SELECT seat_id, customer_id, number_of_members, course_id, entry_time FROM 顧客テーブル WHERE checkout_status = 0 ORDER BY seat_id ASC";
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

                // 子の顧客レコードを削除（空席化）
                String deleteCustomerSql = "DELETE FROM 顧客テーブル WHERE customer_id = ?";
                jdbcTemplate.update(deleteCustomerSql, childCustomerId);
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
}