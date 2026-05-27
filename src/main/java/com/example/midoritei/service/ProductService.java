package com.example.midoritei.service;

import com.example.midoritei.entity.Product;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

@Service
public class ProductService {

    @Autowired
    private JdbcTemplate jdbcTemplate;

    // RowMapper用のラムダ式
    private final org.springframework.jdbc.core.RowMapper<Product> productRowMapper = (rs, rowNum) -> {
        Product product = new Product();
        product.setProductId(rs.getString("product_id"));
        product.setCategoryId(rs.getString("category_id"));
        product.setProductName(rs.getString("product_name"));
        product.setPrice(rs.getInt("price"));
        product.setCourseId(rs.getString("course_id"));
        product.setSalesStatus(rs.getInt("sales_status"));
        product.setDescription(rs.getString("description"));
        product.setIsSeasonal(rs.getInt("is_seasonal"));
        product.setImagePath(rs.getString("image_path"));
        return product;
    };

    /**
     * 販売中（sales_status = 0）の商品のみを取得します。
     */
    public List<Product> getActiveProducts() {
        String sql = "SELECT product_id, category_id, product_name, price, course_id, sales_status, description, is_seasonal, image_path " +
                     "FROM 商品マスタテーブル WHERE sales_status = 0 OR sales_status = 1";
        return jdbcTemplate.query(sql, productRowMapper);
    }

    /**
     * すべての商品を取得します（販売停止中のものも含む）。
     */
    public List<Product> getAllProducts() {
        String sql = "SELECT product_id, category_id, product_name, price, course_id, sales_status, description, is_seasonal, image_path " +
                     "FROM 商品マスタテーブル ORDER BY product_id ASC";
        return jdbcTemplate.query(sql, productRowMapper);
    }

    /**
     * すべてのカテゴリーを取得します。
     */
    public List<Map<String, Object>> getAllCategories() {
        String sql = "SELECT category_id, category_name, display_order FROM カテゴリーテーブル ORDER BY display_order ASC";
        return jdbcTemplate.queryForList(sql);
    }

    /**
     * カテゴリーに紐づくオプションを取得します（分類・オプション紐づけテーブル経由）。
     */
    public List<Map<String, Object>> getOptionsByCategoryId(String categoryId) {
        String sql = "SELECT o.option_id, o.option_name, o.option_add_price " +
                     "FROM オプションマスタテーブル o " +
                     "JOIN \"分類・オプション紐づけテーブル\" m ON o.option_id = m.option_id " +
                     "WHERE m.category_id = ? " +
                     "ORDER BY o.option_id ASC";
        return jdbcTemplate.queryForList(sql, categoryId);
    }

    /**
     * 商品IDで個別の商品を取得します。
     */
    public Product getProductById(String productId) {
        String sql = "SELECT product_id, category_id, product_name, price, course_id, sales_status, description, is_seasonal, image_path " +
                     "FROM 商品マスタテーブル WHERE product_id = ?";
        List<Product> list = jdbcTemplate.query(sql, productRowMapper, productId);
        return list.isEmpty() ? null : list.get(0);
    }

    /**
     * 商品の販売ステータスを反転します（0:販売中 ↔ 1:品切れ/停止）。
     */
    @Transactional
    public void toggleSalesStatus(String productId) {
        String selectSql = "SELECT sales_status FROM 商品マスタテーブル WHERE product_id = ?";
        Integer currentStatus = jdbcTemplate.queryForObject(selectSql, Integer.class, productId);
        if (currentStatus != null) {
            int nextStatus = (currentStatus == 0) ? 1 : 0;
            String updateSql = "UPDATE 商品マスタテーブル SET sales_status = ? WHERE product_id = ?";
            jdbcTemplate.update(updateSql, nextStatus, productId);
        }
    }

    /**
     * 商品の販売ステータスを直接更新します（0:販売中、1:品切れ、2:販売停止中）。
     */
    @Transactional
    public void updateSalesStatus(String productId, int status) {
        String sql = "UPDATE 商品マスタテーブル SET sales_status = ? WHERE product_id = ?";
        jdbcTemplate.update(sql, status, productId);
    }

