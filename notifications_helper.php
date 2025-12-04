<?php
/**
 * notification_helper.php
 * * يحتوي على الدوال المساعدة لإدارة الإشعارات وإضافتها إلى قاعدة البيانات.
 */

// تضمين ملف إعدادات قاعدة البيانات إذا لم يتم تضمينه بالفعل
// (في بعض الأحيان يتم تضمينه في الملف الرئيسي، ولكن إدراجه هنا يضمن عمل الدالة بشكل مستقل)
if (!isset($link)) {
    require_once 'db_config.php';
    // ملاحظة: إذا كنت تستخدم الاتصال كمعامل (Param) للدالة، فلن تحتاج لـ require_once هنا.
    // في هذا المثال، سنفترض أن الاتصال (الـ $link) يتم تمريره كمعامل.
}

/**
 * دالة لإضافة إشعار جديد إلى جدول notifications.
 *
 * @param int    $recipient_id معرف المستخدم المستلم للإشعار.
 * @param string $type         نوع الإشعار (مثل: new_message, pitch_approved, new_comment).
 * @param string $content      محتوى نص الإشعار.
 * @param string $target_url   رابط الصفحة التي يجب التوجيه إليها عند النقر على الإشعار.
 * @param mysqli $link         كائن اتصال قاعدة البيانات.
 * @return bool                صحيح عند النجاح، خطأ عند الفشل.
 */
function create_notification(int $recipient_id, string $type, string $content, string $target_url, $link): bool {
    
    // التأكد من أن المستلم ليس صفراً (للتأكد من وجود مستخدم مستهدف)
    if ($recipient_id <= 0) {
        // يمكنك تسجيل خطأ هنا إذا أردت
        return false;
    }
    
    $status = 'unread';
    
    $sql = "INSERT INTO notifications (user_id, type, content, target_url, status) VALUES (?, ?, ?, ?, ?)";
    
    if($stmt = mysqli_prepare($link, $sql)){
        
        // الربط (issss): integer, string, string, string, string
        mysqli_stmt_bind_param($stmt, "issss", 
            $param_user_id, $param_type, $param_content, $param_target_url, $param_status);
        
        // تعيين المعاملات
        $param_user_id = $recipient_id;
        $param_type = $type;
        $param_content = $content;
        $param_target_url = $target_url;
        $param_status = $status;
        
        if(mysqli_stmt_execute($stmt)){
            mysqli_stmt_close($stmt);
            return true;
        } else {
            // يمكنك تسجيل خطأ في قاعدة البيانات هنا
            // error_log("Database error creating notification: " . mysqli_error($link));
            mysqli_stmt_close($stmt);
            return false;
        }
    }
    
    return false;
}

// لا يوجد وسم إغلاق لملفات PHP النقية لضمان عدم إرسال مسافات أو رؤوس غير مرغوبة
