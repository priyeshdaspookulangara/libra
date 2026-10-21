<?php
// dashboard.php - Member Portal & Borrowing History
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$memberId = $_SESSION['member_id'];

// Refresh member details from DB
$stmtMem = $pdo->prepare("SELECT * FROM MstrMember WHERE MemberNumber = ?");
$stmtMem->execute([$memberId]);
$member = $stmtMem->fetch();

if (!$member) {
    // If member was deleted
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch member borrowing history and active loans
$stmtTrans = $pdo->prepare("
    SELECT t.*, b.BookName, b.AutherName, b.BookClass, b.BookCode, b.BookRemarks
    FROM TransBookIssue t
    LEFT JOIN ExcelBookMaster b ON t.BookNumber = b.BookNumber
    WHERE t.MemberNumber = ?
    ORDER BY t.SlNo DESC
");
$stmtTrans->execute([$memberId]);
$memberTransactions = $stmtTrans->fetchAll();

$activeLoans = array_filter($memberTransactions, function($t) {
    return $t['Status'] === 'Issued';
});

require_once __DIR__ . '/header.php';
?>

<div class="row g-4">
    <!-- Member Profile Sidebar -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body text-center p-4">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 32px;">
                    <i class="fa-solid fa-user"></i>
                </div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($member['MemberName']) ?></h4>
                <p class="text-muted mb-3"><span class="badge bg-secondary"><?= htmlspecialchars($member['MemberNumber']) ?></span></p>

                <div class="border-top pt-3 text-start">
                    <p class="mb-2"><strong><i class="fa-solid fa-phone me-2 text-primary"></i>Phone:</strong> <?= htmlspecialchars($member['PhoneHome'] ?: 'N/A') ?></p>
                    <p class="mb-2"><strong><i class="fa-solid fa-house me-2 text-primary"></i>Address:</strong> <?= htmlspecialchars($member['HouseName'] ?: 'N/A') ?></p>
                    <p class="mb-2"><strong><i class="fa-solid fa-venus-mars me-2 text-primary"></i>Gender:</strong> <?= htmlspecialchars($member['StudentSex'] ?: 'N/A') ?></p>
                    <p class="mb-2"><strong><i class="fa-solid fa-calendar me-2 text-primary"></i>Birth/Joining:</strong> <?= htmlspecialchars($member['MemBirthDate'] ?: 'N/A') ?></p>
                    <p class="mb-0"><strong><i class="fa-solid fa-notes-medical me-2 text-primary"></i>Blood Group / Notes:</strong> <?= htmlspecialchars($member['MemberRemarks'] ?: 'N/A') ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Summary Card -->
        <div class="card bg-primary text-white shadow-sm border-0">
            <div class="card-body p-4 d-flex justify-content-around text-center">
                <div>
                    <h3 class="fw-bold mb-0"><?= count($activeLoans) ?></h3>
                    <small>Currently Borrowed</small>
                </div>
                <div class="border-end border-light"></div>
                <div>
                    <h3 class="fw-bold mb-0"><?= count($memberTransactions) ?></h3>
                    <small>Total Borrowed History</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Loan History & Active Issues -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-dark fw-bold">
                    <i class="fa-solid fa-book-reader me-2 text-primary"></i>My Currently Issued Books
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Shelf Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeLoans)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        You currently have no active borrowed books.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activeLoans as $al): ?>
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            <?= htmlspecialchars($al['BookName'] ?? 'Unknown Book') ?>
                                            <div class="small text-muted">ID: <?= htmlspecialchars($al['BookNumber']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($al['AutherName'] ?? '-') ?></td>
                                        <td><small><?= htmlspecialchars($al['IssueDate']) ?></small></td>
                                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($al['DueDate'] ?? '-') ?></span></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($al['BookRemarks'] ?? '-') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Entire Circulation History -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-dark fw-bold">
                    <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Complete Borrowing History
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Book Title</th>
                                <th>Issue Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($memberTransactions)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No past transactions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($memberTransactions as $mt): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($mt['BookName'] ?? 'Unknown') ?></div>
                                            <small class="text-muted">Author: <?= htmlspecialchars($mt['AutherName'] ?? '-') ?></small>
                                        </td>
                                        <td><small><?= htmlspecialchars($mt['IssueDate']) ?></small></td>
                                        <td><small><?= htmlspecialchars($mt['ReturnDate'] ?: 'Not returned yet') ?></small></td>
                                        <td>
                                            <?php if ($mt['Status'] === 'Issued'): ?>
                                                <span class="badge bg-warning text-dark">Issued</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Returned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
