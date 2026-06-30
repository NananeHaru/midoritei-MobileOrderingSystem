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
        String trimmedStoreId = storeId != null ? storeId.trim() : "MH";
        
        // 採番テーブルから対象店舗の最新連番をロックをかけて取得
        String selectSql = "SELECT current_number FROM 顧客採番テーブル WHERE store_id = ? FOR UPDATE";
        java.util.List<Integer> numbers = jdbcTemplate.queryForList(selectSql, Integer.class, trimmedStoreId);
        
        int currentNum;
        if (numbers.isEmpty()) {
            // レコードがない場合は、顧客テーブルから現在の最大連番を取得して初期値として復元する
            String maxIdSql = "SELECT MAX(customer_id) FROM 顧客テーブル WHERE store_id = ?";
            String maxId = jdbcTemplate.queryForObject(maxIdSql, String.class, trimmedStoreId);
            
            if (maxId != null && maxId.length() >= 7) {
                try {
                    // IDの末尾5桁を取り出して数値化
                    String numPart = maxId.substring(maxId.length() - 5);
                    currentNum = Integer.parseInt(numPart);
                } catch (NumberFormatException e) {
                    currentNum = 0;
                }
            } else {
                currentNum = 0;
            }
            
            // 顧客採番テーブルに最大値を初期値としてインサート
            String insertSql = "INSERT INTO 顧客採番テーブル (store_id, current_number) VALUES (?, ?)";
            jdbcTemplate.update(insertSql, trimmedStoreId, currentNum);
        } else {
            currentNum = numbers.get(0);
        }

        // カウントアップして更新
        int nextNum = currentNum + 1;
        String updateSql = "UPDATE 顧客採番テーブル SET current_number = ? WHERE store_id = ?";
        jdbcTemplate.update(updateSql, nextNum, trimmedStoreId);

        // 店舗ID(2桁) + 連番(5桁ゼロ埋め) の計7桁 of CHAR(7)
        return String.format("%s%05d", trimmedStoreId, nextNum);
    }
}