package com.example.midoritei.controller;

import com.example.midoritei.service.SeatingService;
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
                            Model model) {
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
                            @RequestParam("members") int members, // 💡 画面の hidden から送られてくる人数を受け取る
                            jakarta.servlet.http.HttpSession session) {
        
        // 💡 サービス側の処理に「members」を渡す
        String customerId = seatingService.openTable(storeId, seatId, courseId, members);
        
        // セッション側にも、初期値の「2」ではなく実際の人数（members）をセットする
        com.example.midoritei.entity.CustomerSession customerSession = 
            new com.example.midoritei.entity.CustomerSession(customerId, members, courseId);
        
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