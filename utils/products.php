<?php

require_once __DIR__ . '/../route.php';

function handleRequest(string $method, string $uri, mysqli $mysql): void {
    global $apiBasePath;

    $baseProductsPath = rtrim($apiBasePath, '/') . '/archive' . '/products';

    $logFile = __DIR__ . '/error.log'; // Файл з помилками
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $uri", FILE_APPEND);

    switch ($method) {
        case 'GET':
    
    // Всі записи
        if ($uri === $baseProductsPath || $uri === $baseProductsPath.'/') {
        $result = $mysql->query("SELECT * FROM products ORDER BY date DESC");

                    $items = [];

                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $items[] = $row;
                        }
                    }

                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($items);
                    exit;
                }

                elseif ($uri === $baseProductsPath || $uri === $baseProductsPath.'/laatste') {
                    // Вибираємо 4 останні записи за колонкою date
                    $result = $mysql->query("SELECT * FROM products ORDER BY date DESC LIMIT 4");

                    $items = [];

                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $items[] = $row;
                        }
                    }

                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($items);
                    exit;
                }




            // Пошук
            elseif (strpos($uri, rtrim($baseProductsPath, '/') . '/search=') === 0) {
                $searchTerm = urldecode(substr($uri, strlen(rtrim($baseProductsPath, '/') . '/search=')));

                $stmt = $mysql->prepare("
                    SELECT * FROM products 
                    WHERE title LIKE CONCAT('%', ?, '%') 
                       OR description LIKE CONCAT('%', ?, '%') 
                    ORDER BY date DESC
                ");
                $stmt->bind_param("ss", $searchTerm, $searchTerm);
                $stmt->execute();
                $result = $stmt->get_result();

                $items = [];
                while ($row = $result->fetch_assoc()) {
                    $items[] = $row;
                }

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($items);
                exit;
            }

            // Один запис по slug
            elseif (preg_match('#^' . preg_quote(rtrim($baseProductsPath, '/'), '#') . '/([^/]+)$#', $uri, $matches)) {
                $slug = $matches[1];

                $stmt = $mysql->prepare("SELECT * FROM products WHERE slug = ?");
                $stmt->bind_param("s", $slug);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($item = $result->fetch_assoc()) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($item);
                    exit;
                } else {
                    http_response_code(404);
                    echo json_encode(["error" => "Not found"]);
                    exit;
                }
            }

            break;

            case 'POST':
              if ($uri === $baseProductsPath || $uri === $baseProductsPath . '/') {
    $title = $_POST['title'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'] ?? '';

    if (!$title || !$slug || !$description || !$date || !isset($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid input"]);
        exit;
    }

    $uploadDir = __DIR__ . '/../api/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $imageFile = $_FILES['image'];
    $imagePath = $uploadDir . basename($imageFile['name']);
    if (move_uploaded_file($imageFile['tmp_name'], $imagePath)) {
        $imageUrl = '/api/uploads/' . basename($imageFile['name']); // URL для фронтенду
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to upload image"]);
        exit;
    }

    $stmt = $mysql->prepare("
        INSERT INTO products (title, slug, description, date, image)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $title, $slug, $description, $date, $imageUrl);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "id" => $stmt->insert_id,
            "product" => [
                "title" => $title,
                "slug" => $slug,
                "description" => $description,
                "date" => $date,
                "image" => $imageUrl
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Database insert failed"]);
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

function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) return;
  
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (strpos(trim($line), '#') === 0) continue;
  
      list($name, $value) = explode('=', $line, 2);
      $_ENV[trim($name)] = trim($value);
    }
}