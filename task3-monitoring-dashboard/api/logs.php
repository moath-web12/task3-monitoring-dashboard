<?php
// ==============================================================================
// 1. إعدادات بيئة التشغيل والحماية (Security & Environment Settings)
// ==============================================================================

// إخفاء رسائل أخطاء PHP عن المستخدم النهائي لحماية تفاصيل النظام والسيرفر
ini_set('display_errors', 0);
error_reporting(0);

// ==============================================================================
// 2. إعداد ترويسات الاستجابة ومشاركة الموارد (CORS & Headers)
// ==============================================================================

// تحديد صيغة البيانات المرجعة كـ JSON بترميز UTF-8
header("Content-Type: application/json; charset=UTF-8");

// السماح بالوصول واستقبال الطلبات من أي نطاق خارجي (CORS)
header("Access-Control-Allow-Origin: *");

// ==============================================================================
// 3. تضمين الملفات وتهيئة الاتصال بقاعدة البيانات
// ==============================================================================

// تضمين ملف إعدادات الاتصال بقاعدة البيانات
require_once __DIR__ . '/../config/database.php';

try {
    // إنشاء كائن قاعدة البيانات وفتح الاتصال
    $database = new Database();
    $db       = $database->getConnection();

    // التحقق من نجاح الاتصال بقاعدة البيانات
    if (!$db) {
        http_response_code(500);
        echo json_encode([
            "status"  => "error", 
            "message" => "Database connection failed."
        ]);
        exit();
    }

    // ==========================================================================
    // 4. استخراج الإحصائيات وبيانات السجلات (Queries & Processing)
    // ==========================================================================

    // 1. حساب إجمالي الطلبات الواردة للنظام بشكل شامل من جدول سجلات التكامل
    $totalQuery    = $db->query("SELECT COUNT(*) as total FROM integration_logs");
    $totalRequests = $totalQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. جلب السجلات مع بيانات العميل المرتبط بها عبر دمج الجداول (JOIN)
    $query = "SELECT l.id as log_id, l.customer_id, c.name as customer_name, c.phone, l.status, l.attempts, l.response_code, l.created_at 
              FROM integration_logs l 
              JOIN customers c ON l.customer_id = c.id 
              ORDER BY l.id DESC";

    // تجهيز وتنفيذ الاستعلام لجلب كافة السجلات كمصفوفة ترابطية
    $stmt = $db->prepare($query);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================================================================
    // 5. إرجاع الاستجابة النهائية للعميل (Success Response)
    // ==========================================================================

    // إرجاع كود النجاح 200 مع إجمالي الطلبات ومصفوفة البيانات
    http_response_code(200);
    echo json_encode([
        "status"         => "success",
        "total_requests" => (int)$totalRequests,
        "data"           => $logs
    ]);

// ==============================================================================
// 6. معالجة الأخطاء العامة والاستثناءات (Error Handling)
// ==============================================================================
} catch (Exception $e) {
    // التقاط أي استثناء غير متوقع وإرجاع رمز الخطأ الداخلي 500
    http_response_code(500);
    echo json_encode([
        "status"  => "error", 
        "message" => "Internal server error."
    ]);
}
?>