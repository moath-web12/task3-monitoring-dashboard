# Task 3 - Integration Monitoring Dashboard

لوحة تحكم تفاعلية لمراقبة عمليات Integration، وعرض حالة الطلبات وإعادة محاولة الطلبات الفاشلة.

## Features

- عرض إحصائيات الطلبات:
  - Total Requests
  - Successful
  - Failed
  - Pending
- فلترة السجلات حسب:
  - Status
  - Phone Number
  - Created Date
- عرض آخر API Response.
- إعادة محاولة إرسال الطلبات الفاشلة باستخدام Retry.
- تحديث حالة الطلب وعدد محاولات الإعادة في قاعدة البيانات.

## Requirements

- XAMPP أو أي بيئة تدعم PHP وMySQL.
- PHP 7.4 أو أحدث.
- MySQL.
- متصفح ويب.
- تفعيل PHP cURL Extension.

## Installation Steps

### 1. Clone the Repository

ضع المشروع داخل مجلد `htdocs` في XAMPP:

```text
C:\xampp\htdocs\
