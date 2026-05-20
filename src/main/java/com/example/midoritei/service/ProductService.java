package com.example.midoritei.service;

import com.example.midoritei.entity.Product;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import java.util.List;

@Service
public class ProductService {

    @Autowired
    private JdbcTemplate jdbcTemplate;

    // 販売ステータスが「0:販売中」の商品だけをDBから持ってくる
    public List<Product> getActiveProducts() {
        String sql = "SELECT product_id, category_id, product_name, price, course_id, sales_status " +
                     "FROM 商品マスタテーブル WHERE sales_status = 0";
        
        return jdbcTemplate.query(sql, (rs, rowNum) -> new Product(
            rs.getString("product_id"),
            rs.getString("category_id"),
            rs.getString("product_name"),
            rs.getInt("price"),
            rs.getString("course_id"),
            rs.getInt("sales_status")
        ));
    }
}