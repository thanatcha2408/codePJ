if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
}
async function getPdfText(file) {
    const arrayBuffer = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    let fullText = "";

    for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const textContent = await page.getTextContent();
        const pageText = textContent.items.map(item => item.str).join(" ");
        fullText += pageText + "\n";
    }
    return fullText.trim();
}
async function getDocxText(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function (event) {
            const arrayBuffer = event.target.result;
            if (typeof mammoth !== 'undefined') {
                mammoth.extractRawText({ arrayBuffer: arrayBuffer })
                    .then(function (result) {
                        resolve(result.value);
                    })
                    .catch(function (err) {
                        reject(err);
                    });
            } else {
                reject(new Error("ไม่พบไลบรารี Mammoth.js บนหน้าเว็บ"));
            }
        };
        reader.onerror = function (err) {
            reject(err);
        };
        reader.readAsArrayBuffer(file);
    });
}

const uploadForm = document.getElementById('uploadForm');
if (uploadForm) {
    uploadForm.addEventListener('submit', async function (e) {
        const fileInput = document.getElementById('dsi_file');
        const file = fileInput ? fileInput.files[0] : null;
        if (!file) return;

        const fileName = file.name.toLowerCase();
        const isPdf = fileName.endsWith('.pdf');
        const isDocx = fileName.endsWith('.docx');

        if (isPdf || isDocx) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> กำลังดึงข้อความจาก ${isPdf ? 'PDF' : 'Word'}...`;

            try {
                let extractedText = "";
                if (isPdf) {
                    extractedText = await getPdfText(file);
                } else {
                    extractedText = await getDocxText(file);
                }

                if (!extractedText || extractedText.trim() === "") {
                    alert(`ไม่สามารถสกัดข้อความภาษาไทยหรือข้อความใดๆ จากไฟล์ ${isPdf ? 'PDF' : 'Word'} นี้ได้ (ไฟล์อาจเป็นไฟล์ว่างเปล่าหรือเป็นไฟล์ภาพสแกน)`);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHTML;
                    return;
                }

                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> กำลังส่งวิเคราะห์...';

                const formData = new FormData();
                formData.append('action', 'analyze_text');
                formData.append('text_content', extractedText);
                formData.append('file_name', file.name);

                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        window.location.href = "dashboard.php?upload=success";
                    } else {
                        alert(result.error || "การวิเคราะห์ล้มเหลว");
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHTML;
                    }
                } else {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์หลัก");
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHTML;
                }
            } catch (err) {
                console.error(err);
                alert(`เกิดข้อผิดพลาดขณะอ่านไฟล์ ${isPdf ? 'PDF' : 'Word'}: ` + err.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHTML;
            }
        }
    });
}

const tableSearch = document.getElementById('tableSearch');
if (tableSearch) {
    tableSearch.addEventListener('keyup', function () {
        var value = this.value.toLowerCase().trim();
        var rows = document.querySelectorAll('#caseTable tbody tr');

        rows.forEach(function (row) {
            if (row.cells.length === 1 && row.cells[0].colSpan === 8) {
                return;
            }

            var text = row.textContent.toLowerCase();
            if (text.indexOf(value) > -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    });
}
setTimeout(function () {
    var alerts = document.querySelectorAll('.center-alert');
    alerts.forEach(function (alert) {
        alert.style.opacity = "0";
        setTimeout(function () {
            alert.remove();
        }, 400);
    });
}, 2000);
function initDeleteButtons() {
    document.querySelectorAll('.btn-delete-case').forEach(button => {
        if (button.dataset.deleteBound) return;
        button.dataset.deleteBound = "true";

        button.addEventListener('click', function () {
            const casePk = this.getAttribute('data-case-pk');
            const caseId = this.getAttribute('data-case-id');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'ยืนยันการลบคดี?',
                    text: `คุณต้องการลบคดีเลขที่ "${caseId}" ใช่หรือไม่?\nการดำเนินการนี้จะลบข้อมูลคดีและข้อมูลที่เกี่ยวข้องทั้งหมดออกจากระบบถาวร`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบ',
                    cancelButtonText: 'ยกเลิก',
                    focusCancel: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitDeleteForm(casePk, caseId);
                    }
                });
            } else {
                // กรณีโหลด SweetAlert2 ไม่สำเร็จ ให้ใช้ confirm แบบดั้งเดิมสำรอง
                if (confirm(`คุณต้องการลบคดีเลขที่ "${caseId}" ใช่หรือไม่?\nการดำเนินการนี้จะลบข้อมูลคดีและข้อมูลที่เกี่ยวข้องทั้งหมดอย่างถาวร`)) {
                    submitDeleteForm(casePk, caseId);
                }
            }
        });
    });
}
async function submitDeleteForm(casePk, caseId) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('case_pk', casePk);
    formData.append('case_id', caseId);
    formData.append('ajax', 'true');

    try {
        const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                window.location.replace('dashboard.php?delete=success');
            } else {
                alert('เกิดข้อผิดพลาดในการลบข้อมูล: ' + (result.error || 'ไม่ทราบสาเหตุ'));
            }
        } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์เพื่อลบข้อมูล');
        }
    } catch (error) {
        console.error('Error deleting case:', error);
        alert('เกิดข้อผิดพลาดในการส่งคำขอระบบลบคดี');
    }
}
function checkUrlNotifications() {
    if (typeof Swal !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('delete') && urlParams.get('delete') === 'success') {
            Swal.fire({
                title: 'ลบข้อมูลสำเร็จ!',
                text: 'ข้อมูลคดีและรายละเอียดที่เกี่ยวข้องถูกลบออกจากระบบแล้ว',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.has('upload') && urlParams.get('upload') === 'success') {
            Swal.fire({
                title: 'วิเคราะห์สำเร็จ!',
                text: 'ระบบดึงข้อมูลคำให้การจาก AI และเพิ่มในตารางข้อมูลคดีเรียบร้อยแล้ว',
                icon: 'success',
                timer: 2500,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.has('insert') && urlParams.get('insert') === 'success') {
            Swal.fire({
                title: 'บันทึกสำเร็จ!',
                text: 'ระบบบันทึกข้อมูลคดีเรียบร้อยแล้ว',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.has('edit') && urlParams.get('edit') === 'success') {
            Swal.fire({
                title: 'แก้ไขชื่อคดีสำเร็จ!',
                text: 'ระบบอัปเดตชื่อคดีเรียบร้อยแล้ว',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
}

function printCaseReport(casePk) {
    const modal = document.getElementById(`statementModal${casePk}`);
    if (!modal) return;
    const caseIdLabel = modal.querySelector(`.modal-title`).textContent;
    const parts = caseIdLabel.split('ชื่อคดี:');
    const caseId = parts.length > 1 ? parts[1].trim() : 'คดีความมั่นคง';
    const officer = modal.querySelector(`.officer-val`) ? modal.querySelector(`.officer-val`).textContent.trim() : '-';
    const filename = modal.querySelector(`.filename-val`) ? modal.querySelector(`.filename-val`).textContent.trim() : '-';
    const person = modal.querySelector(`.person-val`) ? modal.querySelector(`.person-val`).textContent.trim() : '-';
    const accuser = modal.querySelector(`.accuser-val`) ? modal.querySelector(`.accuser-val`).textContent.trim() : '-';
    const witness = modal.querySelector(`.witness-val`) ? modal.querySelector(`.witness-val`).textContent.trim() : '-';
    const phone = modal.querySelector(`.phone-val`) ? modal.querySelector(`.phone-val`).textContent.trim() : '-';
    const bank = modal.querySelector(`.bank-val`) ? modal.querySelector(`.bank-val`).textContent.trim() : '-';
    const passport = modal.querySelector(`.passport-val`) ? modal.querySelector(`.passport-val`).textContent.trim() : '-';
    const vehicle = modal.querySelector(`.vehicle-val`) ? modal.querySelector(`.vehicle-val`).textContent.trim() : '-';
    const email = modal.querySelector(`.email-val`) ? modal.querySelector(`.email-val`).textContent.trim() : '-';
    const idCard = modal.querySelector(`.id-card-val`) ? modal.querySelector(`.id-card-val`).textContent.trim() : '-';
    const statement = modal.querySelector(`.statement-text-val`) ? modal.querySelector(`.statement-text-val`).innerHTML.trim() : '-';

    const printWindow = window.open('', '_blank', 'width=900,height=800');
    if (!printWindow) {
        alert("ไม่สามารถเปิดหน้าต่างพิมพ์ได้ กรุณาปิดการบล็อกป๊อปอัพ (Popup Blocker) ของเบราว์เซอร์");
        return;
    }

    printWindow.document.write(`
        <html>
        <head>
            <title>รายงานข้อมูลชื่อคดี ${caseId}</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                body {
                    font-family: 'Sarabun', sans-serif;
                    padding: 40px;
                    color: #333;
                    background: #fff;
                }
                .report-header {
                    border-bottom: 3px double #2c3e50;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                    text-align: center;
                }
                .report-title {
                    font-size: 24px;
                    font-weight: 700;
                    color: #1a365d;
                    margin-bottom: 5px;
                }
                .report-subtitle {
                    font-size: 16px;
                    color: #7f8c8d;
                }
                .section-title {
                    font-size: 18px;
                    font-weight: 600;
                    border-left: 5px solid #1a365d;
                    padding-left: 10px;
                    margin-top: 25px;
                    margin-bottom: 15px;
                    color: #1a365d;
                }
                .meta-table th {
                    background-color: #f8f9fa !important;
                    width: 250px;
                    font-weight: 600;
                }
                .statement-box {
                    background-color: #fafafa;
                    border: 1px solid #e2e8f0;
                    padding: 20px;
                    border-radius: 5px;
                    white-space: pre-wrap;
                    line-height: 1.6;
                    font-size: 15px;
                    text-indent: 1.5cm;
                }
                .print-thead-continued-text {
                    font-size: 18px;
                    font-weight: 600;
                    color: #1a365d;
                    border-left: 5px solid #1a365d;
                    padding-left: 10px;
                    margin: 0;
                    display: none;
                }
                .first-page-cover {
                    display: none;
                }
                @media print {
                    @page {
                        margin: 0;
                    }
                    body {
                        padding: 0 1.5cm 0 1.5cm !important;
                        margin: 0 !important;
                    }
                    .no-print {
                        display: none;
                    }
                    .print-thead-continued-text {
                        display: block;
                    }
                    .first-page-cover {
                        display: block;
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 2.7cm;
                        background: #fff;
                        z-index: 1000;
                    }
                    .report-header {
                        position: relative;
                        background: #fff;
                        z-index: 1001;
                        page-break-inside: avoid;
                    }
                    .statement-box {
                        background-color: transparent !important;
                        border: none !important;
                        padding: 0 !important;
                        page-break-inside: auto;
                    }
                    .meta-table, .section-title {
                        page-break-inside: avoid;
                    }
                    .master-print-table thead {
                        display: table-header-group;
                    }
                    .master-print-table tfoot {
                        display: table-footer-group;
                    }
                }
            </style>
        </head>
        <body onload="window.print(); window.onafterprint = function(){ window.close(); };">
            <div class="first-page-cover"></div>
            
            <table class="master-print-table" style="width: 100%; border-collapse: collapse; border: none;">
                <thead>
                    <tr>
                        <td style="height: 2.7cm; border: none; padding: 0 0 15px 0; vertical-align: bottom;">
                            <div class="print-thead-continued-text">บันทึกคำให้การฉบับย่อ (ต่อ)</div>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: none; padding: 0; vertical-align: top;">
                            <div class="report-header">
                                <div class="report-title">รายงานข้อมูลคำให้การและผลการวิเคราะห์คดี (DSI)</div>
                                <div class="report-subtitle">กองคดีเทคโนโลยีและสารสนเทศ กรมสอบสวนคดีพิเศษ</div>
                            </div>

                            <div class="section-title">ข้อมูลทั่วไปของคดี</div>
                            <table class="table table-bordered meta-table">
                                <tr>
                                    <th>ชื่อคดี</th>
                                    <td><strong>${caseId}</strong></td>
                                </tr>
                                <tr>
                                    <th>พนักงานสอบสวนผู้รับผิดชอบ</th>
                                    <td>${officer}</td>
                                </tr>
                                <tr>
                                    <th>ชื่อไฟล์คำให้การต้นฉบับ</th>
                                    <td>${filename}</td>
                                </tr>
                            </table>

                            <div class="section-title">ข้อมูลสำคัญที่สกัดได้จากคำให้การ</div>
                            <table class="table table-bordered meta-table">
                                <tr>
                                    <th>บุคคลที่น่าสงสัย </th>
                                    <td>${person}</td>
                                </tr>
                                <tr>
                                    <th>ผู้กล่าวหา</th>
                                    <td>${accuser}</td>
                                </tr>
                                <tr>
                                    <th>พยาน</th>
                                    <td>${witness}</td>
                                </tr>
                                <tr>
                                    <th>เบอร์โทรศัพท์ติดต่อ</th>
                                    <td>${phone}</td>
                                </tr>
                                <tr>
                                    <th>ข้อมูลบัญชีธนาคาร</th>
                                    <td>${bank}</td>
                                </tr>
                                <tr>
                                    <th>ข้อมูลสัญชาติ / หนังสือเดินทาง</th>
                                    <td>${passport}</td>
                                </tr>
                                <tr>
                                    <th>ยานพาหนะ</th>
                                    <td>${vehicle}</td>
                                </tr>
                                <tr>
                                    <th>อีเมล</th>
                                    <td>${email}</td>
                                </tr>
                                <tr>
                                    <th>เลขบัตรประชาชน</th>
                                    <td>${idCard}</td>
                                </tr>
                            </table>

                            <div class="section-title">บันทึกคำให้การฉบับย่อ</div>
                            <div class="statement-box">${statement}</div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="height: 1.5cm; border: none; padding: 0;"></td>
                    </tr>
                </tfoot>
            </table>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function initPrintButtons() {

    document.querySelectorAll('.btn-print-case-trigger').forEach(button => {
        if (button.dataset.printBound) return;
        button.dataset.printBound = "true";
        button.addEventListener('click', function () {
            const casePk = this.getAttribute('data-case-pk');
            printCaseReport(casePk);
        });
    });

    document.querySelectorAll('.btn-print-modal-trigger').forEach(button => {
        if (button.dataset.printBound) return;
        button.dataset.printBound = "true";
        button.addEventListener('click', function () {
            const casePk = this.getAttribute('data-case-pk');
            printCaseReport(casePk);
        });
    });
}

function initEditButtons() {
    document.querySelectorAll('.btn-edit-case-name').forEach(button => {
        if (button.dataset.editBound) return;
        button.dataset.editBound = "true";

        button.addEventListener('click', function () {
            const casePk = this.getAttribute('data-case-pk');
            const currentName = this.getAttribute('data-case-name');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'แก้ไขชื่อคดี',
                    input: 'text',
                    inputValue: currentName,
                    inputLabel: 'ระบุชื่อคดีที่ตั้งเองใหม่:',
                    showCancelButton: true,
                    confirmButtonText: 'บันทึก',
                    cancelButtonText: 'ยกเลิก',
                    inputValidator: (value) => {
                        if (!value || value.trim() === '') {
                            return 'กรุณาระบุชื่อคดี';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitEditForm(casePk, result.value.trim());
                    }
                });
            } else {
                const newName = prompt('กรุณาระบุชื่อคดีใหม่:', currentName);
                if (newName !== null && newName.trim() !== '') {
                    submitEditForm(casePk, newName.trim());
                }
            }
        });
    });
}

async function submitEditForm(casePk, newCaseId) {
    const formData = new FormData();
    formData.append('action', 'edit_case_name');
    formData.append('case_pk', casePk);
    formData.append('new_case_id', newCaseId);
    formData.append('ajax', 'true');

    try {
        const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                // ใช้ location.replace เพื่อแทนที่ประวัติหน้าปัจจุบัน ทำให้เวลาแก้ไขแล้วกดย้อนกลับ
                // จะไม่ย้อนกลับมาเจอประวัติหน้าที่ยังมีชื่อคดีแบบเก่าค้างอยู่
                window.location.replace('dashboard.php?edit=success');
            } else {
                alert('เกิดข้อผิดพลาดในการแก้ไขชื่อคดี: ' + (result.error || 'ไม่ทราบสาเหตุ'));
            }
        } else {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์เพื่อแก้ไขชื่อคดี');
        }
    } catch (error) {
        console.error('Error editing case:', error);
        alert('เกิดข้อผิดพลาดในการส่งคำขอแก้ไขชื่อคดี');
    }
}

function initFileInputColor() {
    const fileInput = document.getElementById('dsi_file');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                this.classList.remove('file-input-pending');
                this.classList.add('file-input-success');
            } else {
                this.classList.remove('file-input-success');
                this.classList.add('file-input-pending');
            }
        });
    }
}

function init() {
    initDeleteButtons();
    initPrintButtons();
    initEditButtons();
    checkUrlNotifications();
    initFileInputColor();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

window.addEventListener('pageshow', function (event) {
    const historyTraversal = event.persisted || 
                             (typeof window.performance !== 'undefined' && 
                              window.performance.navigation.type === 2);
    if (historyTraversal) {
        window.location.reload();
    }
});
