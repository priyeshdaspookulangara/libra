<?php
// api/member.php - Mobile Member Profile & Borrowing History API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';

$memberNumber = trim($_GET['MemberNumber'] ?? $_GET['member_number'] ?? '');

if (empty($memberNumber)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'MemberNumber parameter is required.'
    ]);
    exit;
}

try {
    $stmtMem = $pdo->prepare("SELECT MemberNumber, MemberName, PhoneHome, HouseName, StudentSex, MemBirthDate, MemberRemarks FROM MstrMember WHERE MemberNumber = ?");
    $stmtMem->execute([$memberNumber]);
    $member = $stmtMem->fetch();

    if (!$member) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Member not found.'
        ]);
        exit;
    }

    // Fetch history
    $stmtTrans = $pdo->prepare("
        SELECT t.*, b.BookName, b.AutherName, b.BookClass, b.BookCode, b.BookRemarks
        FROM TransBookIssue t
        LEFT JOIN ExcelBookMaster b ON t.BookNumber = b.BookNumber
        WHERE t.MemberNumber = ?
        ORDER BY t.SlNo DESC
    ");
    $stmtTrans->execute([$memberNumber]);
    $history = $stmtTrans->fetchAll();

    $activeLoans = array_values(array_filter($history, function($t) {
        return $t['Status'] === 'Issued';
    }));

    echo json_encode([
        'status' => 'success',
        'member' => $member,
        'summary' => [
            'currently_borrowed' => count($activeLoans),
            'total_history' => count($history)
        ],
        'active_loans' => $activeLoans,
        'history' => $history
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
