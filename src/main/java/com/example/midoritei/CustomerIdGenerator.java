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
        // 採番テーブルから現在の最新連番をロックをかけて取得
        String selectSql = "SELECT current_number FROM 顧客採番テーブル FOR UPDATE";
        Integer currentNum = jdbcTemplate.queryForObject(selectSql, Integer.class);
        
        if (currentNum == null) {
            currentNum = 0;
        }

        // カウントアップして更新
        int nextNum = currentNum + 1;
        String updateSql = "UPDATE 顧客採番テーブル SET current_number = ?";
        jdbcTemplate.update(updateSql, nextNum);

        // 店舗ID(2桁) + 連番(5桁ゼロ埋め) の計7桁のCHAR(7)用文字列を生成
        return String.format("%s%05d", storeId, nextNum);
    }
}