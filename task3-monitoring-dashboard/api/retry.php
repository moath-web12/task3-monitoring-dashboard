<?php
// task3-monitoring-dashboard/api/retry.php

// =========================================================================
// 1. إعداد هيدر نوع الاستجابة (JSON) واستدعاء الاتصال بقاعدة البيانات
// =========================================================================
header("Content-Type: application/json; charset=UTF-8");
require_once "../config/database.php";

$database = new Database();
$db = $database->getConnection();

// =========================================================================
// 2. قراءة بيانات الـ JSON المستقبلة والتحقق من معرف السجل (Log ID)
// =========================================================================
$data = json_decode(file_get_contents("php://input"), true);
$log_id = isset($data['log_id']) ? intval($data['log_id']) : null;

if (!$log_id) {
    http_response_code(400);
    echo json_encode(["error" => "Log ID is required"]);
    exit;
}

// =========================================================================
// 3. جلب بيانات السجل المطلوب مع تفاصيل العميل المرتبط به
// =========================================================================
$query = "SELECT wl.*, c.name, c.phone, c.email 
          FROM webhook_logs wl 
          JOIN customers c ON wl.customer_id = c.id 
          WHERE wl.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(":id", $log_id);
$stmt->execute();
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    http_response_code(404);
    echo json_encode(["error" => "Log record not found"]);
    exit;
}

// =========================================================================
// 4. تجهيز الحمولة (Payload) وإعادة إرسالها للـ API الخارجي عبر cURL
// =========================================================================
$payload = [
    "name"  => $log['name'],
    "phone" => $log['phone'],
    "email" => $log['email']
];

$ch = curl_init("https://httpbin.org/post");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // لتجاوز مشاكل شهادات SSL في السيرفر المحلي
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // حد أقصى للانتظار 5 ثوانٍ

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// =========================================================================
// 5. تحديث حالة السجل وعدد محاولات الإعادة (Retry Count) في قاعدة البيانات
// =========================================================================
$status = ($http_code >= 200 && $http_code < 300) ? 'success' : 'failed';
$response_data = $response ? $response : "cURL Error: " . $curl_error;
$new_retry_count = $log['retry_count'] + 1;

$update_stmt = $db->prepare("UPDATE webhook_logs SET status = :status, api_response = :resp, retry_count = :rc, last_attempt_at = NOW() WHERE id = :id");
$update_stmt->bindParam(":status", $status);
$update_stmt->bindParam(":resp", $response_data);
$update_stmt->bindParam(":rc", $new_retry_count);
$update_stmt->bindParam(":id", $log_id);
$update_stmt->execute();

// =========================================================================
// 6. إرجاع النتيجة النهائية
// =========================================================================
echo json_encode([
    "message" => "Retry attempt completed",
    "status" => $status,
    "retry_count" => $new_retry_count
]);