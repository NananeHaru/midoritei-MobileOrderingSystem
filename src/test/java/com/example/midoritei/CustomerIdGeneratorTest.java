package com.example.midoritei;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.Map;
import com.example.midoritei.service.OrderService;

import static org.junit.jupiter.api.Assertions.assertEquals;

@SpringBootTest
@Transactional
public class CustomerIdGeneratorTest {

    @Autowired
    private CustomerIdGenerator customerIdGenerator;

    @Autowired
    private OrderService orderService;

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @BeforeEach
    public void setUp() {
        // テスト前に顧客テーブル、注文テーブル、注文明細テーブルをクリアしてテストの影響を隔離する
        jdbcTemplate.execute("DELETE FROM 注文明細テーブル");
        jdbcTemplate.execute("DELETE FROM 注文テーブル");
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
        jdbcTemplate.update("INSERT INTO 顧客テーブル (customer_id, store_id, checkout_status) VALUES (?, ?, ?)", "0000123", "MH", 1);
        
        String nextId = customerIdGenerator.generateNextId("MH");
        assertEquals("0000124", nextId);
    }

    @Test
    public void testIgnoreStore99() {
        // store_id = '99' のテストデータは無視して 0000001 から採番されること
        jdbcTemplate.update("INSERT INTO 顧客テーブル (customer_id, store_id, checkout_status) VALUES (?, ?, ?)", "9900001", "99", 1);
        
        String nextId = customerIdGenerator.generateNextId("MH");
        assertEquals("0000001", nextId);
    }

    @Test
    public void testIgnoreOld12DigitId() {
        // 過去の12桁のIDは無視して 0000001 から採番されること
        jdbcTemplate.update("INSERT INTO 顧客テーブル (customer_id, store_id, checkout_status) VALUES (?, ?, ?)", "012607070001", "MH", 1);
        
        String nextId = customerIdGenerator.generateNextId("MH");
        assertEquals("0000001", nextId);
    }

    @Test
    public void testCreateCourseOrder() {
        String customerId = "0000001";
        
        // 顧客を仮登録
        jdbcTemplate.update("INSERT INTO 顧客テーブル (customer_id, store_id, course_id, number_of_members, checkout_status) VALUES (?, ?, ?, ?, ?)", customerId, "MH", "CS02", 3, 1);

        // コース注文の作成
        orderService.createCourseOrder(customerId, "CS02", 3);

        // 注文レコードが追加されたことを確認
        int orderCount = jdbcTemplate.queryForObject("SELECT COUNT(*) FROM 注文テーブル WHERE customer_id = ?", Integer.class, customerId);
        assertEquals(1, orderCount);

        // 注文詳細を確認
        Map<String, Object> detail = jdbcTemplate.queryForMap(
            "SELECT d.product_id, d.quantity, d.unit_price, d.serve_status " +
            "FROM 注文テーブル o JOIN 注文明細テーブル d ON o.order_id = d.order_id " +
            "WHERE o.customer_id = ?", customerId
        );
        assertEquals("CS02", detail.get("product_id"));
        assertEquals(3, ((Number) detail.get("quantity")).intValue());
        assertEquals(2000, ((Number) detail.get("unit_price")).intValue());
        assertEquals(1, ((Number) detail.get("serve_status")).intValue()); // 提供済

        // 注文履歴APIの確認 (商品マスタとの結合テスト)
        List<Map<String, Object>> history = orderService.getOrderHistory(customerId);
        assertEquals(1, history.size());
        assertEquals("スタンダードコース", history.get(0).get("product_name"));
        assertEquals(2000, ((Number) history.get(0).get("price")).intValue());
        assertEquals(3, ((Number) history.get(0).get("quantity")).intValue());
    }
}
