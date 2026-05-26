package com.example.midoritei.controller;

import com.example.midoritei.service.OrderService;
import jakarta.servlet.http.HttpSession;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import java.time.LocalDate;
import java.util.List;
import java.util.Map;

@Controller
@RequestMapping("/staff")
public class StaffController {

    @Autowired
    private OrderService orderService;

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
}
