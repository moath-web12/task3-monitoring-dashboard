<?php
// task3-monitoring-dashboard/index.php

// استدعاء ملف الاتصال بقاعدة البيانات
require_once "config/database.php";

$database = new Database();
$db = $database->getConnection();

// =========================================================================
// 1. حساب بطاقات الإحصائيات الأربعة (Total, Successful, Failed, Pending)
// =========================================================================
$stats_sql = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM webhook_logs";

$stats = $db->query($stats_sql)->fetch(PDO::FETCH_ASSOC);

// =========================================================================
// 2. استقبال مدخلات الفلترة من النموذج وتصفية القيم
// =========================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_phone  = isset($_GET['phone']) ? $_GET['phone'] : '';
$filter_date   = isset($_GET['date']) ? $_GET['date'] : '';

// بناء الاستعلام الأساسي بربط جدول السجلات مع جدول العملاء
$query = "SELECT wl.*, c.name as customer_name, c.phone 
          FROM webhook_logs wl 
          JOIN customers c ON wl.customer_id = c.id 
          WHERE 1=1";

$params = [];

// إضافة شرط التصفية حسب حالة السجل
if (!empty($filter_status)) {
    $query .= " AND wl.status = :status";
    $params[':status'] = $filter_status;
}

// إضافة شرط التصفية برقم الهاتف
if (!empty($filter_phone)) {
    $query .= " AND c.phone LIKE :phone";
    $params[':phone'] = "%" . $filter_phone . "%";
}

// إضافة شرط التصفية بتاريخ الإنشاء
if (!empty($filter_date)) {
    $query .= " AND DATE(wl.created_at) = :date";
    $params[':date'] = $filter_date;
}

// ترتيب السجلات أحدثها أولاً
$query .= " ORDER BY wl.id DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Monitoring Dashboard</title>
    <!-- استدعاء Bootstrap النسخة العربية (RTL) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: system-ui, -apple-system, sans-serif; }
        .card-stat { border-radius: 10px; border: none; }
        .table-container { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        pre.json-body { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 8px; max-height: 400px; overflow-y: auto; text-align: left; direction: ltr; font-family: monospace; }
    </style>
</head>
<body class="p-4">
<div class="container-fluid">
    <h3 class="mb-4">لوحة مراقبة الـ Integration (Task 3)</h3>

    <!-- ========================================================================= -->
    <!-- 3. عرض بطاقات الإحصائيات التجميعية -->
    <!-- ========================================================================= -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-stat bg-primary text-white p-3">
                <div class="small">Total Requests</div>
                <div class="fs-3 fw-bold"><?= $stats['total_requests'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-success text-white p-3">
                <div class="small">Successful</div>
                <div class="fs-3 fw-bold"><?= $stats['successful'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-danger text-white p-3">
                <div class="small">Failed</div>
                <div class="fs-3 fw-bold"><?= $stats['failed'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-warning text-dark p-3">
                <div class="small">Pending</div>
                <div class="fs-3 fw-bold"><?= $stats['pending'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. نموذج تصفية وفلترة السجلات -->
    <!-- ========================================================================= -->
    <div class="table-container mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small">Status Filter</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="success" <?= $filter_status=='success'?'selected':'' ?>>Success</option>
                    <option value="failed" <?= $filter_status=='failed'?'selected':'' ?>>Failed</option>
                    <option value="pending" <?= $filter_status=='pending'?'selected':'' ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars((string)$filter_phone) ?>" class="form-control" placeholder="05xxxxxxxx">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Created Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars((string)$filter_date) ?>" class="form-control">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-dark w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- 5. جدول عرض نتائج السجلات وتفاصيل الـ API -->
    <!-- ========================================================================= -->
    <div class="table-container">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Customer ID</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Last API Response</th>
                    <th>Created Date</th>
                    <th>Last Attempt Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="8" class="text-center text-muted">لا توجد سجلات مطابقة للبحث</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                    <tr>
                        <td><strong>#<?= $row['customer_id'] ?></strong></td>
                        <td><?= htmlspecialchars((string)$row['customer_name']) ?></td>
                        <td><?= htmlspecialchars((string)$row['phone']) ?></td>
                        <td>
                            <?php
                            $badge = 'secondary';
                            if ($row['status'] == 'success') $badge = 'success';
                            if ($row['status'] == 'failed') $badge = 'danger';
                            if ($row['status'] == 'pending') $badge = 'warning';
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= strtoupper($row['status']) ?></span>
                            <?php if ($row['retry_count'] > 0): ?>
                                <small class="text-muted d-block">Tries: <?= $row['retry_count'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- زر فتح نافذة الـ Modal لنسخ ورؤية استجابة الـ API -->
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" data-response="<?= htmlspecialchars((string)($row['api_response'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" onclick="showApiResponse(this)">
                                View Response
                            </button>
                        </td>
                        <td><?= $row['created_at'] ?></td>
                        <td><?= $row['last_attempt_at'] ?? 'N/A' ?></td>
                        <td>
                            <!-- إظهار زر إعادة المحاولة (Retry) فقط في حالة الفشل -->
                            <?php if ($row['status'] === 'failed'): ?>
                                <button onclick="retryRequest(<?= (int)$row['id'] ?>)" class="btn btn-sm btn-outline-danger" id="btn-retry-<?= (int)$row['id'] ?>">
                                    Retry
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 6. نافذة الـ Modal المخصصة لعرض استجابة الـ JSON بأسلوب أنيق -->
<!-- ========================================================================= -->
<div class="modal fade" id="responseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">API Response Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <pre id="modalResponseBody" class="json-body"></pre>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * دالة عرض الـ JSON داخل الـ Modal
 */
function showApiResponse(btn) {
    const rawData = btn.getAttribute('data-response');
    const modalBody = document.getElementById('modalResponseBody');
    
    if (!rawData || rawData.trim() === '') {
        modalBody.innerText = 'No response recorded';
    } else {
        try {
            const parsed = JSON.parse(rawData);
            modalBody.innerText = JSON.stringify(parsed, null, 2);
        } catch (e) {
            modalBody.innerText = rawData;
        }
    }
    
    const responseModal = new bootstrap.Modal(document.getElementById('responseModal'));
    responseModal.show();
}

/**
 * دالة تنفيذ إعادة المحاولة (Retry Request) عبر AJAX
 */
function retryRequest(logId) {
    const btn = document.getElementById('btn-retry-' + logId);
    btn.disabled = true;
    btn.innerText = 'Retrying...';

    fetch('api/retry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ log_id: logId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.message) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to retry'));
            btn.disabled = false;
            btn.innerText = 'Retry';
        }
    })
    .catch(err => {
        alert('Network Error');
        btn.disabled = false;
        btn.innerText = 'Retry';
    });
}
</script>
</body>
</html>