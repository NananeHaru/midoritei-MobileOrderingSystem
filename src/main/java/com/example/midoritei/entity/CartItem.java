package com.example.midoritei.entity;

public class CartItem {
    private Product product;
    private int quantity;
    private String selectedOption; // 「ロック」「ソーダ割り」など（なければ空文字）
    private int optionAddPrice;

    public CartItem(Product product, int quantity, String selectedOption, int optionAddPrice) {
        this.product = product;
        this.quantity = quantity;
        this.selectedOption = selectedOption;
        this.optionAddPrice = optionAddPrice;
    }

    // 1行ずつの小計金額を計算するメソッド
    public int getSubtotal() {
        return (product.getPrice() + optionAddPrice) * quantity;
    }

    // ゲッター
    public Product getProduct() { return product; }
    public int getQuantity() { return quantity; }
    public String getSelectedOption() { return selectedOption; }
    public int getOptionAddPrice() { return optionAddPrice; }
}