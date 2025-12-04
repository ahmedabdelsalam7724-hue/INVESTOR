<?php
// send_message.php - نموذج ومعالجة إرسال رسالة جديدة إلى مستخدم محدد

require_once 'session_manager.php';
require_once 'db_config.php';
require_once 'notification_helper.php'; // لإنشاء الإشعار التلقائي

require_login(); 

$sender_id = $_SESSION["user_id"];
$receiver_id = null;
$receiver_name = "مستخدم غير معروف";
$message_err = $success_msg = "";

// 1. التحقق من وجود ID المستلم في الرابط (GET)
if (isset($_GET['receiver_id']) && is_numeric($_GET['receiver_id'])) {
    $receiver_id = filter_var($_GET['receiver_id'], FILTER_SANITIZE_NUMBER_INT);
}

// 2. جلب اسم المستلم والتأكد من أنه ليس المستخدم الحالي
if ($receiver_id) {
    if ($receiver_id == $sender_id) {
        $message_err = "لا يمكنك إرسال رسالة لنفسك.";
        $receiver_id = null;
    } else {
        $sql_receiver = "SELECT full_name FROM users WHERE user_id = ?";
        if ($stmt_rec = mysqli_prepare($link, $sql_receiver)) {
            mysqli_stmt_bind_param($stmt_rec, "i", $receiver_id);
            mysqli_stmt_execute($stmt_rec);
            mysqli_stmt_bind_result($stmt_rec, $name);
            if (mysqli_stmt_fetch($stmt_rec)) {
                $receiver_name = $name;
            } else {
                $message_err = "المستلم غير موجود.";
                $receiver_id = null;
            }
            mysqli_stmt_close($stmt_rec);
        }
    }
} else {
    // إذا لم يكن هناك ID مستلم محدد، يمكن توجيه المستخدم لصفحة الرسائل
    // header("location: messaging.php");
    // exit;
    $message_err = "الرجاء تحديد مستلم الرسالة.";
}


// 3. معالجة إرسال الرسالة (POST)
if($_SERVER["REQUEST_METHOD"] == "POST" && $receiver_id){
    
    // أ. جلب وتصفية البيانات
    $post_receiver_id = filter_var($_POST["receiver_id"] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $body = trim($_POST["body"] ?? '');
    
    // تأكيد أن الـ ID في الـ POST يطابق الـ ID في الـ GET
    if ($post_receiver_id != $receiver_id) {
        $message_err = "خطأ في معالجة المستلم.";
    } elseif (empty($body)) {
        $message_err = "الرجاء كتابة محتوى الرسالة.";
    }
    
    // ب. إدراج الرسالة
    if(empty($message_err)){
        
        // ملاحظة: قد نحتاج إلى إضافة حقل thread_id لربط الرسائل في محادثة واحدة
        $sql_insert = "INSERT INTO messages (sender_id, receiver_id, body, status) VALUES (?, ?, ?, 'unread')";
         
        if($stmt_insert = mysqli_prepare($link, $sql_insert)){
            
            mysqli_stmt_bind_param($stmt_insert, "iis", $param_sender, $param_receiver, $param_body);
            
            $param_sender = $sender_id;
            $param_receiver = $receiver_id;
            $param_body = $body;
            
            if(mysqli_stmt_execute($stmt_insert)){
                $success_msg = "✅ تم إرسال الرسالة بنجاح!";
                
                // ج. إنشاء إشعار للمستلم
                $notification_content = "لديك رسالة جديدة من {$_SESSION['full_name']} ({$_SESSION['user_role']}).";
                $target_url = "messaging.php"; 
                create_notification($receiver_id, 'new_message', $notification_content, $target_url, $link);
                
                // التوجيه إلى صندوق الرسائل المرسلة
                header("location: messaging.php?status=sent");
                exit();
            } else{
                $message_err = "حدث خطأ في قاعدة البيانات أثناء الإرسال.";
            }

            mysqli_stmt_close($stmt_insert);
        }
    }
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | إرسال رسالة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .message-form-card {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-left: 5px solid var(--color-info);
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        <div class="card message-form-card bg-dark text-white">
            <h2 class="text-center mb-4" style="color: var(--color-info);">📧 إرسال رسالة جديدة</h2>
            
            <?php 
            if(!empty($message_err)){
                echo '<div class="alert alert-danger text-center">' . $message_err . '</div>';
                // إذا كان هناك خطأ، لن يتم عرض النموذج أدناه، بل سيتم عرض زر العودة
                echo '<div class="text-center mt-3"><a href="javascript:history.back()" class="btn btn-outline-secondary">العودة</a></div>';
            } elseif ($receiver_id):
            ?>
            <p class="lead text-center mb-4">إلى: <span class="fw-bold text-warning"><?php echo htmlspecialchars($receiver_name); ?></span></p>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?receiver_id=' . $receiver_id; ?>" method="POST">
                
                <input type="hidden" name="receiver_id" value="<?php echo $receiver_id; ?>">
                
                <div class="mb-4">
                    <label for="body" class="form-label text-muted">محتوى الرسالة</label>
                    <textarea class="form-control" id="body" name="body" rows="6" placeholder="اكتب رسالتك هنا..." required></textarea>
                </div>

                <button type="submit" class="btn btn-info w-100 mt-2">إرسال الرسالة</button>
                <a href="messaging.php" class="btn btn-outline-secondary w-100 mt-2">إلغاء</a>
            </form>
            
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
