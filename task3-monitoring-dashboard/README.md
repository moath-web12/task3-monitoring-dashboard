# مشروع لوحة مراقبة التكامل ونظام إعادة المحاولة

لوحة تحكم ومراقبة مركزية مبنية بلغة PHP وقاعدة بيانات MySQL عبر مكتبة PDO وإطار Bootstrap 5، تتيح متابعة سجلات تكامل العملاء مع الأنظمة الخارجية، وعرض الإحصائيات الفورية، وتصفية السجلات، وإعادة محاولة إرسال العمليات الفاشلة يدوياً (Manual Retry) بحد أقصى 3 محاولات لكل عملية.

## بنية المشروع وآلية العمل

يتكون المشروع من الملفات والمجلدات التالية:

```text
task3-monitoring-dashboard/
├── api/
│   ├── logs.php
│   └── retry.php
├── config/
│   └── database.php
├── database.sql
├── index.php
└── README.md
```

- **`index.php`:** الواجهة المرئية للوحة التحكم لعرض بطاقات الإحصائيات، وشريط الفلترة، وجدول سجلات التكامل مع زر إعادة المحاولة.
- **`api/logs.php`:** نقطة نهاية (`GET`) لإرجاع إجمالي الطلبات وقائمة سجلات التكامل المرتبطة ببيانات العملاء بصيغة JSON.
- **`api/retry.php`:** نقطة نهاية (`POST`) لمعالجة طلبات إعادة المحاولة اليدوية للعمليات الفاشلة والتحقق من شروطها.
- **`config/database.php`:** فئة الاتصال بقاعدة البيانات باستخدام PDO مع دعم الترميز العربي `utf8mb4`.
- **`database.sql`:** ملف SQL لإنشاء جدولي العملاء `customers` وسجلات التكامل `integration_logs`.
- **`README.md`:** دليل التوثيق والتشغيل الشامل للمشروع.

### آلية عمل النظام

1. **حساب الإحصائيات الشاملة:** تحسب لوحة التحكم في `index.php` ونقطة النهاية `api/logs.php` إجمالي الطلبات المسجلة في جدول `integration_logs`، موزعة إلى أربع بطاقات: إجمالي الطلبات (`Total Requests`)، والناجحة (`Successful`)، والفاشلة (`Failed`)،وقيد الانتظار (`Pending`).
2. **البحث والفلترة المتقدمة:** تتيح لوحة التحكم تصفية السجلات عبر معاملات `GET` حسب الحالة (`status`)، أو البحث الجزئي برقم الهاتف (`phone`)، أو تاريخ الإنشاء (`date`) باستخدام استعلامات PDO محمية.
3. **ضوابط نظام إعادة المحاولة:** يتحقق الملف `api/retry.php` قبل إعادة الإرسال من ثلاثة شروط أساسية:
   - وجود سجل التكامل في قاعدة البيانات.
   - أن تكون حالة السجل غير ناجحة (يُمنع إعادة إرسال طلب حالته `success`).
   - ألا يكون عدد المحاولات قد وصل إلى الحد الأقصى المسموح به وهو 3 محاولات (`attempts < 3`).
4. **الاتصال الخارجي وتحديث السجل:** عند استيفاء الشروط، يزيد النظام عداد المحاولات بمقدار `1`، ويرسل بيانات العميل عبر `cURL` إلى الواجهة الخارجية `https://jsonplaceholder.typicode.com/users` مع تفعيل فحص شهادة SSL، ثم يحدث حالة السجل وكود الاستجابة في قاعدة البيانات.

## متطلبات التشغيل

- خادم محلي يدعم PHP (الإصدار 7.4 أو أحدث) وقاعدة بيانات MySQL أو MariaDB (مثل XAMPP أو WampServer أو Laragon).
- تفعيل إضافات `pdo_mysql` و `curl` و `openssl` في إعدادات PHP.
- اتصال بالإنترنت لتحميل تنسيقات Bootstrap 5 وإتمام طلبات إعادة المحاولة مع النظام الخارجي.
- متصفح ويب لعرض لوحة التحكم، وأداة مثل Postman أو `cURL` لاختبار نقاط النهاية البرمجية.

## خطوات التثبيت والتشغيل

1. **نقل المشروع إلى مجلد الخادم المحلي:**
   انسخ مجلد `task3-monitoring-dashboard` إلى مسار الخادم المحلي لديك:
   - مسار XAMPP:
     `C:\xampp\htdocs\task3-monitoring-dashboard`
   - مسار WampServer:
     `C:\wamp64\www\task3-monitoring-dashboard`
   - مسار Laragon:
     `C:\laragon\www\task3-monitoring-dashboard`

2. **تشغيل خدمات الخادم:**
   افتح لوحة تحكم الخادم المحلي وشغّل خدمتي Apache و MySQL.

3. **إعداد قاعدة البيانات:**
   افتح `http://localhost/phpmyadmin` واستورد ملف `database.sql`، أو نفّذ استعلامات SQL الموضحة في القسم التالي لإعداد الجداول وإضافة بيانات تجريبية.

