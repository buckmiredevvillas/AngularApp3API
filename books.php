
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

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset("utf8mb4");

    $method = $_SERVER["REQUEST_METHOD"];

    // READ - Retrieve all books.
    if ($method === "GET") {
        $result = $conn->query(
            "SELECT id, title, author, description FROM books"
        );

        $books = [];

        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }

        echo json_encode($books);
    }

    // CREATE - Add a new book.
    elseif ($method === "POST") {
        $data = json_decode(file_get_contents("php://input"), true);

        $title = trim($data["title"] ?? "");
        $author = trim($data["author"] ?? "");
        $description = trim($data["description"] ?? "");

        if ($title === "" || $author === "") {
            http_response_code(400);
            echo json_encode(["error" => "Title and author are required"]);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO books (title, author, description)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param("sss", $title, $author, $description);
            $stmt->execute();

            http_response_code(201);
            echo json_encode([
                "message" => "Book added successfully",
                "id" => $conn->insert_id
            ]);

            $stmt->close();
        }
    }

    // UPDATE - Edit an existing book.
    elseif ($method === "PUT") {
        $data = json_decode(file_get_contents("php://input"), true);

        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        $title = trim($data["title"] ?? "");
        $author = trim($data["author"] ?? "");
        $description = trim($data["description"] ?? "");

        if (!$id || $title === "" || $author === "") {
            http_response_code(400);
            echo json_encode(["error" => "Valid ID, title and author required"]);
        } else {
            $stmt = $conn->prepare(
                "UPDATE books
                 SET title = ?, author = ?, description = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "sssi", $title, $author, $description, $id
            );
            $stmt->execute();

            echo json_encode(["message" => "Book updated successfully"]);
            $stmt->close();
        }
    }

    // DELETE - Remove a book.
    elseif ($method === "DELETE") {
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "Valid book ID required"]);
        } else {
            $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            echo json_encode(["message" => "Book deleted successfully"]);
            $stmt->close();
        }
    }

    else {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
    }

    $conn->close();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database operation failed"]);
}
