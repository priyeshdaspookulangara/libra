<?php
// api/issue.php - Mobile Circulation & Book Issue/Return API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';

// GET: List circulation transactions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? 'all';
    $sql = "SELECT t.*, b.BookName, b.AutherName FROM TransBookIssue t LEFT JOIN ExcelBookMaster b ON t.BookNumber = b.BookNumber WHERE 1=1";
    $params = [];

    if ($status === 'issued') {
        $sql .= " AND t.Status = 'Issued'";
    } elseif ($status === 'returned') {
        $sql .= " AND t.Status = 'Returned'";
    }

    $sql .= " ORDER BY t.SlNo DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'count' => count($data),
        'data' => $data
    ]);
    exit;
}

// POST: Issue or Return Book
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $action = strtolower(trim($data['action'] ?? 'issue'));

    if ($action === 'return') {
        $slno = intval($data['slno'] ?? $data['SlNo'] ?? 0);
        if (!$slno) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Transaction slno is required for return action.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE TransBookIssue SET Status = 'Returned', ReturnDate = ? WHERE SlNo = ? AND Status = 'Issued'");
            $stmt->execute([date('Y-m-d'), $slno]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Book returned successfully.']);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Transaction not found or already returned.']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    } else { // Issue Book
        $bookNumber   = trim($data['BookNumber'] ?? $data['book_number'] ?? '');
        $memberNumber = trim($data['MemberNumber'] ?? $data['member_number'] ?? '');
        $issueDate    = $data['IssueDate'] ?? date('Y-m-d');
        $dueDate      = $data['DueDate'] ?? date('Y-m-d', strtotime('+14 days'));

        if (empty($bookNumber) || empty($memberNumber)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'BookNumber and MemberNumber are required.']);
            exit;
        }

        try {
            // Check book existence
            $bStmt = $pdo->prepare("SELECT BookName FROM ExcelBookMaster WHERE BookNumber = ?");
            $bStmt->execute([$bookNumber]);
            $book = $bStmt->fetch();

            // Check member existence
            $mStmt = $pdo->prepare("SELECT MemberName FROM MstrMember WHERE MemberNumber = ?");
            $mStmt->execute([$memberNumber]);
            $member = $mStmt->fetch();

            if (!$book) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Book Number not found.']);
                exit;
            }

            if (!$member) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Member Number not found.']);
                exit;
            }

            // Check if already issued
            $chk = $pdo->prepare("SELECT COUNT(*) FROM TransBookIssue WHERE BookNumber = ? AND Status = 'Issued'");
            $chk->execute([$bookNumber]);
            if ($chk->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Book is currently issued to another member.']);
                exit;
            }

            $ins = $pdo->prepare("INSERT INTO TransBookIssue (IssueDate, DueDate, BookNumber, MemberNumber, MemberName, Status) VALUES (?, ?, ?, ?, ?, 'Issued')");
            $ins->execute([$issueDate, $dueDate, $bookNumber, $memberNumber, $member['MemberName']]);
            $slno = $pdo->lastInsertId();

            echo json_encode([
                'status' => 'success',
                'message' => 'Book issued successfully.',
                'slno' => $slno,
                'book' => $book['BookName'],
                'member' => $member['MemberName']
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}
