<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
function analyze_and_verify_n8n($text_content, $file_name, $pdo) {
    $webhook_url = 'https://skilled-kangaroo.pikapod.net/webhook/dsi-file-upload';
    
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'ระบบเซิร์ฟเวอร์ไม่รองรับโมดูล cURL กรุณาเปิดใช้งานใน php.ini'];
    }
    
    $payload = json_encode([
        'body' => $text_content,
        'file_name' => $file_name
    ]);

    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);
    
    if ($curl_err) {
        return ['success' => false, 'error' => 'ส่งไม่สำเร็จ: ไม่สามารถเชื่อมต่อไปยังเซิร์ฟเวอร์ AI ของ n8n ได้ (' . $curl_err . ')'];
    } elseif ($http_code >= 400 || $http_code === 0) {
        return ['success' => false, 'error' => 'ส่งไม่สำเร็จ: เซิร์ฟเวอร์ AI ตอบกลับด้วยข้อผิดพลาด (HTTP Status ' . $http_code . ')'];
    } elseif (empty($response)) {
        return ['success' => false, 'error' => 'ส่งไม่สำเร็จ: ไม่ได้รับการตอบกลับจากเซิร์ฟเวอร์ AI ของ n8n'];
    }
    return ['success' => true];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'analyze_text') {
    header('Content-Type: application/json');
    $text_content = $_POST['text_content'] ?? '';
    $file_name = $_POST['file_name'] ?? 'document.pdf';
    
    if (empty($text_content)) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลข้อความสำหรับการวิเคราะห์']);
        exit();
    }
    
    $result = analyze_and_verify_n8n($text_content, $file_name, $pdo);
    echo json_encode($result);
    exit();
}

