package com.example.midoritei;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.servlet.http.HttpSession;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;
import com.example.midoritei.entity.CustomerSession;

@Component
public class CustomerInterceptor implements HandlerInterceptor {

    private final JdbcTemplate jdbcTemplate;

    public CustomerInterceptor(JdbcTemplate jdbcTemplate) {
        this.jdbcTemplate = jdbcTemplate;
    }

    @Override
    public boolean preHandle(HttpServletRequest request, HttpServletResponse response, Object handler) throws Exception {
        HttpSession session = request.getSession(false);
        if (session == null) {
            response.sendRedirect(request.getContextPath() + "/");
            return false;
        }

        CustomerSession customerSession = (CustomerSession) session.getAttribute("customerSession");
        if (customerSession == null) {
            response.sendRedirect(request.getContextPath() + "/");
            return false;
        }

        String customerId = customerSession.getCustomerId();
        String storeId = customerSession.getStoreId();
        String seatId = customerSession.getSeatId();

        // 顧客テーブルに該当する customerId が存在し、checkout_status = 0 であるかチェック
        String sql = "SELECT COUNT(*) FROM 顧客テーブル WHERE customer_id = ? AND checkout_status = 1";
        Integer count = 0;
        try {
            count = jdbcTemplate.queryForObject(sql, Integer.class, customerId);
        } catch (Exception e) {
            System.out.println("⚠️ CustomerInterceptor DB確認エラー: " + e.getMessage());
        }

        if (count == null || count == 0) {
            // セッションとカートをクリア
            session.removeAttribute("customerSession");
            session.removeAttribute("cart");

            // 元の店舗と座席のスタート画面へリダイレクト（スタート画面で空席ブロックされる）
            if (storeId != null && seatId != null) {
                response.sendRedirect(request.getContextPath() + "/seating/start?store_id=" + storeId + "&seat_id=" + seatId);
            } else {
                response.sendRedirect(request.getContextPath() + "/");
            }
            return false;
        }

        return true;
    }
}
