package com.example.midoritei.controller;

import com.example.midoritei.entity.Product;
import com.example.midoritei.service.ProductService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import java.util.List;

@Controller
public class ProductController {

    @Autowired
    private ProductService productService;

    // メニュー一覧
    @GetMapping("/menu")
    public String showMenu(Model model) {
        List<Product> allProducts = productService.getActiveProducts();
        model.addAttribute("products", allProducts);
        return "menu";
    }

    // ★追加：商品詳細画面（個数・オプション選択）
    @GetMapping("/menu/detail/{productId}")
    public String showDetail(@PathVariable("productId") String productId, Model model) {
        List<Product> allProducts = productService.getActiveProducts();
        
        // クリックされたIDの商品を特定する
        Product targetProduct = allProducts.stream()
                .filter(p -> p.getProductId().equals(productId))
                .findFirst()
                .orElse(null);

        if (targetProduct == null) {
            return "redirect:/menu"; // なければ一覧に戻す
        }

        model.addAttribute("product", targetProduct);
        return "detail"; // detail.htmlを表示
    }
}