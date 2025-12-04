<?php
// profile_settings.php - نموذج إعدادات الملف الشخصي والمهارات

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$user_id = $_SESSION["user_id"];
$user_data = [];
$current_skills = [];
$all_skills_list = [];

// 1. جلب البيانات الحالية للمستخدم (الاسم، السيرة الذاتية)
$sql_user = "SELECT full_name, user_role, biography FROM users WHERE user_id = ?";
if ($stmt_user = mysqli_prepare($link, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($row = mysqli_fetch_assoc($result_user)) {
        $user_data = $row;
    }
    mysqli_stmt_close($stmt_user);
}

// 2. جلب المهارات المسجلة حاليًا للمستخدم
$sql_current_skills = "SELECT s.skill_name 
                       FROM user_skills us 
                       JOIN skills s ON us.skill_id = s.skill_id 
                       WHERE us.user_id = ?";
if ($stmt_skills = mysqli_prepare($link, $sql_current_skills)) {
    mysqli_stmt_bind_param($stmt_skills, "i", $user_id);
    mysqli_stmt_execute($stmt_skills);
    $result_skills = mysqli_stmt_get_result($stmt_skills);
    while ($row = mysqli_fetch_assoc($result_skills)) {
        $current_skills[] = $row['skill_name'];
    }
    mysqli_stmt_close($stmt_skills);
}

// 3. جلب جميع المهارات المتاحة في النظام للمساعدة في الإدخال (اختياري)
$sql_all_skills = "SELECT skill_name FROM skills ORDER BY skill_name ASC";
$result_all_skills = mysqli_query($link, $sql_all_skills);
if ($result_all_skills) {
    while ($row = mysqli_fetch_assoc($result_all_skills)) {
        $all_skills_list[] = $row['skill_name'];
    }
    mysqli_free_result($result_all_skills);
}
// mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | إعدادات الملف الشخصي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .setting-card {
            border-left: 5px solid var(--color-warning);
        }
        .skill-tag {
            background-color: var(--color-primary);
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
        }
    </style>
</head>
<body>
    
    <header class="header">...</header>

    <div class="container py-5">
        <h1 class="text-center mb-5" style="color: var(--color-warning);">📝 إعدادات الملف الشخصي والمهارات</h1>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="card p-4 mb-4 setting-card">
                    <h3 class="mb-3" style="color: var(--color-warning);">تعديل السيرة الذاتية</h3>
                    
                    <form action="update_profile.php" method="POST">
                        <div class="mb-3">
                            <label for="fullName" class="form-label text-muted">الاسم الكامل</label>
                            <input type="text" class="form-control" id="fullName" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" disabled>
                            <div class="form-text">الاسم الكامل لا يمكن تعديله مباشرة من هنا.</div>
                        </div>

                        <div class="mb-3">
                            <label for="userRole" class="form-label text-muted">الدور</label>
                            <input type="text" class="form-control" id="userRole" value="<?php echo htmlspecialchars(($user_data['user_role'] === 'investor') ? 'مستثمر' : 'رائد أعمال'); ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="biography" class="form-label text-muted">السيرة الذاتية (نبذة مختصرة عن خبرتك و اهتماماتك)</label>
                            <textarea class="form-control" id="biography" name="biography" rows="5" required><?php echo htmlspecialchars($user_data['biography'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-warning mt-3">حفظ السيرة الذاتية</button>
                    </form>
                </div>

                <div class="card p-4 setting-card">
                    <h3 class="mb-3" style="color: var(--color-warning);">إضافة/تعديل المهارات 💻</h3>
                    
                    <form action="update_skills.php" method="POST">
                        <div class="mb-3">
                            <label for="skillsInput" class="form-label text-muted">المهارات الحالية:</label>
                            <p>
                                <?php if (!empty($current_skills)): ?>
                                    <?php foreach ($current_skills as $skill): ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-danger small">لم تقم بإضافة أي مهارات بعد.</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="mb-3">
                            <label for="skillsInput" class="form-label text-muted">إضافة أو تحديث المهارات الجديدة (افصل بينها بفاصلة):</label>
                            <input type="text" class="form-control" id="skillsInput" name="skills[]" 
                                   placeholder="مثال: Financial Analysis, Python, Marketing Strategy" 
                                   value="<?php echo htmlspecialchars(implode(', ', $current_skills)); ?>" required>
                            <div class="form-text">ستحل هذه القائمة محل مهاراتك المسجلة سابقاً.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning mt-3">حفظ وتحديث المهارات</button>
                    </form>
                    
                    <div class="mt-4 pt-3 border-top border-secondary">
                        <h5 class="text-white small">اقتراحات المهارات:</h5>
                        <p class="small text-muted">
                            <?php echo htmlspecialchars(implode(', ', array_slice($all_skills_list, 0, 15))); ?>...
                        </p>
                    </div>
                </div>

                <div class="text-center mt-5">
                    <a href="dashboard.php" class="btn btn-outline-secondary">العودة إلى لوحة التحكم</a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
<?php mysqli_close($link); ?>
