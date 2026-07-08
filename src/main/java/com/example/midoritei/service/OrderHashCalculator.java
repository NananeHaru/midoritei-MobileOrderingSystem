package com.example.midoritei.service;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.SerializationFeature;
import java.security.MessageDigest;
import java.nio.charset.StandardCharsets;
import java.util.*;

public class OrderHashCalculator {

    @SuppressWarnings("unchecked")
    public static String computeOrderHash(Map<String, Object> order) {
        try {
            Map<String, Object> material = new TreeMap<>();
            material.put("storeId", order.get("storeId"));
            material.put("customerId", order.get("customerId"));
            material.put("entryTime", order.get("entryTime"));

            List<Map<String, Object>> items = new ArrayList<>();
            List<Map<String, Object>> rawItems = (List<Map<String, Object>>) order.get("items");
            if (rawItems != null) {
                for (Map<String, Object> it : rawItems) {
                    Map<String, Object> itemMaterial = new TreeMap<>();
                    itemMaterial.put("orderTime", it.get("orderTime"));
                    itemMaterial.put("menuName", it.get("menuName"));
                    itemMaterial.put("unitPrice", it.get("unitPrice"));
                    itemMaterial.put("taxRate", it.get("taxRate"));
                    itemMaterial.put("orderQty", it.get("orderQty"));
                    itemMaterial.put("offerQty", it.get("offerQty"));
                    items.add(itemMaterial);
                }
            }
            material.put("items", items);

            ObjectMapper mapper = new ObjectMapper();
            mapper.configure(SerializationFeature.ORDER_MAP_ENTRIES_BY_KEYS, true);
            String jsonStr = mapper.writeValueAsString(material);

            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hashBytes = digest.digest(jsonStr.getBytes(StandardCharsets.UTF_8));

            StringBuilder hexString = new StringBuilder();
            for (byte b : hashBytes) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) {
                    hexString.append('0');
                }
                hexString.append(hex);
            }
            return hexString.toString();
        } catch (Exception e) {
            throw new RuntimeException("Hash calculation error", e);
        }
    }
}
