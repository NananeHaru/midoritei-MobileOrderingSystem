package com.example.midoritei;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.servlet.http.HttpSession;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

@Component
public class StaffInterceptor implements HandlerInterceptor {

    @Override
    public boolean preHandle(HttpServletRequest request, HttpServletResponse response, Object handler) throws Exception {
        HttpSession session = request.getSession(false);
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            // 未ログイン状態のためログイン画面へリダイレクト
            response.sendRedirect(request.getContextPath() + "/staff/login");
            return false;
        }
        return true;
    }
}
