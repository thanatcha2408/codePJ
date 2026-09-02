<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['username'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        require_once 'db_connect.php';

        try {
            $stmt = $pdo->prepare('SELECT username, password_hash FROM users WHERE username = :username');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            $is_valid = false;
            if ($user) {)
                if (password_verify($password, $user['password_hash'])) {
                    $is_valid = true;
                } elseif ($password === $user['password_hash']) {
                    $is_valid = true;
                }
            }

            if ($is_valid) {
                $_SESSION['username'] = $user['username'];
                header('Location: dashboard.php');
                exit();
            } else {
                $error_message = 'บัญชีผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (\PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาดในการเชื่อมต่อระบบฐานข้อมูล';
        }
    } else {
        $error_message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    }
}
?>
<?php 
$page_title = 'เข้าสู่ระบบ';
require_once 'header.php'; 
?>

<div class="login-body-wrapper">
    <div class="login-card">
        <div class="login-header">
            <div class="dsi-logo-container">
                <img src="dsi_logo.png" alt="DSI Logo" class="login-logo-img">
            </div>
            <h4>DSI Investigation System</h4>
            <p>ระบบตรวจสอบข้อมูลคดีความมั่นคง</p>
        </div>
        <div class="login-body">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <div>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <div class="mb-3">
                    <label for="username" class="form-label text-secondary">ชื่อผู้ใช้งาน (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-secondary"></i></span>
                        <input type="text" class="form-control border-start-0 bg-light" id="username" name="username" required placeholder="กรอกชื่อผู้ใช้">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label text-secondary">รหัสผ่าน (Password)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-secondary"></i></span>
                        <input type="password" class="form-control border-start-0 bg-light" id="password" name="password" required placeholder="กรอกรหัสผ่าน">
                    </div>
                </div>

                <button type="submit" class="btn btn-navy w-100 py-2">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
