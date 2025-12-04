<?php
// problem_forum.php - عرض قائمة المشكلات المطروحة في المنتدى

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$problems = [];
$error_message = "";

// 1. استعلام SQL لجلب جميع المشكلات المفتوحة وبيانات الناشر
$sql = "SELECT 
            p.problem_id, 
            p.title, 
            p.description, 
            p.industry, 
            p.status, 
            p.created_at,
            u.full_name AS poster_name,
            u.user_role AS poster_role
        FROM 
            problems p
        JOIN 
            users u ON p.user_id = u.user_id
        WHERE 
            p.status = 'open' -- عرض المشكلات المفتوحة فقط
        ORDER BY 
            p.created_at DESC";

// 2. تنفيذ الاستعلام وجلب النتائج
if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        // قص الوصف ليناسب العرض المختصر
        $row['short_description'] = mb_substr(strip_tags($row['description']), 0, 150, 'UTF-8') . (mb_strlen($row['description'], 'UTF-8') > 150 ? '...' : '');
        
        // جلب عدد التعليقات لكل مشكلة
        $comment_count_sql = "SELECT COUNT(*) AS comment_count FROM problem_comments WHERE problem_id = ?";
        if ($stmt_count = mysqli_prepare($link, $comment_count_sql)) {
            mysqli_stmt_bind_param($stmt_count, "i", $row['problem_id']);
            mysqli_stmt_execute($stmt_count);
            mysqli_stmt_bind_result($stmt_count, $comment_count);
            mysqli_stmt_fetch($stmt_count);
            $row['comment_count'] = $comment_count;
            mysqli_stmt_close($stmt_count);
        }
        
        $problems[] = $row;
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
    <title>INVESTOR | منتدى المشكلات والحلول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .problem-card {
            border-left: 5px solid var(--color-warning);
            transition: transform 0.3s;
        }
        .problem-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2); 
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="color: var(--color-warning);">🏛️ منتدى المشكلات والحلول</h1>
            <a href="submit_problem.php" class="btn btn-warning">
                ❓ اطرح مشكلة جديدة
            </a>
        </div>
        <p class="lead text-muted mb-5">
            شارك في مناقشة التحديات التي تواجه رواد الأعمال واقترح حلولاً مبتكرة.
        </p>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($problems)): ?>
            <div class="row row-cols-1 g-4">
                <?php foreach ($problems as $problem): ?>
                <div class="col">
                    <div class="card h-100 p-4 problem-card">
                        <div class="card-body">
                            <h4 class="card-title mb-2" style="color: var(--color-warning);">
                                <?php echo htmlspecialchars($problem['title']); ?>
                            </h4>
                            <p class="card-subtitle mb-3 text-muted small">
                                بواسطة: 
                                <span class="fw-bold text-primary">
                                    <?php echo htmlspecialchars($problem['poster_name']); ?>
                                </span>
                                (<span class="badge bg-<?php echo $problem['poster_role'] === 'investor' ? 'primary' : 'success'; ?>"><?php echo $problem['poster_role'] === 'investor' ? 'مستثمر' : 'رائد أعمال'; ?></span>)
                            </p>
                            
                            <p class="card-text text-white mt-3">
                                **التفاصيل:** <?php echo htmlspecialchars($problem['short_description']); ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    <span class="badge bg-secondary me-3">
                                        القطاع: <?php echo htmlspecialchars($problem['industry']); ?>
                                    </span>
                                    <span class="badge bg-info">
                                        💬 <?php echo $problem['comment_count']; ?> تعليق/حل
                                    </span>
                                </div>
                                
                                <a href="problem_comments.php?id=<?php echo $problem['problem_id']; ?>" class="btn btn-outline-warning">
                                    مناقشة الحلول
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-5" role="alert">
                لا توجد مشكلات مطروحة للنقاش حالياً. 
                <a href="submit_problem.php" class="alert-link">كن أول من يطرح تحدياً!</a>
            </div>
        <?php endif; ?>

    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
