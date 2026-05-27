package com.example.midoritei.controller;

import com.example.midoritei.service.OrderService;
import com.example.midoritei.service.SeatingService;
import com.example.midoritei.service.ProductService;
import com.example.midoritei.entity.Product;
import com.example.midoritei.entity.CartItem;
import jakarta.servlet.http.HttpSession;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

@Controller
@RequestMapping("/staff")
public class StaffController {

    @Autowired
    private OrderService orderService;

    @Autowired
    private SeatingService seatingService;

    @Autowired
    private ProductService productService;

    /**
     * ログイン画面を表示します。
     */
    @GetMapping("/login")
    public String loginPage(HttpSession session) {
        if (session != null && session.getAttribute("loggedInStaff") != null) {
            return "redirect:/staff/orders";
        }
        return "staff/login";
    }

    /**
     * ログイン認証を処理します（プランA: 固定値での認証）。
     */
    @PostMapping("/login")
    public String login(@RequestParam("employeeId") String employeeId,
                        @RequestParam("password") String password,
                        HttpSession session,
                        Model model) {
        // 仮のアカウント情報 (プランA)
        boolean isValid = false;
        if (("staff01".equals(employeeId) && "password".equals(password)) ||
            ("admin".equals(employeeId) && "password".equals(password))) {
            isValid = true;
        }

        if (isValid) {
            session.setAttribute("loggedInStaff", employeeId);
            return "redirect:/staff/orders";
        } else {
            model.addAttribute("error", "従業員IDまたはパスワードが正しくありません。");
            return "staff/login";
        }
    }

    /**
     * 注文一覧・提供管理画面を表示します。
     */
    @GetMapping("/orders")
    public String ordersPage(@RequestParam(value = "date", required = false) String dateStr,
                             Model model) {
        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = LocalDate.now().toString();
        }

        // 注文一覧データおよび集計値の取得
        List<Map<String, Object>> orderDetails = orderService.getAllOrderDetails(dateStr);
        Map<String, Object> stats = orderService.getOrderStats(dateStr);

        model.addAttribute("orderDetails", orderDetails);
        model.addAttribute("stats", stats);
        model.addAttribute("selectedDate", dateStr);

