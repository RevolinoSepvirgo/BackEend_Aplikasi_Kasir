<?php
require __DIR__ . '/../vendor/autoload.php';

use App\ProductController;
use App\CategoryController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Content-Type: application/json");

// 1. Ambil Header & Token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader) {
    http_response_code(401);
    die(json_encode(["error" => "Token missing"]));
}

// 2. Decode Token
try {
    $token = str_replace('Bearer ', '', $authHeader);
    $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));
} catch (\Exception $e) {
    http_response_code(401);
    die(json_encode(["error" => "Invalid Token"]));
}

// 3. Ambil Method & Path URL
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));

// Misal URL: /products/5 -> $resource = 'products', $id = 5
$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;

$data = json_decode(file_get_contents("php://input"), true);

// 4. Arahkan ke Controller
if ($resource === 'categories') {
    $ctrl = new CategoryController();
    switch ($method) {
        case 'GET':    $ctrl->getAll($decoded); break;
        case 'POST':   $ctrl->create($decoded, $data); break;
        case 'PUT':    $ctrl->update($decoded, $id, $data); break;
        case 'DELETE': $ctrl->delete($decoded, $id); break;
        default: http_response_code(405); break;
    }
} elseif ($resource === 'products') {
    $ctrl = new ProductController();
    switch ($method) {
        case 'GET':    $ctrl->getAll($decoded); break;
        case 'POST':   $ctrl->create($decoded, $data); break;
        case 'PUT':    $ctrl->update($decoded, $id, $data); break;
        case 'DELETE': $ctrl->delete($decoded, $id); break;
        default: http_response_code(405); break;
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint tidak ditemukan"]);
}