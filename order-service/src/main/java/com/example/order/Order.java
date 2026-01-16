package com.example.order;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;
import java.util.List;
import java.util.Map;
import java.util.Date;
import java.util.UUID;

@Document(collection = "orders")
public class Order {
    @Id
    private String id;
    private String nomorStruk = "STR-" + UUID.randomUUID().toString().substring(0, 8).toUpperCase();
    private String namaToko; 
    private List<Map<String, Object>> items;
    
    private Double subtotal;   // <--- Pastikan variabel ini ada
    private Double totalHarga; 
    private String metodePembayaran; 
    private Double bayar;            
    private Double kembali;          
    private Date createdAt = new Date();

    // --- GETTER & SETTER (WAJIB LENGKAP) ---

    public String getId() { return id; }
    public void setId(String id) { this.id = id; }

    public String getNomorStruk() { return nomorStruk; }
    public void setNomorStruk(String nomorStruk) { this.nomorStruk = nomorStruk; }

    public String getNamaToko() { return namaToko; }
    public void setNamaToko(String namaToko) { this.namaToko = namaToko; }

    public List<Map<String, Object>> getItems() { return items; }
    public void setItems(List<Map<String, Object>> items) { this.items = items; }

    // SETTER UNTUK SUBTOTAL (Ini yang bikin error tadi)
    public Double getSubtotal() { return subtotal; }
    public void setSubtotal(Double subtotal) { this.subtotal = subtotal; }

    public Double getTotalHarga() { return totalHarga; }
    public void setTotalHarga(Double totalHarga) { this.totalHarga = totalHarga; }

    public String getMetodePembayaran() { return metodePembayaran; }
    public void setMetodePembayaran(String metodePembayaran) { this.metodePembayaran = metodePembayaran; }

    public Double getBayar() { return bayar; }
    public void setBayar(Double bayar) { this.bayar = bayar; }

    public Double getKembali() { return kembali; }
    public void setKembali(Double kembali) { this.kembali = kembali; }

    public Date getCreatedAt() { return createdAt; }
}