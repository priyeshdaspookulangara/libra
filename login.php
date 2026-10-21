<?php
// login.php - Member Authentication
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['member_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberNumber = trim($_POST['MemberNumber'] ?? '');
    $memberName   = trim($_POST['MemberName'] ?? '');

    if (empty($memberNumber) || empty($memberName)) {
        $error = 'Please enter both Member Number and Member Name.';
    } else {
        try {
            // Authenticate against MstrMember table (exact MemberNumber & case-insensitive MemberName comparison)
            $stmt = $pdo->prepare("SELECT * FROM MstrMember WHERE MemberNumber = ? AND LOWER(TRIM(MemberName)) = LOWER(TRIM(?))");
            $stmt->execute([$memberNumber, $memberName]);
            $member = $stmt->fetch();

            if ($member) {
                // Set session variables
                $_SESSION['member_id'] = $member['MemberNumber'];
                $_SESSION['member_name'] = $member['MemberName'];
                $_SESSION['member_data'] = $member;

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid Member Number or Member Name combination.';
            }
        } catch (PDOException $e) {
            $error = 'Database error during authentication: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-5">
        <div class="card shadow-lg border-0 rounded-3">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0 fw-bold"><i class="fa-solid fa-user-lock me-2"></i>Member Login</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <p class="text-muted text-center mb-4 small">
                    Please log in using your registered Member ID and Full Name.
                </p>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Member ID (MemberNumber)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                            <input type="text" name="MemberNumber" class="form-control" placeholder="e.g. M001" required value="<?= htmlspecialchars($_POST['MemberNumber'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Full Name (MemberName)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="MemberName" class="form-control" placeholder="e.g. Anil Kumar" required value="<?= htmlspecialchars($_POST['MemberName'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold py-2">
                            <i class="fa-solid fa-sign-in-alt me-1"></i> Log In to Dashboard
                        </button>
                    </div>
                </form>

                <div class="mt-4 pt-3 border-top text-center text-muted small">
                    Demo Credentials: ID <code>M001</code> / Name <code>Anil Kumar</code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
