<?php
namespace App;
use PDO;

class ProductController {
    private $db;
    private $imgBaseUrl;

    public function __construct() { 
        $this->db = DB::connect(); 
        
        // Ambil domain server secara dinamis
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        // URL ini mengarah ke endpoint view gambar di index.php
        $this->imgBaseUrl = "$protocol://$host/index.php/uploads/view/";
    }

    // Fungsi pembantu untuk mengubah nama file menjadi URL lengkap
    private function transformProduct($product) {
        if (!empty($product['gambar_produk'])) {
            $product['gambar_produk'] = $this->imgBaseUrl . $product['gambar_produk'];
        }
        return $product;
    }

    public function getAll($user) {
        $stmt = $this->db->prepare("SELECT p.*, c.nama_kategori FROM products p 
                                    LEFT JOIN categories c ON p.category_id = c.id 
                                    WHERE p.nama_toko = ?");
        $stmt->execute([$user->nama_toko]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ubah semua gambar menjadi URL lengkap
        $output = array_map([$this, 'transformProduct'], $results);
        
        echo json_encode($output);
    }

    public function getById($user, $id) {
        $stmt = $this->db->prepare("SELECT p.*, c.nama_kategori FROM products p 
                                    LEFT JOIN categories c ON p.category_id = c.id 
                                    WHERE p.id = ? AND p.nama_toko = ?");
        $stmt->execute([$id, $user->nama_toko]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            http_response_code(404);
            die(json_encode(["error" => "Produk tidak ditemukan"]));
        }

        echo json_encode($this->transformProduct($result));
    }

    public function create($user, $data) {
        $gambar = $data['gambar_produk'] ?? null;
        $stmt = $this->db->prepare("INSERT INTO products (nama_toko, category_id, nama_produk, harga, stok, gambar_produk) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user->nama_toko, 
            $data['category_id'], 
            $data['nama_produk'], 
            $data['harga'], 
            $data['stok'],
            $gambar
        ]);
        echo json_encode(["message" => "Produk berhasil ditambah"]);
    }

    public function update($user, $id, $data) {
        if (!$id) { http_response_code(400); die(json_encode(["error" => "ID diperlukan"])); }
        
        $gambar = $data['gambar_produk'] ?? null;
        $stmt = $this->db->prepare("UPDATE products SET nama_produk = ?, harga = ?, stok = ?, category_id = ?, gambar_produk = ? 
                                    WHERE id = ? AND nama_toko = ?");
        $stmt->execute([
            $data['nama_produk'], 
            $data['harga'], 
            $data['stok'], 
            $data['category_id'], 
            $gambar, 
            $id, 
            $user->nama_toko
        ]);
        echo json_encode(["message" => "Produk berhasil diupdate"]);
    }

    public function delete($user, $id) {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ? AND nama_toko = ?");
        $stmt->execute([$id, $user->nama_toko]);
        echo json_encode(["message" => "Produk berhasil dihapus"]);
    }

    public function reduceStock($user, $data) {
        if (!isset($data['items']) || !is_array($data['items'])) {
            http_response_code(400);
            die(json_encode(["error" => "Data items tidak valid"]));
        }

        $this->db->beginTransaction();
        try {
            foreach ($data['items'] as $item) {
                $id_produk = $item['id_produk'];
                $qty = (int)$item['qty'];

                $stmt = $this->db->prepare("UPDATE products SET stok = stok - ? WHERE id = ? AND nama_toko = ? AND stok >= ?");
                $stmt->execute([$qty, $id_produk, $user->nama_toko, $qty]);

                if ($stmt->rowCount() === 0) {
                    throw new \Exception("Stok tidak cukup atau produk ID $id_produk tidak ditemukan");
                }
            }
            $this->db->commit();
            echo json_encode(["message" => "Stok berhasil dikurangi"]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}