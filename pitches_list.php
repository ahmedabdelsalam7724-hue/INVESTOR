<?php
// pitches_list.php - عرض قائمة عروض التمويل المتاحة

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$pitches = [];

// 1. استعلام SQL لجلب العروض المفتوحة وبيانات الناشر
$sql = "SELECT 
            p.pitch_id, 
            p.title, 
            p.description, 
            p.category, 
            p.required_amount, 
            p.equity_offered, 
            p.created_at,
            u.full_name AS entrepreneur_name
        FROM 
            pitches p
        JOIN 
            users u ON p.user_id = u.user_id
        WHERE 
            p.status = 'open'
        ORDER BY 
            p.created_at DESC";

// 2. تنفيذ الاستعلام وجلب النتائج
if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        // قص الوصف ليناسب العرض المختصر
        $row['short_description'] = mb_substr(strip_tags($row['description']), 0, 150, 'UTF-8') . (mb_strlen($row['description'], 'UTF-8') > 150 ? '...' : '');
        $pitches[] = $row;
    }
    mysqli_free_result($result);
} else {
    // التعامل مع خطأ في الاستعلام
    $error_message = "خطأ في قاعدة البيانات: " . mysqli_error($link);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | جميع عروض التمويل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .pitch-card {
            border-left: 5px solid var(--color-success);
            transition: box-shadow 0.3s;
        }
        .pitch-card:hover {
            box-shadow: 0 0 15px rgba(40, 167, 69, 0.4); 
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        <h1 class="text-center mb-4" style="color: var(--color-success);">💼 عروض التمويل المتاحة</h1>
        <p class="text-center lead text-muted mb-5">
            تصفح أحدث الفرص الاستثمارية التي نشرها رواد الأعمال.
        </p>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($pitches)): ?>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($pitches as $pitch): ?>
                <div class="col">
                    <div class="card h-100 p-4 pitch-card">
                        <div class="card-body d-flex flex-column">
                            <h4 class="card-title mb-2" style="color: var(--color-success);"><?php echo htmlspecialchars($pitch['title']); ?></h4>
                            <p class="card-subtitle mb-2 text-muted small">
                                بواسطة: **<?php echo htmlspecialchars($pitch['entrepreneur_name']); ?>** | 
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($pitch['category']); ?></span>
                            </p>
                            
                            <p class="card-text text-white mt-3 flex-grow-1">
                                <?php echo htmlspecialchars($pitch['short_description']); ?>
                            </p>

                            <div class="row mt-3 mb-3">
                                <div class="col-6">
                                    <h6 class="text-primary mb-0">المبلغ المطلوب:</h6>
                                    <p class="fw-bold fs-5 text-white">
                                        $<?php echo number_format($pitch['required_amount']); ?>
                                    </p>
                                </div>
                                <div class="col-6 text-end">
                                    <h6 class="text-primary mb-0">الملكية المعروضة:</h6>
                                    <p class="fw-bold fs-5 text-white">
                                        <?php echo number_format($pitch['equity_offered'], 1); ?>%
                                    </p>
                                </div>
                            </div>
                            
                            <a href="pitch_details.php?id=<?php echo $pitch['pitch_id']; ?>" class="btn btn-outline-success mt-auto">عرض التفاصيل والتقييمات</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                لا توجد عروض تمويل متاحة حالياً.
            </div>
        <?php endif; ?>

    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