    /**
     * 商品を追加または更新します。
     */
    @Transactional
    public void saveProduct(Product product) {
        Product existing = getProductById(product.getProductId());
        if (existing != null) {
            // 更新 (UPDATE)
            String sql = "UPDATE 商品マスタテーブル SET category_id = ?, product_name = ?, price = ?, course_id = ?, sales_status = ?, description = ?, is_seasonal = ?, image_path = ? " +
                         "WHERE product_id = ?";
            jdbcTemplate.update(sql,
                product.getCategoryId(),
                product.getProductName(),
                product.getPrice(),
                product.getCourseId(),
                product.getSalesStatus(),
                product.getDescription(),
                product.getIsSeasonal(),
                product.getImagePath(),
                product.getProductId()
            );
        } else {
            // 新規追加 (INSERT)
            String nextId = product.getProductId();
            if (nextId == null || nextId.trim().isEmpty()) {
                nextId = generateNextProductId(product.getCategoryId());
                product.setProductId(nextId); // 自動採番されたIDを書き戻す
            }
            String sql = "INSERT INTO 商品マスタテーブル (product_id, category_id, product_name, price, course_id, sales_status, description, is_seasonal, image_path) " +
                         "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            jdbcTemplate.update(sql,
                nextId,
                product.getCategoryId(),
                product.getProductName(),
                product.getPrice(),
                product.getCourseId(),
                product.getSalesStatus(),
                product.getDescription(),
                product.getIsSeasonal(),
                product.getImagePath()
            );
        }
    }

    /**
     * 新規商品IDを自動採番します。
     */
    private synchronized String generateNextProductId(String categoryId) {
        String prefix = "CT01".equals(categoryId) ? "PR" : "FP";
        String sql = "SELECT MAX(product_id) FROM 商品マスタテーブル WHERE product_id LIKE ?";
        String maxId = jdbcTemplate.queryForObject(sql, String.class, prefix + "%");
        int nextNum = 1;
        if (maxId != null && maxId.length() == 6) {
            try {
                nextNum = Integer.parseInt(maxId.substring(2)) + 1;
            } catch (NumberFormatException e) {
                // 解析失敗時はデフォルトの1
            }
        }
        return String.format("%s%04d", prefix, nextNum);
    }

    /**
     * 複数条件による商品の検索を行います。
     */
    public List<Product> searchProducts(String productName, String keyword, List<String> categoryIds, List<String> priceRanges, List<String> courseIds, boolean seasonalOnly, List<Integer> salesStatuses, String searchOperator) {
        StringBuilder sql = new StringBuilder("SELECT product_id, category_id, product_name, price, course_id, sales_status, description, is_seasonal, image_path FROM 商品マスタテーブル WHERE 1=1");
        List<Object> params = new ArrayList<>();

        boolean isOr = "OR".equalsIgnoreCase(searchOperator);

        // 1. 商品名部分一致 (常に AND)
        if (productName != null && !productName.trim().isEmpty()) {
            sql.append(" AND product_name LIKE ?");
            params.add("%" + productName.trim() + "%");
        }

        // 2. キーワード部分一致 (常に AND)
        if (keyword != null && !keyword.trim().isEmpty()) {
            sql.append(" AND (product_name LIKE ? OR description LIKE ?)");
            params.add("%" + keyword.trim() + "%");
            params.add("%" + keyword.trim() + "%");
        }

        // 3. カテゴリーID + 季節限定 の結合
        boolean hasCategory = categoryIds != null && !categoryIds.isEmpty();
        boolean hasSeasonal = seasonalOnly;

        if (hasCategory || hasSeasonal) {
            if (isOr) {
                // OR の場合: (category_id IN (A, B) OR is_seasonal = 1)
                sql.append(" AND (");
                boolean needsOr = false;
                if (hasCategory) {
                    sql.append("category_id IN (");
                    for (int i = 0; i < categoryIds.size(); i++) {
                        sql.append(i == 0 ? "?" : ", ?");
                        params.add(categoryIds.get(i));
                    }
                    sql.append(")");
                    needsOr = true;
                }
                if (hasSeasonal) {
                    if (needsOr) sql.append(" OR ");
                    sql.append("is_seasonal = 1");
                }
                sql.append(")");
            } else {
                // AND の場合: category_id = A AND category_id = B AND is_seasonal = 1
                if (hasCategory) {
                    for (String catId : categoryIds) {
                        sql.append(" AND category_id = ?");
                        params.add(catId);
                    }
                }
                if (hasSeasonal) {
                    sql.append(" AND is_seasonal = 1");
                }
            }
        }

        // 4. 価格帯の結合
        if (priceRanges != null && !priceRanges.isEmpty()) {
            List<String> priceConds = new ArrayList<>();
            for (String range : priceRanges) {
                if ("~500".equals(range)) {
                    priceConds.add("price <= 500");
                } else if ("500~1000".equals(range)) {
                    priceConds.add("(price >= 500 AND price <= 1000)");
                } else if ("1000~2000".equals(range)) {
                    priceConds.add("(price >= 1000 AND price <= 2000)");
                } else if ("2000~3000".equals(range)) {
                    priceConds.add("(price >= 2000 AND price <= 3000)");
                } else if ("3000~4000".equals(range)) {
                    priceConds.add("(price >= 3000 AND price <= 4000)");
                } else if ("4000~5000".equals(range)) {
                    priceConds.add("(price >= 4000 AND price <= 5000)");
                } else if ("5000~".equals(range)) {
                    priceConds.add("price >= 5000");
                }
            }

            if (!priceConds.isEmpty()) {
                if (isOr) {
                    sql.append(" AND (");
                    for (int i = 0; i < priceConds.size(); i++) {
                        sql.append(i == 0 ? "" : " OR ");
                        sql.append(priceConds.get(i));
                    }
                    sql.append(")");
                } else {
                    for (String cond : priceConds) {
                        sql.append(" AND ").append(cond);
                    }
                }
            }
        }

        // 5. 対象コースの結合
        if (courseIds != null && !courseIds.isEmpty()) {
            List<String> courseConds = new ArrayList<>();
            List<String> courseParams = new ArrayList<>();
            for (String course : courseIds) {
                if ("none".equals(course)) {
                    courseConds.add("course_id IS NULL");
                } else {
                    courseConds.add("course_id = ?");
                    courseParams.add(course);
                }
            }

            if (!courseConds.isEmpty()) {
                if (isOr) {
                    sql.append(" AND (");
                    int paramIndex = 0;
                    for (int i = 0; i < courseConds.size(); i++) {
                        sql.append(i == 0 ? "" : " OR ");
                        String cond = courseConds.get(i);
                        sql.append(cond);
                        if (cond.contains("?")) {
                            params.add(courseParams.get(paramIndex++));
                        }
                    }
                    sql.append(")");
                } else {
                    int paramIndex = 0;
                    for (String cond : courseConds) {
                        sql.append(" AND ").append(cond);
                        if (cond.contains("?")) {
                            params.add(courseParams.get(paramIndex++));
                        }
                    }
                }
            }
        }

        // 6. 状態（販売ステータス）の結合
        if (salesStatuses != null && !salesStatuses.isEmpty()) {
            if (isOr) {
                sql.append(" AND sales_status IN (");
                for (int i = 0; i < salesStatuses.size(); i++) {
                    sql.append(i == 0 ? "?" : ", ?");
                    params.add(salesStatuses.get(i));
                }
                sql.append(")");
            } else {
                for (Integer status : salesStatuses) {
                    sql.append(" AND sales_status = ?");
                    params.add(status);
                }
            }
        }

        sql.append(" ORDER BY product_id ASC");
        return jdbcTemplate.query(sql.toString(), productRowMapper, params.toArray());
    }

    /**
     * 商品に直接紐づくオプションの一覧を取得します（商品オプション紐づけテーブル経由）。
     */
    public List<Map<String, Object>> getOptionsByProductId(String productId) {
        String sql = "SELECT o.option_id, o.option_name, o.option_add_price " +
                     "FROM オプションマスタテーブル o " +
                     "JOIN \"商品オプション紐づけテーブル\" m ON o.option_id = m.option_id " +
                     "WHERE m.product_id = ? " +
                     "ORDER BY o.option_id ASC";
        return jdbcTemplate.queryForList(sql, productId);
    }

    /**
     * 商品とオプションの紐づけ情報を更新・保存します。
     */
    @Transactional
    public void saveProductOptions(String productId, List<Integer> optionIds) {
        // 一度、既存の紐づけを全削除
        String deleteSql = "DELETE FROM \"商品オプション紐づけテーブル\" WHERE product_id = ?";
        jdbcTemplate.update(deleteSql, productId);

        // 新しくチェックされたオプションIDをインサート
        if (optionIds != null && !optionIds.isEmpty()) {
            String insertSql = "INSERT INTO \"商品オプション紐づけテーブル\" (product_id, option_id) VALUES (?, ?)";
            for (Integer optionId : optionIds) {
                jdbcTemplate.update(insertSql, productId, optionId);
            }
        }
    }
}