        return "staff/orders";
    }

    /**
     * 注文削除画面を表示します。
     */
    @GetMapping("/orders/delete")
    public String ordersDeletePage(@RequestParam(value = "date", required = false) String dateStr,
                                   HttpSession session,
                                   Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = LocalDate.now().toString();
        }

        List<Map<String, Object>> orderDetails = orderService.getAllOrderDetails(dateStr);
        model.addAttribute("orderDetails", orderDetails);
        model.addAttribute("selectedDate", dateStr);

        return "staff/orders_delete";
    }

    /**
     * 注文明細のキャンセル（削除）を処理します。
     */
    @PostMapping("/orders/delete/item")
    public String cancelOrderItem(@RequestParam("orderItemId") int orderItemId,
                                  @RequestParam("cancelQuantity") int cancelQuantity,
                                  @RequestParam(value = "seatId", required = false) String seatId,
                                  @RequestParam(value = "productName", required = false) String productName,
                                  @RequestParam(value = "date", required = false) String dateStr,
                                  HttpSession session,
                                  RedirectAttributes redirectAttributes) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        orderService.cancelOrderItem(orderItemId, cancelQuantity);

        // 削除完了情報をリダイレクト先に渡す（Thymeleaf側で完了モーダル表示に使用）
        redirectAttributes.addFlashAttribute("deletedSeatId", seatId);
        redirectAttributes.addFlashAttribute("deletedProductName", productName);
        redirectAttributes.addFlashAttribute("deletedQuantity", cancelQuantity);
        redirectAttributes.addFlashAttribute("showCompleteModal", true);

        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = LocalDate.now().toString();
        }
        return "redirect:/staff/orders/delete?date=" + dateStr;
    }

    /**
     * 注文の提供ステータスを更新します。
     */
    @PostMapping("/orders/status")
    public String updateStatus(@RequestParam("orderItemId") int orderItemId,
                               @RequestParam("status") int status,
                               @RequestParam(value = "date", required = false) String dateStr) {
        orderService.updateOrderStatus(orderItemId, status);
        
        // 元々選択されていた日付を引き継いでリダイレクト
        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = LocalDate.now().toString();
        }
        return "redirect:/staff/orders?date=" + dateStr;
    }

    /**
     * ログアウトを処理します。
     */
    @GetMapping("/logout")
    public String logout(HttpSession session) {
        if (session != null) {
            session.invalidate();
        }
        return "redirect:/staff/login";
    }

    // ==========================================
    // 代理注文 (注文追加) 機能のエンドポイント
    // ==========================================

    /**
     * 代理注文の座席選択画面を表示します。
     */
    @GetMapping("/orders/add")
    public String ordersAddSeatingPage(HttpSession session, Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        model.addAttribute("activeSeats", seatingService.getActiveSeats());
        return "staff/orders_add_seating";
    }

    /**
     * 選択された座席を確定し、代理注文カートを開始します。
     */
    @PostMapping("/orders/add/select-seat")
    public String selectSeat(@RequestParam("seatId") String seatId,
                             @RequestParam("customerId") String customerId,
                             HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        session.setAttribute("staffSeatId", seatId);
        session.setAttribute("staffCustomerId", customerId);
        session.setAttribute("staffCart", new ArrayList<CartItem>()); // カート初期化
        return "redirect:/staff/orders/add/menu";
    }

    /**
     * 代理注文のメニュー選択画面を表示します。
     */
    @GetMapping("/orders/add/menu")
    public String ordersAddMenuPage(HttpSession session, Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        String seatId = (String) session.getAttribute("staffSeatId");
        if (seatId == null) {
            return "redirect:/staff/orders/add";
        }

        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");
        if (cart == null) {
            cart = new ArrayList<>();
            session.setAttribute("staffCart", cart);
        }

        int totalAmount = cart.stream().mapToInt(CartItem::getSubtotal).sum();

        model.addAttribute("seatId", seatId);
        model.addAttribute("totalAmount", totalAmount);
        model.addAttribute("products", productService.getActiveProducts());
        return "staff/orders_add_menu";
    }

    /**
     * 代理注文カートに商品を追加します。
     */
    @PostMapping("/orders/add/cart/add")
    public String addToStaffCart(@RequestParam("productId") String productId,
                                 @RequestParam("quantity") int quantity,
                                 HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");
        if (cart == null) {
            cart = new ArrayList<>();
            session.setAttribute("staffCart", cart);
        }

        List<Product> allProducts = productService.getActiveProducts();
        Product product = allProducts.stream()
                .filter(p -> p.getProductId().equals(productId))
                .findFirst()
                .orElse(null);

        if (product != null) {
            // 代理注文における同一商品のマージ処理
            boolean exists = false;
            for (int i = 0; i < cart.size(); i++) {
                CartItem item = cart.get(i);
                if (item.getProduct().getProductId().equals(productId)) {
                    // 数量を加算して置き換え
                    cart.set(i, new CartItem(item.getProduct(), item.getQuantity() + quantity, "", 0));
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                cart.add(new CartItem(product, quantity, "", 0));
            }
            session.setAttribute("staffCart", cart);
        }
        return "redirect:/staff/orders/add/menu";
    }

    /**
     * 代理注文のカート（カゴを見る）画面を表示します。
     */
    @GetMapping("/orders/add/cart")
    public String ordersAddCartPage(HttpSession session, Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        String seatId = (String) session.getAttribute("staffSeatId");
        if (seatId == null) {
            return "redirect:/staff/orders/add";
        }

        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");
        if (cart == null) {
            cart = new ArrayList<>();
            session.setAttribute("staffCart", cart);
        }

        int totalAmount = cart.stream().mapToInt(CartItem::getSubtotal).sum();

        model.addAttribute("seatId", seatId);
        model.addAttribute("cart", cart);
        model.addAttribute("totalAmount", totalAmount);
        return "staff/orders_add_cart";
    }

    /**
     * カート内の商品を更新または削除します。
     */
    @PostMapping("/orders/add/cart/update")
    public String updateStaffCart(@RequestParam("productId") String productId,
                                  @RequestParam("quantity") int quantity,
                                  @RequestParam(value = "remove", required = false, defaultValue = "false") boolean remove,
                                  HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");
        if (cart != null) {
            for (int i = 0; i < cart.size(); i++) {
                CartItem item = cart.get(i);
                if (item.getProduct().getProductId().equals(productId)) {
                    if (remove || quantity <= 0) {
                        cart.remove(i);
                    } else {
                        cart.set(i, new CartItem(item.getProduct(), quantity, item.getSelectedOption(), item.getOptionAddPrice()));
                    }
                    break;
                }
            }
            session.setAttribute("staffCart", cart);
        }
        return "redirect:/staff/orders/add/cart";
    }

    /**
     * 代理注文を確定して保存します。
     */
    @PostMapping("/orders/add/cart/submit")
    public String submitStaffOrder(HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        String customerId = (String) session.getAttribute("staffCustomerId");
        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");

        if (customerId != null && cart != null && !cart.isEmpty()) {
            orderService.submitOrder(customerId, cart);

            session.setAttribute("lastOrderSeatId", session.getAttribute("staffSeatId"));
            session.setAttribute("lastOrderCount", cart.stream().mapToInt(CartItem::getQuantity).sum());
            session.setAttribute("lastOrderAmount", cart.stream().mapToInt(CartItem::getSubtotal).sum());

            session.removeAttribute("staffSeatId");
            session.removeAttribute("staffCustomerId");
            session.removeAttribute("staffCart");

            return "redirect:/staff/orders/add/complete";
        }
        return "redirect:/staff/orders/add";
    }

    /**
     * 代理注文の完了画面を表示します。
     */
    @GetMapping("/orders/add/complete")
    public String ordersAddCompletePage(HttpSession session, Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        String seatId = (String) session.getAttribute("lastOrderSeatId");
        if (seatId == null) {
            return "redirect:/staff/orders/add";
        }

        model.addAttribute("seatId", seatId);
        model.addAttribute("itemCount", session.getAttribute("lastOrderCount"));
        model.addAttribute("totalAmount", session.getAttribute("lastOrderAmount"));

        session.removeAttribute("lastOrderSeatId");
        session.removeAttribute("lastOrderCount");
        session.removeAttribute("lastOrderAmount");

        return "staff/orders_add_complete";
    }
}
