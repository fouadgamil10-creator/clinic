<?php
// submit_appointment.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db_connect.php'; 

// --- دالة موحدة لرفع الملفات (زي ما هي، سليمة) ---
function handle_uploaded_file($file_key, $prefix) {
    global $upload_dir; 
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        $file_tmp_name = $_FILES[$file_key]['tmp_name'];
        $original_name = basename($_FILES[$file_key]['name']);
        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; 
        if (in_array($file_extension, $allowed_extensions) && $_FILES[$file_key]['size'] <= $max_size) {
            $new_filename = $prefix . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($file_tmp_name, $target_file)) {
                return $target_file; 
            } else { error_log("Failed to move file: " . $original_name); return null; }
        } else { error_log("Invalid file type/size: " . $original_name); return null; }
    }
    return null; 
}
// --- نهاية الدالة ---

$upload_dir = 'uploads/';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $doctor = trim($_POST['doctor']); // 'doctor1' أو 'doctor2'
    $booking_datetime = trim($_POST['booking_datetime']);
    
    $insurance_company = isset($_POST['insurance_company']) && !empty($_POST['insurance_company']) ? trim($_POST['insurance_company']) : null;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : null; 
    
    $transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : null;

    $insurance_card_path = null;
    $transaction_screenshot_path = null;

    $is_valid = true;
    $is_insurance_booking = ($insurance_company !== null); 

    if (empty($name) || empty($email) || empty($phone) || empty($doctor) || empty($booking_datetime)) {
        $is_valid = false;
        error_log("Validation failed: Basic fields missing.");
    }

    if ($is_insurance_booking) {
        // === حالة التأمين ===
        $insurance_card_path = handle_uploaded_file('insurance_card_photo', 'card_');
        if ($insurance_card_path === null) {
            $is_valid = false;
            error_log("Validation failed: Insurance booking, but no card photo uploaded.");
        }
    } else {
        // === حالة الدفع المباشر ===
        if (empty($payment_method)) {
            $is_valid = false;
            error_log("Validation failed: Non-insurance booking, but no payment method selected.");
        }
        if ($payment_method == 'instapay') {
            $transaction_screenshot_path = handle_uploaded_file('transaction_screenshot', 'receipt_');
            if ($transaction_screenshot_path === null) {
                $is_valid = false; 
                error_log("Validation failed: Instapay booking, but no receipt photo uploaded.");
            }
        }
    }

    if (!$is_valid) {
        header("Location: booking_error.html");
        exit();
    }

    // ----------------- (معدل بالكامل) التحقق من سعة الميعاد (حسب الطبيب) -----------------
    
    $is_doctor1 = ($doctor == 'doctor1'); // هل هو دكتور ماجد؟

    if ($is_insurance_booking) {
        // --- 1. التحقق من سعة التأمين ---
        $max_capacity = $is_doctor1 ? 1 : 2; // (جديد) د.ماجد: 1، د.شهيرة: 2
        $check_stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM appointments 
             WHERE appointment_time = ? AND doctor_name = ? AND insurance_company IS NOT NULL"
        );
    } else {
        // --- 2. التحقق من سعة العادي ---
        $max_capacity = 1; // (العادي دائماً 1)
        $check_stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM appointments 
             WHERE appointment_time = ? AND doctor_name = ? AND insurance_company IS NULL"
        );
    }

    // تنفيذ الاستعلام
    $check_stmt->bind_param("ss", $booking_datetime, $doctor);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    $current_count = (int)$row['count'];
    $check_stmt->close();

    // 3. المقارنة
    if ($current_count >= $max_capacity) {
        error_log("Booking failed: Slot capacity reached for this pool. Slot: $booking_datetime, Count: $current_count, Max: $max_capacity");
        header("Location: booking_error.html"); // ابعته لصفحة الخطأ
        exit();
    }
    // ----------------- (نهاية خطوة التحقق من السعة) -----------------

    // 7. تحضير جملة SQL (لو كل حاجة تمام)
    $stmt = $conn->prepare("INSERT INTO appointments (
        patient_name, patient_email, patient_phone, doctor_name, appointment_time, 
        payment_method, insurance_company, transaction_id, screenshot_path, insurance_card_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssssssssss", 
        $name, $email, $phone, $doctor, $booking_datetime,
        $payment_method, $insurance_company, $transaction_id, $transaction_screenshot_path, $insurance_card_path
    );

    // 8. تنفيذ الجملة وإرسال الإيميل (زي ما هو، سليم)
    if ($stmt->execute()) {
        
        $to_email = "bookingclinic890@gmail.com"; 
        $subject = "حجز موعد جديد في العيادة - " . $name;
        $doctor_display_name = ($doctor == 'doctor1') ? "د/ ماجد فوزي" : "د/ شهيرة لويز";
        $base_url = "http://localhost/familyclinic/"; 

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: no-reply@family-clinic.com" . "\r\n" . "Reply-To: " . $email . "\r\n";

        $message = "<html><body style='direction: rtl; font-family: Arial, sans-serif; text-align: right;'>";
        $message .= "<h2 style='color: #007bff;'>تم استلام حجز موعد جديد</h2>";
        $message .= "<table border='1' cellpadding='10' style='width: 100%; border-collapse: collapse; border-color: #ddd;'>";
        $message .= "<tr><td style='background: #f4f7f6; width: 150px;'><strong>اسم المريض:</strong></td><td>" . htmlspecialchars($name) . "</td></tr>";
        $message .= "<tr><td style='background: #f4f7f6;'><strong>رقم الهاتف:</strong></td><td>" . htmlspecialchars($phone) . "</td></tr>";
        $message .= "<tr><td style='background: #f4f7f6;'><strong>الطبيب المطلوب:</strong></td><td>" . htmlspecialchars($doctor_display_name) . "</td></tr>";
        $message .= "<tr><td style='background: #f4f7f6;'><strong>الموعد:</strong></td><td>" . htmlspecialchars($booking_datetime) . "</td></tr>";

        if ($is_insurance_booking) {
            $message .= "<tr><td style='background: #f4f7f6;'><strong>نظام الحجز:</strong></td><td style='color: #007bff; font-weight: bold;'>تأمين</td></tr>";
            $message .= "<tr><td style='background: #f4f7f6;'><strong>شركة التأمين:</strong></td><td>" . htmlspecialchars($insurance_company) . "</td></tr>";
            if ($insurance_card_path) {
                $full_image_url = $base_url . $insurance_card_path;
                $message .= "<tr><td style='background: #f4f7f6;'><strong>صورة الكارنيه:</strong></td>";
                $message .= "<td><a href='" . $full_image_url . "' target='_blank'><img src='" . $full_image_url . "' alt='صورة كارنيه التأمين' style='max-width: 400px; height: auto;' /></a></td></tr>";
            }
        } else {
            $message .= "<tr><td style='background: #f4f7f6;'><strong>نظام الحجز:</strong></td><td style='color: #1abc9c; font-weight: bold;'>دفع مباشر</td></tr>";
            $message .= "<tr><td style='background: #f4f7f6;'><strong>طريقة الدفع:</strong></td><td>" . htmlspecialchars($payment_method) . "</td></tr>";
            if ($transaction_screenshot_path) {
                $full_image_url = $base_url . $transaction_screenshot_path;
                $message .= "<tr><td style='background: #f4f7f6;'><strong>صورة الإيصال:</strong></td>";
                $message .= "<td><a href='" . $full_image_url . "' target='_blank'><img src='" . $full_image_url . "' alt='صورة إيصال الدفع' style='max-width: 400px; height: auto;' /></a></td></tr>";
            }
        }
        $message .= "</table>";
        $message .= "</body></html>";

        @mail($to_email, $subject, $message, $headers);
        
        header("Location: booking_success.html");
        
    } else {
        error_log("Database INSERT failed: " . $stmt->error);
        header("Location: booking_error.html");
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: booking_error.html");
}
?>