<?php
require __DIR__ . '/../vendor/autoload.php';

use App\ProductController;
use App\CategoryController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// --- PERBAIKAN ROUTING ---
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Menghapus 'index.php' dari path jika ada
$path = str_replace('/index.php', '', $path);
$parts = explode('/', trim($path, '/'));

$resource = $parts[0] ?? ''; // Contoh: 'products' atau 'uploads'
$id = $parts[1] ?? null;     // Contoh: '1' atau 'view'
$extra = $parts[2] ?? null;  // Contoh: 'nama_gambar.jpg'

// --- 1. ENDPOINT VIEW GAMBAR (Tanpa Auth) ---
// URL: domain.com/index.php/uploads/view/nama_file.jpg
if ($resource === 'uploads' && $id === 'view' && $extra) {
    $uploadDir = __DIR__ . '/uploads/';
    $filename = basename($extra); 
    $filepath = $uploadDir . $filename;

    if (file_exists($filepath) && is_file($filepath)) {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/' . $ext;
        header('Content-Type: $mime');
        readfile($filepath);
        exit;
    }
    http_response_code(404);
    die(json_encode(["error" => "Gambar tidak ditemukan"]));
}

// --- 2. HEALTH CHECK ---
if ($resource === '' || $resource === 'index.php') {
    die(json_encode([
        "status" => "API Ready",
        "service" => "Product Service",
        "database" => "MySQL Connected"
    ]));
}

// --- 3. VALIDASI TOKEN ---
if (!$authHeader) {
    http_response_code(401);
    die(json_encode(["error" => "Token missing"]));
}

try {
    $token = str_replace('Bearer ', '', $authHeader);
    $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));
} catch (\Exception $e) {
    http_response_code(401);
    die(json_encode(["error" => "Invalid Token"]));
}

// Parse input data
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    $data = $_POST;
} else {
    $data = json_decode(file_get_contents("php://input"), true);
}

// --- 4. ROUTING KE CONTROLLER ---
if ($resource === 'categories') {
    $ctrl = new CategoryController();
    switch ($method) {
        case 'GET':    $ctrl->getAll($decoded); break;
        case 'POST':   $ctrl->create($decoded, $data); break;
        case 'PUT':    $ctrl->update($decoded, $id, $data); break;
        case 'DELETE': $ctrl->delete($decoded, $id); break;
        default: http_response_code(405); break;
    }
} 
elseif ($resource === 'products') {
    $ctrl = new ProductController();

    if ($id === 'reduce-stock' && $method === 'POST') {
        $ctrl->reduceStock($decoded, $data);
        exit;
    }

    switch ($method) {
        case 'GET':
            if ($id && is_numeric($id)) {
                $ctrl->getById($decoded, $id);
            } else {
                $ctrl->getAll($decoded);
            }
            break;
        case 'POST':   $ctrl->create($decoded, $data); break;
        case 'PUT':    $ctrl->update($decoded, $id, $data); break;
        case 'DELETE': $ctrl->delete($decoded, $id); break;
        default: http_response_code(405); break;
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint tidak ditemukan"]);
}