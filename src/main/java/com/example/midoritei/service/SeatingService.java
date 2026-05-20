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

    @Transactional
    public String openTable(String storeId, String seatId, String courseId) {
        // CHAR(7)の顧客IDを自動生成
        String customerId = idGenerator.generateNextId(storeId);
        
        // 顧客テーブルに新規登録（初期状態をインサート）
        String sql = "INSERT INTO 顧客テーブル " +
                     "(customer_id, store_id, seat_id, course_id, entry_time, checkout_status, total_amount) " +
                     "VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        jdbcTemplate.update(sql,
            customerId,
            storeId,
            seatId,
            "none".equals(courseId) ? null : courseId, // コースなしの場合はNULLを代入
            LocalDateTime.now(),
            0, // 0:利用中
            0  // 初期合計金額は0円
        );

        return customerId; 
    }
}