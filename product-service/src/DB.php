<?php
namespace App;
use PDO;

class DB {
    public static function connect() {
        $host = getenv('PRODUCT_DB_HOST');
        $db   = getenv('PRODUCT_DB_NAME');
        $user = getenv('PRODUCT_DB_USER');
        $pass = getenv('PRODUCT_DB_PASSWORD');

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 1. Tabel Kategori
            $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_toko VARCHAR(100) NOT NULL,
                nama_kategori VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // 2. Tabel Produk (ditambah kolom gambar_produk)
            $pdo->exec("CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_toko VARCHAR(100) NOT NULL,
                category_id INT NOT NULL,
                nama_produk VARCHAR(255) NOT NULL,
                harga INT NOT NULL,
                stok INT NOT NULL,
                gambar_produk VARCHAR(255) NULL, 
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            return $pdo;
        } catch (\Exception $e) {
            http_response_code(500);
            die(json_encode(["error" => "DB Error: " . $e->getMessage()]));
        }
    }
}