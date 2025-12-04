<?php
/**
 * تفعيل عرض الأخطاء (لأغراض التطوير والاختبار فقط!)
 * هذا سيجعل أخطاء PHP تظهر على الشاشة بدلاً من إخفائها أو إظهار خطأ 500 عام.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// register.php - صفحة تسجيل مستخدم جديد

require_once 'session_manager.php';
require_once 'db_config.php';

// متغيرات لتخزين البيانات والرسائل
$full_name = $email = $password = $confirm_password = $user_role = $expertise = "";
$full_name_err = $email_err = $password_err = $confirm_password_err = $user_role_err = $expertise_err = "";

// معالجة بيانات النموذج عند الإرسال
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // 1. التحقق من صحة الاسم الكامل
    if(empty(trim($_POST["full_name"] ?? ''))){
        $full_name_err = "الرجاء إدخال الاسم الكامل.";
    } else{
        $full_name = trim($_POST["full_name"]);
    }

    // 2. التحقق من صحة البريد الإلكتروني (والتأكد من عدم تكراره)
    if(empty(trim($_POST["email"] ?? ''))){
        $email_err = "الرجاء إدخال البريد الإلكتروني.";
    } else{
        // تهيئة استعلام للتحقق من وجود البريد الإلكتروني مسبقاً
        $sql = "SELECT user_id FROM users WHERE email = ?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $email_err = "هذا البريد الإلكتروني مستخدم بالفعل.";
                } else{
                    $email = trim($_POST["email"]);
                }
            } else{
                echo "حدث خطأ في قاعدة البيانات أثناء التحقق من البريد.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 3. التحقق من صحة كلمة المرور
    if(empty(trim($_POST["password"] ?? ''))){
        $password_err = "الرجاء إدخال كلمة المرور.";     
    } elseif(strlen(trim($_POST["password"] ?? '')) < 6){
        $password_err = "يجب أن لا تقل كلمة المرور عن 6 أحرف.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // 4. التحقق من تطابق تأكيد كلمة المرور
    if(empty(trim($_POST["confirm_password"] ?? ''))){
        $confirm_password_err = "الرجاء تأكيد كلمة المرور.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "كلمة المرور غير متطابقة.";
        }
    }

    // 5. التحقق من اختيار الدور
    if(empty(trim($_POST["user_role"] ?? '')) || !in_array($_POST["user_role"], ['investor', 'entrepreneur'])){
        $user_role_err = "الرجاء اختيار دورك (مستثمر أو رائد أعمال).";
    } else {
        $user_role = trim($_POST["user_role"]);
    }

    // 6. جلب حقل الخبرة (اختياري)
    $expertise = trim($_POST["expertise"] ?? '');


    // 7. إذا لم يكن هناك أخطاء إدخال، قم بإدراج المستخدم في قاعدة البيانات
    if(empty($full_name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($user_role_err)){
        
        // استعلام الإدراج
        $sql = "INSERT INTO users (full_name, email, password, user_role, expertise) VALUES (?, ?, ?, ?, ?)";
         
        if($stmt = mysqli_prepare($link, $sql)){
            
            // الربط: (ssiss) string, string, string, string, string - (لنفترض أن الخبرة هي VARCHAR/TEXT)
            mysqli_stmt_bind_param($stmt, "sssss", $param_full_name, $param_email, $param_password, $param_role, $param_expertise);
            
            // تعيين المعاملات
            $param_full_name = $full_name;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // تشفير كلمة المرور
            $param_role = $user_role;
            $param_expertise = $expertise;
            
            if(mysqli_stmt_execute($stmt)){
                // تم التسجيل بنجاح، التوجيه لصفحة تسجيل الدخول
                header("location: login.php?status=registered");
                exit();
            } else{
                // إذا فشل الإدراج هنا (وهذا هو المكان المحتمل لـ 500 إذا لم تُكتشف المشكلة سابقًا)
                // ستظهر رسالة الخطأ بفضل ini_set أعلاه
                echo "حدث خطأ غير متوقع. يرجى مراجعة سجلات الخادم (Logs)."; 
            }

            mysqli_stmt_close($stmt);
        }
    }
    
    // إغلاق الاتصال إذا كان لا يزال مفتوحًا
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | إنشاء حساب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .register-form-container {
            max-width: 550px;
            margin: 50px auto;
            padding: 40px;
            border-left: 5px solid var(--color-info);
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="register-form-container bg-dark text-white">
        <h2 class="text-center mb-4" style="color: var(--color-info);">انضم لشبكتنا 👋</h2>
        <p class="text-muted text-center mb-4">أنشئ حسابك لبدء استكشاف الفرص والمشاريع.</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            
            <div class="mb-3">
                <label for="full_name" class="form-label text-muted">الاسم الكامل</label>
                <input type="text" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label text-muted">البريد الإلكتروني</label>
                <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <div class="invalid-feedback"><?php echo $email_err; ?></div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label text-muted">كلمة المرور</label>
                <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                <div class="invalid-feedback"><?php echo $password_err; ?></div>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label text-muted">تأكيد كلمة المرور</label>
                <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
            </div>
            
            <div class="mb-3">
                <label class="form-label text-muted d-block">سجل كـ:</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="user_role" id="role_investor" value="investor" <?php echo ($user_role === 'investor') ? 'checked' : ''; ?> required>
                    <label class="form-check-label" for="role_investor">مستثمر</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="user_role" id="role_entrepreneur" value="entrepreneur" <?php echo ($user_role === 'entrepreneur') ? 'checked' : ''; ?> required>
                    <label class="form-check-label" for="role_entrepreneur">رائد أعمال</label>
                </div>
                <div class="text-danger small mt-1"><?php echo $user_role_err; ?></div>
            </div>
            
            <div class="mb-4">
                <label for="expertise" class="form-label text-muted">مجالات الخبرة (اختياري)</label>
                <input type="text" class="form-control" id="expertise" name="expertise" value="<?php echo htmlspecialchars($expertise); ?>" placeholder="مثال: تحليل مالي، تطوير تطبيقات">
            </div>

            <button type="submit" class="btn btn-info w-100 mt-3">إنشاء حساب</button>
            <p class="text-center mt-3 text-muted">
                هل لديك حساب بالفعل؟ <a href="login.php" style="color: var(--color-primary);">تسجيل الدخول</a>
            </p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
