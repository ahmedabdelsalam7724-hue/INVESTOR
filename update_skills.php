<?php
// update_skills.php - تحديث بيانات ملف المهارات والتعاون (team_profiles)

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$user_id = $_SESSION["user_id"];
$user_role = $_SESSION["user_role"];

$skills_offered = $skills_needed = $collaboration_role = $industries_of_interest = $availability_status = '';
$message_err = $success_msg = "";
$is_existing_profile = false;

// 1. جلب البيانات الحالية للملف الشخصي (إن وجدت)
$sql_fetch = "SELECT * FROM team_profiles WHERE user_id = ?";
if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
    mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
    mysqli_stmt_execute($stmt_fetch);
    $result_fetch = mysqli_stmt_get_result($stmt_fetch);
    
    if (mysqli_num_rows($result_fetch) == 1) {
        $profile = mysqli_fetch_assoc($result_fetch);
        $is_existing_profile = true;
        
        // تعبئة المتغيرات بالبيانات الموجودة
        $skills_offered = $profile['skills_offered'];
        $skills_needed = $profile['skills_needed'];
        $collaboration_role = $profile['collaboration_role'];
        $industries_of_interest = $profile['industries_of_interest'];
        $availability_status = $profile['availability_status'];
    }
    mysqli_stmt_close($stmt_fetch);
}

