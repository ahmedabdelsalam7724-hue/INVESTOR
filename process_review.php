<?php
// process_review.php - معالجة إرسال التقييمات والتعليقات

require_once 'session_manager.php';
require_once 'db_config.php';
require_once 'notification_helper.php'; // لإنشاء الإشعار التلقائي

require_login(); 

$user_id = $_SESSION["user_id"];
$full_name = $_SESSION["full_name"];
$user_role = $_SESSION["user_role"];
$pitch_id = null;

// التحقق من أن الطلب هو POST
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // 1. جمع البيانات وتنقيتها
    $pitch_id = filter_var($_POST["pitch_id"] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $rating = filter_var($_POST["rating"] ?? 0, FILTER_SANITIZE_NUMBER_INT); // 1-5
    $comment = trim($_POST["comment"] ?? '');
    
    // 2. التحقق من صحة الإدخالات الأساسية
    if($pitch_id <= 0 || $rating < 1 || $rating > 5 || empty($comment)){
        // توجيه مع رسالة خطأ
        header("location: pitch_details.php?id={$pitch_id}&error=invalid_review_data");
        exit;
    }

    // 3. التحقق من أن المستخدم لم يقم بالتقييم لهذا العرض مسبقاً (لمنع التقييمات المتعددة)
    $sql_check = "SELECT review_id FROM reviews WHERE user_id = ? AND pitch_id = ?";
    if($stmt_check = mysqli_prepare($link, $sql_check)){
        mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $pitch_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if(mysqli_stmt_num_rows($stmt_check) > 0){
            // المستخدم قام بالتقييم بالفعل
            header("location: pitch_details.php?id={$pitch_id}&error=already_reviewed");
            exit;
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // 4. إدراج التقييم الجديد
    $sql_insert = "INSERT INTO reviews (pitch_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
    
    if($stmt_insert = mysqli_prepare($link, $sql_insert)){
        
        // الربط (iiis: integer, integer, integer, string)
        mysqli_stmt_bind_param($stmt_insert, "iiis", $param_pitch_id, $param_user_id, $param_rating, $param_comment);
        
        $param_pitch_id = $pitch_id;
        $param_user_id = $user_id;
        $param_rating = $rating;
        $param_comment = $comment;
        
        if(mysqli_stmt_execute($stmt_insert)){
            
            // ===========================================
            // 5. إنشاء إشعار لمالك العرض
            // ===========================================
            
            // أ. جلب ID ناشر العرض وعنوانه
            $sql_publisher = "SELECT user_id, title FROM pitches WHERE pitch_id = ?";
            if ($stmt_pub = mysqli_prepare($link, $sql_publisher)) {
                mysqli_stmt_bind_param($stmt_pub, "i", $pitch_id);
                mysqli_stmt_execute($stmt_pub);
                mysqli_stmt_bind_result($stmt_pub, $pitch_publisher_id, $pitch_title);
                
                if (mysqli_stmt_fetch($stmt_pub)) {
                    
                    // ب. إنشاء محتوى الإشعار
                    $notification_content = "تم تقييم عرضك '{$pitch_title}' بتقييم {$rating}/5 من قبل {$full_name} ({$user_role}).";
                    $target_url = "pitch_details.php?id={$pitch_id}";

                    // ج. استدعاء الدالة المساعدة
                    create_notification($pitch_publisher_id, 'new_review', $notification_content, $target_url, $link);
                }
                mysqli_stmt_close($stmt_pub);
            }
            
            // 6. التوجيه إلى صفحة التفاصيل برسالة نجاح
            header("location: pitch_details.php?id={$pitch_id}&status=review_success");
            exit;
        } else{
            // فشل الإدراج
            header("location: pitch_details.php?id={$pitch_id}&error=db_insert_failed");
            exit;
        }

        mysqli_stmt_close($stmt_insert);
    }
} else {
    // إذا لم يكن الطلب POST، يتم التوجيه للصفحة الرئيسية
    header("location: index.php");
    exit;
}

mysqli_close($link);
?>
