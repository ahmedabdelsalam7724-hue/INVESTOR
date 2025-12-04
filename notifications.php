<?php
// notifications.php - عرض قائمة إشعارات المستخدم

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$user_id = $_SESSION["user_id"];
$notifications = [];
$error_message = "";

// 1. معالجة تحديث حالة الإشعار عند النقر
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_to_mark = filter_var($_GET['mark_read'], FILTER_SANITIZE_NUMBER_INT);
    
    // تأكد من أن المستخدم الحالي هو مالك الإشعار قبل التحديث
    $sql_update = "UPDATE notifications SET status = 'read' WHERE notification_id = ? AND user_id = ?";
    if ($stmt_update = mysqli_prepare($link, $sql_update)) {
        mysqli_stmt_bind_param($stmt_update, "ii", $notification_to_mark, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        // جلب عنوان URL المستهدف للقيام بعملية التوجيه
        $sql_get_url = "SELECT target_url FROM notifications WHERE notification_id = ?";
        if ($stmt_get_url = mysqli_prepare($link, $sql_get_url)) {
            mysqli_stmt_bind_param($stmt_get_url, "i", $notification_to_mark);
            mysqli_stmt_execute($stmt_get_url);
            mysqli_stmt_bind_result($stmt_get_url, $target_url);
            if (mysqli_stmt_fetch($stmt_get_url)) {
                // التوجيه إلى الصفحة المستهدفة بعد التحديث
                header("location: " . $target_url);
                exit;
            }
            mysqli_stmt_close($stmt_get_url);
        }
        // إذا لم يكن هناك target_url، التوجيه لصفحة الإشعارات
        header("location: notifications.php?status=marked_read");
        exit;
    }
}


// 2. جلب جميع الإشعارات للمستخدم (غير المقروءة أولاً)
$sql = "SELECT 
            notification_id, 
            type, 
            content, 
            target_url, 
            status, 
            created_at
        FROM 
            notifications
        WHERE 
            user_id = ?
        ORDER BY 
            status DESC, created_at DESC"; -- غير المقروءة (unread) تأتي قبل المقروءة (read)

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
    } else {
        $error_message = "خطأ في قاعدة البيانات: " . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
}

// 3. (اختياري) معالجة تحديث جميع الإشعارات إلى مقروءة
if (isset($_GET['mark_all']) && $_GET['mark_all'] == 'true') {
    $sql_mark_all = "UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
    if ($stmt_all = mysqli_prepare($link, $sql_mark_all)) {
        mysqli_stmt_bind_param($stmt_all, "i", $user_id);
        mysqli_stmt_execute($stmt_all);
        mysqli_stmt_close($stmt_all);
        header("location: notifications.php?status=all_marked_read");
        exit;
    }
}

mysqli_close($link);

// دالة مساعدة لتحديد الأيقونة بناءً على النوع
function get_notification_icon($type) {
    switch ($type) {
        case 'new_message': return '📧';
        case 'new_pitch_review': return '⭐';
        case 'pitch_approved': return '✅';
        case 'pitch_declined': return '❌';
        case 'new_comment': return '💬';
        case 'new_reply': return '💬';
        default: return '🔔';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | مركز الإشعارات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .notification-card {
            border-left: 5px solid var(--color-secondary);
            transition: background-color 0.3s;
        }
        .unread-notification {
            background-color: var(--bg-card-darker);
            border-left: 5px solid var(--color-warning); /* تمييز غير المقروء بلون مختلف */
        }
        .notification-card:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="color: var(--color-warning);">🔔 مركز الإشعارات</h1>
            <a href="notifications.php?mark_all=true" class="btn btn-outline-secondary btn-sm">
                تعليم الكل كمقروء
            </a>
        </div>
        
        <p class="lead text-muted mb-4">
            الإشعارات غير المقروءة تأتي أولاً ويتم تمييزها.
        </p>

        <?php 
        if (!empty($error_message)) {
            echo '<div class="alert alert-danger text-center">' . $error_message . '</div>';
        }
        if (isset($_GET['status']) && $_GET['status'] == 'all_marked_read') {
            echo '<div class="alert alert-success text-center">✅ تم تعليم جميع الإشعارات كمقروءة.</div>';
        }
        ?>

        <?php if (!empty($notifications)): ?>
            <div class="list-group">
                <?php foreach ($notifications as $notification): 
                    $is_unread = $notification['status'] === 'unread';
                    $card_class = $is_unread ? 'unread-notification' : '';
                    
                    // الرابط سيوجه إلى target_url بعد تحديث الحالة
                    $link_url = 'notifications.php?mark_read=' . $notification['notification_id'];
                    $target_link = $notification['target_url'] ? $link_url : '#';
                ?>
                
                <a href="<?php echo htmlspecialchars($target_link); ?>" 
                   class="list-group-item list-group-item-action p-3 mb-2 rounded notification-card <?php echo $card_class; ?> text-white">
                    
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <h5 class="mb-1 fw-bold text-<?php echo $is_unread ? 'warning' : 'info'; ?>">
                            <?php echo get_notification_icon($notification['type']); ?> 
                            <?php echo htmlspecialchars($notification['content']); ?>
                        </h5>
                        <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?></small>
                    </div>
                    
                    <p class="mb-1 small text-<?php echo $is_unread ? 'light' : 'secondary'; ?>">
                        <?php echo $is_unread ? 'غير مقروء - انقر للتفاصيل' : 'مقروء'; ?>
                    </p>
                </a>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-5" role="alert">
                لا توجد إشعارات حالياً.
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
             <a href="dashboard.php" class="btn btn-outline-secondary">العودة إلى لوحة التحكم</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
