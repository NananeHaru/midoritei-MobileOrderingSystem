package com.example.midoritei.config;

import com.example.midoritei.interceptor.SeatingInterceptor;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.servlet.HandlerInterceptor;
import org.springframework.web.servlet.config.annotation.InterceptorRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

@Configuration
public class WebConfig implements WebMvcConfigurer {
    @Autowired
    private SeatingInterceptor seatingInterceptor;

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        // 席選択画面は強制リダイレクト
        registry.addInterceptor(seatingInterceptor).addPathPatterns("/seating/**");
        
        // 全ページでキャッシュを無効化して、戻るボタンによる「過去の表示」を防ぐ
        registry.addInterceptor(new HandlerInterceptor() {
            @Override
            public boolean preHandle(HttpServletRequest request, HttpServletResponse response, Object handler) {
                response.setHeader("Cache-Control", "no-cache, no-store, must-revalidate");
                return true;
            }
        }).addPathPatterns("/**"); // すべてのパスでキャッシュを禁止
    }
}