<?php
// pitch_details.php - عرض تفاصيل عرض تمويل محدد

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

// 1. التحقق من وجود ID العرض في الـ URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: pitches_list.php?error=invalid_pitch_id");
    exit;
}

$pitch_id = $_GET['id'];
$pitch = null;
$reviews = [];
$average_rating = 0;
$rating_count = 0;
$user_id = $_SESSION['user_id'];

// 2. جلب تفاصيل العرض الأساسية وبيانات الناشر
$sql_pitch = "SELECT 
                p.*, 
                u.full_name AS entrepreneur_name, 
                u.user_id AS entrepreneur_id
              FROM 
                pitches p
              JOIN 
                users u ON p.user_id = u.user_id
              WHERE 
                p.pitch_id = ?";

if ($stmt_pitch = mysqli_prepare($link, $sql_pitch)) {
    mysqli_stmt_bind_param($stmt_pitch, "i", $pitch_id);
    mysqli_stmt_execute($stmt_pitch);
    $result_pitch = mysqli_stmt_get_result($stmt_pitch);
    
    if (mysqli_num_rows($result_pitch) == 1) {
        $pitch = mysqli_fetch_assoc($result_pitch);
    }
    mysqli_stmt_close($stmt_pitch);
}

// إذا لم يتم العثور على العرض، يتم التوجيه
if (!$pitch) {
    header("location: pitches_list.php?error=pitch_not_found");
    exit;
}

// 3. جلب التقييمات والتعليقات لهذا العرض
$sql_reviews = "SELECT 
                    r.*, 
                    u.full_name AS reviewer_name,
                    u.user_role AS reviewer_role
                FROM 
                    reviews r
                JOIN 
                    users u ON r.user_id = u.user_id
                WHERE 
                    r.pitch_id = ?
                ORDER BY 
                    r.created_at DESC";

if ($stmt_reviews = mysqli_prepare($link, $sql_reviews)) {
    mysqli_stmt_bind_param($stmt_reviews, "i", $pitch_id);
    mysqli_stmt_execute($stmt_reviews);
    $result_reviews = mysqli_stmt_get_result($stmt_reviews);
    
    if ($result_reviews) {
        while ($row = mysqli_fetch_assoc($result_reviews)) {
            $reviews[] = $row;
        }
    }
    mysqli_stmt_close($stmt_reviews);
}

// 4. حساب متوسط التقييم
if (!empty($reviews)) {
    $rating_sum = 0;
    foreach ($reviews as $review) {
        $rating_sum += $review['rating'];
    }
    $rating_count = count($reviews);
    $average_rating = round($rating_sum / $rating_count, 1);
}


// 5. وظيفة مساعدة لعرض النجوم
function display_stars($rating) {
    $output = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $output .= '<span style="color: gold;">★</span>';
        } else {
            $output .= '<span style="color: gray;">★</span>';
        }
    }
    return $output;
}

