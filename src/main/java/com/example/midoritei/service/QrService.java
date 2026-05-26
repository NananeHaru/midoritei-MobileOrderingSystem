package com.example.midoritei.service;

import com.google.zxing.BarcodeFormat;
import com.google.zxing.client.j2se.MatrixToImageWriter;
import com.google.zxing.common.BitMatrix;
import com.google.zxing.qrcode.QRCodeWriter;
import org.springframework.stereotype.Service;

import java.io.ByteArrayOutputStream;

@Service
public class QrService {

    /**
     * 指定されたURLを埋め込んだQRコードの画像（PNG）をバイト配列として生成します
     */
    public byte[] generateQrCode(String url) throws Exception {
        QRCodeWriter qrCodeWriter = new QRCodeWriter();
        
        // URL、フォーマット（QRコード）、幅(250px)、高さ(250px)を指定してマトリックスを生成
        BitMatrix bitMatrix = qrCodeWriter.encode(url, BarcodeFormat.QR_CODE, 250, 250);
        
        // 画像データを書き出すストリームを用意
        ByteArrayOutputStream outputStream = new ByteArrayOutputStream();
        
        // マトリックスをPNG画像としてストリームに書き込み
        MatrixToImageWriter.writeToStream(bitMatrix, "PNG", outputStream);
        
        return outputStream.toByteArray();
    }
}