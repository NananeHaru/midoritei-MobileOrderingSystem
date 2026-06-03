package com.example.midoritei.controller;

import com.example.midoritei.entity.CartItem;
import com.example.midoritei.entity.Product;
import com.example.midoritei.service.ProductService;
import com.example.midoritei.service.OrderService;
import jakarta.servlet.http.HttpSession;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;

// ★この2行が足りていないとエラーになります！
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
@Controller
public class ProductController {

    @Autowired
    private ProductService productService;

    @Autowired
    private OrderService orderService;

    // 💡 DBから直接SQLを実行するために JdbcTemplate を追加します
    @Autowired
    private org.springframework.jdbc.core.JdbcTemplate jdbcTemplate;

    // -------------------------------------------------------------
    // ⏱️ ★新しく追加：すべての画面で自動的に残り秒数を引き渡す共通処理
    // -------------------------------------------------------------
    @ModelAttribute
    public void addRemainingSeconds(jakarta.servlet.http.HttpSession session, Model model) {
        long remainingSeconds = 0;
        long currentTime = System.currentTimeMillis();
        
        // セッションから客の座席状況を取り出す
        com.example.midoritei.entity.CustomerSession customerSession = 
            (com.example.midoritei.entity.CustomerSession) session.getAttribute("customerSession");
        
        if (customerSession != null) {
            long endTime = customerSession.getSessionEndTime();
            remainingSeconds = (endTime - currentTime) / 1000;
        }
        
        if (remainingSeconds < 0) {
            remainingSeconds = 0;
        }

        // これを書くだけで、このController内にあるすべての画面（/menu, /cart, /orders/history等）に
        // 自動的に remainingSeconds が引き渡されるようになります！
        model.addAttribute("remainingSeconds", remainingSeconds);
    }

    // メニュー一覧
    @GetMapping("/menu")
    public String showMenu(Model model, HttpSession session) {
        List<Product> allProducts = productService.getActiveProducts();
        model.addAttribute("products", allProducts);

        // 💡 【追加】セッションからお客様情報（席開設時に入れたもの）を取得
        com.example.midoritei.entity.CustomerSession customerSession = 
            (com.example.midoritei.entity.CustomerSession) session.getAttribute("customerSession");
        
        // 💡 【追加】コースIDを画面に渡す（もしセッションになければ安全のために「CS01:コースなし」にする）
        String userCourseId = (customerSession != null) ? customerSession.getCourse() : "CS01";
        model.addAttribute("userCourseId", userCourseId);

        List<CartItem> cart = (List<CartItem>) session.getAttribute("cart");
        if (cart == null) { cart = new ArrayList<>(); }

        int totalAmount = cart.stream().mapToInt(CartItem::getSubtotal).sum();
        model.addAttribute("totalAmount", totalAmount);
        model.addAttribute("cartCount", cart.size());

        return "menu";
    }

    // 商品詳細画面
    @GetMapping("/menu/detail/{productId}")
    public String showDetail(@PathVariable("productId") String productId, Model model) {
        List<Product> allProducts = productService.getActiveProducts();
        Product targetProduct = allProducts.stream()
                .filter(p -> p.getProductId().equals(productId))
                .findFirst()
                .orElse(null);

        if (targetProduct == null) { return "redirect:/menu"; }

        model.addAttribute("product", targetProduct);
        model.addAttribute("options", productService.getOptionsByProductId(targetProduct.getProductId()));
        return "detail";
    }

    // カゴに追加
    @PostMapping("/cart/add")
    public String addToCart(@RequestParam("productId") String productId,
                            @RequestParam("quantity") int quantity,
                            @RequestParam(value = "selectedOption", defaultValue = "なし") String selectedOption,
                            @RequestParam(value = "optionAddPrice", defaultValue = "0") int optionAddPrice,
                            HttpSession session) {

        List<Product> allProducts = productService.getActiveProducts();
        Product product = allProducts.stream()
                .filter(p -> p.getProductId().equals(productId))
                .findFirst()
                .orElse(null);

        if (product != null) {
            List<CartItem> cart = (List<CartItem>) session.getAttribute("cart");
            if (cart == null) { cart = new ArrayList<>(); }
            cart.add(new CartItem(product, quantity, selectedOption, optionAddPrice));
            session.setAttribute("cart", cart);
        }
        return "redirect:/menu";
    }

    // ★追加：カゴ画面（カート一覧）の表示
    @GetMapping("/cart")
    public String showCart(Model model, HttpSession session) {
        List<CartItem> cart = (List<CartItem>) session.getAttribute("cart");
        if (cart == null) { cart = new ArrayList<>(); }

        int totalAmount = cart.stream().mapToInt(CartItem::getSubtotal).sum();
        model.addAttribute("cart", cart);
        model.addAttribute("totalAmount", totalAmount);
        return "cart"; // cart.htmlを表示
    }

