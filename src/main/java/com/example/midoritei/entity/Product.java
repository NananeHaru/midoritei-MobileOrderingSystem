package com.example.midoritei.entity;

public class Product {
    private String productId;
    private String categoryId;
    private String productName;
    private int price;
    private String courseId;
    private int salesStatus;

    public Product(String productId, String categoryId, String productName, int price, String courseId, int salesStatus) {
        this.productId = productId;
        this.categoryId = categoryId;
        this.productName = productName;
        this.price = price;
        this.courseId = courseId;
        this.salesStatus = salesStatus;
    }

    public String getProductId() { return productId; }
    public String getCategoryId() { return categoryId; }
    public String getProductName() { return productName; }
    public int getPrice() { return price; }
    public String getCourseId() { return courseId; }
    public int getSalesStatus() { return salesStatus; }

    // 画像の保存パスを自動生成するメソッド
    public String getImagePath() {
        return "/images/" + this.productId + ".png";
    }
}