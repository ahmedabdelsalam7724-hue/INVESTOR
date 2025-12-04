<?php
// my_pitches.php - عرض وإدارة عروض التمويل الخاصة بالمستخدم الحالي

require_once 'session_manager.php';
require_once 'db_config.php';

// 1. التأكد من تسجيل الدخول والتحقق من الدور (يفضل أن يكون رائد أعمال)
require_login(); 
// check_role('entrepreneur'); // (اختياري) يمكن تعطيله للسماح للمستثمر برؤية هذا الملف فارغًا

$user_id = $_SESSION["user_id"];
$my_pitches = [];
$error_message = "";

// 2. معالجة طلب الحذف (إذا تم إرسال pitch_id للحذف)
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $pitch_id_to_delete = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    // التأكد من أن المستخدم الحالي هو مالك العرض قبل الحذف
    $sql_delete = "DELETE FROM pitches WHERE pitch_id = ? AND user_id = ?";
    if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
        mysqli_stmt_bind_param($stmt_delete, "ii", $pitch_id_to_delete, $user_id);
        if (mysqli_stmt_execute($stmt_delete)) {
            // توجيه المستخدم برسالة نجاح (نستخدم التوجيه لتنظيف الـ URL)
            header("location: my_pitches.php?status=deleted");
            exit();
        } else {
            $error_message = "حدث خطأ أثناء محاولة حذف العرض.";
        }
        mysqli_stmt_close($stmt_delete);
    }
}


// 3. جلب جميع العروض التي نشرها المستخدم الحالي
$sql = "SELECT 
            pitch_id, 
            title, 
            description, 
            category, 
            required_amount, 
            equity_offered, 
            status,
            created_at
        FROM 
            pitches
        WHERE 
            user_id = ?
        ORDER BY 
            created_at DESC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // قص الوصف ليناسب العرض المختصر
            $row['short_description'] = mb_substr(strip_tags($row['description']), 0, 100, 'UTF-8') . (mb_strlen($row['description'], 'UTF-8') > 100 ? '...' : '');
            $my_pitches[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
} else {
    $error_message = "خطأ في قاعدة البيانات أثناء جلب العروض.";
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | عروضي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .pitch-card {
            border-left: 5px solid var(--color-primary);
        }
        .status-open { border-color: #198754 !important; } /* Success/Green */
        .status-funded { border-color: #0d6efd !important; } /* Primary/Blue */
        .status-closed { border-color: #6c757d !important; } /* Secondary/Gray */
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        <h1 class="text-center mb-4" style="color: var(--color-primary);">📄 إدارة عروض التمويل الخاصة بي</h1>
        <p class="text-center lead text-muted mb-5">
            عرض وتتبع حالة العروض التي قمت بنشرها.
        </p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
            <div class="alert alert-success text-center">✅ تم حذف العرض بنجاح.</div>
        <?php endif; ?>
        <?php if (isset($_GET['status']) && $_GET['status'] == 'updated'): ?>
            <div class="alert alert-success text-center">✅ تم تحديث العرض بنجاح.</div>
        <?php endif; ?>

        <?php if (!empty($my_pitches)): ?>
            <div class="row row-cols-1 g-4">
                <?php foreach ($my_pitches as $pitch): 
                    // تحديد لون البطاقة حسب الحالة
                    $status_class = 'status-' . strtolower($pitch['status']);
                    $status_text = match(strtolower($pitch['status'])) {
                        'open' => 'مفتوح للتمويل',
                        'funded' => 'تم تمويله',
                        'closed' => 'مغلق',
                        default => 'غير محدد',
                    };
                ?>
                <div class="col">
                    <div class="card p-4 pitch-card <?php echo $status_class; ?>">
                        <div class="card-body">
                            <h4 class="card-title mb-2" style="color: var(--color-primary);"><?php echo htmlspecialchars($pitch['title']); ?></h4>
                            <p class="card-subtitle mb-3 text-muted small">
                                الحالة: <span class="badge bg-<?php echo match(strtolower($pitch['status'])){'open'=>'success', 'funded'=>'primary', 'closed'=>'secondary', default=>'secondary'}; ?>"><?php echo $status_text; ?></span> | 
                                تاريخ النشر: <?php echo date('Y-m-d', strtotime($pitch['created_at'])); ?>
                            </p>
                            
                            <p class="card-text text-white">
                                <?php echo htmlspecialchars($pitch['short_description']); ?>
                            </p>

                            <div class="mt-3">
                                <span class="badge bg-info me-3">$<?php echo number_format($pitch['required_amount']); ?> مطلوب</span>
                                <span class="badge bg-danger"><?php echo number_format($pitch['equity_offered'], 1); ?>% حصة معروضة</span>
                            </div>
                            
                            <div class="mt-4">
                                <a href="pitch_details.php?id=<?php echo $pitch['pitch_id']; ?>" class="btn btn-outline-primary btn-sm me-2">عرض التفاصيل</a>
                                <a href="edit_pitch.php?id=<?php echo $pitch['pitch_id']; ?>" class="btn btn-outline-warning btn-sm me-2">تعديل العرض</a>
                                
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $pitch['pitch_id']; ?>)">حذف</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                لم تقم بنشر أي عروض تمويل بعد. 
                <a href="submit_pitch.php" class="alert-link">انقر هنا لنشر عرضك الأول.</a>
            </div>
        <?php endif; ?>

    </div>
    
    <script>
        function confirmDelete(pitchId) {
            if (confirm("هل أنت متأكد من أنك تريد حذف هذا العرض؟ هذا الإجراء لا يمكن التراجع عنه.")) {
                window.location.href = "my_pitches.php?action=delete&id=" + pitchId;
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