    // ★追加：カゴから指定した行を削除する処理
    @PostMapping("/cart/remove")
    public String removeFromCart(@RequestParam("index") int index, HttpSession session) {
        List<CartItem> cart = (List<CartItem>) session.getAttribute("cart");
        if (cart != null && index >= 0 && index < cart.size()) {
            cart.remove(index);
            session.setAttribute("cart", cart);
        }
        return "redirect:/cart";
    }

    // ★追加：注文確定ボタンを押した時の処理
    @PostMapping("/cart/submit")
    public String submitOrder(HttpSession session) {
        List<CartItem> cart = (List<CartItem>) session.getAttribute("cart");
        
        if (cart != null && !cart.isEmpty()) {
            String customerId = "0100001";
            com.example.midoritei.entity.CustomerSession customerSession = 
                (com.example.midoritei.entity.CustomerSession) session.getAttribute("customerSession");
            if (customerSession != null) {
                customerId = customerSession.getCustomerId();
            }
            orderService.submitOrder(customerId, cart);
            
            // 注文が確定したのでカゴを空っぽにする
            session.removeAttribute("cart");
        }
        
        // 注文完了後はメニュー一覧に戻す（または「ご注文ありがとうございました」画面でもOK）
        return "redirect:/menu";
    }

    /**
     * 7. 注文履歴画面を表示
     */
    @GetMapping("/orders/history")
    public String showOrderHistory(Model model, HttpSession session) {
        String customerId = "0100001";
        com.example.midoritei.entity.CustomerSession customerSession = 
            (com.example.midoritei.entity.CustomerSession) session.getAttribute("customerSession");
        if (customerSession != null) {
            customerId = customerSession.getCustomerId();
        }
        List<Map<String, Object>> historyList = orderService.getOrderHistory(customerId);
        model.addAttribute("historyList", historyList);
        return "history"; // ★ここが "seating/history" になっていたり、綴りが違っていませんか？
    }

    // （既存のクラスの中に追記してください）

    // ★会計画面の表示（本番環境ルート完全対応版・共通化スッキリ版）
    @GetMapping("/orders/billing")
    public String showBilling(Model model, HttpSession session) {
        String customerId = "0100001"; // 演習用の固定顧客ID
        com.example.midoritei.entity.CustomerSession customerSession = 
            (com.example.midoritei.entity.CustomerSession) session.getAttribute("customerSession");
        if (customerSession != null) {
            customerId = customerSession.getCustomerId();
        }
        
        List<Map<String, Object>> billDetails = orderService.getOrderHistory(customerId);
        int finalAmount = orderService.getTotalBillingAmount(customerId);
        
        model.addAttribute("billDetails", billDetails);
        model.addAttribute("finalAmount", finalAmount);
        model.addAttribute("orderId", customerId); 

        // 💡 タイマー計算のロジック（remainingSecondsの計算部分）は
        // まるごと消去しました！自動で共通処理から引き渡されます。
        
        return "billing"; 
    }
    

@GetMapping("/orders/billing/complete")
public String showBillingComplete(@RequestParam(required = false) String id, Model model, HttpSession session) {
    String customerId = id;
    if (customerId == null || customerId.trim().isEmpty()) {
        customerId = "0100001";
        com.example.midoritei.entity.CustomerSession customerSession = 
            (com.example.midoritei.entity.CustomerSession) session.getAttribute("customerSession");
        if (customerSession != null) {
            customerId = customerSession.getCustomerId();
        }
    }
    
    try {
        // 1. 仕様書に合わせるデータ（オブジェクト）を作成
        PaymentUpdateRequest requestData = new PaymentUpdateRequest();
        requestData.setMethod("updateStatus");   // 仕様書の method 値
        requestData.setCustomerId(customerId);   // 顧客ID
        requestData.setHash(null);               // ハッシュ値（今回は同一性判定しないのでnull）
        requestData.setBillStatus(8);            // 会計中ステータス（値: 8）

        // 2. 相手（レジシステム側）のAPIのURLを設定
        // ※演習環境に合わせてURL（http://localhost:XXXX など）が変わる場合はここを調整します
        String apiUrl = "http://localhost:8080/api/orders"; 

        // 3. RestTemplateを使って、POSTメソッドでJSONデータを送信
        org.springframework.web.client.RestTemplate restTemplate = new org.springframework.web.client.RestTemplate();
        restTemplate.postForObject(apiUrl, requestData, String.class);
        
        System.out.println("★レジAPIへの会計状況更新（status:8）に成功しました！");

    } catch (Exception e) {
        System.out.println("⚠️ API送信でエラーが発生しました: " + e.getMessage());
        // 演習環境によっては相手のAPIサーバーがまだ起動していないとここに落ちます
    }

    // 完了画面に顧客IDを引き渡す処理（既存）
    model.addAttribute("orderId", customerId);
    return "billing_complete"; 
}


    // ★追加：退店ボタンを押した時の処理（セッションをクリアして最初に戻る）
    @PostMapping("/orders/checkout")
    public String checkout(HttpSession session) {
        // セッションに保存されているカートや席情報をすべて消去
        session.invalidate();
        
        // 最初の「注文開始（席選択）」画面にリダイレクト
        return "redirect:/seating/start"; 
    }
}