4. **مطابقة إعدادات الاتصال:**
   افتح الملف `config/database.php` وتأكد من مطابقة اسم قاعدة البيانات في المتغير `$db_name` (`internship_db` أو `webhook_db`).

5. **فتح لوحة التحكم في المتصفح:**
   انتقل عبر المتصفح إلى الرابط التالي لعرض لوحة المراقبة:
   `http://localhost/task3-monitoring-dashboard/index.php`

## إعدادات قاعدة البيانات وملف SQL

تُضبط بيانات الاتصال الافتراضية في الملف `config/database.php` كالتالي:

| المعامل | القيمة الافتراضية في الكود | الوصف |
| :--- | :--- | :--- |
| `$host` | `localhost` | عنوان خادم قاعدة البيانات |
| `$db_name` | `internship_db` | اسم قاعدة البيانات المعتمدة في الاتصال |
| `$username` | `root` | اسم مستخدم قاعدة البيانات |
| `$password` | `""` | كلمة مرور قاعدة البيانات (فارغة افتراضياً) |
| `charset` | `utf8mb4` | ترميز الاتصال لدعم النصوص العربية |

### كود إنشاء قاعدة البيانات والجداول (`database.sql`)

```sql
CREATE DATABASE IF NOT EXISTS `internship_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `internship_db`;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `integration_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
  `attempts` INT(11) NOT NULL DEFAULT 1,
  `response_code` INT(11) DEFAULT NULL,
  `response_body` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### استعلام إضافة بيانات تجريبية لاختبار اللوحة وإعادة المحاولة

```sql
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `status`) VALUES
(1, 'سعد علي العماري', '0501234567', 'saad@example.com', 'active'),
(2, 'معاذ العماري', '0559876543', 'muath@example.com', 'active'),
(3, 'خالد محمد', '0541112233', 'khaled@example.com', 'active');

INSERT INTO `integration_logs` (`customer_id`, `status`, `attempts`, `response_code`, `response_body`) VALUES
(1, 'success', 1, 201, '{"id": 11}'),
(2, 'failed', 1, 500, 'Internal Server Error'),
(3, 'failed', 3, 503, 'Service Unavailable');
```

### توصيف جدول العملاء (`customers`)

| العمود | النوع | القيود | الوصف |
| :--- | :--- | :--- | :--- |
| `id` | `INT(11)` | `PRIMARY KEY, AUTO_INCREMENT` | المعرف الفريد للعميل |
| `name` | `VARCHAR(100)` | `NOT NULL` | اسم العميل |
| `phone` | `VARCHAR(20)` | `NOT NULL, UNIQUE` | رقم الجوال |
| `email` | `VARCHAR(100)` | `DEFAULT NULL` | البريد الإلكتروني |
| `status` | `ENUM('active', 'inactive')` | `DEFAULT 'active'` | حالة العميل |
| `created_at` | `TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | تاريخ إنشاء السجل |

### توصيف جدول سجلات التكامل (`integration_logs`)

| العمود | النوع | القيود | الوصف |
| :--- | :--- | :--- | :--- |
| `id` | `INT(11)` | `PRIMARY KEY, AUTO_INCREMENT` | المعرف الفريد لسجل التكامل (`log_id`) |
| `customer_id` | `INT(11)` | `NOT NULL, INDEX` | معرف العميل المرتبط بالسجل |
| `status` | `ENUM('pending', 'success', 'failed')` | `DEFAULT 'pending'` | حالة عملية التكامل |
| `attempts` | `INT(11)` | `DEFAULT 1` | عدد المحاولات المنفذة (الحد الأقصى 3) |
| `response_code` | `INT(11)` | `DEFAULT NULL` | كود استجابة HTTP الأخير من النظام الخارجي |
| `response_body` | `TEXT` | `DEFAULT NULL` | محتوى استجابة النظام الخارجي |
| `created_at` | `TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | تاريخ إنشاء السجل |
| `updated_at` | `TIMESTAMP` | `ON UPDATE CURRENT_TIMESTAMP` | تاريخ آخر محاولة تحديث |

## نقاط النهاية وطريقة الاستخدام

يحتوي المشروع على واجهة لوحة التحكم ونقطتي نهاية برمجيتين (API Endpoints):

| الوظيفة | الطريقة | المسار | كود النجاح | الوصف |
| :--- | :--- | :--- | :--- | :--- |
| واجهة لوحة التحكم | `GET` | `/index.php` | `200 OK` | عرض الإحصائيات وجدول السجلات مع دعم الفلترة |
| جلب السجلات والإحصائيات | `GET` | `/api/logs.php` | `200 OK` | جلب إجمالي الطلبات وقائمة سجلات التكامل بصيغة JSON |
| إعادة المحاولة اليدوية | `POST` | `/api/retry.php` | `200 OK` | إعادة إرسال عملية فاشلة إلى النظام الخارجي عبر `log_id` |

### 1. استخدام الفلاتر في لوحة التحكم (`/index.php`)

يمكن تصفية الجدول مباشرة من واجهة المتصفح أو عبر تمرير معاملات الاستعلام (Query Parameters) في الرابط:

- `status`: تصفية حسب الحالة (`success` أو `failed` أو `pending`).
- `phone`: بحث برقم الجوال أو جزء منه.
- `date`: تصفية حسب تاريخ الإنشاء بصيغة `YYYY-MM-DD`.

**مثال رابط مع الفلترة:**

```text
http://localhost/task3-monitoring-dashboard/index.php?status=failed&phone=055&date=2026-10-21
```

### 2. جلب الإحصائيات وسجلات المراقبة (`/api/logs.php`)

- **طريقة الطلب:** `GET`
- **الرابط:** `http://localhost/task3-monitoring-dashboard/api/logs.php`

