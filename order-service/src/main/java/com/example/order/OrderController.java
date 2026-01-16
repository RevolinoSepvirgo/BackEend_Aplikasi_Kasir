package com.example.order;

import io.jsonwebtoken.Claims;
import io.jsonwebtoken.Jwts;
import io.jsonwebtoken.security.Keys;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.mongodb.core.MongoTemplate;
import org.springframework.data.mongodb.core.query.Criteria;
import org.springframework.data.mongodb.core.query.Query;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.client.RestTemplate;
import org.springframework.core.ParameterizedTypeReference;


import java.util.*;

@RestController
@RequestMapping("/api/orders")
public class OrderController {

    @Autowired
    private MongoTemplate mongoTemplate;

    // WAJIB SAMA DENGAN .ENV (Minimal 32 Karakter)
    private final String JWT_SECRET = "rahasia_kasir_123_paling_aman_dan_panjang_sekali";

    @PostMapping
    public Map<String, Object> createOrder(@RequestBody Order order, @RequestHeader("Authorization") String token) {
        Map<String, Object> response = new HashMap<>();
        RestTemplate restTemplate = new RestTemplate();

        try {
            // 1. Ambil identitas toko dari JWT
            String namaToko = extractNamaToko(token);
            order.setNamaToko(namaToko);

            // 2. AMBIL DATA PRODUK DARI PHP UNTUK VALIDASI HARGA & NAMA
            HttpHeaders headers = new HttpHeaders();
            headers.set("Authorization", token); // Kirim token ke PHP agar diizinkan akses
            HttpEntity<String> entity = new HttpEntity<>(headers);

            // Ganti 'product_service' sesuai nama service di docker-compose.yml Anda
            String phpUrl = "http://product_service/products";
            ResponseEntity<List<Map<String, Object>>> productResponse = restTemplate.exchange(
                phpUrl, HttpMethod.GET, entity, new ParameterizedTypeReference<List<Map<String, Object>>>() {}
            );

            List<Map<String, Object>> masterProducts = productResponse.getBody();

            // 3. HITUNG TOTAL & VALIDASI BERDASARKAN DATA ASLI PHP
            double calculatedTotal = 0.0;
            List<Map<String, Object>> finalItems = new ArrayList<>();

            for (Map<String, Object> itemOrder : order.getItems()) {
                String idCari = itemOrder.get("id_produk").toString();
                int qty = Integer.parseInt(itemOrder.get("qty").toString());

                // Cari produk di list master (PHP)
                Map<String, Object> productMaster = masterProducts.stream()
                    .filter(p -> p.get("id").toString().equals(idCari))
                    .findFirst().orElse(null);

                if (productMaster == null) {
                    response.put("error", "Produk ID " + idCari + " tidak ada di tokomu!");
                    return response;
                }

                double hargaAsli = Double.parseDouble(productMaster.get("harga").toString());
                double totalLine = hargaAsli * qty;

                // Bungkus data lengkap item untuk struk
                Map<String, Object> newItem = new HashMap<>();
                newItem.put("id_produk", idCari);
                newItem.put("nama_produk", productMaster.get("nama_produk"));
                newItem.put("harga", hargaAsli);
                newItem.put("qty", qty);
                newItem.put("total", totalLine);

                finalItems.add(newItem);
                calculatedTotal += totalLine;
            }

            order.setItems(finalItems);
            order.setSubtotal(calculatedTotal);
            order.setTotalHarga(calculatedTotal);

            // 4. LOGIKA PEMBAYARAN & KEMBALIAN
            if ("Tunai".equalsIgnoreCase(order.getMetodePembayaran())) {
                if (order.getBayar() == null || order.getBayar() < order.getTotalHarga()) {
                    response.put("error", "Uang bayar kurang! Total: " + order.getTotalHarga());
                    return response;
                }
                order.setKembali(order.getBayar() - order.getTotalHarga());
            } else {
                order.setBayar(order.getTotalHarga());
                order.setKembali(0.0);
            }

            // 5. SIMPAN KE MONGODB (Data Utama)
            mongoTemplate.save(order);

            // 6. PERINTAHKAN PHP UNTUK POTONG STOK
            try {
                Map<String, Object> stockReq = new HashMap<>();
                stockReq.put("items", finalItems);
                HttpEntity<Map<String, Object>> stockEntity = new HttpEntity<>(stockReq, headers);
                restTemplate.postForEntity("http://product_service/products/reduce-stock", stockEntity, String.class);
                System.out.println("✅ Stok berhasil dipotong di PHP");
            } catch (Exception e) {
                System.out.println("⚠️ Gagal potong stok: " + e.getMessage());
            }

            // 7. SYNC KE REPORT SERVICE (PYTHON)
            try {
                Map<String, Object> syncData = new HashMap<>();
                syncData.put("nama_toko", namaToko);
                syncData.put("total_harga", order.getTotalHarga());
                syncData.put("metode_pembayaran", order.getMetodePembayaran());
                syncData.put("items", finalItems);
                restTemplate.postForEntity("http://report-service:5000/api/reports/sync", syncData, String.class);
                System.out.println("✅ Laporan terupdate di Redis");
            } catch (Exception e) {
                System.out.println("⚠️ Gagal sync laporan: " + e.getMessage());
            }

            // RESPON SUKSES
            response.put("message", "Transaksi Berhasil!");
            response.put("nomorStruk", order.getNomorStruk());
            response.put("totalBelanja", order.getTotalHarga());
            response.put("kembalian", order.getKembali());
            return response;

        } catch (Exception e) {
            e.printStackTrace();
            response.put("error", "Sistem Error: " + e.getMessage());
            return response;
        }
    }

    @GetMapping
    public List<Order> getOrders(@RequestHeader("Authorization") String token) {
        try {
            String namaToko = extractNamaToko(token);
            Query query = new Query();
            query.addCriteria(Criteria.where("namaToko").is(namaToko));
            return mongoTemplate.find(query, Order.class);
        } catch (Exception e) {
            return null;
        }
    }

    private String extractNamaToko(String token) {
        String pureToken = token.replace("Bearer ", "").trim();
        Claims claims = Jwts.parserBuilder()
                .setSigningKey(Keys.hmacShaKeyFor(JWT_SECRET.getBytes()))
                .build()
                .parseClaimsJws(pureToken)
                .getBody();
        return claims.get("nama_toko", String.class);
    }
}