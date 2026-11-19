
<?php
// Allow Angular to communicate with PHP.
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Handle browser preflight requests.
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

// Database connection settings.
$host = "localhost";
$username = "root";
$password = "";
$database = "angularapp3";

$uploadedPath = null;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli(
        $host, $username, $password, $database
    );

    $conn->set_charset("utf8mb4");

    $method = $_SERVER["REQUEST_METHOD"];

    // READ - Retrieve books and their images.
    if ($method === "GET") {

        $result = $conn->query(
            "SELECT id, title, author, description, image
             FROM books"
        );

        $books = [];

        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }

        echo json_encode($books);
    }

    // CREATE - Add a book with an optional image.
    elseif ($method === "POST") {

        // Accept both regular JSON and uploaded form data.
        $data = !empty($_POST)
            ? $_POST
            : json_decode(
                file_get_contents("php://input"),
                true
            );

        $data = is_array($data) ? $data : [];

        $title = trim($data["title"] ?? "");
        $author = trim($data["author"] ?? "");
        $description = trim($data["description"] ?? "");
        $image = null;

        if ($title === "" || $author === "") {
            throw new InvalidArgumentException(
                "Title and author are required."
            );
        }

        // Process an uploaded book-cover picture.
        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["image"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    "Image upload failed."
                );
            }

            // Maximum image size: 2 MB.
            if ($file["size"] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException(
                    "Image must be smaller than 2 MB."
                );
            }

            // Check the actual file type.
            $mime = (new finfo(FILEINFO_MIME_TYPE))
                ->file($file["tmp_name"]);

            $allowed = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if (!isset($allowed[$mime])) {
                throw new InvalidArgumentException(
                    "Only JPG, PNG and WEBP images are allowed."
                );
            }

            // Save with a unique filename.
            $image = bin2hex(random_bytes(16))
                . "." . $allowed[$mime];

            $uploadDirectory = __DIR__ . "/uploads/";

            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }

            $uploadedPath = $uploadDirectory . $image;

            if (!move_uploaded_file(
                $file["tmp_name"],
                $uploadedPath
            )) {
                $uploadedPath = null;
                throw new RuntimeException(
                    "Could not save uploaded image."
                );
            }
        }

        // Insert the book into MySQL.
        $stmt = $conn->prepare(
            "INSERT INTO books
             (title, author, description, image)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssss",
            $title,
            $author,
            $description,
            $image
        );

        $stmt->execute();

        http_response_code(201);

        echo json_encode([
            "message" => "Book added successfully",
            "id" => $conn->insert_id,
            "image" => $image
        ]);

        $stmt->close();
    }

    // UPDATE - Edit an existing book.
    elseif ($method === "PUT") {

        $data = json_decode(
            file_get_contents("php://input"), true
        );

        $data = is_array($data) ? $data : [];

        $id = filter_input(
            INPUT_GET, "id", FILTER_VALIDATE_INT
        );

        $title = trim($data["title"] ?? "");
        $author = trim($data["author"] ?? "");
        $description = trim($data["description"] ?? "");

        if (!$id || $title === "" || $author === "") {
            throw new InvalidArgumentException(
                "Valid ID, title and author required."
            );
        }

        $stmt = $conn->prepare(
            "UPDATE books
             SET title = ?, author = ?, description = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sssi",
            $title,
            $author,
            $description,
            $id
        );

        $stmt->execute();

        echo json_encode([
            "message" => "Book updated successfully"
        ]);

        $stmt->close();
    }

    // DELETE - Remove an existing book.
    elseif ($method === "DELETE") {

        $id = filter_input(
            INPUT_GET, "id", FILTER_VALIDATE_INT
        );

        if (!$id) {
            throw new InvalidArgumentException(
                "Valid book ID required."
            );
        }

        $stmt = $conn->prepare(
            "DELETE FROM books WHERE id = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo json_encode([
            "message" => "Book deleted successfully"
        ]);

        $stmt->close();
    }

    else {
        http_response_code(405);
        echo json_encode([
            "error" => "Method not allowed"
        ]);
    }

    $conn->close();

} catch (InvalidArgumentException $e) {

    if ($uploadedPath && is_file($uploadedPath)) {
        unlink($uploadedPath);
    }

    http_response_code(400);

    echo json_encode([
        "error" => $e->getMessage()
    ]);

} catch (Throwable $e) {

    if ($uploadedPath && is_file($uploadedPath)) {
        unlink($uploadedPath);
    }

    http_response_code(500);

    
}
