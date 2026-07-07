package com.example.midoritei;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

@Component
public class CustomerIdGenerator {

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @Transactional
    public String generateNextId(String storeId) {
        // 1. データベース（顧客テーブル）から、7桁以下の数値である顧客IDの最大値を取得
        // ※既存の12桁のIDや英数字ID、テスト用店舗（'99'）のデータを除外して最大値を取得します。
        int currentMaxSeq = 0;
        try {
            String maxSeqSql = "SELECT COALESCE(MAX(CAST(customer_id AS UNSIGNED)), 0) " +
                               "FROM 顧客テーブル " +
                               "WHERE customer_id >= '0000000' AND customer_id <= '9999999' " +
                               "  AND LENGTH(customer_id) = 7 " +
                               "  AND store_id != '99'";
            currentMaxSeq = jdbcTemplate.queryForObject(maxSeqSql, Integer.class);
        } catch (Exception e) {
            // 万が一の例外時は0スタートとする安全ガード
            currentMaxSeq = 0;
        }

        // 2. 次の連番を7桁のゼロ埋め文字列にする (例: 1 -> "0000001")
        return String.format("%07d", currentMaxSeq + 1);
    }
}