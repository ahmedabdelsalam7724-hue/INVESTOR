<?php
// team_matching.php - البحث عن شركاء، مستشارين، أو مؤسسين مشاركين

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$user_id = $_SESSION["user_id"];
$current_role = $_SESSION["user_role"];
$available_partners = [];
$search_term = '';
$filter_role = '';
$error_message = "";

// 1. معالجة بيانات البحث والتصفية
if ($_SERVER["REQUEST_METHOD"] == "GET" && (isset($_GET['search']) || isset($_GET['role_filter']))) {
    $search_term = trim($_GET['search'] ?? '');
    $filter_role = trim($_GET['role_filter'] ?? '');
}

// 2. بناء استعلام SQL لجلب قائمة الشركاء المتاحين
// نستبعد المستخدم الحالي من قائمة النتائج
$sql = "SELECT 
            user_id, 
            full_name, 
            user_role, 
            bio, 
            expertise 
        FROM 
            users 
        WHERE 
            user_id != ?";
$params = [$user_id];
$types = "i";

// إضافة شروط البحث
$conditions = [];
if (!empty($filter_role)) {
    $conditions[] = "user_role = ?";
    $params[] = $filter_role;
    $types .= "s";
}
if (!empty($search_term)) {
    // البحث في الاسم، السيرة الذاتية (Bio)، والخبرة (Expertise)
    $conditions[] = "(full_name LIKE ? OR bio LIKE ? OR expertise LIKE ?)";
    $search_pattern = "%" . $search_term . "%";
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $types .= "sss";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY user_role, full_name ASC";

// 3. تنفيذ الاستعلام
if ($stmt = mysqli_prepare($link, $sql)) {
    // ربط المعاملات ديناميكياً
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            // قص السيرة الذاتية لتناسب العرض المختصر
            $row['short_bio'] = mb_substr(strip_tags($row['bio'] ?? ''), 0, 150, 'UTF-8') . (mb_strlen($row['bio'] ?? '', 'UTF-8') > 150 ? '...' : '');
            $available_partners[] = $row;
        }
    } else {
        $error_message = "خطأ في قاعدة البيانات أثناء جلب الشركاء.";
    }
    mysqli_stmt_close($stmt);
} else {
    $error_message = "خطأ في تهيئة الاستعلام.";
}

mysqli_close($link);

// وظيفة مساعدة لعرض لون الدور
function get_role_badge_class($role) {
    return ($role === 'investor') ? 'bg-primary' : 'bg-success';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | مطابقة الفريق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .partner-card {
            border-left: 5px solid var(--color-primary);
            transition: box-shadow 0.3s;
        }
        .partner-card:hover {
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2); 
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        <h1 class="text-center mb-4" style="color: var(--color-primary);">🤝 مطابقة الفريق والتعاون</h1>
        <p class="text-center lead text-muted mb-5">
            ابحث عن مستثمرين، مؤسسين مشاركين، أو مستشارين لنمو مشروعك.
        </p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-5 bg-dark p-4 rounded border-info">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label text-muted">البحث بالاسم أو المهارات</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="مثال: ذكاء اصطناعي، تسويق رقمي" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-4">
                    <label for="role_filter" class="form-label text-muted">التصفية حسب الدور</label>
                    <select class="form-select" id="role_filter" name="role_filter">
                        <option value="">جميع الأدوار</option>
                        <option value="investor" <?php echo $filter_role === 'investor' ? 'selected' : ''; ?>>مستثمر</option>
                        <option value="entrepreneur" <?php echo $filter_role === 'entrepreneur' ? 'selected' : ''; ?>>رائد أعمال/مؤسس مشارك</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">بحث 🔎</button>
                </div>
            </div>
        </form>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($available_partners)): ?>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($available_partners as $partner): ?>
                <div class="col">
                    <div class="card h-100 p-4 partner-card">
                        <div class="card-body d-flex flex-column">
                            <h4 class="card-title mb-2" style="color: var(--color-primary);">
                                <?php echo htmlspecialchars($partner['full_name']); ?>
                                <span class="badge <?php echo get_role_badge_class($partner['user_role']); ?> ms-2">
                                    <?php echo $partner['user_role'] === 'investor' ? 'مستثمر' : 'رائد أعمال'; ?>
                                </span>
                            </h4>
                            
                            <p class="card-text text-white mt-3 flex-grow-1">
                                **الخبرات:** <?php echo htmlspecialchars($partner['expertise'] ?? 'غير محدد'); ?>
                            </p>
                            <p class="card-text text-muted small">
                                **السيرة:** <?php echo htmlspecialchars($partner['short_bio']); ?>
                            </p>

                            <a href="send_message.php?receiver_id=<?php echo $partner['user_id']; ?>" class="btn btn-outline-info mt-auto">
                                💬 ابدأ محادثة الآن
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-5" role="alert">
                عذراً، لم يتم العثور على شركاء يطابقون معايير البحث.
            </div>
        <?php endif; ?>

    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
