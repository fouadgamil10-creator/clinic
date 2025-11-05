<?php
// db_connect.php

$servername = "localhost"; // الخادم (غالباً localhost)
$username = "root";      // اسم المستخدم الافتراضي لـ XAMPP
$password = "";          // كلمة المرور الافتراضية لـ XAMPP (فارغة)
$dbname = "family_clinic_db"; // اسم قاعدة البيانات التي أنشأتها

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// ضبط الترميز لضمان دعم اللغة العربية
$conn->set_charset("utf8mb4");

?>