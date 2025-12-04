<?php
// sell_company.php - نموذج ومعالجة عرض شركة أو حصة للاستحواذ

require_once 'session_manager.php';
require_once 'db_config.php';

// 1. التأكد من تسجيل الدخول
require_login(); 

// 2. التحقق من الدور: يسمح لرواد الأعمال بنشر عرض البيع.
check_role('entrepreneur'); 

$user_id = $_SESSION["user_id"];

// متغيرات لتخزين رسائل الخطأ والنجاح
$offer_err = $success_msg = "";

// معالجة بيانات النموذج عند الإرسال
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // 3. جمع البيانات وتنقيتها
    $company_name = trim($_POST["company_name"] ?? '');
    $valuation = filter_var($_POST["valuation"] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $equity_offered = filter_var($_POST["equity_offered"] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $reason = trim($_POST["reason"] ?? '');
    $industry = trim($_POST["industry"] ?? '');
    
    // 4. التحقق من صحة الإدخالات
    if(empty($company_name) || empty($reason) || empty($industry) || $valuation <= 0 || $equity_offered <= 0 || $equity_offered > 100){
        $offer_err = "الرجاء ملء جميع الحقول والتأكد من أن التقييم والنسب صحيحة (النسبة يجب أن تكون بين 1% و 100%).";
    }

    // 5. إدراج العرض في جدول الاستحواذ
    if(empty($offer_err)){
        
        $sql_insert = "INSERT INTO acquisitions (user_id, company_name, industry, valuation, equity_offered, reason, status) 
                       VALUES (?, ?, ?, ?, ?, ?, 'available')";
         
        if($stmt_insert = mysqli_prepare($link, $sql_insert)){
            
            // الربط (isssdss: integer, string, string, double, double, string, string)
            mysqli_stmt_bind_param($stmt_insert, "isssdss", $param_user_id, $param_name, $param_industry, $param_valuation, $param_equity, $param_reason, $param_status);
            
            // تعيين المعاملات
            $param_user_id = $user_id;
            $param_name = $company_name;
            $param_industry = $industry;
            $param_valuation = $valuation;
            $param_equity = $equity_offered;
            $param_reason = $reason;
            $param_status = 'available'; // حالة العرض
            
            if(mysqli_stmt_execute($stmt_insert)){
                $success_msg = "✅ تم نشر عرض بيع الشركة بنجاح! سيتم مراجعته وعرضه على المستثمرين.";
                // مسح البيانات بعد النجاح
                $company_name = $reason = $industry = '';
                $valuation = $equity_offered = 0;
            } else{
                $offer_err = "حدث خطأ في قاعدة البيانات أثناء النشر.";
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
    <title>INVESTOR | عرض شركة للبيع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .sell-form-card {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-dark);
            border-left: 5px solid var(--color-danger);
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="sell-form-card">
        <h2 class="text-center mb-4" style="color: var(--color-danger);">📉 عرض شركتك للبيع/الاستحواذ</h2>
        <p class="text-muted text-center">أدخل تفاصيل شركتك والحصة التي تعرضها للمستثمرين المهتمين بالاستحواذ.</p>

        <?php 
        if(!empty($offer_err)){
            echo '<div class="alert alert-danger text-center">' . $offer_err . '</div>';
        } elseif(!empty($success_msg)){
            echo '<div class="alert alert-success text-center">' . $success_msg . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            
            <div class="mb-3">
                <label for="company_name" class="form-label text-muted">اسم الشركة / المشروع</label>
                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_name ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label for="industry" class="form-label text-muted">القطاع/الصناعة</label>
                <select class="form-select" id="industry" name="industry" required>
                    <option value="" disabled selected>اختر قطاع الشركة</option>
                    <option value="Technology">التكنولوجيا (Software)</option>
                    <option value="Fintech">التقنية المالية (Fintech)</option>
                    <option value="Healthcare">الرعاية الصحية</option>
                    <option value="E-commerce">التجارة الإلكترونية</option>
                    <option value="Other">أخرى</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="reason" class="form-label text-muted">السبب الموجز للبيع/البحث عن استحواذ (التحديات، التخارج، إلخ.)</label>
                <textarea class="form-control" id="reason" name="reason" rows="4" required><?php echo htmlspecialchars($reason ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="valuation" class="form-label text-muted">تقييم الشركة الحالي ($)</label>
                    <input type="number" step="1000" min="1000" class="form-control" id="valuation" name="valuation" value="<?php echo htmlspecialchars($valuation > 0 ? $valuation : ''); ?>" required>
                    <div class="form-text">التقييم الكلي الذي تطمح إليه الشركة.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="equity_offered" class="form-label text-muted">حصة الملكية المعروضة للبيع (%)</label>
                    <input type="number" step="0.5" min="1" max="100" class="form-control" id="equity_offered" name="equity_offered" value="<?php echo htmlspecialchars($equity_offered > 0 ? $equity_offered : ''); ?>" required>
                    <div class="form-text">إذا كانت 100% يعني بيع الشركة بالكامل.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-danger w-100 mt-3">نشر عرض البيع الآن</button>
            <p class="text-center mt-3 text-muted">
                <a href="dashboard.php" style="color: var(--color-primary);">العودة إلى لوحة التحكم</a>
            </p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