// mysqli_close($link); // (نغلق الاتصال في نهاية الملف)
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | <?php echo htmlspecialchars($pitch['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .pitch-details-card {
            border-left: 5px solid var(--color-success);
        }
        .review-card {
            border-left: 3px solid var(--color-warning);
        }
        .rating-box {
            background-color: var(--color-primary);
            color: white;
            padding: 15px;
            border-radius: 5px;
            font-size: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        
        <div class="card p-5 pitch-details-card mb-5">
            <h1 class="mb-3" style="color: var(--color-success);"><?php echo htmlspecialchars($pitch['title']); ?></h1>
            <p class="lead text-muted">
                بواسطة: <span class="fw-bold text-info"><?php echo htmlspecialchars($pitch['entrepreneur_name']); ?></span>
                <span class="badge bg-secondary ms-3"><?php echo htmlspecialchars($pitch['category']); ?></span>
            </p>
            
            <hr class="my-4 text-muted">

            <div class="row mb-4">
                <div class="col-md-4 text-center">
                    <div class="rating-box">
                        <?php echo display_stars($average_rating); ?>
                        <p class="mb-0 fs-5 mt-1">
                            <?php echo number_format($average_rating, 1); ?> / 5 (<?php echo $rating_count; ?> تقييم)
                        </p>
                    </div>
                </div>
                <div class="col-md-8">
                    <h3 class="text-primary mb-2">💰 التمويل والملكية</h3>
                    <ul class="list-group list-group-flush bg-transparent">
                        <li class="list-group-item bg-transparent text-white">
                            **المبلغ المطلوب:** <span class="fw-bold text-success">$<?php echo number_format($pitch['required_amount']); ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-white">
                            **الملكية المعروضة:** <span class="fw-bold text-danger"><?php echo number_format($pitch['equity_offered'], 1); ?>%</span>
                        </li>
                        <li class="list-group-item bg-transparent text-white">
                            **حالة العرض:** <span class="badge bg-<?php echo strtolower($pitch['status']) === 'open' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($pitch['status']); ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <h3 class="mt-4" style="color: var(--color-primary);">📋 الوصف التفصيلي:</h3>
            <p class="text-white fs-6"><?php echo nl2br(htmlspecialchars($pitch['description'])); ?></p>
            
            <?php if ($_SESSION['user_role'] === 'investor' && $pitch['entrepreneur_id'] !== $user_id): ?>
                <a href="send_message.php?receiver_id=<?php echo $pitch['entrepreneur_id']; ?>" class="btn btn-lg btn-info mt-4">
                    💬 تواصل مع رائد الأعمال
                </a>
            <?php endif; ?>
        </div>

        <h2 class="mt-5 mb-4" style="color: var(--color-warning);">⭐ التقييمات والتحليلات (<?php echo $rating_count; ?>)</h2>

        <?php 
        // تحقق: إذا كان المستخدم هو الناشر، أو دوره ليس مستثمر، لا يمكنه التقييم
        if ($pitch['entrepreneur_id'] !== $user_id): 
            $can_review = true;
            // يمكن هنا إضافة تحقق آخر: هل قام المستخدم بالتقييم بالفعل؟
            
            if ($can_review):
        ?>
        <div class="card p-4 mb-5 bg-dark border-secondary">
            <h4 class="mb-3 text-white">أضف تقييمك وتعليقك</h4>
            <form action="process_review.php" method="POST">
                <input type="hidden" name="pitch_id" value="<?php echo $pitch_id; ?>">
                
                <div class="mb-3">
                    <label class="form-label text-muted">التقييم (من 1 إلى 5):</label>
                    <select class="form-select" name="rating" required>
                        <option value="5">5 نجوم - ممتاز</option>
                        <option value="4">4 نجوم - جيد جداً</option>
                        <option value="3">3 نجوم - متوسط</option>
                        <option value="2">2 نجوم - ضعيف</option>
                        <option value="1">1 نجمة - سيئ</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="comment" class="form-label text-muted">التعليق/التحليل:</label>
                    <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                </div>

                <button type="submit" class="btn btn-warning mt-2">إرسال التقييم</button>
            </form>
        </div>
        <?php 
            endif;
        endif; 
        ?>

        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
            <div class="card p-3 mb-3 review-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">
                        <?php echo htmlspecialchars($review['reviewer_name']); ?>
                        <span class="badge bg-<?php echo $review['reviewer_role'] === 'investor' ? 'primary' : 'success'; ?> small ms-2">
                            <?php echo $review['reviewer_role'] === 'investor' ? 'مستثمر' : 'رائد أعمال'; ?>
                        </span>
                    </h5>
                    <small class="text-muted"><?php echo date('Y-m-d', strtotime($review['created_at'])); ?></small>
                </div>
                <div class="mt-2 mb-2">
                    <?php echo display_stars($review['rating']); ?>
                    <span class="fw-bold ms-2" style="color: gold;"><?php echo number_format($review['rating'], 1); ?></span>
                </div>
                <p class="text-light"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                كن أول من يقيّم هذا العرض!
            </div>
        <?php endif; ?>

    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
<?php mysqli_close($link); ?>
