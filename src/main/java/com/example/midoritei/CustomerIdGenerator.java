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
        // 1. 🛠️ 店舗ID（2桁）＋今日の日付（6桁）のプレフィックスを作成 (例: "01" + "260630" -> "01260630")
        String datePart = java.time.format.DateTimeFormatter.ofPattern("yyMMdd").format(java.time.LocalDateTime.now());
        String customerPrefix = storeId + datePart;

        // 2. 今日のこの店舗の最大連番をDB（顧客テーブル）から高速に取得
        // ※末尾4桁を数値化して最大値を取ります。データがなければ 0 が返ります。
        int currentMaxSeq = 0;
        try {
            String maxSeqSql = "SELECT COALESCE(MAX(CAST(RIGHT(customer_id, 4) AS UNSIGNED)), 0) " +
                               "FROM 顧客テーブル WHERE customer_id LIKE ?";
            currentMaxSeq = jdbcTemplate.queryForObject(maxSeqSql, Integer.class, customerPrefix + "%");
        } catch (Exception e) {
            // 万が一の例外時は0スタートとする安全ガード
            currentMaxSeq = 0;
        }

        // 3. 次の連番を4桁のゼロ埋め文字列にする (例: 1 -> "0001")
        String seqPart = String.format("%04d", currentMaxSeq + 1);

        // 4. ガッチャンコして新しい12桁の顧客IDを確定 (例: "012606300001")
        return customerPrefix + seqPart;
    }
}