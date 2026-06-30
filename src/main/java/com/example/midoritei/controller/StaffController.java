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

    @Autowired
    private org.springframework.jdbc.core.JdbcTemplate jdbcTemplate;

    /**
     * スタッフのルートURL（/staff または /staff/）にアクセスした際、
     * ログイン状態であれば注文一覧へ、未ログインであればログイン画面へリダイレクトします。
     */
    @GetMapping({"", "/"})
    public String index(HttpSession session) {
        if (session != null && session.getAttribute("loggedInStaff") != null) {
            return "redirect:/staff/orders";
        }
        return "redirect:/staff/login";
    }

    /**
     * ログイン画面を表示します。
     */
    @GetMapping("/login")
    public String loginPage(HttpSession session, Model model) {
        if (session != null && session.getAttribute("loggedInStaff") != null) {
            return "redirect:/staff/orders";
        }
        // 店舗リストを取得してモデルに追加
        List<Map<String, Object>> stores = jdbcTemplate.queryForList("SELECT store_id, store_name FROM 店舗マスタテーブル");
        model.addAttribute("stores", stores);
        return "staff/login";
    }

    /**
     * ログイン認証を処理します（プランA: 固定値での認証）。
     */
    @PostMapping("/login")
    public String login(@RequestParam("employeeId") String employeeId,
                        @RequestParam("password") String password,
                        @RequestParam("storeId") String storeId,
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
            session.setAttribute("loggedInStoreId", storeId);
            
            // 店舗名を取得してセッションに格納
            try {
                String storeName = jdbcTemplate.queryForObject(
                    "SELECT store_name FROM 店舗マスタテーブル WHERE store_id = ?",
                    String.class,
                    storeId
                );
                session.setAttribute("loggedInStoreName", storeName);
            } catch (Exception e) {
                session.setAttribute("loggedInStoreName", "不明な店舗");
            }
            
            return "redirect:/staff/orders";
        } else {
            // 店舗リストを再取得してモデルに追加
            List<Map<String, Object>> stores = jdbcTemplate.queryForList("SELECT store_id, store_name FROM 店舗マスタテーブル");
            model.addAttribute("stores", stores);
            model.addAttribute("error", "従業員IDまたはパスワードが正しくありません。");
            return "staff/login";
        }
    }

    /**
     * 注文一覧・提供管理画面を表示します。
     */
    @GetMapping("/orders")
    public String ordersPage(@RequestParam(value = "date", required = false) String dateStr,
                             @RequestParam(value = "statusFilter", defaultValue = "all") String statusFilter,
                             @RequestParam(value = "seatId", required = false) String seatId,
                             HttpSession session,
                             Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = LocalDate.now().toString();
        }

        String storeId = (String) session.getAttribute("loggedInStoreId");

        // 注文一覧データおよび集計値の取得
        List<Map<String, Object>> orderDetails = orderService.getAllOrderDetails(dateStr, statusFilter, storeId, seatId);
        Map<String, Object> stats = orderService.getOrderStats(dateStr, storeId);

        model.addAttribute("orderDetails", orderDetails);
        model.addAttribute("stats", stats);
        model.addAttribute("selectedDate", dateStr);
        model.addAttribute("statusFilter", statusFilter);
        model.addAttribute("selectedSeatId", seatId);

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

        String storeId = (String) session.getAttribute("loggedInStoreId");

        List<Map<String, Object>> orderDetails = orderService.getAllOrderDetails(dateStr, "all", storeId);
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
                               @RequestParam(value = "date", required = false) String dateStr,
                               @RequestParam(value = "statusFilter", defaultValue = "all") String statusFilter,
                               @RequestParam(value = "seatId", required = false) String seatId) {
        orderService.updateOrderStatus(orderItemId, status);
        
        // 元々選択されていた日付を引き継いでリダイレクト
        if (dateStr == null || dateStr.trim().isEmpty()) {
            dateStr = LocalDate.now().toString();
        }
        String redirectUrl = "redirect:/staff/orders?date=" + dateStr + "&statusFilter=" + statusFilter;
        if (seatId != null && !seatId.trim().isEmpty()) {
            redirectUrl += "&seatId=" + seatId;
        }
        return redirectUrl;
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
        String storeId = (String) session.getAttribute("loggedInStoreId");
        model.addAttribute("activeSeats", seatingService.getActiveSeatsDetail(storeId));
        
        List<Map<String, Object>> courses = jdbcTemplate.queryForList(
            "SELECT course_id, course_name FROM コースマスタテーブル ORDER BY course_id ASC"
        );
        model.addAttribute("courses", courses);
        
        return "staff/orders_add_seating";
    }

    /**
     * 選択された座席を確定し、代理注文カートを開始します。
     */
    @PostMapping("/orders/add/select-seat")
    public String selectSeat(@RequestParam("seatId") String seatId,
                             @RequestParam("customerId") String customerId,
                             @RequestParam("courseId") String courseId,
                             HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        
        // 顧客テーブルのコースを更新（現在のコースがコースなし「CS01」の場合のみ変更を許可）
        jdbcTemplate.update(
            "UPDATE 顧客テーブル SET course_id = ? WHERE customer_id = ? AND (TRIM(course_id) = 'CS01' OR TRIM(course_id) = ?)",
            courseId,
            customerId,
            courseId
        );
        
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
        String customerId = (String) session.getAttribute("staffCustomerId");
        if (seatId == null) {
            return "redirect:/staff/orders/add";
        }

        String courseId = getCourseIdByCustomerId(customerId);

        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");
        if (cart == null) {
            cart = new ArrayList<>();
            session.setAttribute("staffCart", cart);
        }

        for (CartItem item : cart) {
            adjustProductPriceByCourse(item.getProduct(), courseId);
        }

        int totalAmount = cart.stream().mapToInt(CartItem::getSubtotal).sum();

        List<Product> activeProducts = productService.getActiveProducts();
        adjustProductPricesByCourse(activeProducts, courseId);

        String courseName = getCourseNameByCustomerId(customerId);

        model.addAttribute("seatId", seatId);
        model.addAttribute("courseName", courseName);
        model.addAttribute("totalAmount", totalAmount);
        model.addAttribute("products", activeProducts);
        return "staff/orders_add_menu";
    }

    /**
     * 代理注文カートに商品を追加します。
     */
    @PostMapping("/orders/add/cart/add")
    public String addToStaffCart(@RequestParam("productId") String productId,
                                 @RequestParam("quantity") int quantity,
                                 @RequestParam(value = "selectedOption", defaultValue = "") String selectedOption,
                                 @RequestParam(value = "optionAddPrice", defaultValue = "0") int optionAddPrice,
                                 HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        String customerId = (String) session.getAttribute("staffCustomerId");
        String courseId = getCourseIdByCustomerId(customerId);

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
            adjustProductPriceByCourse(product, courseId);

            // 代理注文における同一商品のマージ処理 (商品IDとオプション情報が両方一致する場合のみマージ)
            boolean exists = false;
            for (int i = 0; i < cart.size(); i++) {
                CartItem item = cart.get(i);
                if (item.getProduct().getProductId().equals(productId) && item.getSelectedOption().equals(selectedOption)) {
                    // 数量を加算して置き換え
                    cart.set(i, new CartItem(item.getProduct(), item.getQuantity() + quantity, selectedOption, optionAddPrice));
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                cart.add(new CartItem(product, quantity, selectedOption, optionAddPrice));
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
        String customerId = (String) session.getAttribute("staffCustomerId");
        if (seatId == null) {
            return "redirect:/staff/orders/add";
        }

        String courseId = getCourseIdByCustomerId(customerId);

        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");
        if (cart == null) {
            cart = new ArrayList<>();
            session.setAttribute("staffCart", cart);
        }

        for (CartItem item : cart) {
            adjustProductPriceByCourse(item.getProduct(), courseId);
        }

        int totalAmount = cart.stream().mapToInt(CartItem::getSubtotal).sum();

        String courseName = getCourseNameByCustomerId(customerId);

        model.addAttribute("seatId", seatId);
        model.addAttribute("courseName", courseName);
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
        String customerId = (String) session.getAttribute("staffCustomerId");
        String courseId = getCourseIdByCustomerId(customerId);

        List<CartItem> cart = (List<CartItem>) session.getAttribute("staffCart");
        if (cart != null) {
            for (int i = 0; i < cart.size(); i++) {
                CartItem item = cart.get(i);
                if (item.getProduct().getProductId().equals(productId)) {
                    if (remove || quantity <= 0) {
                        cart.remove(i);
                    } else {
                        adjustProductPriceByCourse(item.getProduct(), courseId);
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
            String courseId = getCourseIdByCustomerId(customerId);
            for (CartItem item : cart) {
                adjustProductPriceByCourse(item.getProduct(), courseId);
            }

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

    // ==========================================
    // 座席一覧 (フロア座席管理) 機能のエンドポイント
    // ==========================================

    /**
     * 座席一覧画面を表示します。
     */
    @GetMapping("/seating")
    public String seatingPage(HttpSession session, Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        String storeId = (String) session.getAttribute("loggedInStoreId");

        // 食事中（checkout_status = 0）の席一覧を取得
        List<Map<String, Object>> activeSeats = seatingService.getActiveSeatsDetail(storeId);
        
        // 親子関係を持つグループの整理
        // 親の顧客ID -> グループ情報 (ラベル, カラー)
        Map<String, String> groupLabels = new java.util.HashMap<>();
        Map<String, String> groupColors = new java.util.HashMap<>();
        String[] labels = new String[26];
        for (int j = 0; j < 26; j++) labels[j] = String.valueOf((char) ('A' + j));
        String[] colors = {"#1a73e8", "#a142f4", "#e8710a", "#12b5cb", "#e52592", "#137333"};

        // 統合グループの親顧客IDを、子席の merged_at 最小値昇順（統合操作を行った順）で収集してラベルを割り当て
        List<String> sortedParentIds = activeSeats.stream()
            .filter(s -> s.get("parent_customer_id") != null)
            .map(s -> ((String) s.get("parent_customer_id")).trim())
            .distinct()
            .sorted((id1, id2) -> {
                java.sql.Timestamp t1 = activeSeats.stream()
                    .filter(s -> s.get("parent_customer_id") != null && id1.equals(((String) s.get("parent_customer_id")).trim()))
                    .map(s -> (java.sql.Timestamp) s.get("merged_at"))
                    .filter(t -> t != null)
                    .min(java.util.Comparator.naturalOrder()).orElse(null);
                java.sql.Timestamp t2 = activeSeats.stream()
                    .filter(s -> s.get("parent_customer_id") != null && id2.equals(((String) s.get("parent_customer_id")).trim()))
                    .map(s -> (java.sql.Timestamp) s.get("merged_at"))
                    .filter(t -> t != null)
                    .min(java.util.Comparator.naturalOrder()).orElse(null);
                if (t1 == null && t2 == null) return 0;
                if (t1 == null) return 1;
                if (t2 == null) return -1;
                return t1.compareTo(t2);
            })
            .collect(java.util.stream.Collectors.toList());

        for (int i = 0; i < sortedParentIds.size(); i++) {
            int labelIdx = i % 26;
            groupLabels.put(sortedParentIds.get(i), labels[labelIdx]);
            groupColors.put(sortedParentIds.get(i), colors[labelIdx % colors.length]);
        }
        
        // データベースから店舗の全座席を取得
        List<Map<String, Object>> storeSeats = jdbcTemplate.queryForList(
            "SELECT seat_id, seat_type FROM 座席マスタテーブル WHERE store_id = ? ORDER BY LENGTH(seat_id) ASC, seat_id ASC",
            storeId
        );
        
        // 座席状態をマージしたデータを構築
        List<Map<String, Object>> seats = new java.util.ArrayList<>();
        for (Map<String, Object> s : storeSeats) {
            final String seatId = (String) s.get("seat_id");
            final String seatType = (String) s.get("seat_type");
            Map<String, Object> seatMap = new java.util.HashMap<>();
            seatMap.put("seatId", seatId);
            seatMap.put("type", seatType);
            
            // 現在の席データがあるか探索
            Map<String, Object> activeSeat = activeSeats.stream()
                .filter(as -> seatId.equals(String.valueOf(as.get("seat_id"))))
                .findFirst()
                .orElse(null);
            
            if (activeSeat != null) {
                seatMap.put("status", "使用中");
                String myCustomerId = activeSeat.get("customer_id") != null ? ((String) activeSeat.get("customer_id")).trim() : "";
                seatMap.put("customerId", myCustomerId);
                seatMap.put("numberOfMembers", activeSeat.get("number_of_members"));
                seatMap.put("courseId", activeSeat.get("course_id") != null ? ((String) activeSeat.get("course_id")).trim() : null);
                seatMap.put("courseName", activeSeat.get("course_name") != null ? ((String) activeSeat.get("course_name")).trim() : "コースなし");
                seatMap.put("entryTime", activeSeat.get("entry_time"));
                
                // 親の顧客ID
                String parentCustomerId = activeSeat.get("parent_customer_id") != null ? ((String) activeSeat.get("parent_customer_id")).trim() : null;
                
                if (parentCustomerId != null) {
                    // 自分は子席
                    seatMap.put("parentCustomerId", parentCustomerId);
                    // 親座席の番号を取得
                    String parentSeatId = activeSeats.stream()
                        .filter(as -> parentCustomerId.equals(as.get("customer_id") != null ? ((String) as.get("customer_id")).trim() : null))
                        .map(as -> String.valueOf(as.get("seat_id")))
                        .findFirst()
                        .orElse(null);
                    seatMap.put("parentSeatId", parentSeatId);
                    
                    // グループ情報の適用
                    if (groupLabels.containsKey(parentCustomerId)) {
                        seatMap.put("groupId", parentCustomerId);
                        seatMap.put("groupLabel", groupLabels.get(parentCustomerId));
                        seatMap.put("groupColor", groupColors.get(parentCustomerId));
                    }
                } else {
                    // 自分は親席、あるいは単独席
                    // 自分が親（＝自分を親顧客IDとしている子がいる）か確認
                    List<String> childSeatIds = activeSeats.stream()
                        .filter(as -> as.get("parent_customer_id") != null && myCustomerId.equals(((String) as.get("parent_customer_id")).trim()))
                        .map(as -> String.valueOf(as.get("seat_id")))
                        .collect(java.util.stream.Collectors.toList());
                    
                    if (!childSeatIds.isEmpty()) {
                        seatMap.put("childSeatIds", childSeatIds);
                        if (groupLabels.containsKey(myCustomerId)) {
                            seatMap.put("groupId", myCustomerId);
                            seatMap.put("groupLabel", groupLabels.get(myCustomerId));
                            seatMap.put("groupColor", groupColors.get(myCustomerId));
                        }
                    }
                }
            } else {
                seatMap.put("status", "空席");
            }
            seats.add(seatMap);
        }

        model.addAttribute("seats", seats);
        return "staff/seating_management";
    }

    /**
     * 空席を使用中に変更（席開設）します。
     */
    @PostMapping("/seating/open")
    public String openTable(@RequestParam("seatId") String seatId,
                            HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        
        String storeId = (String) session.getAttribute("loggedInStoreId");
        // 初期値：店舗ID=storeId, 人数=0名, コース=なし(CS01)
        seatingService.openTable(storeId, seatId, "CS01", 0);
        return "redirect:/staff/seating";
    }

    /**
     * 使用中の座席を空席に変更します。
     */
    @PostMapping("/seating/close")
    public String closeTable(@RequestParam("seatId") String seatId,
                             HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        
        String storeId = (String) session.getAttribute("loggedInStoreId");
        seatingService.closeTable(storeId, seatId);
        return "redirect:/staff/seating";
    }

    /**
     * 席替え（座席変更）を処理します。
     */
    @PostMapping("/seating/change")
    public String changeSeat(@RequestParam("currentSeatId") String currentSeatId,
                             @RequestParam("newSeatId") String newSeatId,
                             HttpSession session) {
         if (session == null || session.getAttribute("loggedInStaff") == null) {
             return "redirect:/staff/login";
         }
         
         String storeId = (String) session.getAttribute("loggedInStoreId");
         seatingService.changeSeat(storeId, currentSeatId, newSeatId);
         return "redirect:/staff/seating";
     }

    /**
     * 座席統合を処理します。
     */
    @PostMapping("/seating/merge")
    public String mergeSeats(@RequestParam("parentSeatId") String parentSeatId,
                             @RequestParam("childSeatIds") List<String> childSeatIds,
                             HttpSession session) {
         if (session == null || session.getAttribute("loggedInStaff") == null) {
             return "redirect:/staff/login";
         }
         
         String storeId = (String) session.getAttribute("loggedInStoreId");
         seatingService.mergeSeats(storeId, parentSeatId, childSeatIds);
         return "redirect:/staff/seating";
     }

    /**
     * テーブル結合（統合）解除を処理します。
     */
    @PostMapping("/seating/unmerge")
    public String unmergeSeat(@RequestParam("seatId") String seatId,
                              HttpSession session) {
         if (session == null || session.getAttribute("loggedInStaff") == null) {
             return "redirect:/staff/login";
         }
         
         String storeId = (String) session.getAttribute("loggedInStoreId");
         seatingService.unmergeSeat(storeId, seatId);
         return "redirect:/staff/seating";
     }

    private void adjustProductPriceByCourse(Product product, String courseId) {
        if (product == null) return;
        String trimmedCourseId = (courseId != null) ? courseId.trim() : "";
        String prodCourseId = (product.getCourseId() != null) ? product.getCourseId().trim() : "";
        
        // 親コースID（parent_course_id）を引き当て
        String parentCourseId = trimmedCourseId;
        if (!trimmedCourseId.isEmpty()) {
            try {
                parentCourseId = jdbcTemplate.queryForObject(
                    "SELECT COALESCE(parent_course_id, course_id) FROM コースマスタテーブル WHERE TRIM(course_id) = ?",
                    String.class,
                    trimmedCourseId
                );
                if (parentCourseId != null) {
                    parentCourseId = parentCourseId.trim();
                }
            } catch (Exception e) {
                parentCourseId = trimmedCourseId;
            }
        }

        if ("CS03".equals(parentCourseId)) { // premium
            if ("CS02".equals(prodCourseId) || "CS03".equals(prodCourseId)) {
                product.setPrice(0);
            }
        } else if ("CS02".equals(parentCourseId)) { // standard
            if ("CS02".equals(prodCourseId)) {
                product.setPrice(0);
            }
        }
    }

    private void adjustProductPricesByCourse(List<Product> products, String courseId) {
        if (products != null) {
            for (Product product : products) {
                adjustProductPriceByCourse(product, courseId);
            }
        }
    }

    private String getCourseIdByCustomerId(String customerId) {
        if (customerId == null || customerId.trim().isEmpty()) {
            return "CS01";
        }
        try {
            String courseId = jdbcTemplate.queryForObject(
                "SELECT course_id FROM 顧客テーブル WHERE customer_id = ?",
                String.class,
                customerId
            );
            return (courseId != null) ? courseId.trim() : "CS01";
        } catch (Exception e) {
            return "CS01";
        }
    }

    private String getCourseNameByCustomerId(String customerId) {
        if (customerId == null || customerId.trim().isEmpty()) {
            return "コースなし";
        }
        try {
            String courseName = jdbcTemplate.queryForObject(
                "SELECT cm.course_name FROM 顧客テーブル c " +
                "LEFT JOIN コースマスタテーブル cm ON TRIM(c.course_id) = TRIM(cm.course_id) " +
                "WHERE c.customer_id = ?",
                String.class,
                customerId
            );
            return (courseName != null) ? courseName.trim() : "コースなし";
        } catch (Exception e) {
            return "コースなし";
        }
    }
}
