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
    public String qrSimulator(Model model) {
        java.util.List<java.util.Map<String, Object>> stores = jdbcTemplate.queryForList(
            "SELECT store_id, store_name FROM 店舗マスタテーブル"
        );
        java.util.List<java.util.Map<String, Object>> seats = jdbcTemplate.queryForList(
            "SELECT store_id, seat_id FROM 座席マスタテーブル ORDER BY store_id, LENGTH(seat_id) ASC, seat_id ASC"
        );
        model.addAttribute("stores", stores);
        model.addAttribute("seats", seats);
        return "seating/qr_simulator";
    }

    // 1. トップ画面を表示（/seating/start でアクセスされた時）
    @GetMapping("/start")
    public String startPage(@RequestParam(value = "store_id", defaultValue = "MH") String storeId,
                            @RequestParam(value = "seat_id", defaultValue = "1") String seatId,
                            jakarta.servlet.http.HttpSession session,
                            Model model) {
        
        // 店舗名の設定
        try {
            String sql = "SELECT store_name FROM 店舗マスタテーブル WHERE store_id = ?";
            String storeName = jdbcTemplate.queryForObject(sql, String.class, storeId);
            model.addAttribute("storeName", storeName); 
        } catch (Exception e) {
            model.addAttribute("storeName", "緑橋一号店");
        }
        model.addAttribute("storeId", storeId);
        model.addAttribute("seatId", seatId);

        // 対象座席の利用中顧客データを検索
        java.util.Map<String, Object> cust = getActiveCustomerData(storeId, seatId);
        if (cust == null) {
            // 空席状態なのでセッションをクリアしてアクセスをブロックする
            session.removeAttribute("customerSession");
            session.removeAttribute("cart");
            model.addAttribute("isBlocked", true);
            return "index";
        }

        String dbCustomerId = (String) cust.get("customer_id");
        int dbMembers = ((Number) cust.get("number_of_members")).intValue();
        String dbCourseId = (String) cust.get("course_id");
        java.sql.Timestamp dbEntryTime = (java.sql.Timestamp) cust.get("entry_time");

        // セッションから現在の客の座席状況を取り出す
        com.example.midoritei.entity.CustomerSession currentSession = 
            (com.example.midoritei.entity.CustomerSession) session.getAttribute("customerSession");

        // DBの顧客IDとセッションの顧客IDが完全に一致する場合のみ、正常なリロードとしてメニューへスキップ
        if (currentSession != null && dbCustomerId.equals(currentSession.getCustomerId())) {
            return "redirect:/menu"; 
        }

        // 不一致またはセッションがない（新規着席や店員による再オープン等）場合は、古いセッション情報とカート情報をクリアする
        session.removeAttribute("customerSession");
        session.removeAttribute("cart");

        // 人数が 0名（未設定）の場合は人数選択・コース選択フローへ進めるため「注文開始」画面を表示する
        if (dbMembers == 0) {
            model.addAttribute("isBlocked", false);
            return "index";
        }

        // 人数が設定済みの場合は自動ログイン処理
        int timeLimitMinutes = 120;
        try {
            String timeSql = "SELECT time_limit FROM コースマスタテーブル WHERE course_id = ?";
            timeLimitMinutes = jdbcTemplate.queryForObject(timeSql, Integer.class, dbCourseId.trim());
        } catch (Exception e) {
            // デフォルト120分
        }

        com.example.midoritei.entity.CustomerSession customerSession = 
            new com.example.midoritei.entity.CustomerSession(dbCustomerId, dbMembers, dbCourseId, storeId, seatId);
        
        if (!"CS01".equals(dbCourseId.trim())) {
            long endTime = dbEntryTime.getTime() + ((long) timeLimitMinutes * 60 * 1000);
            customerSession.setSessionEndTime(endTime);
        }
        
        session.setAttribute("customerSession", customerSession);
        return "redirect:/menu";
    }

    // 2. 人数選択画面へ案内
    @GetMapping("/members")
    public String membersPage(@RequestParam("store_id") String storeId,
                              @RequestParam("seat_id") String seatId,
                              Model model) {
        java.util.Map<String, Object> cust = getActiveCustomerData(storeId, seatId);
        if (cust == null) {
            return "redirect:/seating/start?store_id=" + storeId + "&seat_id=" + seatId;
        }
        int members = ((Number) cust.get("number_of_members")).intValue();
        if (members > 0) {
            // 既に設定済みの場合はstartにリダイレクトして自動ログインさせる
            return "redirect:/seating/start?store_id=" + storeId + "&seat_id=" + seatId;
        }

        model.addAttribute("storeId", storeId);
        model.addAttribute("seatId", seatId);
        return "seating/members";
    }

    // 3. コース選択画面へ案内
    @GetMapping("/course")
    public String coursePage(@RequestParam("store_id") String storeId,
                             @RequestParam("seat_id") String seatId,
                             @RequestParam("members") int members,
                             Model model) {
        java.util.Map<String, Object> cust = getActiveCustomerData(storeId, seatId);
        if (cust == null) {
            return "redirect:/seating/start?store_id=" + storeId + "&seat_id=" + seatId;
        }
        int existingMembers = ((Number) cust.get("number_of_members")).intValue();
        if (existingMembers > 0) {
            // 既に設定済みの場合はstartにリダイレクトして自動ログインさせる
            return "redirect:/seating/start?store_id=" + storeId + "&seat_id=" + seatId;
        }

        model.addAttribute("storeId", storeId);
        model.addAttribute("seatId", seatId);
        model.addAttribute("members", members);
        return "seating/course";
    }

    // 4. 次へボタンでDBに席開設情報を保存し、メニュー画面へリダイレクト
    @PostMapping("/open")
    public String openTable(@RequestParam("store_id") String storeId,
                            @RequestParam("seat_id") String seatId,
                            @RequestParam("course_id") String courseId,
                            @RequestParam("members") int members, 
                            jakarta.servlet.http.HttpSession session) {
        
        // 念のため、既に席開設が完了していないか再度確認
        java.util.Map<String, Object> cust = getActiveCustomerData(storeId, seatId);
        if (cust != null) {
            int existingMembers = ((Number) cust.get("number_of_members")).intValue();
            if (existingMembers > 0) {
                // すでに誰かが設定完了しているため、上書きせずそのセッションでログインしてメニューへ
                String customerId = (String) cust.get("customer_id");
                String existingCourseId = (String) cust.get("course_id");
                java.sql.Timestamp entryTime = (java.sql.Timestamp) cust.get("entry_time");

                int timeLimitMinutes = 120;
                try {
                    String timeSql = "SELECT time_limit FROM コースマスタテーブル WHERE course_id = ?";
                    timeLimitMinutes = jdbcTemplate.queryForObject(timeSql, Integer.class, existingCourseId.trim());
                } catch (Exception e) {}

                com.example.midoritei.entity.CustomerSession customerSession = 
                    new com.example.midoritei.entity.CustomerSession(customerId, existingMembers, existingCourseId, storeId, seatId);
                
                if (!"CS01".equals(existingCourseId.trim())) {
                    long endTime = entryTime.getTime() + ((long) timeLimitMinutes * 60 * 1000);
                    customerSession.setSessionEndTime(endTime);
                }
                
                session.setAttribute("customerSession", customerSession);
                return "redirect:/menu";
            }
        }

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
            int initialAmount = coursePrice * members;
            String updateAmountSql = "UPDATE 顧客テーブル SET total_amount = ? WHERE customer_id = ?";
            jdbcTemplate.update(updateAmountSql, initialAmount, customerId);
            System.out.println("★顧客テーブルの初期合計金額にコース料金（" + initialAmount + "円）を設定しました。");
        } catch (Exception e) {
            System.out.println("⚠️ 顧客テーブルの金額更新に失敗しました: " + e.getMessage());
        }

        // セッション側にお客様情報をセット
        com.example.midoritei.entity.CustomerSession customerSession = 
            new com.example.midoritei.entity.CustomerSession(customerId, members, courseId, storeId, seatId);
        
        if (!"CS01".equals(courseId.trim())) {
            long endTime = System.currentTimeMillis() + ((long) timeLimitMinutes * 60 * 1000);
            customerSession.setSessionEndTime(endTime);
        }
        
        session.setAttribute("customerSession", customerSession);
        
        return "redirect:/menu";
    }

    /**
     * 対象座席の利用中顧客セッションを検索します。
     */
    private java.util.Map<String, Object> getActiveCustomerData(String storeId, String seatId) {
        String sql = "SELECT customer_id, number_of_members, course_id, entry_time FROM 顧客テーブル " +
                     "WHERE store_id = ? AND seat_id = ? AND checkout_status = 0";
        try {
            java.util.List<java.util.Map<String, Object>> activeCustomers = jdbcTemplate.queryForList(sql, storeId, seatId);
            if (!activeCustomers.isEmpty()) {
                return activeCustomers.get(0);
            }
        } catch (Exception e) {
            System.out.println("⚠️ 席確認エラー: " + e.getMessage());
        }
        return null;
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