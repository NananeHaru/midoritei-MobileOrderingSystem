package com.example.midoritei.interceptor;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.servlet.http.HttpSession;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

@Component
public class SeatingInterceptor implements HandlerInterceptor {
    @Override
    public boolean preHandle(HttpServletRequest request, HttpServletResponse response, Object handler) throws Exception {
        HttpSession session = request.getSession(false);
        
        // ① すでに注文中(customerSessionあり)なら、どのページでも強制的にメニューへ
        if (session != null && session.getAttribute("customerSession") != null) {
            response.sendRedirect(request.getContextPath() + "/menu");
            return false;
        }

        // ② 注文中じゃないのに、直接「コース選択画面」へURLを打って来ようとしたらトップへ戻す
        // (※ただし、/seating/start と /seating/qr-simulator は例外にする)
        String uri = request.getRequestURI();
        if (!uri.contains("/start") && !uri.contains("/qr-simulator") && !uri.contains("/open") && !uri.contains("/members")) {
            // 必要に応じてここでリダイレクトをかける
        }

        return true;
    }
}