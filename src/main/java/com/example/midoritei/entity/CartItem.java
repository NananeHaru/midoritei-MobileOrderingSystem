package com.example.midoritei.entity;

public class CartItem {
    private Product product;
    private int quantity;
    private String selectedOption;
    private int optionAddPrice;

    // コンストラクタ
    public CartItem(Product product, int quantity, String selectedOption, int optionAddPrice) {
        this.product = product;
        this.quantity = quantity;
        this.selectedOption = selectedOption;
        this.optionAddPrice = optionAddPrice;
    }

    // ★これがControllerから呼ばれているメソッドです
    public void setQuantity(int quantity) {
        this.quantity = quantity;
    }

    // 以下のゲッターも必要です
    public Product getProduct() { return product; }
    public int getQuantity() { return quantity; }
    public String getSelectedOption() { return selectedOption; }
    public int getOptionAddPrice() { return optionAddPrice; }
    
    // 小計計算
    public int getSubtotal() {
        return (product.getPrice() + optionAddPrice) * quantity;
    }
}