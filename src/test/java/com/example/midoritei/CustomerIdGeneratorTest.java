package com.example.midoritei;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.transaction.annotation.Transactional;

import static org.junit.jupiter.api.Assertions.assertEquals;

@SpringBootTest
@Transactional
public class CustomerIdGeneratorTest {

    @Autowired
    private CustomerIdGenerator customerIdGenerator;

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @BeforeEach
    public void setUp() {
        // テスト前に顧客テーブルをクリアしてテストの影響を隔離する
        jdbcTemplate.execute("DELETE FROM 顧客テーブル");
    }

    @Test
    public void testGenerateFirstId() {
        // データが無い時は 0000001 が生成されること
        String nextId = customerIdGenerator.generateNextId("MH");
        assertEquals("0000001", nextId);
    }

    @Test
    public void testGenerateSequentialId() {
        // 既存の7桁のIDがある場合、その最大値+1が採番されること
        jdbcTemplate.update("INSERT INTO 顧客テーブル (customer_id, store_id, checkout_status) VALUES (?, ?, ?)", "0000123", "MH", 0);
        
        String nextId = customerIdGenerator.generateNextId("MH");
        assertEquals("0000124", nextId);
    }

    @Test
    public void testIgnoreStore99() {
        // store_id = '99' のテストデータは無視して 0000001 から採番されること
        jdbcTemplate.update("INSERT INTO 顧客テーブル (customer_id, store_id, checkout_status) VALUES (?, ?, ?)", "9900001", "99", 0);
        
        String nextId = customerIdGenerator.generateNextId("MH");
        assertEquals("0000001", nextId);
    }

    @Test
    public void testIgnoreOld12DigitId() {
        // 過去の12桁のIDは無視して 0000001 から採番されること
        jdbcTemplate.update("INSERT INTO 顧客テーブル (customer_id, store_id, checkout_status) VALUES (?, ?, ?)", "012607070001", "MH", 0);
        
        String nextId = customerIdGenerator.generateNextId("MH");
        assertEquals("0000001", nextId);
    }
}
