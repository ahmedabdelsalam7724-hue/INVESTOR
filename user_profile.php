<?php
// user_profile.php - عرض وتحرير بيانات الملف الشخصي الأساسية

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$user_id = $_SESSION["user_id"];
$full_name = $bio = $expertise = $current_role = '';
$error_message = $success_message = "";

// 1. جلب بيانات المستخدم الحالية
$sql_fetch = "SELECT full_name, user_role, bio, expertise FROM users WHERE user_id = ?";
if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
    mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
    mysqli_stmt_execute($stmt_fetch);
    mysqli_stmt_bind_result($stmt_fetch, $full_name, $current_role, $bio, $expertise);
    mysqli_stmt_fetch($stmt_fetch);
    mysqli_stmt_close($stmt_fetch);
}

// 2. معالجة تحديث البيانات عند الإرسال (POST)
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // أ. جمع البيانات الجديدة وتنقيتها
    $full_name_new = trim($_POST["full_name"] ?? '');
    $bio_new = trim($_POST["bio"] ?? '');
    $expertise_new = trim($_POST["expertise"] ?? '');
    
    // ملاحظة: دور المستخدم (user_role) لا يُسمح بتغييره هنا.

    // ب. التحقق من صحة الإدخالات الأساسية
    if(empty($full_name_new)){
        $error_message = "الاسم الكامل مطلوب ولا يمكن تركه فارغًا.";
    }

    if(empty($error_message)){
        
        // ج. تحديث بيانات المستخدم
        $sql_update = "UPDATE users SET full_name = ?, bio = ?, expertise = ? WHERE user_id = ?";
        
        if($stmt_update = mysqli_prepare($link, $sql_update)){
            // الربط (sssi)
            mysqli_stmt_bind_param($stmt_update, "sssi", 
                $full_name_new, $bio_new, $expertise_new, $user_id);
            
            if(mysqli_stmt_execute($stmt_update)){
                $success_message = "✅ تم تحديث ملفك الشخصي بنجاح!";
                
                // تحديث البيانات في جلسة PHP
                $_SESSION['full_name'] = $full_name_new;
                
                // تحديث المتغيرات لعرض البيانات الجديدة في النموذج
                $full_name = $full_name_new;
                $bio = $bio_new;
                $expertise = $expertise_new;
            } else{
                $error_message = "حدث خطأ في قاعدة البيانات أثناء التحديث.";
            }
            mysqli_stmt_close($stmt_update);
        }
    }
}

mysqli_close($link);

// دالة مساعدة لعرض نص الدور باللغة العربية
function get_arabic_role($role) {
    return $role === 'investor' ? 'مستثمر' : 'رائد أعمال';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | ملفي الشخصي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .profile-card {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            border-left: 5px solid var(--color-success);
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="profile-card bg-dark text-white">
        <h2 class="text-center mb-4" style="color: var(--color-success);">👤 تعديل الملف الشخصي</h2>
        
        <div class="alert alert-info text-center mb-4">
            <span class="fw-bold">دورك في المنصة:</span> 
            <span class="badge bg-primary fs-6"><?php echo get_arabic_role($current_role); ?></span>
        </div>

        <?php 
        if(!empty($error_message)){
            echo '<div class="alert alert-danger text-center">' . $error_message . '</div>';
        } elseif(!empty($success_message)){
            echo '<div class="alert alert-success text-center">' . $success_message . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            
            <div class="mb-3">
                <label for="full_name" class="form-label text-muted">الاسم الكامل</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="bio" class="form-label text-muted">السيرة الذاتية القصيرة</label>
                <textarea class="form-control" id="bio" name="bio" rows="4" placeholder="وصف موجز عنك، خلفيتك، وأهدافك..."><?php echo htmlspecialchars($bio); ?></textarea>
            </div>
            
            <div class="mb-4">
                <label for="expertise" class="form-label text-muted">مجالات الخبرة (افصل بينها بفاصلة)</label>
                <input type="text" class="form-control" id="expertise" name="expertise" value="<?php echo htmlspecialchars($expertise); ?>" placeholder="مثال: الاستثمار المبكر، الذكاء الاصطناعي، التوسع الدولي">
            </div>

            <button type="submit" class="btn btn-success w-100 mt-3">حفظ التغييرات</button>
            <a href="dashboard.php" class="btn btn-outline-secondary w-100 mt-2">العودة إلى لوحة التحكم</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