// 2. معالجة تحديث/إدراج البيانات عند الإرسال (POST)
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // أ. جمع البيانات الجديدة وتنقيتها
    $skills_offered_new = trim($_POST["skills_offered"] ?? '');
    $skills_needed_new = trim($_POST["skills_needed"] ?? '');
    $collaboration_role_new = trim($_POST["collaboration_role"] ?? '');
    $industries_of_interest_new = trim($_POST["industries_of_interest"] ?? '');
    $availability_status_new = trim($_POST["availability_status"] ?? 'open_to_chat');

    // ب. التحقق من صحة الإدخالات الأساسية
    if(empty($skills_offered_new) || empty($collaboration_role_new)){
        $message_err = "الرجاء إدخال المهارات التي تقدمها والدور الذي تبحث عنه للتعاون.";
    }

    if(empty($message_err)){
        
        if ($is_existing_profile) {
            // ج. تحديث ملف موجود
            $sql_action = "UPDATE team_profiles SET 
                           skills_offered = ?, skills_needed = ?, collaboration_role = ?, 
                           industries_of_interest = ?, availability_status = ? 
                           WHERE user_id = ?";
            
            if($stmt_action = mysqli_prepare($link, $sql_action)){
                // الربط (sssssi)
                mysqli_stmt_bind_param($stmt_action, "sssssi", 
                    $skills_offered_new, $skills_needed_new, $collaboration_role_new, 
                    $industries_of_interest_new, $availability_status_new, $user_id);
                
                if(mysqli_stmt_execute($stmt_action)){
                    $success_msg = "✅ تم تحديث ملف المهارات بنجاح!";
                } else{
                    $message_err = "حدث خطأ في قاعدة البيانات أثناء التحديث.";
                }
                mysqli_stmt_close($stmt_action);
            }
        } else {
            // د. إدراج ملف جديد
            $sql_action = "INSERT INTO team_profiles 
                           (user_id, skills_offered, skills_needed, collaboration_role, industries_of_interest, availability_status) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            
            if($stmt_action = mysqli_prepare($link, $sql_action)){
                // الربط (isssss)
                mysqli_stmt_bind_param($stmt_action, "isssss", 
                    $user_id, $skills_offered_new, $skills_needed_new, $collaboration_role_new, 
                    $industries_of_interest_new, $availability_status_new);
                
                if(mysqli_stmt_execute($stmt_action)){
                    $success_msg = "✅ تم إنشاء ملف المهارات بنجاح!";
                    $is_existing_profile = true; // تم الإنشاء الآن
                } else{
                    $message_err = "حدث خطأ في قاعدة البيانات أثناء الإنشاء.";
                }
                mysqli_stmt_close($stmt_action);
            }
        }
        
        // تحديث المتغيرات لعرض البيانات الجديدة في النموذج بعد الإرسال
        $skills_offered = $skills_offered_new;
        $skills_needed = $skills_needed_new;
        $collaboration_role = $collaboration_role_new;
        $industries_of_interest = $industries_of_interest_new;
        $availability_status = $availability_status_new;
    }
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | تحديث مهارات التعاون</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .skills-form-card {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            border-left: 5px solid var(--color-primary);
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="skills-form-card bg-dark text-white">
        <h2 class="text-center mb-4" style="color: var(--color-primary);">
            <?php echo $is_existing_profile ? '✏️ تحديث ملف مهارات التعاون' : '➕ إنشاء ملف مهارات جديد'; ?>
        </h2>
        <p class="text-muted text-center">
            أكمل هذا الملف لتظهر في صفحة **مطابقة الفريق** وجذب الشركاء المناسبين.
        </p>

        <?php 
        if(!empty($message_err)){
            echo '<div class="alert alert-danger text-center">' . $message_err . '</div>';
        } elseif(!empty($success_msg)){
            echo '<div class="alert alert-success text-center">' . $success_msg . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            
            <div class="mb-3">
                <label for="skills_offered" class="form-label text-muted">المهارات التي تقدمها (افصل بينها بفاصلة)</label>
                <textarea class="form-control" id="skills_offered" name="skills_offered" rows="3" required placeholder="مثال: تحليل مالي، تطوير Flutter، بناء فرق المبيعات"><?php echo htmlspecialchars($skills_offered); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="skills_needed" class="form-label text-muted">المهارات التي تبحث عنها في شريك (اختياري)</label>
                <textarea class="form-control" id="skills_needed" name="skills_needed" rows="3" placeholder="مثال: خبرة قانونية، تطوير أندرويد، استثمار مرحلة البذرة"><?php echo htmlspecialchars($skills_needed); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="collaboration_role" class="form-label text-muted">الدور الذي تبحث عنه أو تعرضه</label>
                <select class="form-select" id="collaboration_role" name="collaboration_role" required>
                    <option value="" disabled <?php echo empty($collaboration_role) ? 'selected' : ''; ?>>اختر دور التعاون</option>
                    <option value="Co-Founder" <?php echo $collaboration_role === 'Co-Founder' ? 'selected' : ''; ?>>مؤسس مشارك</option>
                    <option value="Advisor" <?php echo $collaboration_role === 'Advisor' ? 'selected' : ''; ?>>مستشار</option>
                    <option value="Mentor" <?php echo $collaboration_role === 'Mentor' ? 'selected' : ''; ?>>مرشد/موجّه</option>
                    <option value="Investor" <?php echo $collaboration_role === 'Investor' ? 'selected' : ''; ?>>مستثمر</option>
                    <option value="Employee/Contractor" <?php echo $collaboration_role === 'Employee/Contractor' ? 'selected' : ''; ?>>موظف/متعاقد</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="industries_of_interest" class="form-label text-muted">القطاعات المهتم بها (افصل بفاصلة)</label>
                <input type="text" class="form-control" id="industries_of_interest" name="industries_of_interest" value="<?php echo htmlspecialchars($industries_of_interest); ?>" placeholder="مثال: Fintech, E-commerce, Gaming">
            </div>

            <div class="mb-4">
                <label for="availability_status" class="form-label text-muted">حالة التوفر للتعاون</label>
                <select class="form-select" id="availability_status" name="availability_status">
                    <option value="available" <?php echo $availability_status === 'available' ? 'selected' : ''; ?>>متاح للتعاون الفوري</option>
                    <option value="open_to_chat" <?php echo $availability_status === 'open_to_chat' || empty($availability_status) ? 'selected' : ''; ?>>متاح للمحادثة والاستكشاف</option>
                    <option value="not_available" <?php echo $availability_status === 'not_available' ? 'selected' : ''; ?>>غير متاح حاليًا</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">
                <?php echo $is_existing_profile ? 'حفظ التحديثات' : 'إنشاء الملف'; ?>
            </button>
            <a href="team_matching.php" class="btn btn-outline-secondary w-100 mt-2">العودة إلى المطابقة</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
