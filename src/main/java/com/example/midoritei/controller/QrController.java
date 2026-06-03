package com.example.midoritei.controller;

import com.example.midoritei.service.QrService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import jakarta.servlet.http.HttpServletRequest;

@RestController
public class QrController {

    @Autowired
    private QrService qrService;

    /**
     * 注文IDを受け取り、その注文の決済用URLを埋め込んだQRコード画像を返す
     */
    @GetMapping(value = "/order/qr/{orderId}", produces = MediaType.IMAGE_PNG_VALUE)
    public byte[] getQrCode(@PathVariable String orderId) throws Exception {
        // 本番時は localhost ではなく実際のドメインになります
        String paymentUrl = "http://localhost:8080/orders/billing?id=" + orderId;
        
        // QRコード画像を生成してブラウザにそのまま返却
        return qrService.generateQrCode(paymentUrl);
    }

    /**
     * 店舗IDと座席IDを受け取り、その座席での注文開始URLを埋め込んだQRコード画像を返す
     */
    @GetMapping(value = "/seating/qr-image", produces = MediaType.IMAGE_PNG_VALUE)
    public byte[] getSeatingQrImage(@RequestParam("store_id") String storeId,
                                    @RequestParam("seat_id") String seatId,
                                    HttpServletRequest request) throws Exception {
        String scheme = request.getScheme();
        String serverName = request.getServerName();
        int serverPort = request.getServerPort();
        String baseUrl = scheme + "://" + serverName + ":" + serverPort;
        String seatingUrl = baseUrl + "/seating/start?store_id=" + storeId + "&seat_id=" + seatId;
        return qrService.generateQrCode(seatingUrl);
    }
}