package com.example.midoritei.entity;

/**
 * 人数やコースなど、現在の客の座席状況を保持するクラス
 */
public class CustomerSession {
    
    private String customerId;
    private int numberOfMembers;
    private String course;
    private long sessionStartTime;
    private long sessionEndTime; // 👈 ★この行を追加します！
    
    public CustomerSession() {
    }
    
    // コンストラクタ（新しく sessionEndTime も計算してセットするようにします）
    public CustomerSession(String customerId, int numberOfMembers, String course) {
        this.customerId = customerId;
        this.numberOfMembers = numberOfMembers;
        this.course = course;
        this.sessionStartTime = System.currentTimeMillis();
        
        // 💡 とりあえず「一律で80分（1時間20分）」を終了時刻として計算してセットする例
        // 80分 ＝ 80 * 60秒 * 1000ミリ秒
        this.sessionEndTime = this.sessionStartTime + (80L * 60L * 1000L);
    }
    
    public String getCustomerId() {
        return customerId;
    }
    
    public void setCustomerId(String customerId) {
        this.customerId = customerId;
    }
    
    public int getNumberOfMembers() {
        return numberOfMembers;
    }
    
    public void setNumberOfMembers(int numberOfMembers) {
        this.numberOfMembers = numberOfMembers;
    }
    
    public String getCourse() {
        return course;
    }
    
    public void setCourse(String course) {
        this.course = course;
    }
    
    public long getSessionStartTime() {
        return sessionStartTime;
    }
    
    public void setSessionStartTime(long sessionStartTime) {
        this.sessionStartTime = sessionStartTime;
    }

    // 👈 ★新しく追加した sessionEndTime のゲッターとセッター
    public long getSessionEndTime() {
        return sessionEndTime;
    }

    public void setSessionEndTime(long sessionEndTime) {
        this.sessionEndTime = sessionEndTime;
    }
}