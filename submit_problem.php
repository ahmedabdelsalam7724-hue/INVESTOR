<?php
// submit_problem.php - نموذج ومعالجة نشر مشكلة جديدة في المنتدى

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$user_id = $_SESSION["user_id"];

// متغيرات لتخزين رسائل الخطأ والنجاح
$problem_err = $success_msg = "";
$title = $description = $industry = '';

// معالجة بيانات النموذج عند الإرسال
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // 1. جمع البيانات وتنقيتها
    $title = trim($_POST["title"] ?? '');
    $description = trim($_POST["description"] ?? '');
    $industry = trim($_POST["industry"] ?? '');
    
    // 2. التحقق من صحة الإدخالات
    if(empty($title) || empty($description) || empty($industry)){
        $problem_err = "الرجاء ملء جميع الحقول المطلوبة.";
    }

    // 3. إدراج المشكلة في قاعدة البيانات
    if(empty($problem_err)){
        
        $sql_insert = "INSERT INTO problems (user_id, title, description, industry, status) 
                       VALUES (?, ?, ?, ?, 'open')";
         
        if($stmt_insert = mysqli_prepare($link, $sql_insert)){
            
            // الربط (isss: integer, string, string, string)
            mysqli_stmt_bind_param($stmt_insert, "isss", $param_user_id, $param_title, $param_description, $param_industry);
            
            // تعيين المعاملات
            $param_user_id = $user_id;
            $param_title = $title;
            $param_description = $description;
            $param_industry = $industry;
            
            if(mysqli_stmt_execute($stmt_insert)){
                $success_msg = "✅ تم نشر المشكلة بنجاح! سيتمكن الأعضاء من المساهمة بالحلول.";
                
                // مسح البيانات بعد النجاح
                $title = $description = $industry = '';
            } else{
                $problem_err = "حدث خطأ في قاعدة البيانات أثناء النشر.";
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
    <title>INVESTOR | نشر مشكلة جديدة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .problem-form-card {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-dark);
            border-left: 5px solid var(--color-warning);
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="problem-form-card">
        <h2 class="text-center mb-4" style="color: var(--color-warning);">❓ اطرح مشكلة أو تحديًا</h2>
        <p class="text-muted text-center">شارك التحدي الذي تواجهه في مجالك للحصول على حلول وآراء من شبكة المستثمرين ورواد الأعمال.</p>

        <?php 
        if(!empty($problem_err)){
            echo '<div class="alert alert-danger text-center">' . $problem_err . '</div>';
        } elseif(!empty($success_msg)){
            echo '<div class="alert alert-success text-center">' . $success_msg . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            
            <div class="mb-3">
                <label for="title" class="form-label text-muted">عنوان المشكلة (مثال: صعوبة في التسويق لأول 1000 عميل)</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
            </div>

            <div class="mb-3">
                <label for="industry" class="form-label text-muted">القطاع/الصناعة</label>
                <select class="form-select" id="industry" name="industry" required>
                    <option value="" disabled selected>اختر فئة المشكلة</option>
                    <option value="Technology">التكنولوجيا (Software)</option>
                    <option value="Fintech">التقنية المالية (Fintech)</option>
                    <option value="Healthcare">الرعاية الصحية</option>
                    <option value="E-commerce">التجارة الإلكترونية</option>
                    <option value="General Management">الإدارة العامة</option>
                    <option value="Marketing">التسويق والمبيعات</option>
                    <option value="Other">أخرى</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label text-muted">الوصف التفصيلي للمشكلة (السياق، ما جربته، وما تتوقعه)</label>
                <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($description); ?></textarea>
            </div>

            <button type="submit" class="btn btn-warning w-100 mt-3">نشر المشكلة في المنتدى</button>
            <p class="text-center mt-3 text-muted">
                <a href="problem_forum.php" style="color: var(--color-primary);">العودة إلى المنتدى</a>
            </p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
