<?php

require_once __DIR__ . '/../route.php';

function loginRequest(string $method, string $uri, mysqli $mysql): void {
    global $apiBasePath;

    $baseAdminPath = rtrim($apiBasePath, '/') . '/admin';

    switch ($method) {
        case 'POST':
            // Логін
            if ($uri === $baseAdminPath . '/login') {
                $login = $_POST['login'] ?? '';
                $password = $_POST['password'] ?? '';

                if (!$login || !$password) {
                    http_response_code(400);
                    echo json_encode(["error" => "Login and password required"]);
                    exit;
                }

                $stmt = $mysql->prepare("SELECT id, password FROM admin WHERE login = ? LIMIT 1");
                $stmt->bind_param("s", $login);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();
                $stmt->close();

                if ($admin && password_verify($password, $admin['password'])) {
                    // Генеруємо токен
                    $token = bin2hex(random_bytes(32));
                    $stmtToken = $mysql->prepare("INSERT INTO tokens (admin_id, token, created_at) VALUES (?, ?, NOW())");
                    $stmtToken->bind_param("is", $admin['id'], $token);
                    $stmtToken->execute();
                    $stmtToken->close();

                    echo json_encode(["success" => true, "token" => $token]);
                    exit;
                } else {
                    http_response_code(401);
                    echo json_encode(["error" => "Invalid credentials"]);
                    exit;
                }
            }

            break;

        case 'GET':
            // Перевірка токена
            if ($uri === $baseAdminPath . '/verify') {
                $headers = getallheaders();
                $token = $headers['Authorization'] ?? '';

                if (!$token) {
                    http_response_code(400);
                    echo json_encode(["error" => "Token required"]);
                    exit;
                }

                $stmt = $mysql->prepare("SELECT admin_id FROM tokens WHERE token = ? LIMIT 1");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if ($row) {
                    echo json_encode(["success" => true, "admin_id" => $row['admin_id']]);
                    exit;
                } else {
                    http_response_code(401);
                    echo json_encode(["error" => "Invalid token"]);
                    exit;
                }
            }
            break;

        case 'DELETE':
            // Видалення токена (логаут)
            if ($uri === $baseAdminPath . '/logout') {
                $headers = getallheaders();
                $token = $headers['Authorization'] ?? '';

                if (!$token) {
                    http_response_code(400);
                    echo json_encode(["error" => "Token required"]);
                    exit;
                }

                $stmt = $mysql->prepare("DELETE FROM tokens WHERE token = ?");
                $stmt->bind_param("s", $token);

                if ($stmt->execute()) {
                    echo json_encode(["success" => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "Failed to logout"]);
                }
                $stmt->close();
                exit;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed"]);
            exit;
    }
}