**مثال الطلب باستخدام cURL:**

```bash
curl -X GET "http://localhost/task3-monitoring-dashboard/api/logs.php"
```

**الاستجابة الناجحة (`200 OK`):**

```json
{
  "status": "success",
  "total_requests": 3,
  "data": [
    {
      "log_id": 3,
      "customer_id": 3,
      "customer_name": "خالد محمد",
      "phone": "0541112233",
      "status": "failed",
      "attempts": 3,
      "response_code": 503,
      "created_at": "2026-10-21 14:20:00"
    },
    {
      "log_id": 2,
      "customer_id": 2,
      "customer_name": "معاذ العماري",
      "phone": "0559876543",
      "status": "failed",
      "attempts": 1,
      "response_code": 500,
      "created_at": "2026-10-21 14:15:00"
    },
    {
      "log_id": 1,
      "customer_id": 1,
      "customer_name": "سعد علي العماري",
      "phone": "0501234567",
      "status": "success",
      "attempts": 1,
      "response_code": 201,
      "created_at": "2026-10-21 14:10:00"
    }
  ]
}
```

**استجابة خطأ الاتصال بقاعدة البيانات (`500 Internal Server Error`):**

```json
{
  "status": "error",
  "message": "Database connection failed."
}
```

### 3. إعادة محاولة إرسال عملية فاشلة (`/api/retry.php`)

- **طريقة الطلب:** `POST`
- **الرابط:** `http://localhost/task3-monitoring-dashboard/api/retry.php`
- **الترويسة:** `Content-Type: application/json`

**حقول جسم الطلب:**

| الحقل | النوع | الحالة | الوصف |
| :--- | :--- | :--- | :--- |
| `log_id` | `integer` | إلزامي | المعرف الفريد لسجل التكامل المراد إعادة محاولته |

**مثال جسم الطلب:**

```json
{
  "log_id": 2
}
```

**مثال الطلب باستخدام cURL:**

```bash
curl -X POST "http://localhost/task3-monitoring-dashboard/api/retry.php" \
  -H "Content-Type: application/json" \
  -d '{"log_id":2}'
```

**الاستجابة عند نجاح إعادة المحاولة (`200 OK`):**

```json
{
  "status": "success",
  "message": "Retry successful. External API processed the request.",
  "attempts": 2
}
```

**أمثلة استجابات الخطأ والتحقق:**

عند تجاوز الحد الأقصى للمحاولات (3 محاولات) (`400 Bad Request`):

```json
{
  "status": "error",
  "message": "Maximum retry limit (3 attempts) reached. Cannot retry further."
}
```

عند محاولة إعادة إرسال عملية ناجحة مسبقاً (`400 Bad Request`):

```json
{
  "status": "error",
  "message": "Cannot retry a request that is already successful."
}
```

عند عدم إرسال المعرف `log_id` (`400 Bad Request`):

```json
{
  "status": "error",
  "message": "Log ID is required."
}
```

عند فشل النظام الخارجي أثناء إعادة المحاولة (`400 Bad Request`):

```json
{
  "status": "error",
  "message": "Retry failed. External API responded with code: 500",
  "attempts": 2
}
```

عند عدم العثور على سجل التكامل المطلوب (`404 Not Found`):

```json
{
  "status": "error",
  "message": "Integration log record not found."
}
```

عند استخدام طريقة HTTP غير `POST` (`405 Method Not Allowed`):

```json
{
  "status": "error",
  "message": "Method not allowed. Use POST."
}
```

## ملخص أكواد حالة HTTP

| الكود | الحالة | الوصف |
| :--- | :--- | :--- |
| `200 OK` | `success` | نجاح جلب السجلات أو نجاح إعادة المحاولة مع النظام الخارجي |
| `400 Bad Request` | `error` | نقص `log_id`، أو تجاوز 3 محاولات، أو العملية ناجحة مسبقاً، أو فشل استجابة النظام الخارجي |
| `404 Not Found` | `error` | عدم وجود سجل تكامل بالمعرف `log_id` المرسل |
| `405 Method Not Allowed` | `error` | استدعاء `/api/retry.php` بطريقة غير `POST` |
| `500 Internal Server Error` | `error` | فشل الاتصال بقاعدة البيانات أو خطأ داخلي في الخادم |