$upload_error = '';
$upload_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (Error Code: ' . $file['error'] . ')';
    } else {
        $allowed_extensions = ['txt', 'pdf'];
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension'] ?? '');
        
        if (!in_array($extension, $allowed_extensions)) {
            $upload_error = 'ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะไฟล์นามสกุล .txt และ .pdf เท่านั้น';
        } else {
            $file_content = file_get_contents($file['tmp_name']);
            $result = analyze_and_verify_n8n($file_content, $file['name'], $pdo);
            
            if ($result['success']) {
                header("Location: dashboard.php?upload=success");
                exit();
            } else {
                $upload_error = $result['error'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert_manual') {
    $case_id = trim($_POST['case_id'] ?? '');
    $officer_name = trim($_POST['officer_name'] ?? '');
    $statement_text = trim($_POST['statement_text'] ?? '');
    $person_name = trim($_POST['person_name'] ?? '');
    $witness_name = trim($_POST['witness_name'] ?? '');
    $accuser_name = trim($_POST['accuser_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');
    $passport_number = trim($_POST['passport_number'] ?? '');
    $vehicle = trim($_POST['vehicle'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $id_card_number = trim($_POST['id_card_number'] ?? '');

    if (empty($case_id)) {
        $upload_error = 'กรุณากรอกชื่อคดี';
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE case_id = :case_id");
            $stmt_check->execute(['case_id' => $case_id]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception('มีชื่อคดีนี้ในระบบแล้ว');
            }

            $stmt_case = $pdo->prepare("INSERT INTO cases (case_id, officer_name, file_name, statement_text) VALUES (:case_id, :officer_name, 'กรอกข้อมูลด้วยตนเอง', :statement_text)");
            $stmt_case->execute([
                'case_id' => $case_id,
                'officer_name' => $officer_name,
                'statement_text' => $statement_text
            ]);

            $stmt_entity = $pdo->prepare("INSERT INTO extracted_entities (case_id, person_name, witness_name, accuser_name, phone_number, bank_name, account_number, nationality, passport_number, vehicle, email, id_card_number) VALUES (:case_id, :person_name, :witness_name, :accuser_name, :phone_number, :bank_name, :account_number, :nationality, :passport_number, :vehicle, :email, :id_card_number)");
            $stmt_entity->execute([
                'case_id' => $case_id,
                'person_name' => $person_name,
                'witness_name' => $witness_name,
                'accuser_name' => $accuser_name,
                'phone_number' => $phone_number,
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'nationality' => $nationality,
                'passport_number' => $passport_number,
                'vehicle' => $vehicle,
                'email' => $email,
                'id_card_number' => $id_card_number
            ]);

            $pdo->commit();
            header("Location: dashboard.php?insert=success");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $upload_error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูลคดี: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_case_name') {
    $case_pk = filter_input(INPUT_POST, 'case_pk', FILTER_VALIDATE_INT);
    $new_case_id = trim($_POST['new_case_id'] ?? '');

    if (!$case_pk || empty($new_case_id)) {
        $upload_error = 'ข้อมูลไม่ถูกต้อง';
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $upload_error]);
            exit();
        }
    } else {
        try {
            $pdo->beginTransaction();
            $stmt_select = $pdo->prepare("SELECT case_id FROM cases WHERE id = :case_pk");
            $stmt_select->execute(['case_pk' => $case_pk]);
            $old_case_id = $stmt_select->fetchColumn();

            if ($old_case_id) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE case_id = :new_case_id AND id != :case_pk");
                $stmt_check->execute(['new_case_id' => $new_case_id, 'case_pk' => $case_pk]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('มีชื่อคดีนี้ในระบบแล้ว');
                }
                $stmt_entity = $pdo->prepare("UPDATE extracted_entities SET case_id = :new_case_id WHERE case_id = :old_case_id");
                $stmt_entity->execute([
                    'new_case_id' => $new_case_id,
                    'old_case_id' => $old_case_id
                ]);
                $stmt_case = $pdo->prepare("UPDATE cases SET case_id = :new_case_id WHERE id = :case_pk");
                $stmt_case->execute([
                    'new_case_id' => $new_case_id,
                    'case_pk' => $case_pk
                ]);

                $pdo->commit();

                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit();
                }

                header("Location: dashboard.php?edit=success");
                exit();
            } else {
                $upload_error = 'ไม่พบข้อมูลคดีที่ต้องการแก้ไข';
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $upload_error]);
                    exit();
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $upload_error = 'เกิดข้อผิดพลาดในการแก้ไขชื่อคดี: ' . $e->getMessage();
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $upload_error]);
                exit();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $case_pk = filter_input(INPUT_POST, 'case_pk', FILTER_VALIDATE_INT);

    if ($case_pk) {
        try {
            $pdo->beginTransaction();
            $stmt_select = $pdo->prepare("SELECT case_id FROM cases WHERE id = :case_pk");
            $stmt_select->execute(['case_pk' => $case_pk]);
            $case = $stmt_select->fetch();

            if ($case) {
                $case_id = $case['case_id'];
                $stmt1 = $pdo->prepare("DELETE FROM extracted_entities WHERE case_id = :case_id");
                $stmt1->execute(['case_id' => $case_id]);
                $pdo->exec("DELETE FROM extracted_entities WHERE case_id = 'undefined' OR case_id = ''");
                $stmt2 = $pdo->prepare("DELETE FROM cases WHERE id = :case_pk");
                $stmt2->execute(['case_pk' => $case_pk]);
                $pdo->commit();
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit();
                }
                header("Location: dashboard.php?delete=success");
                exit();
            } else {
                $upload_error = 'ไม่พบข้อมูลคดีที่ต้องการลบในระบบ';
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $upload_error]);
                    exit();
                }
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $upload_error = 'เกิดข้อผิดพลาดในการลบข้อมูลคดี: ' . $e->getMessage();
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $upload_error]);
                exit();
            }
        }
    } else {
        $upload_error = 'ข้อมูลคดีไม่ถูกต้อง ไม่สามารถลบได้';
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $upload_error]);
            exit();
        }
    }
}
if (isset($_GET['upload']) && $_GET['upload'] === 'success') {
    $upload_success = 'ส่งไฟล์ให้ AI วิเคราะห์สำเร็จ ระบบอัปเดตและแสดงข้อมูลใหม่ในตารางแล้ว';
} elseif (isset($_GET['delete']) && $_GET['delete'] === 'success') {
    $upload_success = 'ลบข้อมูลคดีเรียบร้อยแล้ว';
} elseif (isset($_GET['insert']) && $_GET['insert'] === 'success') {
    $upload_success = 'เพิ่มข้อมูลคดีเรียบร้อยแล้ว';
} elseif (isset($_GET['edit']) && $_GET['edit'] === 'success') {
    $upload_success = 'แก้ไขชื่อคดีเรียบร้อยแล้ว';
}
try {
    $sql = "SELECT 
                c.id AS case_pk, 
                c.case_id, 
                c.officer_name, 
                c.file_name, 
                c.statement_text,
                e.person_name, 
                e.witness_name, 
                e.accuser_name, 
                e.phone_number, 
                e.bank_name, 
                e.account_number, 
                e.nationality, 
                e.passport_number,
                e.vehicle,
                e.email,
                e.id_card_number
            FROM cases c
            LEFT JOIN extracted_entities e ON c.case_id = e.case_id
            ORDER BY c.id DESC";
    $stmt = $pdo->query($sql);
    $cases = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . htmlspecialchars($e->getMessage()));
}
?>
<?php
$page_title = 'ระบบตรวจสอบข้อมูลคดีความมั่นคง';
require_once 'header.php';
?>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-navy px-3">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="dsi_logo.png" alt="DSI Logo" class="navbar-logo-img me-2">
            <span class="navbar-brand-text">ระบบตรวจสอบข้อมูลคดีความมั่นคง (DSI)</span>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-light me-3 d-none d-md-inline-block">
                <i class="fa-solid fa-user-tie me-1"></i> ผู้ใช้งาน: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            </span>
            <a href="logout.php" class="btn btn-logout">
                <i class="fa-solid fa-right-from-bracket me-1"></i> ออกจากระบบ
            </a>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="container-fluid dashboard-container">
    <div class="row">
        <div class="col-12">
            
            <?php if (!empty($upload_error)): ?>
                <div class="alert alert-danger d-flex flex-column align-items-center justify-content-center text-center p-4 center-alert" role="alert" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 320px; max-width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.25); border-radius: 12px; border: none; transition: opacity 0.4s ease;">
                    <i class="fa-solid fa-circle-exclamation fs-1 mb-3"></i>
                    <div class="fw-bold">
                        <?php echo htmlspecialchars($upload_error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card table-card mb-4">
                <div class="table-card-header bg-light">
                    <h5 class="table-title">
                        <i class="fa-solid fa-cloud-arrow-up text-primary"></i> อัปโหลดไฟล์คำให้การเพื่อส่งให้ AI วิเคราะห์
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="dashboard.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="row align-items-center">
                            <div class="col-md-9 mb-3 mb-md-0">
                                <input type="file" name="file" id="dsi_file" class="form-control bg-light file-input-pending" accept=".txt,.pdf,.docx" required>
                                <div class="form-text text-muted mt-2">
                                    <i class="fa-solid fa-circle-info me-1"></i> รองรับไฟล์คำให้การขนาดยาวเฉพาะรูปแบบเอกสาร<strong> .docx</strong> เท่านั้น
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-navy w-100 py-2">
                                    <i class="fa-solid fa-paper-plane me-1"></i> ส่งไฟล์ให้ AI วิเคราะห์
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card table-card">
                <div class="table-card-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <h5 class="table-title">
                        <i class="fa-solid fa-folder-open"></i> รายการข้อมูลคดีที่สกัดจากระบบสืบสวน
                    </h5>
                    <!-- ช่องค้นหาข้อมูลด่วนแบบเรียลไทม์ -->
                    <div class="input-group search-box">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                        <input type="text" id="tableSearch" class="form-control bg-light" placeholder="ค้นหา คดี/ชื่อ/ธนาคาร...">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dsi-table" id="caseTable">
                        <thead>
                            <tr>
                                <th style="width: 10%;">ชื่อคดี</th>
                                <th style="width: 12%;">ชื่อไฟล์ต้นฉบับ</th>
                                <th style="width: 12%;">ชื่อพนักงานสอบสวน</th>
                                <th style="width: 12%;">บุคคลที่น่าสงสัย</th>
                                <th style="width: 10%;">ผู้กล่าวหา</th>
                                <th style="width: 10%;">พยาน</th>
                                <th style="width: 10%;">เบอร์โทรศัพท์</th>
                                <th style="width: 13%;">ข้อมูลธนาคาร / เลขบัญชี</th>
                                <th style="width: 11%;">สัญชาติ / เลขบัตรประชาชน</th>
                                <th style="width: 10%;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cases)): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">ไม่พบข้อมูลคดีในระบบ</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cases as $row): ?>
                                    <tr>
                                        <td class="text-center font-monospace fw-bold text-navy">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <span class="case-name-text"><?php echo htmlspecialchars($row['case_id']); ?></span>
                                                <button type="button" class="btn btn-link btn-sm p-0 text-secondary btn-edit-case-name" 
                                                        data-case-pk="<?php echo htmlspecialchars($row['case_pk']); ?>" 
                                                        data-case-name="<?php echo htmlspecialchars($row['case_id']); ?>"
                                                        title="แก้ไขชื่อคดี">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="text-break"><?php echo htmlspecialchars($row['file_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
                                        <td>
                                            <?php echo !empty($row['person_name']) ? htmlspecialchars($row['person_name']) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($row['accuser_name']) ? htmlspecialchars($row['accuser_name']) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($row['witness_name']) ? htmlspecialchars($row['witness_name']) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo !empty($row['phone_number']) ? htmlspecialchars($row['phone_number']) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['bank_name']) || !empty($row['account_number'])): ?>
                                                <div class="badge-extracted">
                                                    <strong>ธนาคาร:</strong> <?php echo htmlspecialchars($row['bank_name'] ?: '-'); ?>
                                                </div>
                                                <div class="badge-extracted font-monospace">
                                                    <strong>เลขบัญชี:</strong> <?php echo htmlspecialchars($row['account_number'] ?: '-'); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['nationality']) || !empty($row['id_card_number'])): ?>
                                                <div class="badge-extracted">
                                                    <strong>สัญชาติ:</strong> <?php echo htmlspecialchars($row['nationality'] ?: '-'); ?>
                                                </div>
                                                <div class="badge-extracted font-monospace">
                                                    <strong>เลขบัตรประชาชน:</strong> <?php echo htmlspecialchars($row['id_card_number'] ?: '-'); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-grid gap-1">
                                                <button type="button" class="btn btn-view-statement btn-sm w-100" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#statementModal<?php echo htmlspecialchars($row['case_pk']); ?>">
                                                    <i class="fa-solid fa-file-lines me-1"></i> ดูคำให้การ
                                                </button>
                                                <button type="button" class="btn btn-print-case btn-sm w-100 btn-print-case-trigger" 
                                                        data-case-pk="<?php echo htmlspecialchars($row['case_pk']); ?>">
                                                    <i class="fa-solid fa-print me-1"></i> พิมพ์เอกสาร
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm w-100 btn-delete-case" 
                                                        data-case-pk="<?php echo htmlspecialchars($row['case_pk']); ?>" 
                                                        data-case-id="<?php echo htmlspecialchars($row['case_id']); ?>">
                                                    <i class="fa-solid fa-trash-can me-1"></i> ลบคดี
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Modal สำหรับแสดงสรุปคำให้การแบบย่อของคดีนี้ -->
                                    <div class="modal fade" id="statementModal<?php echo htmlspecialchars($row['case_pk']); ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo htmlspecialchars($row['case_pk']); ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header modal-header-navy">
                                                    <h5 class="modal-title" id="modalLabel<?php echo htmlspecialchars($row['case_pk']); ?>">
                                                        <i class="fa-solid fa-file-invoice me-2"></i> สรุปคำให้การแบบย่อ - ชื่อคดี: <?php echo htmlspecialchars($row['case_id']); ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4 text-start">
                                                    <div class="mb-3 border-bottom pb-2">
                                                        <strong>พนักงานสอบสวนเจ้าของคดี:</strong> <span class="officer-val"><?php echo htmlspecialchars($row['officer_name']); ?></span><br>
                                                        <strong>ชื่อไฟล์ต้นฉบับ:</strong> <span class="filename-val"><?php echo htmlspecialchars($row['file_name']); ?></span>
                                                    </div>
                                                    
                                                    <!-- ตารางย่อสำหรับข้อมูลสรุปของคดีที่จะพิมพ์ออกรายงาน -->
                                                    <div class="mb-3 p-3 bg-light rounded text-start" style="font-size: 0.9rem;">
                                                        <div class="row">
                                                            <div class="col-sm-6 mb-2"><strong>บุคคลที่น่าสงสัย:</strong> <span class="person-val"><?php echo !empty($row['person_name']) ? htmlspecialchars($row['person_name']) : '-'; ?></span></div>
                                                             <div class="col-sm-6 mb-2"><strong>ผู้กล่าวหา:</strong> <span class="accuser-val"><?php echo !empty($row['accuser_name']) ? htmlspecialchars($row['accuser_name']) : '-'; ?></span></div>
                                                             <div class="col-sm-6 mb-2"><strong>พยาน:</strong> <span class="witness-val"><?php echo !empty($row['witness_name']) ? htmlspecialchars($row['witness_name']) : '-'; ?></span></div>
                                                            <div class="col-sm-6 mb-2"><strong>เบอร์โทรศัพท์:</strong> <span class="phone-val"><?php echo !empty($row['phone_number']) ? htmlspecialchars($row['phone_number']) : '-'; ?></span></div>
                                                            <div class="col-sm-6 mb-2"><strong>ธนาคาร/เลขบัญชี:</strong> <span class="bank-val"><?php echo htmlspecialchars($row['bank_name'] ?: '-'); ?> (<?php echo htmlspecialchars($row['account_number'] ?: '-'); ?>)</span></div>
                                                            <div class="col-sm-6 mb-2"><strong>สัญชาติ/พาสปอร์ต:</strong> <span class="passport-val"><?php echo htmlspecialchars($row['nationality'] ?: '-'); ?> (<?php echo htmlspecialchars($row['passport_number'] ?: '-'); ?>)</span></div>
                                                            <div class="col-sm-6 mb-2"><strong>ยานพาหนะ:</strong> <span class="vehicle-val"><?php echo !empty($row['vehicle']) ? htmlspecialchars($row['vehicle']) : '-'; ?></span></div>
                                                            <div class="col-sm-6 mb-2"><strong>อีเมล:</strong> <span class="email-val"><?php echo !empty($row['email']) ? htmlspecialchars($row['email']) : '-'; ?></span></div>
                                                            <div class="col-sm-6 mb-2"><strong>เลขบัตรประชาชน:</strong> <span class="id-card-val"><?php echo !empty($row['id_card_number']) ? htmlspecialchars($row['id_card_number']) : '-'; ?></span></div>
                                                        </div>
                                                    </div>

                                                    <p class="text-secondary fw-semibold mb-2"><i class="fa-solid fa-quote-left me-1"></i> บันทึกคำให้การฉบับย่อ:</p>
                                                    <div class="bg-light p-3 rounded text-dark text-break statement-text-val" style="white-space: pre-wrap; font-size: 0.95rem; max-height: 400px; overflow-y: auto;">
                                                        <?php echo !empty($row['statement_text']) ? nl2br(htmlspecialchars($row['statement_text'])) : '<span class="text-muted">ไม่มีบันทึกคำให้การในระบบ</span>'; ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light d-flex justify-content-between">
                                                    <button type="button" class="btn btn-print-case btn-sm px-3 btn-print-modal-trigger" data-case-pk="<?php echo htmlspecialchars($row['case_pk']); ?>">
                                                        <i class="fa-solid fa-print me-1"></i> พิมพ์รายงาน
                                                    </button>
                                                    <button type="button" class="btn btn-secondary px-4 btn-sm" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Modal สำหรับกรอกคดีความด้วยตนเอง -->
