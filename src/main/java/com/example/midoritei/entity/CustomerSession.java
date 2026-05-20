package com.example.midoritei.entity;

/**
 * 人数やコースなど、現在の客の座席状況を保持するクラス
 */
public class CustomerSession {
    
    private String customerId;
    private int numberOfMembers;
    private String course;
    private long sessionStartTime;
    
    public CustomerSession() {
    }
    
    public CustomerSession(String customerId, int numberOfMembers, String course) {
        this.customerId = customerId;
        this.numberOfMembers = numberOfMembers;
        this.course = course;
        this.sessionStartTime = System.currentTimeMillis();
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
}
