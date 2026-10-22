<?php
// ==============================================================================
// 1. تضمين الملفات وتهيئة الاتصال بقاعدة البيانات
// ==============================================================================

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db       = $database->getConnection();

// ==============================================================================
// 2. استخراج الإحصائيات العامة لسجلات التكامل (Dashboard Statistics)
// ==============================================================================

// حساب إجمالي الطلبات الكلية المسجلة في النظام
$totalRequests = $db->query("SELECT COUNT(*) FROM integration_logs")->fetchColumn();

// حساب عدد العمليات الناجحة
$successful    = $db->query("SELECT COUNT(*) FROM integration_logs WHERE status = 'success'")->fetchColumn();

// حساب عدد العمليات الفاشلة
$failed        = $db->query("SELECT COUNT(*) FROM integration_logs WHERE status = 'failed'")->fetchColumn();

// حساب عدد العمليات المعلقة (قيد الانتظار)
$pending       = $db->query("SELECT COUNT(*) FROM integration_logs WHERE status = 'pending'")->fetchColumn();

// ==============================================================================
// 3. استقبال معايير الفلترة وبناء استعلام السجلات (Filtering & Query Building)
// ==============================================================================

// استقبال قيم الفلاتر من الرابط عبر GET
$statusFilter = $_GET['status'] ?? '';
$phoneFilter  = $_GET['phone'] ?? '';
$dateFilter   = $_GET['date'] ?? '';

// الاستعلام الأساسي مع الربط بجدول العملاء لجلب بيانات العميل والسجل
$query = "SELECT l.id as log_id, l.customer_id, c.name as customer_name, c.phone, l.status, l.attempts, l.response_code, l.response_body, l.created_at, l.updated_at 
          FROM integration_logs l 
          JOIN customers c ON l.customer_id = c.id WHERE 1=1";

$params = [];

// فلترة السجلات بحسب الحالة (Success, Failed, Pending)
if (!empty($statusFilter)) {
    $query   .= " AND l.status = ?";
    $params[] = $statusFilter;
}

// فلترة السجلات برقم جوال العميل
if (!empty($phoneFilter)) {
    $query   .= " AND c.phone LIKE ?";
    $params[] = "%$phoneFilter%";
}

// فلترة السجلات بتاريخ محدد
if (!empty($dateFilter)) {
    $query   .= " AND DATE(l.created_at) = ?";
    $params[] = $dateFilter;
}

// ترتيب النتائج تنازلياً لجلب أحدث السجلات أولاً
$query .= " ORDER BY l.id DESC";

