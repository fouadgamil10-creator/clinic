<?php
// get_booked_times.php
header('Content-Type: application/json');
include 'db_connect.php'; 

$doctor = isset($_GET['doctor']) ? $_GET['doctor'] : '';

$booked_slots = [];

if ($doctor == 'doctor1' || $doctor == 'doctor2') {
    
    // بنعد كل نوع لوحده
    $stmt = $conn->prepare("
        SELECT 
            appointment_time,
            -- عد الحجوزات العادية (اللي معندهاش شركة تأمين)
            COUNT(CASE WHEN insurance_company IS NULL THEN 1 END) as regular_count,
            -- عد حجوزات التأمين (اللي عندها شركة تأمين)
            COUNT(CASE WHEN insurance_company IS NOT NULL THEN 1 END) as insurance_count
        FROM appointments 
        WHERE doctor_name = ? 
        GROUP BY appointment_time
    ");
    $stmt->bind_param("s", $doctor);

} else {
    // احتياطي
    $stmt = $conn->prepare("
        SELECT 
            appointment_time,
            COUNT(CASE WHEN insurance_company IS NULL THEN 1 END) as regular_count,
            COUNT(CASE WHEN insurance_company IS NOT NULL THEN 1 END) as insurance_count
        FROM appointments 
        GROUP BY appointment_time
    ");
}

$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $full_datetime_string = date('Y-m-d H:i', strtotime($row['appointment_time']));
    
    // بنرجع أوبجكت لكل ميعاد فيه العدادين
    $booked_slots[$full_datetime_string] = [
        'regular' => (int)$row['regular_count'],
        'insurance' => (int)$row['insurance_count']
    ];
}

$stmt->close();
$conn->close();

// هيرجع حاجة زي: { "2025-11-05 14:00": {"regular": 1, "insurance": 0} }
echo json_encode($booked_slots);
?>