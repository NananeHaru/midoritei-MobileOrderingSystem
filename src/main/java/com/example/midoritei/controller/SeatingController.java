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
                            @RequestParam("course_id") String courseId,
                            jakarta.servlet.http.HttpSession session) { // 💡 引数に HttpSession を追加しました
        
        // 自動採番＆DBインサートを実行（ここで本物の customerId が発行されます！）
        String customerId = seatingService.openTable(storeId, seatId, courseId);
        
        // -------------------------------------------------------------
        // ⏱️ 本番仕様：入店した瞬間に「終了時刻」を計算してセッションに保存する！
        // -------------------------------------------------------------
        // ※とりあえず人数は仮で「2」にしていますが、これでCustomerSession内で
        // 「入店時刻」と「80分後の終了時刻」が完全に自動計算されます。
        com.example.midoritei.entity.CustomerSession customerSession = 
            new com.example.midoritei.entity.CustomerSession(customerId, 2, courseId);
        
        // セッションに「本物の顧客セッション」を保存！
        session.setAttribute("customerSession", customerSession);
        // -------------------------------------------------------------
        
        // 固定のテスト用に、以前コントローラーで使っていたダミーID「0100001」ではなく、
        // 今回発行された本物の `customerId` を使いたいところですが、まずは連動テストのために
        // セッションへ本物データを格納した状態でメニュー画面へ遷移させます。
        
        return "redirect:/menu";
    }
}