<?php
// submit_pitch.php - نموذج ومعالجة نشر عرض تمويل جديد

require_once 'session_manager.php';
require_once 'db_config.php';

// 1. التأكد من تسجيل الدخول
require_login(); 

// 2. التحقق من الدور: يجب أن يكون رائد أعمال فقط هو من يمكنه نشر عرض
check_role('entrepreneur'); 

$user_id = $_SESSION["user_id"];

// متغيرات لتخزين رسائل الخطأ والنجاح
$pitch_err = $success_msg = "";

// معالجة بيانات النموذج عند الإرسال
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // 3. جمع البيانات وتنقيتها
    $title = trim($_POST["title"] ?? '');
    $description = trim($_POST["description"] ?? '');
    $category = trim($_POST["category"] ?? '');
    $required_amount = filter_var($_POST["required_amount"] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $equity_offered = filter_var($_POST["equity_offered"] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    // 4. التحقق من صحة الإدخالات
    if(empty($title) || empty($description) || empty($category) || $required_amount <= 0 || $equity_offered <= 0 || $equity_offered > 100){
        $pitch_err = "الرجاء ملء جميع الحقول والتأكد من أن المبالغ والنسب صحيحة (النسبة يجب أن تكون بين 1% و 100%).";
    }

    // 5. إدراج العرض في قاعدة البيانات
    if(empty($pitch_err)){
        
        $sql_insert = "INSERT INTO pitches (user_id, title, description, category, required_amount, equity_offered, status) 
                       VALUES (?, ?, ?, ?, ?, ?, 'open')";
         
        if($stmt_insert = mysqli_prepare($link, $sql_insert)){
            
            // الربط (isssdd: integer, string, string, string, double, double)
            mysqli_stmt_bind_param($stmt_insert, "isssdd", $param_user_id, $param_title, $param_description, $param_category, $param_amount, $param_equity);
            
            // تعيين المعاملات
            $param_user_id = $user_id;
            $param_title = $title;
            $param_description = $description;
            $param_category = $category;
            $param_amount = $required_amount;
            $param_equity = $equity_offered;
            
            if(mysqli_stmt_execute($stmt_insert)){
                $success_msg = "✅ تم نشر عرض التمويل بنجاح! سيتم مراجعته وعرضه على المستثمرين.";
                // مسح البيانات بعد النجاح
                $title = $description = $category = '';
                $required_amount = $equity_offered = 0;
            } else{
                $pitch_err = "حدث خطأ في قاعدة البيانات أثناء النشر.";
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
    <title>INVESTOR | نشر عرض تمويل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .pitch-form-card {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-dark);
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="pitch-form-card">
        <h2 class="text-center mb-4" style="color: var(--color-success);">🚀 نشر عرض تمويل جديد</h2>
        <p class="text-muted text-center">أدخل تفاصيل مشروعك والمبلغ المطلوب للحصول على تمويل من شبكتنا.</p>

        <?php 
        if(!empty($pitch_err)){
            echo '<div class="alert alert-danger text-center">' . $pitch_err . '</div>';
        } elseif(!empty($success_msg)){
            echo '<div class="alert alert-success text-center">' . $success_msg . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            
            <div class="mb-3">
                <label for="title" class="form-label text-muted">عنوان عرض التمويل (مثال: منصة SaaS لإدارة المطاعم)</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label for="category" class="form-label text-muted">القطاع/الفئة</label>
                <select class="form-select" id="category" name="category" required>
                    <option value="" disabled selected>اختر فئة المشروع</option>
                    <option value="Technology">التكنولوجيا (Software)</option>
                    <option value="Fintech">التقنية المالية (Fintech)</option>
                    <option value="Healthcare">الرعاية الصحية</option>
                    <option value="E-commerce">التجارة الإلكترونية</option>
                    <option value="Real Estate">العقارات</option>
                    <option value="Other">أخرى</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label text-muted">الوصف التفصيلي للمشروع (الفكرة، الفريق، السوق المستهدف، الميزة التنافسية)</label>
                <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="required_amount" class="form-label text-muted">المبلغ المطلوب للتمويل ($)</label>
                    <input type="number" step="1000" min="1000" class="form-control" id="required_amount" name="required_amount" value="<?php echo htmlspecialchars($required_amount > 0 ? $required_amount : ''); ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="equity_offered" class="form-label text-muted">حصة الملكية المعروضة (%)</label>
                    <input type="number" step="0.5" min="1" max="100" class="form-control" id="equity_offered" name="equity_offered" value="<?php echo htmlspecialchars($equity_offered > 0 ? $equity_offered : ''); ?>" required>
                    <div class="form-text">النسبة المئوية من حصة شركتك مقابل التمويل.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 mt-3">نشر العرض الآن</button>
            <p class="text-center mt-3 text-muted">
                <a href="dashboard.php" style="color: var(--color-primary);">العودة إلى لوحة التحكم</a>
            </p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
