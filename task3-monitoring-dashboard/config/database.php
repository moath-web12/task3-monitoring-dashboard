<?php
/**
 * كلاس الاتصال بقاعدة البيانات (Database Connection Class)
 * يتبع النمط الفردي/المساعد لإدارة الاتصال بقاعدة بيانات MySQL باستخدام PDO
 */
class Database {
    // ---------------------------------------------------------
    // إعدادات وبيانات الاتصال بقاعدة البيانات (خاصة بالفيئة Private)
    // ---------------------------------------------------------
    private $host = "localhost";      // عنوان خادم قاعدة البيانات
    private $db_name = "internship_db"; // اسم قاعدة البيانات المراد الاتصال بها
    private $username = "root";       // اسم مستخدم قاعدة البيانات
    private $password = "";           // كلمة مرور قاعدة البيانات (فارغة افتراضياً في البيئة المحلية)
    
    // متغير عام للتحكم في جلسة الاتصال المستقبلية (PDO Object)
    public $conn;

    /**
     * دالة إنشاء وإنشاء جلسة الاتصال بقاعدة البيانات
     * 
     * @return PDO|null يُرجع كائن الاتصال PDO في حال النجاح
     */
    public function getConnection() {
        // تفريغ أي اتصال سابق لضمان بدء جلسة جديدة
        $this->conn = null;

        try {
            // إنشاء كائن PDO جديد مع إرسال بيانات السيرفر والقاعدة والاسم والباسورد
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            
            // ضبط الترميز ليدعم اللغة العربية والرموز التعبيرية بشكل كامل (utf8mb4)
            $this->conn->exec("set names utf8mb4");
            
            // إعداد وضع معالجة الأخطاء ليرمي استثناءات (Exceptions) عند حدوث أي خطأ في الاستعلامات
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch(PDOException $exception) {
            // في حال فشل الاتصال: إرجاع رمز الخطأ 500 (Internal Server Error)
            http_response_code(500);
            
            // طباعة رسالة الخطأ لتنسيق JSON وإيقاف التنفيذ
            echo json_encode(["error" => "Database connection failed: " . $exception->getMessage()]);
            exit;
        }

        // إرجاع كائن الاتصال الناجح لاستخدامه في الملفات الأخرى
        return $this->conn;
    }
}
