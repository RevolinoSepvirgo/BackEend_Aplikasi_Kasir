<?php
namespace App;
use PDO;

class ProductController {
    private $db;
    public function __construct() { $this->db = DB::connect(); }

    public function getAll($user) {
        // Gunakan LEFT JOIN agar jika kategori terhapus, produk tetap muncul (opsional)
        $stmt = $this->db->prepare("SELECT p.*, c.nama_kategori FROM products p 
                                    LEFT JOIN categories c ON p.category_id = c.id 
                                    WHERE p.nama_toko = ?");
        $stmt->execute([$user->nama_toko]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create($user, $data) {
        $stmt = $this->db->prepare("INSERT INTO products (nama_toko, category_id, nama_produk, harga, stok) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user->nama_toko, $data['category_id'], $data['nama_produk'], $data['harga'], $data['stok']]);
        echo json_encode(["message" => "Produk berhasil ditambah"]);
    }

    public function update($user, $id, $data) {
        if (!$id) { http_response_code(400); die(json_encode(["error" => "ID diperlukan"])); }
        
        $stmt = $this->db->prepare("UPDATE products SET nama_produk = ?, harga = ?, stok = ?, category_id = ? 
                                    WHERE id = ? AND nama_toko = ?");
        $stmt->execute([$data['nama_produk'], $data['harga'], $data['stok'], $data['category_id'], $id, $user->nama_toko]);
        echo json_encode(["message" => "Produk ID $id berhasil diupdate"]);
    }

    public function delete($user, $id) {
        if (!$id) { http_response_code(400); die(json_encode(["error" => "ID diperlukan"])); }

        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ? AND nama_toko = ?");
        $stmt->execute([$id, $user->nama_toko]);
        echo json_encode(["message" => "Produk ID $id berhasil dihapus"]);
    }
}