<?php
// messaging.php - عرض قائمة المحادثات (Inbox)

require_once 'session_manager.php';
require_once 'db_config.php';

require_login(); 

$user_id = $_SESSION["user_id"];
$conversations = [];
$error_message = "";

// 1. استعلام معقد لجلب آخر رسالة في كل محادثة فريدة
// المحادثة تُعرّف بزوج من المستخدمين (sender_id, receiver_id) بغض النظر عن ترتيبهم.

// العثور على آخر رسالة لكل محادثة بين المستخدمين
$sql = "
    SELECT 
        m1.message_id, 
        m1.body, 
        m1.created_at, 
        m1.sender_id, 
        m1.receiver_id,
        m1.status,
        u.full_name AS partner_name,
        u.user_id AS partner_id
    FROM 
        messages m1
    INNER JOIN (
        -- تحديد أحدث رسالة لكل مجموعة محادثة (طرفي الرسالة)
        SELECT
            GREATEST(sender_id, receiver_id) AS user_a,
            LEAST(sender_id, receiver_id) AS user_b,
            MAX(created_at) AS last_message_time
        FROM 
            messages
        WHERE 
            sender_id = ? OR receiver_id = ?
        GROUP BY 
            user_a, user_b
    ) AS latest_messages 
    ON 
        latest_messages.last_message_time = m1.created_at
        AND (
            (m1.sender_id = latest_messages.user_a AND m1.receiver_id = latest_messages.user_b) OR
            (m1.sender_id = latest_messages.user_b AND m1.receiver_id = latest_messages.user_a)
        )
    JOIN 
        users u ON u.user_id = 
            CASE 
                WHEN m1.sender_id = ? THEN m1.receiver_id 
                ELSE m1.sender_id 
            END
    ORDER BY 
        m1.created_at DESC
";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        $seen_conversations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // تحديد شريك المحادثة بشكل مستقل
            $partner_id = ($row['sender_id'] == $user_id) ? $row['receiver_id'] : $row['sender_id'];
            
            // إنشاء مفتاح فريد للمحادثة بغض النظر عن الترتيب
            $conv_key = min($user_id, $partner_id) . '_' . max($user_id, $partner_id);

            // التأكد من أننا نأخذ أحدث رسالة واحدة فقط لكل محادثة (لمنع التكرار في حال تطابق التوقيت)
            if (!isset($seen_conversations[$conv_key])) {
                // جلب اسم الشريك الفعلي
                $partner_name_query = "SELECT full_name FROM users WHERE user_id = ?";
                if ($stmt_partner = mysqli_prepare($link, $partner_name_query)) {
                    mysqli_stmt_bind_param($stmt_partner, "i", $partner_id);
                    mysqli_stmt_execute($stmt_partner);
                    mysqli_stmt_bind_result($stmt_partner, $partner_name_fetch);
                    mysqli_stmt_fetch($stmt_partner);
                    $row['partner_name'] = $partner_name_fetch;
                    mysqli_stmt_close($stmt_partner);
                }

                // التحقق من وجود رسائل غير مقروءة من هذا الشريك
                $unread_count_sql = "SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND status = 'unread'";
                if ($stmt_unread = mysqli_prepare($link, $unread_count_sql)) {
                    mysqli_stmt_bind_param($stmt_unread, "ii", $partner_id, $user_id);
                    mysqli_stmt_execute($stmt_unread);
                    mysqli_stmt_bind_result($stmt_unread, $unread_count);
                    mysqli_stmt_fetch($stmt_unread);
                    $row['unread_count'] = $unread_count;
                    mysqli_stmt_close($stmt_unread);
                }

                $row['partner_id'] = $partner_id;
                $conversations[] = $row;
                $seen_conversations[$conv_key] = true;
            }
        }
    } else {
        $error_message = "خطأ في قاعدة البيانات: " . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVESTOR | صندوق الرسائل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .inbox-container {
            max-width: 900px;
            margin: 50px auto;
        }
        .conversation-item {
            cursor: pointer;
            border-left: 5px solid var(--color-secondary);
            transition: background-color 0.3s;
        }
        .conversation-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        .unread-indicator {
            background-color: var(--color-info) !important;
            border-left: 5px solid var(--color-info) !important;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-dark bg-dark">...</header>

    <div class="container py-5">
        <div class="inbox-container">
            <h1 class="text-center mb-4" style="color: var(--color-info);">📬 صندوق الرسائل الواردة</h1>

            <?php if (isset($_GET['status']) && $_GET['status'] == 'sent'): ?>
                <div class="alert alert-success text-center">✅ تم إرسال رسالتك بنجاح.</div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="list-group">
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $conv): 
                        // تحديد ما إذا كانت المحادثة تحتوي على رسائل غير مقروءة
                        $is_unread = $conv['unread_count'] > 0;
                        $unread_class = $is_unread ? 'unread-indicator bg-dark' : 'bg-dark';
                    ?>
                    
                    <a href="conversation.php?partner_id=<?php echo $conv['partner_id']; ?>" 
                       class="list-group-item list-group-item-action p-3 mb-2 rounded conversation-item <?php echo $unread_class; ?> text-white">
                        
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 fw-bold text-info">
                                🧑‍🤝‍🧑 <?php echo htmlspecialchars($conv['partner_name']); ?>
                            </h5>
                            <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($conv['created_at'])); ?></small>
                        </div>
                        
                        <p class="mb-1 text-light">
                            <?php 
                                // عرض ملخص الرسالة
                                $prefix = ($conv['sender_id'] == $user_id) ? 'أنت: ' : '';
                                echo $prefix . mb_substr(strip_tags($conv['body']), 0, 70, 'UTF-8') . (mb_strlen($conv['body'], 'UTF-8') > 70 ? '...' : '');
                            ?>
                        </p>
                        
                        <?php if ($is_unread): ?>
                            <span class="badge bg-danger rounded-pill float-end mt-2">
                                <?php echo $conv['unread_count']; ?> رسالة جديدة
                            </span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="alert alert-info text-center mt-4">
                        صندوق الرسائل فارغ حالياً. ابدأ محادثة جديدة مع مستثمر أو رائد أعمال!
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-4">
                 <a href="dashboard.php" class="btn btn-outline-secondary">العودة إلى لوحة التحكم</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>
</html>
