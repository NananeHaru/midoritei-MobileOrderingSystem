package com.example.midoritei.entity;

public class Product {
    // フィールド（カプセル化のためprivateにします）
    private String productId;
    private String categoryId;
    private String productName;
    private int price;
    private String courseId;
    private int salesStatus;
    private String imagePath; // 画像用のフィールドも合わせて用意しておきます

    // 1. 空のコンストラクタ（new Product() 用）
    public Product() {
    }

    // 2. 引数付きのコンストラクタ
    public Product(String productId, String categoryId, String productName, int price, String courseId, int salesStatus, String imagePath) {
        this.productId = productId;
        this.categoryId = categoryId;
        this.productName = productName;
        this.price = price;
        this.courseId = courseId;
        this.salesStatus = salesStatus;
        this.imagePath = imagePath;
    }

    // 3. ゲッターとセッター（これらが無いとServiceでエラーになります）
    public String getProductId() { return productId; }
    public void setProductId(String productId) { this.productId = productId; }

    public String getCategoryId() { return categoryId; }
    public void setCategoryId(String categoryId) { this.categoryId = categoryId; }

    public String getProductName() { return productName; }
    public void setProductName(String productName) { this.productName = productName; }

    public int getPrice() { return price; }
    public void setPrice(int price) { this.price = price; }

    public String getCourseId() { return courseId; }
    public void setCourseId(String courseId) { this.courseId = courseId; }

    public int getSalesStatus() { return salesStatus; }
    public void setSalesStatus(int salesStatus) { this.salesStatus = salesStatus; }

    // 画像パス用のゲッター・セッター（detail.htmlなどで画像を表示するのに使います）
    public String getImagePath() {
        // もしDBのimagePathが空っぽだったら、ダミー画像を返すようにしておくと安全です
        if (imagePath == null || imagePath.isEmpty()) {
            return "/images/no-image.png"; 
        }
        return imagePath;
    }
    public void setImagePath(String imagePath) { this.imagePath = imagePath; }
}