// تجهيز وتنفيذ الاستعلام الآمن باستخدام Prepared Statements
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة مراقبة التكامل - Integration Monitoring Dashboard</title>
    <!-- مكتبة Bootstrap لتنسيق الواجهة ودعم الاتجاه من اليمين لليسار (RTL) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
    <div class="container">
        <h2 class="mb-4 text-center">لوحة مراقبة التكامل والطلبات (Monitoring Dashboard)</h2>
        
        <!-- ==================================================================== -->
        <!-- بطاقات مؤشرات الأداء والإحصائيات الشاملة                             -->
        <!-- ==================================================================== -->
        <div class="row text-white mb-4 text-center">
            <!-- بطاقة إجمالي الطلبات -->
            <div class="col-md-3 mb-2">
                <div class="card bg-primary p-3 shadow-sm">
                    <h5>Total Requests</h5>
                    <h3><?php echo $totalRequests; ?></h3>
                </div>
            </div>
            <!-- بطاقة العمليات الناجحة -->
            <div class="col-md-3 mb-2">
                <div class="card bg-success p-3 shadow-sm">
                    <h5>Successful</h5>
                    <h3><?php echo $successful; ?></h3>
                </div>
            </div>
            <!-- بطاقة العمليات الفاشلة -->
            <div class="col-md-3 mb-2">
                <div class="card bg-danger p-3 shadow-sm">
                    <h5>Failed</h5>
                    <h3><?php echo $failed; ?></h3>
                </div>
            </div>
            <!-- بطاقة العمليات المعلقة -->
            <div class="col-md-3 mb-2">
                <div class="card bg-warning text-dark p-3 shadow-sm">
                    <h5>Pending</h5>
                    <h3><?php echo $pending; ?></h3>
                </div>
            </div>
        </div>

        <!-- ==================================================================== -->
        <!-- نموذج شريط الفلاتر للبحث والتصفية (Filter Form)                      -->
        <!-- ==================================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <!-- تصفية حسب حالة السجل -->
                    <div class="col-md-3">
                        <label class="form-label">الحالة (Status)</label>
                        <select name="status" class="form-select">
                            <option value="">الكل</option>
                            <option value="success" <?php if($statusFilter=='success') echo 'selected'; ?>>Success</option>
                            <option value="failed" <?php if($statusFilter=='failed') echo 'selected'; ?>>Failed</option>
                            <option value="pending" <?php if($statusFilter=='pending') echo 'selected'; ?>>Pending</option>
                        </select>
                    </div>
                    <!-- تصفية حسب رقم الجوال -->
                    <div class="col-md-3">
                        <label class="form-label">رقم الجوال (Phone)</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phoneFilter); ?>" placeholder="بحث برقم الجوال...">
                    </div>
                    <!-- تصفية حسب تاريخ الإنشاء -->
                    <div class="col-md-3">
                        <label class="form-label">التاريخ (Date)</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                    </div>
                    <!-- أزرار تطبيق الفلترة وإلغائها -->
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-dark w-100 me-2">فلترة</button>
                        <a href="index.php" class="btn btn-secondary w-100">إعادة تعيين</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ==================================================================== -->
        <!-- جدول عرض تفاصيل سجلات التكامل (Records Table)                       -->
        <!-- ==================================================================== -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">سجلات التكامل (Records Table)</div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped align-middle text-center">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Last API Response</th>
                            <th>Created Date</th>
                            <th>Last Attempt Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- في حال عدم وجود أي نتائج مطابقة -->
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="9" class="text-muted">لا توجد سجلات مطابقة</td></tr>
                        <?php else: ?>
                            <!-- عرض السجلات داخل صفوف الجدول -->
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['customer_id']; ?></td>
                                    <td><?php echo htmlspecialchars($log['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($log['phone']); ?></td>
                                    <!-- شارة ملونة توضح حالة السجل -->
                                    <td>
                                        <span class="badge bg-<?php echo $log['status']=='success'?'success':($log['status']=='failed'?'danger':'warning'); ?>">
                                            <?php echo $log['status']; ?>
                                        </span>
                                    </td>
                                    <!-- عدد المحاولات المنفذة من أصل 3 محاولات -->
                                    <td><?php echo $log['attempts']; ?> / 3</td>
                                    <!-- رمز استجابة السيرفر الخارجي إن وجد -->
                                    <td><small class="text-muted"><?php echo htmlspecialchars($log['response_code'] ? 'Code: '.$log['response_code'] : '-'); ?></small></td>
                                    <td><?php echo $log['created_at']; ?></td>
                                    <td><?php echo $log['updated_at']; ?></td>
                                    <!-- عمود الإجراءات: تفعيل زر إعادة المحاولة للطلبات الفاشلة فقط التي لم تتجاوز الحد الأقصى -->
                                    <td>
                                        <?php if($log['status'] === 'failed' && $log['attempts'] < 3): ?>
                                            <button onclick="retryLog(<?php echo $log['log_id']; ?>)" class="btn btn-sm btn-warning">Retry</button>
                                        <?php else: ?>
                                            <span class="text-muted small">غير متاح</span>
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

    <!-- ======================================================================== -->
    <!-- كود JavaScript لإرسال طلب إعادة المحاولة عبر AJAX/Fetch                   -->
    <!-- ======================================================================== -->
    <script>
        /**
         * إرسال طلب إعادة محاولة إرسال السجل إلى نقطة الـ API
         * 
         * @param {number} logId معرف السجل المطلوب إعادة إرساله
         */
        function retryLog(logId) {
            // تأكيد العملية من المستخدم أولاً
            if (!confirm('هل تريد إعادة المحاولة لهذا الطلب؟')) return;

            // استدعاء واجهة برمجة التطبيقات الخاصة بإعادة المحاولة
            fetch('api/retry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id: logId })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                // إعادة تحميل الصفحة لتحديث الجداول وحالات المحاولة
                location.reload();
            })
            .catch(err => alert('حدث خطأ أثناء الاتصال.'));
        }
    </script>
</body>
</html>