<?php
namespace App;
use PDO;

class CategoryController {
    private $db;
    public function __construct() { $this->db = DB::connect(); }

    public function getAll($user) {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE nama_toko = ?");
        $stmt->execute([$user->nama_toko]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create($user, $data) {
        $stmt = $this->db->prepare("INSERT INTO categories (nama_toko, nama_kategori) VALUES (?, ?)");
        $stmt->execute([$user->nama_toko, $data['nama_kategori']]);
        echo json_encode(["message" => "Kategori berhasil dibuat"]);
    }

    public function update($user, $id, $data) {
        $stmt = $this->db->prepare("UPDATE categories SET nama_kategori = ? WHERE id = ? AND nama_toko = ?");
        $stmt->execute([$data['nama_kategori'], $id, $user->nama_toko]);
        echo json_encode(["message" => "Kategori berhasil diupdate"]);
    }

    public function delete($user, $id) {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ? AND nama_toko = ?");
        $stmt->execute([$id, $user->nama_toko]);
        echo json_encode(["message" => "Kategori berhasil dihapus"]);
    }
}