<?php

require_once __DIR__ . '/../route.php';

function loginRequest(string $method, string $uri, mysqli $mysql): void {
    global $apiBasePath;

    $baseLoginPath = rtrim($apiBasePath, '/') . '/auth';

    $logFile = __DIR__ . '/request.log';
    $date = date('Y-m-d H:i:s');
    $rawInput = file_get_contents("php://input");

    $log  = "[$date] $method $uri" . PHP_EOL;

    if (!empty($_POST)) {
        $log .= "POST: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    if (!empty($_FILES)) {
        $log .= "FILES: " . json_encode(array_keys($_FILES)) . PHP_EOL;
    }
    if ($rawInput && empty($_POST)) {
        $log .= "RAW: " . $rawInput . PHP_EOL;
    }

    $log .= str_repeat("-", 40) . PHP_EOL;
    file_put_contents($logFile, $log, FILE_APPEND);

    switch ($method) {
        case 'POST':
            
            // Логін
            if ($uri === $baseLoginPath . '/login') {
                $rawInput = file_get_contents("php://input");
                $data = json_decode($rawInput, true);

                $login = $data['login'] ?? '';
                $password = $data['password'] ?? '';
                
                
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
                    file_put_contents($logFile, 'sdfsfsdf', FILE_APPEND);
                    // Генеруємо токен
                    $token = bin2hex(random_bytes(32));
                    $adminId = (int)$admin['id'];

                    $stmtToken = $mysql->prepare(
                        "INSERT INTO tokens (admin_id, temporary_token, created_at) VALUES (?, ?, NOW())"
                    );

                    if (!$stmtToken) {
                        http_response_code(500);
                        echo json_encode(["error" => "Prepare failed: " . $mysql->error]);
                        exit;
                    }

                    $stmtToken->bind_param("is", $adminId, $token);

                    if (!$stmtToken->execute()) {
                        http_response_code(500);
                        echo json_encode([
                            "error" => "Token insert failed",
                            "details" => $stmtToken->error
                        ]);
                        exit;
                    }

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
            if ($uri === $baseLoginPath . '/verify') {
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
            if ($uri === $baseLoginPath . '/logout') {
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
