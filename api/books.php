<?php
// api/books.php - Mobile Books Inventory & Search API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../search_helper.php';

// Handle GET: Search / List Books
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $titleQuery      = trim($_GET['title_query'] ?? $_GET['title'] ?? '');
    $titleMatchMode  = $_GET['title_match_mode'] ?? 'contains';
    $authorQuery     = trim($_GET['author_query'] ?? $_GET['author'] ?? '');
    $authorMatchMode = $_GET['author_match_mode'] ?? 'contains';
    $bookNumberQuery = trim($_GET['book_number'] ?? '');
    $barcodeQuery    = trim($_GET['barcode'] ?? '');
    $callNumQuery    = trim($_GET['call_number'] ?? '');
    $categoryQuery   = trim($_GET['category'] ?? '');
    $languageQuery   = trim($_GET['language'] ?? '');
    $availability    = $_GET['availability'] ?? 'all';

    $sql = "SELECT b.*,
            (SELECT COUNT(*) FROM TransBookIssue t WHERE t.BookNumber = b.BookNumber AND t.Status = 'Issued') AS IsIssued,
            (SELECT MemberName FROM TransBookIssue t WHERE t.BookNumber = b.BookNumber AND t.Status = 'Issued' ORDER BY SlNo DESC LIMIT 1) AS IssuedTo
            FROM ExcelBookMaster b WHERE 1=1";

    $params = [];

    if ($availability === 'available') {
        $sql .= " AND b.BookNumber NOT IN (SELECT BookNumber FROM TransBookIssue WHERE Status = 'Issued')";
    } elseif ($availability === 'issued') {
        $sql .= " AND b.BookNumber IN (SELECT BookNumber FROM TransBookIssue WHERE Status = 'Issued')";
    }

    if (!empty($bookNumberQuery)) {
        $sql .= " AND b.BookNumber LIKE ?";
        $params[] = "%$bookNumberQuery%";
    }
    if (!empty($barcodeQuery)) {
        $sql .= " AND b.BookCode LIKE ?";
        $params[] = "%$barcodeQuery%";
    }
    if (!empty($callNumQuery)) {
        $sql .= " AND b.CallNumber LIKE ?";
        $params[] = "%$callNumQuery%";
    }
    if (!empty($categoryQuery)) {
        $sql .= " AND b.BookClass = ?";
        $params[] = $categoryQuery;
    }
    if (!empty($languageQuery)) {
        $sql .= " AND b.Language = ?";
        $params[] = $languageQuery;
    }

    if (!empty($titleQuery) && $titleMatchMode !== 'phonetic') {
        if ($titleMatchMode === 'starts_with') {
            $sql .= " AND b.BookName LIKE ?";
            $params[] = "$titleQuery%";
        } else {
            $sql .= " AND b.BookName LIKE ?";
            $params[] = "%$titleQuery%";
        }
    }

    if (!empty($authorQuery) && $authorMatchMode !== 'phonetic') {
        if ($authorMatchMode === 'initial') {
            $sql .= " AND b.AutherName LIKE ?";
            $params[] = "$authorQuery%";
        } else {
            $sql .= " AND b.AutherName LIKE ?";
            $params[] = "%$authorQuery%";
        }
    }

    $sql .= " ORDER BY b.BookNumber ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawBooks = $stmt->fetchAll();

    $filteredBooks = [];
    foreach ($rawBooks as $b) {
        $titleMatch = true;
        if (!empty($titleQuery) && $titleMatchMode === 'phonetic') {
            $titleMatch = isPhoneticMatch($titleQuery, $b['BookName']);
        }

        $authorMatch = true;
        if (!empty($authorQuery) && $authorMatchMode === 'phonetic') {
            $authorMatch = isPhoneticMatch($authorQuery, $b['AutherName']);
        }

        if ($titleMatch && $authorMatch) {
            $b['IsIssued'] = (bool)$b['IsIssued'];
            $filteredBooks[] = $b;
        }
    }

    echo json_encode([
        'status' => 'success',
        'count' => count($filteredBooks),
        'data' => $filteredBooks
    ]);
    exit;
}

// Handle POST: Add / Update Book
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $bookNumber = trim($data['BookNumber'] ?? '');
    $bookName   = trim($data['BookName'] ?? '');
    $authorName = trim($data['AutherName'] ?? '');
    $bookClass  = trim($data['BookClass'] ?? '');
    $bookCode   = trim($data['BookCode'] ?? '');
    $callNumber = trim($data['CallNumber'] ?? '');
    $bookPrice  = floatval($data['BookPrice'] ?? 0);
    $remarks    = trim($data['BookRemarks'] ?? '');
    $language   = trim($data['Language'] ?? 'Malayalam');

    if (empty($bookNumber) || empty($bookName) || empty($authorName)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'BookNumber, BookName, and AutherName are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO ExcelBookMaster (BookNumber, BookName, AutherName, BookClass, BookCode, CallNumber, BookPrice, BookRemarks, Language)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE BookName = VALUES(BookName), AutherName = VALUES(AutherName), BookClass = VALUES(BookClass), BookCode = VALUES(BookCode), CallNumber = VALUES(CallNumber), BookPrice = VALUES(BookPrice), BookRemarks = VALUES(BookRemarks), Language = VALUES(Language)");
        $stmt->execute([$bookNumber, $bookName, $authorName, $bookClass, $bookCode, $callNumber, $bookPrice, $remarks, $language]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Book saved successfully.',
            'book_number' => $bookNumber
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
