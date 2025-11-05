<?php
// تفعيل عرض الأخطاء على الشاشة
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$to = "clinicfamily222@gmail.com"; // <-- ضع إيميلك الحقيقي هنا
$subject = "Test from XAMPP";
$message = "This is a test email from the clinic website.";
$headers = "From: test@family-clinic.com";

echo "جاري محاولة إرسال الإيميل إلى " . $to . "<br>";

// محاولة إرسال الإيميل
if (mail($to, $subject, $message, $headers)) {
    echo "<h1>نجاح!</h1>";
    echo "أمر الـ PHP mail() تم تنفيذه بنجاح (هذا لا يعني أن الإيميل وصل، بل يعني أن PHP نجح في تسليم الأمر لـ sendmail).";
} else {
    echo "<h1>فشل!</h1>";
    echo "أمر الـ PHP mail() فشل. هذا يعني 100% أن هناك خطأ في إعدادات ملف 'php.ini' (المسار 'sendmail_path' غير صحيح أو الدالة معطلة).";
}

echo "<br><br>...انتهى الاختبار.";
?>