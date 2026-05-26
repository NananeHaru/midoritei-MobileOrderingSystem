package com.example.midoritei.controller;

import lombok.Data;

@Data
public class PaymentUpdateRequest {
    private String method;
    private String customerId;
    private String hash;
    private int billStatus;
}