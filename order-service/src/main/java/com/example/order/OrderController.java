package com.example.order;

import io.jsonwebtoken.Claims;
import io.jsonwebtoken.Jwts;
import io.jsonwebtoken.security.Keys;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.mongodb.core.MongoTemplate;
import org.springframework.data.mongodb.core.query.Criteria;
import org.springframework.data.mongodb.core.query.Query;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/orders")
public class OrderController {

    @Autowired
    private MongoTemplate mongoTemplate;

    // WAJIB: Gunakan secret minimal 32 karakter
    private final String JWT_SECRET = "rahasia_kasir_123_paling_aman_dan_panjang_sekali";

    @PostMapping
    public Map<String, String> saveOrder(@RequestBody Order order, @RequestHeader("Authorization") String token) {
        try {
            String namaToko = extractNamaToko(token);
            order.setNamaToko(namaToko);
            mongoTemplate.save(order);
            return Map.of("message", "Transaksi Toko " + namaToko + " berhasil disimpan di MongoDB");
        } catch (Exception e) {
            return Map.of("error", "Gagal simpan: " + e.getMessage());
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
            // Jika error, akan terlihat di log Docker
            System.out.println("Error Get Orders: " + e.getMessage());
            return null;
        }
    }

    private String extractNamaToko(String token) {
        // .trim() untuk memastikan tidak ada spasi liar di ujung token
        String pureToken = token.replace("Bearer ", "").trim();
        
        Claims claims = Jwts.parserBuilder()
                .setSigningKey(Keys.hmacShaKeyFor(JWT_SECRET.getBytes()))
                .build()
                .parseClaimsJws(pureToken)
                .getBody();
                
        return claims.get("nama_toko", String.class);
    }
}