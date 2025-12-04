<?php
// process_problem_comment.php - معالجة إرسال تعليق/حل لمشكلة في المنتدى

require_once 'session_manager.php';
require_once 'db_config.php';
require_once 'notification_helper.php'; // لإنشاء الإشعار التلقائي

require_login(); 

$user_id = $_SESSION["user_id"];
$full_name = $_SESSION["full_name"];
$problem_id = null;

// التحقق من أن الطلب هو POST
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // 1. جمع البيانات وتنقيتها
    $problem_id = filter_var($_POST["problem_id"] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $comment_body = trim($_POST["comment_body"] ?? '');
    
    // 2. التحقق من صحة الإدخالات الأساسية
    if($problem_id <= 0 || empty($comment_body)){
        // توجيه مع رسالة خطأ
        header("location: problem_forum.php?error=invalid_comment_data");
        exit;
    }
    
    // 3. إدراج التعليق الجديد
    $sql_insert = "INSERT INTO problem_comments (problem_id, user_id, comment_body) VALUES (?, ?, ?)";
    
    if($stmt_insert = mysqli_prepare($link, $sql_insert)){
        
        // الربط (iis: integer, integer, string)
        mysqli_stmt_bind_param($stmt_insert, "iis", $param_problem_id, $param_user_id, $param_comment_body);
        
        $param_problem_id = $problem_id;
        $param_user_id = $user_id;
        $param_comment_body = $comment_body;
        
        if(mysqli_stmt_execute($stmt_insert)){
            
            // ===========================================
            // 4. إنشاء إشعار لمالك المشكلة (ناشرها)
            // ===========================================
            
            // أ. جلب ID ناشر المشكلة وعنوانها
            $sql_poster = "SELECT user_id, title FROM problems WHERE problem_id = ?";
            if ($stmt_poster = mysqli_prepare($link, $sql_poster)) {
                mysqli_stmt_bind_param($stmt_poster, "i", $problem_id);
                mysqli_stmt_execute($stmt_poster);
                mysqli_stmt_bind_result($stmt_poster, $problem_poster_id, $problem_title);
                
                if (mysqli_stmt_fetch($stmt_poster)) {
                    // تأكد من أن المعلق ليس هو نفسه ناشر المشكلة لتجنب الإشعارات الذاتية
                    if ($problem_poster_id != $user_id) {
                        
                        // ب. إنشاء محتوى الإشعار
                        $notification_content = "تم إضافة تعليق جديد على مشكلتك '{$problem_title}' من قبل {$full_name}.";
                        $target_url = "problem_details.php?id={$problem_id}";

                        // ج. استدعاء الدالة المساعدة
                        create_notification($problem_poster_id, 'new_comment', $notification_content, $target_url, $link);
                    }
                }
                mysqli_stmt_close($stmt_poster);
            }
            
            // 5. التوجيه إلى صفحة تفاصيل المشكلة برسالة نجاح
            header("location: problem_details.php?id={$problem_id}&status=comment_added");
            exit;
        } else{
            // فشل الإدراج
            header("location: problem_details.php?id={$problem_id}&error=db_insert_failed");
            exit;
        }

        mysqli_stmt_close($stmt_insert);
    }
} else {
    // إذا لم يكن الطلب POST، يتم التوجيه لصفحة المنتدى
    header("location: problem_forum.php");
    exit;
}

mysqli_close($link);
?>
