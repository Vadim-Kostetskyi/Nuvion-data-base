<?php

require_once __DIR__ . '/../route.php';

function loginRequest(string $method, string $uri, mysqli $mysql): void {
    global $apiBasePath;

    $baseLoginPath = rtrim($apiBasePath, '/') . '/auth';
    
    switch ($method) {
        case 'POST':
            if ($uri === $baseLoginPath . '/login') {
                $data = json_decode($rawInput, true);
                $login = $data['login'] ?? '';
                $password = $data['password'] ?? '';

                if (!$login || !$password) {
                    http_response_code(400);
                    echo json_encode(["error" => "Login and password required"]);
                    exit;
                }

                $stmt = $mysql->prepare("SELECT id, password FROM admin WHERE login = ? LIMIT 1");
                $mysql->query("DELETE FROM tokens WHERE expires_at <= NOW()");

                if (!$stmt) {
                    http_response_code(500);
                    echo json_encode(["error" => "Prepare failed: " . $mysql->error]);
                    exit;
                }

                $stmt->bind_param("s", $login);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();
                $stmt->close();

                if ($admin && password_verify($password, $admin['password'])) {
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+3 months'));

                    $stmtToken = $mysql->prepare(
                        "INSERT INTO tokens (temporary_token, created_at, expires_at) VALUES (?, NOW(), ?)"
                    );
                    if (!$stmtToken) {
                        http_response_code(500);
                        echo json_encode(["error" => "Prepare token failed: " . $mysql->error]);
                        exit;
                    }

                    $stmtToken->bind_param("ss", $token, $expiresAt);
                    if (!$stmtToken->execute()) {
                        http_response_code(500);
                        echo json_encode(["error" => "Token insert failed", "details" => $stmtToken->error]);
                        exit;
                    }
                    $stmtToken->close();

                    echo json_encode(["success" => true, "token" => $token, "expires_at" => $expiresAt]);
                    exit;
                } else {
                    http_response_code(401);
                    echo json_encode(["error" => "Invalid credentials"]);
                    exit;
                }
            }
            break;

        case 'GET':
            if ($uri === $baseLoginPath . '/verify') {
                // $headers = getallheaders();
                // $auth = $headers['Authorization'] ?? '';
                // $token = str_replace('Bearer ', '', $auth);
                // file_put_contents($logFile, $headers, FILE_APPEND);

                $headers = getallheaders();
                
                $headersString = json_encode($headers);
                
                if (preg_match('/Bearer\s([a-f0-9]{64})/', $headersString, $matches)) {
                    $token = $matches[1];
                } else {
                    $token = 'NOT FOUND';
                }

                if (!$token) {
                    http_response_code(400);
                    echo json_encode(["error" => "Token required"]);
                    exit;
                }

                // Перевірка на дійсний токен та термін дії
                $stmt = $mysql->prepare("SELECT id FROM tokens WHERE temporary_token = ? AND expires_at > NOW() LIMIT 1");
                $mysql->query("DELETE FROM tokens WHERE expires_at <= NOW()");
                
                if (!$stmt) {
                    http_response_code(500);
                    echo json_encode(["error" => "Prepare failed: " . $mysql->error]);
                    exit;
                }

                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if ($row) echo json_encode(["success" => true, "id" => $row['id']]);
                else {
                    http_response_code(401);
                    echo json_encode(["error" => "Invalid or expired token"]);
                }
                exit;
            }
            break;

        case 'DELETE':
            if ($uri === $baseLoginPath . '/logout') {
                $headers = getallheaders();
                $auth = $headers['Authorization'] ?? '';
                $token = str_replace('Bearer ', '', $auth);

                if (!$token) {
                    http_response_code(400);
                    echo json_encode(["error" => "Token required"]);
                    exit;
                }

                $stmt = $mysql->prepare("DELETE FROM tokens WHERE temporary_token = ?");
                if (!$stmt) {
                    http_response_code(500);
                    echo json_encode(["error" => "Prepare failed: " . $mysql->error]);
                    exit;
                }

                $stmt->bind_param("s", $token);
                if ($stmt->execute()) echo json_encode(["success" => true]);
                else {
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
