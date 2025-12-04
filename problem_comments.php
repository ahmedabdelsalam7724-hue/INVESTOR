<?php
// problem_comments.php - عرض تفاصيل مشكلة معينة والتعليقات/الحلول المقترحة

require_once 'session_manager.php';
require_once 'db_config.php';
require_once 'notification_helper.php'; // لإنشاء إشعار للرد

require_login(); 

$user_id = $_SESSION["user_id"];
$problem_id = null;
$problem = null;
$comments = [];
$error_message = $success_message = "";

// 1. التحقق من وجود ID المشكلة
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $problem_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
}

if (!$problem_id) {
    header("location: problem_forum.php?error=invalid_problem_id");
    exit;
}

// 2. جلب تفاصيل المشكلة الأساسية وبيانات الناشر
$sql_problem = "SELECT 
                    p.*, 
                    u.full_name AS poster_name,
                    u.user_id AS poster_id
                FROM 
                    problems p
                JOIN 
                    users u ON p.user_id = u.user_id
                WHERE 
                    p.problem_id = ?";

if ($stmt_problem = mysqli_prepare($link, $sql_problem)) {
    mysqli_stmt_bind_param($stmt_problem, "i", $problem_id);
    mysqli_stmt_execute($stmt_problem);
    $result_problem = mysqli_stmt_get_result($stmt_problem);
    
    if (mysqli_num_rows($result_problem) == 1) {
        $problem = mysqli_fetch_assoc($result_problem);
    }
    mysqli_stmt_close($stmt_problem);
}

if (!$problem) {
    header("location: problem_forum.php?error=problem_not_found");
    exit;
}

// 3. معالجة إرسال التعليق (POST)
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    $comment_body = trim($_POST["comment_body"] ?? '');
    
    if (empty($comment_body)) {
        $error_message = "الرجاء كتابة محتوى التعليق.";
    } else {
        // إدراج التعليق الجديد
        $sql_insert = "INSERT INTO problem_comments (problem_id, user_id, comment_body) VALUES (?, ?, ?)";
        
        if($stmt_insert = mysqli_prepare($link, $sql_insert)){
            mysqli_stmt_bind_param($stmt_insert, "iis", $problem_id, $user_id, $comment_body);
            
            if(mysqli_stmt_execute($stmt_insert)){
                
                $success_message = "✅ تم إضافة تعليقك بنجاح.";
                
                // إنشاء إشعار لناشر المشكلة إذا لم يكن المستخدم الحالي
                if ($problem['poster_id'] != $user_id) {
                    $notification_content = "تم إضافة تعليق جديد على مشكلتك '{$problem['title']}' من قبل {$_SESSION['full_name']}.";
                    $target_url = "problem_comments.php?id={$problem_id}"; 
                    create_notification($problem['poster_id'], 'new_comment', $notification_content, $target_url, $link);
                }

                // إعادة توجيه (PRG)
                header("location: problem_comments.php?id={$problem_id}&status=comment_added");
                exit();
            } else {
                $error_message = "حدث خطأ في قاعدة البيانات أثناء إضافة التعليق.";
            }
            mysqli_stmt_close($stmt_insert);
        }
    }
}


// 4. جلب جميع التعليقات على المشكلة
$sql_comments = "SELECT 
                    pc.*, 
                    u.full_name AS commenter_name,
                    u.user_role AS commenter_role
                FROM 
                    problem_comments pc
                JOIN 
                    users u ON pc.user_id = u.user_id
                WHERE 
                    pc.problem_id = ?
                ORDER BY 
                    pc.created_at ASC"; 

if ($stmt_comm = mysqli_prepare($link, $sql_comments)) {
    mysqli_stmt_bind_param($stmt_comm, "i", $problem_id);
    mysqli_stmt_execute($stmt_comm);
    $result_comm = mysqli_stmt_get_result($stmt_comm);
    
    if ($result_comm) {
        while ($row = mysqli_fetch_assoc($result_comm)) {
            $comments[] = $row;
        }
    }
    mysqli_stmt_close($stmt_comm);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | مناقشة المشكلة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .problem-detail-card {
            border-left: 5px solid var(--color-warning);
        }
        .comment-card {
            background-color: var(--bg-card);
            border-left: 3px solid var(--color-info);
        }
        .problem-box {
            background-color: var(--bg-card-darker);
            padding: 20px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        
        <div class="card p-5 problem-detail-card mb-5">
            <p class="text-muted mb-1">
                <a href="problem_forum.php" class="text-decoration-none text-info">
                    ⬅️ العودة للمنتدى
                </a>
            </p>
            <h1 class="mb-3" style="color: var(--color-warning);">
                <?php echo htmlspecialchars($problem['title']); ?>
            </h1>
            <p class="lead text-muted">
                بواسطة: 
                <span class="fw-bold text-primary">
                    <?php echo htmlspecialchars($problem['poster_name']); ?>
                    (<?php echo $problem['poster_id'] == $user_id ? 'أنت' : ''; ?>)
                </span>
                | <span class="badge bg-secondary"><?php echo htmlspecialchars($problem['industry']); ?></span>
            </p>
            
            <hr class="my-4 text-muted">

            <h3 class="mt-4" style="color: var(--color-primary);">📚 وصف المشكلة:</h3>
            <div class="problem-box text-white">
                <?php echo nl2br(htmlspecialchars($problem['description'])); ?>
            </div>
        </div>

        <h2 class="mt-5 mb-4" style="color: var(--color-info);">
            💡 التعليقات والحلول المقترحة (<?php echo count($comments); ?>)
        </h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['status']) && $_GET['status'] == 'comment_added'): ?>
            <div class="alert alert-success text-center">✅ تم إضافة تعليقك بنجاح.</div>
        <?php endif; ?>

        <div class="card p-4 mb-5 bg-dark border-secondary">
            <h4 class="mb-3 text-white">شارك رأيك أو اقترح حلاً</h4>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $problem_id; ?>" method="POST">
                <input type="hidden" name="problem_id" value="<?php echo $problem_id; ?>">
                
                <div class="mb-3">
                    <textarea class="form-control" id="comment_body" name="comment_body" rows="4" placeholder="اكتب تعليقك أو حل مقترح هنا..." required></textarea>
                </div>

                <button type="submit" class="btn btn-info mt-2">إضافة التعليق</button>
            </form>
        </div>

        <div class="row">
            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $comment): ?>
                <div class="col-12 mb-3">
                    <div class="card p-3 comment-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-white">
                                👤 <?php echo htmlspecialchars($comment['commenter_name']); ?>
                                <span class="badge bg-<?php echo $comment['commenter_role'] === 'investor' ? 'primary' : 'success'; ?> small ms-2">
                                    <?php echo $comment['commenter_role'] === 'investor' ? 'مستثمر' : 'رائد أعمال'; ?>
                                </span>
                            </h6>
                            <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></small>
                        </div>
                        <hr class="my-2 text-secondary">
                        <p class="text-light"><?php echo nl2br(htmlspecialchars($comment['comment_body'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    لا توجد تعليقات حتى الآن. كن أول من يساهم في الحل!
                </div>
            <?php endif; ?>
        </div>

    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
