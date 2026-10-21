<?php
// api/login.php - Mobile Member Login API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';

// Accept JSON input or standard POST params
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$memberNumber = trim($data['MemberNumber'] ?? $data['member_number'] ?? '');
$memberName   = trim($data['MemberName'] ?? $data['member_name'] ?? '');

if (empty($memberNumber) || empty($memberName)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'MemberNumber and MemberName parameters are required.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT MemberNumber, MemberName, PhoneHome, HouseName, StudentSex, MemBirthDate, MemberRemarks FROM MstrMember WHERE MemberNumber = ? AND LOWER(TRIM(MemberName)) = LOWER(TRIM(?))");
    $stmt->execute([$memberNumber, $memberName]);
    $member = $stmt->fetch();

    if ($member) {
        // Generate simple API session token for mobile clients
        $token = base64_encode($member['MemberNumber'] . ':' . time());

        echo json_encode([
            'status' => 'success',
            'message' => 'Authentication successful.',
            'token' => $token,
            'member' => $member
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid Member Number or Member Name.'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
