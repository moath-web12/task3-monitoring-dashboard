<?php
// ==============================================================================
// 1. إعدادات بيئة التشغيل والحماية (Security & Environment Settings)
// ==============================================================================

// إخفاء رسائل أخطاء PHP عن المستخدم النهائي لحماية تفاصيل النظام والسيرفر
ini_set('display_errors', 0);
error_reporting(0);

// ==============================================================================
// 2. إعداد ترويسات الاستجابة والتحقق من طريقة الطلب (Headers & Method Check)
// ==============================================================================

// تحديد صيغة البيانات المرجعة كـ JSON بترميز UTF-8
header("Content-Type: application/json; charset=UTF-8");

// حصر استقبال الطلبات على طريقة POST فقط
header("Access-Control-Allow-Methods: POST");

// تحديد الترويسات المسموح باستقبالها في الطلب
header("Access-Control-Allow-Headers: Content-Type");

// التحقق الصارم من أن الطلب وارد بطريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status"  => "error", 
        "message" => "Method not allowed. Use POST."
    ]);
    exit();
}

// ==============================================================================
// 3. تضمين الملفات والتحقق من المدخلات (Dependencies & Input Validation)
// ==============================================================================

// تضمين ملف إعدادات الاتصال بقاعدة البيانات
require_once __DIR__ . '/../config/database.php';

// قراءة بيانات الطلب الواردة بصيغة JSON وفك تشفيرها
$input = file_get_contents("php://input");
$data  = json_decode($input, true);
$logId = $data['log_id'] ?? null;

// التحقق من إرسال معرف السجل (log_id)
if (!$logId) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error", 
        "message" => "Log ID is required."
    ]);
    exit();
}

// ==============================================================================
// 4. تنفيذ ومعالجة منطق إعادة المحاولة (Main Retry Logic)
// ==============================================================================

try {
    // إنشاء الاتصال بقاعدة البيانات
    $database = new Database();
    $db       = $database->getConnection();

    // --------------------------------------------------------------------------
    // أ. الاستعلام عن السجل وجلب بيانات العميل المرتبط به
    // --------------------------------------------------------------------------
    $stmt = $db->prepare("SELECT l.*, c.name, c.phone, c.email FROM integration_logs l JOIN customers c ON l.customer_id = c.id WHERE l.id = :id LIMIT 1");
    $stmt->bindParam(':id', $logId, PDO::PARAM_INT);
    $stmt->execute();
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    // التحقق من وجود السجل في قاعدة البيانات
    if (!$log) {
        http_response_code(404);
        echo json_encode([
            "status"  => "error", 
            "message" => "Integration log record not found."
        ]);
        exit();
    }

    // منع إعادة محاولة طلب تم بنجاح مسبقاً
    if ($log['status'] === 'success') {
        http_response_code(400);
        echo json_encode([
            "status"  => "error", 
            "message" => "Cannot retry a request that is already successful."
        ]);
        exit();
    }

    // التحقق من الحد الأقصى للمحاولات المسموح بها (3 محاولات كحد أقصى)
    if ($log['attempts'] >= 3) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error", 
            "message" => "Maximum retry limit (3 attempts) reached. Cannot retry further."
        ]);
        exit();
    }

    // زيادة عدد المحاولات لمحاولة الإرسال الجديدة
    $newAttempts = $log['attempts'] + 1;

    // --------------------------------------------------------------------------
    // ب. إعداد وإعادة إرسال البيانات للـ External API عبر cURL
    // --------------------------------------------------------------------------
    $externalApiUrl = "https://jsonplaceholder.typicode.com/users";
    $payload        = json_encode([
        "customer_id" => (int)$log['customer_id'],
        "name"        => $log['name'],
        "phone"       => $log['phone'],
        "email"       => $log['email']
    ]);

    // تهيئة جلسة cURL وضبط الخيارات
    $ch = curl_init($externalApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // تفعيل التحقق من شهادة SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    // استخدام ملف شهادة cacert.pem في حال وجوده في مجلد config
    $caCertPath = __DIR__ . '/../config/cacert.pem';
    if (file_exists($caCertPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
    }

    // تنفيذ طلب الإرسال واستخراج النتائج
    $response  = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // --------------------------------------------------------------------------
    // ج. تقييم الاستجابة وتحديث حالة السجل
    // --------------------------------------------------------------------------
    if ($curlErrno !== 0 || $httpCode < 200 || $httpCode >= 300) {
        // في حال فشل الاتصال أو إرجاع كود خطأ من السيرفر الخارجي
        $update = $db->prepare("UPDATE integration_logs SET status = 'failed', attempts = :attempts, response_code = :code, response_body = :body WHERE id = :id");
        $update->bindParam(':attempts', $newAttempts, PDO::PARAM_INT);
        $update->bindParam(':code', $httpCode, PDO::PARAM_INT);
        $update->bindParam(':body', $response);
        $update->bindParam(':id', $logId, PDO::PARAM_INT);
        $update->execute();

        // إرجاع استجابة تفيد بفشل محاولة الإعادة
        http_response_code(400);
        echo json_encode([
            "status"   => "error",
            "message"  => "Retry failed. External API responded with code: $httpCode",
            "attempts" => $newAttempts
        ]);
    } else {
        // في حال نجاح الاتصال وتأكيد الاستلام من السيرفر الخارجي
        $update = $db->prepare("UPDATE integration_logs SET status = 'success', attempts = :attempts, response_code = :code, response_body = :body WHERE id = :id");
        $update->bindParam(':attempts', $newAttempts, PDO::PARAM_INT);
        $update->bindParam(':code', $httpCode, PDO::PARAM_INT);
        $update->bindParam(':body', $response);
        $update->bindParam(':id', $logId, PDO::PARAM_INT);
        $update->execute();

        // إرجاع استجابة النجاح
        http_response_code(200);
        echo json_encode([
            "status"   => "success",
            "message"  => "Retry successful. External API processed the request.",
            "attempts" => $newAttempts
        ]);
    }

// ==============================================================================
// 5. معالجة الاستثناءات والأخطاء العامة (Exception Handling)
// ==============================================================================
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error", 
        "message" => "Internal server error during retry."
    ]);
}
?>