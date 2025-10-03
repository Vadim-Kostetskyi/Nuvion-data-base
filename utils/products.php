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
        header('Content-Type: application/json; charset=utf-8');

        // Всі записи
        if ($uri === $baseProductsPath || $uri === $baseProductsPath . '/') {
            $result = $mysql->query("SELECT * FROM products ORDER BY date DESC");
            $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

            // Перетворюємо поле images з рядка у масив
            foreach ($items as &$item) {
                $item['images'] = isset($item['images']) && $item['images'] !== ''
                    ? explode(',', $item['images'])
                    : [];
            }

            echo json_encode($items);
            exit;
        }

        // 4 останні записи
        if ($uri === $baseProductsPath . '/laatste') {
            $result = $mysql->query("SELECT * FROM products ORDER BY date DESC LIMIT 4");
            $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

            foreach ($items as &$item) {
                $item['images'] = isset($item['images']) && $item['images'] !== ''
                    ? explode(',', $item['images'])
                    : [];
            }

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
                $item['images'] = isset($item['images']) && $item['images'] !== ''
                    ? explode(',', $item['images'])
                    : [];

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

                    // Перевірка обов’язкових полів
                    if (!$title || !$slug || !$description) {
                        http_response_code(400);
                        echo json_encode(["error" => "Title, slug and description are required"]);
                        exit;
                    }

                    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
                        http_response_code(400);
                        echo json_encode(["error" => "At least one image is required"]);
                        exit;
                    }

                    $uploadDir = __DIR__ . '/../api/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                    $imageFiles = [];
                    $tmpNames = $_FILES['images']['tmp_name'];
                    $names = $_FILES['images']['name'];
                    $errors = $_FILES['images']['error'];

                    if (is_array($tmpNames)) {
                        foreach ($tmpNames as $i => $tmpName) {
                            if ($errors[$i] === UPLOAD_ERR_OK) {
                                $fileName = basename($names[$i]);
                                move_uploaded_file($tmpName, $uploadDir . $fileName);
                                $imageFiles[] = $fileName;
                            }
                        }
                    } else {
                        if ($errors === UPLOAD_ERR_OK) {
                            $fileName = basename($names);
                            move_uploaded_file($tmpNames, $uploadDir . $fileName);
                            $imageFiles[] = $fileName;
                        }
                    }

                    if (empty($imageFiles)) {
                        http_response_code(400);
                        echo json_encode(["error" => "No images uploaded"]);
                        exit;
                    }

                    // Зберігаємо назви файлів через кому (тільки самі імена)
                    $imageFilesWithPath = array_map(function($img) {
                        return '/api/uploads/' . $img;
                    }, $imageFiles);

                    // Зберігаємо як рядок через кому
                    $imageString = implode(',', $imageFilesWithPath);

                    $stmt = $mysql->prepare("
                        INSERT INTO products (title, slug, description, date, images, work_performed, address, language)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("ssssssss", $title, $slug, $description, $date, $imageString, $work_performed, $address, $language);

                    if ($stmt->execute()) {
                        // Масив для відповіді (вже з повними шляхами)
                        $imageUrls = explode(',', $imageString);

                        http_response_code(201);
                        echo json_encode([
                            "success" => true,
                            "id" => $stmt->insert_id,
                            "product" => [
                                "title" => $title,
                                "slug" => $slug,
                                "description" => $description,
                                "date" => $date,
                                "images" => $imageUrls,
                                "work_performed" => $work_performed,
                                "address" => $address,
                                "language" => $language
                            ]
                        ]);
                    }
                    else {
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

            $uploadDir = __DIR__ . '/../api/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $imageFiles = [];
            if (isset($_FILES['images'])) {
                $tmpNames = $_FILES['images']['tmp_name'];
                $names = $_FILES['images']['name'];
                $errors = $_FILES['images']['error'];

                if (is_array($tmpNames)) {
                    foreach ($tmpNames as $i => $tmpName) {
                        if ($errors[$i] === UPLOAD_ERR_OK) {
                            $fileName = basename($names[$i]);
                            move_uploaded_file($tmpName, $uploadDir . $fileName);
                            $imageFiles[] = '/api/uploads/' . $fileName;
                        }
                    }
                } else {
                    if ($errors === UPLOAD_ERR_OK) {
                        $fileName = basename($names);
                        move_uploaded_file($tmpNames, $uploadDir . $fileName);
                        $imageFiles[] = '/api/uploads/' . $fileName;
                    }
                }
            }

            // Якщо нові зображення не завантажені, залишаємо старі
            if (empty($imageFiles)) {
                $stmtSelect = $mysql->prepare("SELECT images FROM products WHERE id = ?");
                $stmtSelect->bind_param("i", $id);
                $stmtSelect->execute();
                $result = $stmtSelect->get_result();
                $oldImages = $result->fetch_assoc()['images'] ?? '';
                $stmtSelect->close();
                $imageFiles = explode(',', $oldImages);
            }

            // Зберігаємо як рядок через кому
            $imageString = implode(',', $imageFiles);

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
                    SET title = ?, description = ?, date = ?, images = ?, work_performed = ?, address = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("ssssssi", $title, $description, $date, $imageString, $work_performed, $address, $updateId);
                $stmt->execute();
                $stmt->close();
                $id = $updateId;
            } else {
                // Створюємо новий запис
                $stmt = $mysql->prepare("
                    INSERT INTO products (slug, title, description, date, images, work_performed, address, language)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssssss", $slug, $title, $description, $date, $imageString, $work_performed, $address, $language);
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
                    "images" => $imageFiles, // масив з повними шляхами
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

        // Отримуємо images з БД
        $stmtSelect = $mysql->prepare("SELECT images FROM products WHERE id = ?");
        $stmtSelect->bind_param("i", $id);
        $stmtSelect->execute();
        $result = $stmtSelect->get_result();
        $row = $result->fetch_assoc();
        $stmtSelect->close();

        if ($row) {
            $imagesString = $row['images'] ?? '';
            if ($imagesString) {
                $images = explode(',', $imagesString);
                foreach ($images as $imagePath) {
                    $fullPath = __DIR__ . '/..' . $imagePath; // додаємо корінь
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }
            }
        }

        // Видаляємо продукт
        $stmt = $mysql->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
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
