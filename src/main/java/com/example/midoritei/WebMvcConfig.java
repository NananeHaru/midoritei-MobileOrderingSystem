package com.example.midoritei;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.servlet.config.annotation.InterceptorRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

@Configuration
public class WebMvcConfig implements WebMvcConfigurer {

    @Autowired
    private StaffInterceptor staffInterceptor;

    @Autowired
    private CustomerInterceptor customerInterceptor;

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        registry.addInterceptor(staffInterceptor)
                .addPathPatterns("/staff/**")
                .excludePathPatterns(
                        "/staff/login",
                        "/css/**",
                        "/js/**",
                        "/images/**",
                        "/webjars/**"
                );

        registry.addInterceptor(customerInterceptor)
                .addPathPatterns(
                        "/menu", "/menu/**",
                        "/cart", "/cart/**",
                        "/orders", "/orders/**"
                );
    }
}
