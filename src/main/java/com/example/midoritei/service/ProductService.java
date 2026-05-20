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

    /**
     * 商品マスタテーブルから、販売ステータスが「0（販売中）」の商品のみを取得します。
     */
    public List<Product> getActiveProducts() {
        // 設計図のテーブル名「商品マスタテーブル」とカラム名に合わせています
        String sql = "SELECT product_id, category_id, product_name, price, course_id, sales_status FROM 商品マスタテーブル WHERE sales_status = 0";
        
        return jdbcTemplate.query(sql, (rs, rowNum) -> {
            Product product = new Product();
            product.setProductId(rs.getString("product_id"));
            product.setCategoryId(rs.getString("category_id"));
            product.setProductName(rs.getString("product_name"));
            product.setPrice(rs.getInt("price"));
            product.setCourseId(rs.getString("course_id"));
            product.setSalesStatus(rs.getInt("sales_status"));
            return product;
        });
    }
}