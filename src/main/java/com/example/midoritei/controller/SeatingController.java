package com.example.midoritei.controller;

import com.example.midoritei.service.SeatingService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;

@Controller
@RequestMapping("/seating")
public class SeatingController {

    @Autowired
    private SeatingService seatingService;

    // 1. トップ画面を表示（初期アクセス）
    @GetMapping("/start")
    public String startPage() {
        return "index";
    }

    // 2. 人数選択画面へ案内
    @GetMapping("/members")
    public String membersPage() {
        return "seating/members";
    }

    // 3. コース選択画面へ案内
    @GetMapping("/course")
    public String coursePage() {
        return "seating/course";
    }

    // 4. 次へボタンでDBに席開設情報を保存し、メニュー画面へリダイレクト
    @PostMapping("/open")
    public String openTable(@RequestParam("store_id") String storeId,
                            @RequestParam("seat_id") String seatId,
                            @RequestParam("course_id") String courseId) {
        
        // 自動採番＆DBインサートを実行
        String customerId = seatingService.openTable(storeId, seatId, courseId);
        
        // 本来はここでcustomerIdをセッションに保持しますが、まずはメニュー画面へ遷移させます
        return "redirect:/menu";
    }
}