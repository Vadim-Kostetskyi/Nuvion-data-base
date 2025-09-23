<?php

require_once __DIR__ . '/../route.php';


    // $logFile = __DIR__ . '/request.log';
    // $date = date('Y-m-d H:i:s');
    // $rawInput = file_get_contents("php://input");

    // $log  = "[$date] $method $uri" . PHP_EOL;

    // if (!empty($_POST)) {
    //     $log .= "POST: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    // }
    // if (!empty($_FILES)) {
    //     $log .= "FILES: " . json_encode(array_keys($_FILES)) . PHP_EOL;
    // }
    // if ($rawInput && empty($_POST)) {
    //     $log .= "RAW: " . $rawInput . PHP_EOL;
    // }

    // $log .= str_repeat("-", 40) . PHP_EOL;
    // file_put_contents($logFile, $log, FILE_APPEND);

function productRequest(string $method, string $uri, mysqli $mysql): void {
    global $apiBasePath;

    $baseProductsPath = rtrim($apiBasePath, '/') . '/archive/products';

    switch ($method) {
        case 'GET':
            // Всі записи
            if ($uri === $baseProductsPath || $uri === $baseProductsPath . '/') {
                $result = $mysql->query("SELECT * FROM products ORDER BY date DESC");
                $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($items);
                exit;
            }

            // 4 останні записи
            if ($uri === $baseProductsPath . '/laatste') {
                $result = $mysql->query("SELECT * FROM products ORDER BY date DESC LIMIT 4");
                $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($items);
                exit;
            }

            // Один запис по slug
            if (preg_match('#^' . preg_quote($baseProductsPath, '#') . '/([^/]+)$#', $uri, $matches)) {
                $slug = $matches[1];
                $stmt = $mysql->prepare("SELECT * FROM products WHERE slug = ? LIMIT 1");
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
            // Створення нового продукту
            if ($uri === $baseProductsPath || $uri === $baseProductsPath . '/') {
                $title = $_POST['title'] ?? '';
                $slug = $_POST['slug'] ?? '';
                $description = $_POST['description'] ?? '';
                $date = $_POST['date'] ?? '';
                $work_performed = $_POST['work_performed'] ?? '';
                $address = $_POST['address'] ?? '';
                $language = $_POST['language'] ?? 'uk';

                if (!$title || !$slug || !$description || !isset($_FILES['image'])) {
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid input"]);
                    exit;
                }

                $uploadDir = __DIR__ . '/../api/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $imageFile = $_FILES['image'];
                $imagePath = $uploadDir . basename($imageFile['name']);
                if (!move_uploaded_file($imageFile['tmp_name'], $imagePath)) {
                    http_response_code(500);
                    echo json_encode(["error" => "Failed to upload image"]);
                    exit;
                }
                $imageUrl = '/api/uploads/' . basename($imageFile['name']);

                $stmt = $mysql->prepare("
                    INSERT INTO products (title, slug, description, date, image, work_performed, address, language)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssssss", $title, $slug, $description, $date, $imageUrl, $work_performed, $address, $language);
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
                            "image" => $imageUrl,
                            "work_performed" => $work_performed,
                            "address" => $address,
                            "language" => $language
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "Database insert failed"]);
                }
                $stmt->close();
                exit;
            }

            // Оновлення продукту (по id)
            if (preg_match('#^' . preg_quote($baseProductsPath, '#') . '/(\d+)$#', $uri, $matches)) {
                $id = (int)$matches[1];
                $title = $_POST['title'] ?? '';
                $slug = $_POST['slug'] ?? '';
                $description = $_POST['description'] ?? '';
                $date = $_POST['date'] ?? '';
                $work_performed = $_POST['work_performed'] ?? '';
                $address = $_POST['address'] ?? '';
                $language = $_POST['language'] ?? 'uk';

                if (!$title || !$slug) {
                    http_response_code(400);
                    echo json_encode(["error" => "Title and slug are required"]);
                    exit;
                }

                // Отримуємо стару картинку
                $stmtSelect = $mysql->prepare("SELECT image FROM products WHERE id = ?");
                $stmtSelect->bind_param("i", $id);
                $stmtSelect->execute();
                $result = $stmtSelect->get_result();
                $oldImage = $result->fetch_assoc()['image'] ?? '';
                $stmtSelect->close();

                // Обробка нового зображення
                $imageUrl = $oldImage;
                if (!empty($_FILES['image']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../api/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $imageFile = $_FILES['image'];
                    $imagePath = $uploadDir . basename($imageFile['name']);
                    if (move_uploaded_file($imageFile['tmp_name'], $imagePath)) {
                        $imageUrl = '/api/uploads/' . basename($imageFile['name']);
                    }
                }

                // Перевірка: чи існує продукт з таким slug + language
                $stmtCheck = $mysql->prepare("SELECT id FROM products WHERE slug = ? AND language = ?");
                $stmtCheck->bind_param("ss", $slug, $language);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                $existing = $resultCheck->fetch_assoc();
                $stmtCheck->close();

                if ($existing) {
                    // Оновлюємо існуючий запис
                    $updateId = $existing['id'];
                    $stmt = $mysql->prepare("
                        UPDATE products 
                        SET title = ?, description = ?, date = ?, image = ?, work_performed = ?, address = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ssssssi", $title, $description, $date, $imageUrl, $work_performed, $address, $updateId);
                    $stmt->execute();
                    $stmt->close();
                    $id = $updateId;
                } else {
                    // Створюємо новий запис
                    $stmt = $mysql->prepare("
                        INSERT INTO products (slug, title, description, date, image, work_performed, address, language)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("ssssssss", $slug, $title, $description, $date, $imageUrl, $work_performed, $address, $language);
                    $stmt->execute();
                    $id = $stmt->insert_id;
                    $stmt->close();
                }

                echo json_encode([
                    "success" => true,
                    "id" => $id,
                    "product" => [
                        "title" => $title,
                        "slug" => $slug,
                        "description" => $description,
                        "date" => $date,
                        "image" => $imageUrl,
                        "work_performed" => $work_performed,
                        "address" => $address,
                        "language" => $language
                    ]
                ]);
                exit;
            }
            break;

        case 'DELETE':
            // Видалення продукту
            if (preg_match('#^' . preg_quote($baseProductsPath, '#') . '/(\d+)$#', $uri, $matches)) {
                $id = (int)$matches[1];

                $stmtSelect = $mysql->prepare("SELECT image FROM products WHERE id = ?");
                $stmtSelect->bind_param("i", $id);
                $stmtSelect->execute();
                $result = $stmtSelect->get_result();
                $imageUrl = $result->fetch_assoc()['image'] ?? '';
                $stmtSelect->close();

                $stmt = $mysql->prepare("DELETE FROM products WHERE id = ?");
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    if ($imageUrl && file_exists(__DIR__ . '/../' . $imageUrl)) {
                        unlink(__DIR__ . '/../' . $imageUrl);
                    }
                    http_response_code(200);
                    echo json_encode(["success" => true, "id" => $id]);
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "Failed to delete product"]);
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
