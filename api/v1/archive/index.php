<?php
require_once '../../../utils/products.php';
require_once '../../../helper.php';
require_once '../../../route.php';

ob_start();
loadEnv();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$mysql = new mysqli(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    $_ENV['DB_NAME']
);

$mysql->query("SET NAMES 'utf8'");

if ($mysql->connect_error) {
    http_response_code(500);
    echo json_encode([
        "error" => "Database connection failed",
        "code" => $mysql->connect_errno,
        "message" => $mysql->connect_error,
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

productRequest($method, $uri, $mysql);
