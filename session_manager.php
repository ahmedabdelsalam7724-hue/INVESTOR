<?php
/*
 * session_manager.php
 * لإدارة الجلسات، التحقق من حالة تسجيل الدخول، والتحقق من الأدوار.
 */

// بدء الجلسة إذا لم تكن قد بدأت بالفعل
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * وظيفة للتحقق من تسجيل دخول المستخدم.
 * إذا لم يكن مسجلاً، يتم توجيهه إلى صفحة الدخول.
 */
function require_login() {
    if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
        // لم يتم تسجيل الدخول، قم بالتوجيه إلى صفحة الدخول
        header("location: login.php");
        exit;
    }
}

/**
 * وظيفة للتحقق من أن المستخدم لديه دور معين.
 */
function check_role($required_role) {
    if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== $required_role) {
        // ليس لديه الدور المطلوب، يتم توجيهه إلى لوحة التحكم أو رسالة خطأ
        header("location: dashboard.php?error=unauthorized_role");
        exit;
    }
}

/**
 * وظيفة لتسجيل الخروج الآمن.
 */
function logout() {
    // 1. تفريغ جميع متغيرات الجلسة
    $_SESSION = array(); 
    
    // 2. تدمير ملف تعريف الارتباط (Cookie) الخاص بالجلسة
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // 3. تدمير الجلسة
    session_destroy();  
    
    // 4. التوجيه إلى الصفحة الرئيسية أو الدخول
    header("location: index.php"); 
    exit;
}
// الملف ينتهي هنا بدون وسم الإغلاق ?>
