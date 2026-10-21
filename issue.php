<?php
// issue.php - Circulation & Issue Transaction Module
require_once __DIR__ . '/db.php';

$message = '';
$messageType = 'success';

// Handle Book Issue Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_issue_book'])) {
    $bookNumber   = trim($_POST['BookNumber'] ?? '');
    $memberNumber = trim($_POST['MemberNumber'] ?? '');
    $issueDate    = $_POST['IssueDate'] ?? date('Y-m-d');
    $dueDate      = $_POST['DueDate'] ?? date('Y-m-d', strtotime('+14 days'));

    if (empty($bookNumber) || empty($memberNumber)) {
        $message = 'Please select both a valid Book Number and Member Number.';
        $messageType = 'danger';
    } else {
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
                $message = "Book Number '$bookNumber' does not exist in inventory.";
                $messageType = 'danger';
            } elseif (!$member) {
                $message = "Member Number '$memberNumber' does not exist in registry.";
                $messageType = 'danger';
            } else {
                // Check if book is already issued
                $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM TransBookIssue WHERE BookNumber = ? AND Status = 'Issued'");
                $chkStmt->execute([$bookNumber]);
                if ($chkStmt->fetchColumn() > 0) {
                    $message = "Book '$bookNumber' is currently issued to another member.";
                    $messageType = 'warning';
                } else {
                    // Record transaction in TransBookIssue
                    $ins = $pdo->prepare("INSERT INTO TransBookIssue (IssueDate, DueDate, BookNumber, MemberNumber, MemberName, Status) VALUES (?, ?, ?, ?, ?, 'Issued')");
                    $ins->execute([$issueDate, $dueDate, $bookNumber, $memberNumber, $member['MemberName']]);

                    $message = "Book '{$book['BookName']}' successfully issued to {$member['MemberName']} (ID: $memberNumber).";
                }
            }
        } catch (PDOException $e) {
            $message = "Transaction error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle Book Return Action
if (isset($_GET['return_slno'])) {
    $slno = intval($_GET['return_slno']);
    try {
        $stmt = $pdo->prepare("UPDATE TransBookIssue SET Status = 'Returned', ReturnDate = ? WHERE SlNo = ? AND Status = 'Issued'");
        $stmt->execute([date('Y-m-d'), $slno]);
        if ($stmt->rowCount() > 0) {
            $message = "Book returned successfully.";
        } else {
            $message = "Invalid transaction or book already returned.";
            $messageType = 'warning';
        }
    } catch (PDOException $e) {
        $message = "Error returning book: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch pre-selected book if passed in URL
$preSelectedBook = trim($_GET['book_number'] ?? '');

// Fetch available books for dropdown
$availableBooks = $pdo->query("
    SELECT b.BookNumber, b.BookName, b.AutherName
    FROM ExcelBookMaster b
    WHERE b.BookNumber NOT IN (SELECT BookNumber FROM TransBookIssue WHERE Status = 'Issued')
    ORDER BY b.BookName ASC
")->fetchAll();

// Fetch all members for dropdown
$membersList = $pdo->query("SELECT MemberNumber, MemberName, PhoneHome FROM MstrMember ORDER BY MemberName ASC")->fetchAll();

// Fetch Active & Recent Transactions
$transactions = $pdo->query("
    SELECT t.*, b.BookName, b.AutherName
    FROM TransBookIssue t
    LEFT JOIN ExcelBookMaster b ON t.BookNumber = b.BookNumber
    ORDER BY t.SlNo DESC
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fa-solid fa-hand-holding-hand me-2 text-primary"></i>Circulation & Book Issue Module</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Issue Book Form -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="fa-solid fa-plus-circle me-2"></i>Issue Book Transaction</h5>
            </div>
            <div class="card-body p-4">
                <form action="issue.php" method="POST">
                    <input type="hidden" name="action_issue_book" value="1">

                    <!-- Select Book -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Book (BookNumber) *</label>
                        <select name="BookNumber" class="form-select" required>
                            <option value="">-- Choose Book --</option>
                            <?php if ($preSelectedBook): ?>
                                <?php
                                $preBookStmt = $pdo->prepare("SELECT BookNumber, BookName, AutherName FROM ExcelBookMaster WHERE BookNumber = ?");
                                $preBookStmt->execute([$preSelectedBook]);
                                $pBook = $preBookStmt->fetch();
                                if ($pBook):
                                ?>
                                <option value="<?= htmlspecialchars($pBook['BookNumber']) ?>" selected>
                                    <?= htmlspecialchars($pBook['BookNumber']) ?> - <?= htmlspecialchars($pBook['BookName']) ?> (<?= htmlspecialchars($pBook['AutherName']) ?>)
                                </option>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php foreach ($availableBooks as $b): ?>
                                <?php if ($b['BookNumber'] !== $preSelectedBook): ?>
                                    <option value="<?= htmlspecialchars($b['BookNumber']) ?>">
                                        <?= htmlspecialchars($b['BookNumber']) ?> - <?= htmlspecialchars($b['BookName']) ?> (<?= htmlspecialchars($b['AutherName']) ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Select Member -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Member (MemberNumber) *</label>
                        <select name="MemberNumber" class="form-select" required>
                            <option value="">-- Choose Member --</option>
                            <?php foreach ($membersList as $m): ?>
                                <option value="<?= htmlspecialchars($m['MemberNumber']) ?>">
                                    <?= htmlspecialchars($m['MemberNumber']) ?> - <?= htmlspecialchars($m['MemberName']) ?> (Phone: <?= htmlspecialchars($m['PhoneHome'] ?? 'N/A') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Issue Date -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Issue Date</label>
                        <input type="date" name="IssueDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- Due Date -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Due Return Date</label>
                        <input type="date" name="DueDate" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary fw-bold py-2">
                            <i class="fa-solid fa-check-circle me-1"></i> Issue Book Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Active Circulation Summary -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-dark fw-bold"><i class="fa-solid fa-history me-2 text-primary"></i>Transaction History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Issue Date</th>
                                <th>Book Title</th>
                                <th>Issued To (Member)</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No circulation records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td><small class="text-muted">#<?= $t['SlNo'] ?></small></td>
                                        <td><small><?= htmlspecialchars($t['IssueDate']) ?></small></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($t['BookName'] ?? 'Unknown Book') ?></div>
                                            <small class="text-muted">Book ID: <?= htmlspecialchars($t['BookNumber']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($t['MemberName']) ?></div>
                                            <small class="text-muted">Member ID: <?= htmlspecialchars($t['MemberNumber']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($t['Status'] === 'Issued'): ?>
                                                <span class="badge bg-warning text-dark">Active Issue</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Returned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($t['Status'] === 'Issued'): ?>
                                                <a href="issue.php?return_slno=<?= $t['SlNo'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Confirm book return?');">
                                                    <i class="fa-solid fa-arrow-rotate-left me-1"></i> Return
                                                </a>
                                            <?php else: ?>
                                                <small class="text-success"><i class="fa-solid fa-check"></i> Completed</small>
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