<div class="modal fade" id="manualInsertModal" tabindex="-1" aria-labelledby="manualInsertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="dashboard.php" method="POST">
                <input type="hidden" name="action" value="insert_manual">
                <div class="modal-header modal-header-navy">
                    <h5 class="modal-title" id="manualInsertModalLabel">
                        <i class="fa-solid fa-pen-to-square me-2"></i> กรอกข้อมูลคดีใหม่ด้วยตนเอง
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <!-- ส่วนที่ 1: ข้อมูลทั่วไป -->
                    <h6 class="text-navy mb-3 border-bottom pb-2 fw-semibold">
                        <i class="fa-solid fa-info-circle me-1"></i> ข้อมูลคดีเบื้องต้น
                    </h6>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="m_case_id" class="form-label text-secondary fw-medium">ชื่อคดีที่ตั้งเอง <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light" id="m_case_id" name="case_id" required placeholder="เช่น คดีเว็บพนันรายใหญ่">
                        </div>
                        <div class="col-md-6">
                            <label for="m_officer_name" class="form-label text-secondary fw-medium">พนักงานสอบสวนผู้รับผิดชอบ</label>
                            <input type="text" class="form-control bg-light" id="m_officer_name" name="officer_name" placeholder="เช่น ร.ต.อ. สมชาย ดีใจ">
                        </div>
                    </div>
                    
                    <!-- ส่วนที่ 2: ข้อมูลผู้ต้องสงสัยและหลักฐานที่พบ -->
                    <h6 class="text-navy mb-3 border-bottom pb-2 fw-semibold mt-4">
                        <i class="fa-solid fa-user-shield me-1"></i> ข้อมูลสำคัญที่ตรวจพบ
                    </h6>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="m_person_name" class="form-label text-secondary fw-medium">บุคคลที่น่าสงสัย </label>
                            <input type="text" class="form-control bg-light" id="m_person_name" name="person_name" placeholder="เช่น นายวิชัย มั่งมี">
                        </div>
                        <div class="col-md-6">
                            <label for="m_phone_number" class="form-label text-secondary fw-medium">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="text" class="form-control bg-light" id="m_phone_number" name="phone_number" placeholder="เช่น 081-234-5678">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="m_accuser_name" class="form-label text-secondary fw-medium">ผู้กล่าวหา (Accuser)</label>
                            <input type="text" class="form-control bg-light" id="m_accuser_name" name="accuser_name" placeholder="เช่น นายสมคิด รักดี">
                        </div>
                        <div class="col-md-6">
                            <label for="m_witness_name" class="form-label text-secondary fw-medium">พยาน (Witness)</label>
                            <input type="text" class="form-control bg-light" id="m_witness_name" name="witness_name" placeholder="เช่น นายภควี นาคจู">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="m_bank_name" class="form-label text-secondary fw-medium">ชื่อธนาคาร</label>
                            <input type="text" class="form-control bg-light" id="m_bank_name" name="bank_name" placeholder="เช่น ธนาคารกสิกรไทย">
                        </div>
                        <div class="col-md-6">
                            <label for="m_account_number" class="form-label text-secondary fw-medium">เลขที่บัญชี</label>
                            <input type="text" class="form-control bg-light" id="m_account_number" name="account_number" placeholder="เช่น 123-4-56789-0">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="m_nationality" class="form-label text-secondary fw-medium">สัญชาติ</label>
                            <input type="text" class="form-control bg-light" id="m_nationality" name="nationality" placeholder="เช่น ไทย">
                        </div>
                        <div class="col-md-6">
                            <label for="m_passport_number" class="form-label text-secondary fw-medium">หนังสือเดินทาง (Passport No.)</label>
                            <input type="text" class="form-control bg-light" id="m_passport_number" name="passport_number" placeholder="เช่น AA1234567">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="m_vehicle" class="form-label text-secondary fw-medium">ยานพาหนะ</label>
                            <input type="text" class="form-control bg-light" id="m_vehicle" name="vehicle" placeholder="เช่น โตโยต้า สีดำ ทะเบียน 1กข 1234">
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="m_email" class="form-label text-secondary fw-medium">อีเมล</label>
                            <input type="email" class="form-control bg-light" id="m_email" name="email" placeholder="เช่น suspect@example.com">
                        </div>
                        <div class="col-md-4">
                            <label for="m_id_card_number" class="form-label text-secondary fw-medium">เลขบัตรประชาชน</label>
                            <input type="text" class="form-control bg-light" id="m_id_card_number" name="id_card_number" placeholder="เช่น 1-2345-67890-12-3">
                        </div>
                    </div>

                    <!-- ส่วนที่ 3: บันทึกคำให้การ -->
                    <h6 class="text-navy mb-3 border-bottom pb-2 fw-semibold mt-4">
                        <i class="fa-solid fa-quote-left me-1"></i> รายละเอียดบันทึกคำให้การ
                    </h6>
                    <div class="mb-3">
                        <label for="m_statement_text" class="form-label text-secondary fw-medium">ข้อความคำให้การทั้งหมด</label>
                        <textarea class="form-control bg-light" id="m_statement_text" name="statement_text" rows="5" placeholder="กรอกรายละเอียดคำให้การหรือคำชี้แจง..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4 btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success px-4 btn-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกข้อมูลคดี
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$page_scripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
    'dashboard.js?v=1.4'
];
require_once 'footer.php'; 
?>
