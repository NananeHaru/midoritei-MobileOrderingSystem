package com.example.midoritei.controller;

import com.example.midoritei.service.SeatingService;

import jakarta.servlet.http.HttpSession;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model; 
import org.springframework.jdbc.core.JdbcTemplate; 
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;

@Controller
@RequestMapping("/seating")
public class SeatingController {

    @Autowired
    private SeatingService seatingService;

    @Autowired
    private JdbcTemplate jdbcTemplate;

    // QRコードシミュレーター画面を表示
    @GetMapping("/qr-simulator")
    public String qrSimulator() {
        return "seating/qr_simulator";
    }

    // 1. トップ画面を表示（/seating/start でアクセスされた時）
    @GetMapping("/start")
    public String startPage(@RequestParam(value = "store_id", defaultValue = "MH") String storeId,
                            @RequestParam(value = "seat_id", defaultValue = "1") String seatId,
                            Model model, 
                            jakarta.servlet.http.HttpSession session) { // 💡 session を引数に追加
        
        // ★ここに追加：すでにセッションがあれば注文画面へスキップ！
        if (session.getAttribute("customerSession") != null) {
            return "redirect:/menu"; 
        }

        try {
            String sql = "SELECT store_name FROM 店舗マスタテーブル WHERE store_id = ?";
            String storeName = jdbcTemplate.queryForObject(sql, String.class, storeId);
            model.addAttribute("storeName", storeName); 
        } catch (Exception e) {
            model.addAttribute("storeName", "緑橋一号店");
        }
        model.addAttribute("storeId", storeId);
        model.addAttribute("seatId", seatId);
        return "index";
    }

    // 2. 人数選択画面
    @GetMapping("/members")
    public String membersPage(HttpSession session) {
        // もしすでに注文中ならメニューへ！
        if (session.getAttribute("customerSession") != null) {
            return "redirect:/menu"; 
        }
        return "seating/members";
    }

    // 3. コース選択画面
    @GetMapping("/course")
    public String coursePage(HttpSession session) {
        // もしすでに注文中ならメニューへ！
        if (session.getAttribute("customerSession") != null) {
            return "redirect:/menu"; 
        }
        return "seating/course";
    }

   // 4. 次へボタンでDBに席開設情報を保存し、メニュー画面へリダイレクト
    @PostMapping("/open")
    public String openTable(@RequestParam("store_id") String storeId,
                            @RequestParam("seat_id") String seatId,
                            @RequestParam("course_id") String courseId,
                            @RequestParam("members") int members, 
                            jakarta.servlet.http.HttpSession session) {
        
        // 💡 サービス側の処理に「members」を渡す（ここで顧客テーブルにレコードが作られます）
        String customerId = seatingService.openTable(storeId, seatId, courseId, members);
        
        // 💡 DBのコースマスタテーブルから、制限時間（分）と【コース料金(price)】を取得する
        int timeLimitMinutes = 0;
        int coursePrice = 0;
        try {
            // 💡 price と time_limit をまとめて取得
            String sql = "SELECT time_limit, price FROM コースマスタテーブル WHERE course_id = ?";
            java.util.Map<String, Object> courseMap = jdbcTemplate.queryForMap(sql, courseId.trim());
            timeLimitMinutes = ((Number) courseMap.get("time_limit")).intValue();
            coursePrice = ((Number) courseMap.get("price")).intValue();
        } catch (Exception e) {
            timeLimitMinutes = 120;
            coursePrice = 0;
        }

        // 💡 【ここが重要！】新しく作られた顧客テーブルの total_amount に、コース料金を初期値としてセットする
        try {
            String updateAmountSql = "UPDATE 顧客テーブル SET total_amount = ? WHERE customer_id = ?";
            jdbcTemplate.update(updateAmountSql, coursePrice, customerId);
            System.out.println("★顧客テーブルの初期合計金額にコース料金（" + coursePrice + "円）を設定しました。");
        } catch (Exception e) {
            System.out.println("⚠️ 顧客テーブルの金額更新に失敗しました: " + e.getMessage());
        }

        // セッション側にお客様情報をセット
        com.example.midoritei.entity.CustomerSession customerSession = 
            new com.example.midoritei.entity.CustomerSession(customerId, members, courseId);
        
        if (!"CS01".equals(courseId.trim())) {
            long endTime = System.currentTimeMillis() + ((long) timeLimitMinutes * 60 * 1000);
            customerSession.setSessionEndTime(endTime);
        }
        
        session.setAttribute("customerSession", customerSession);
        
        return "redirect:/menu";
    }
}

// 💡 http://localhost:8080/ (スラッシュだけ) でアクセスされた時も確実に同じ画面を出し、DB連動させるための別クラス
@Controller
class MainRootController {
    @GetMapping("/")
    public String rootPage() {
        return "redirect:/seating/qr-simulator";
    }
}