<?php
require_once(__DIR__ . '/config.php');

$PAGE->set_url(new moodle_url('/join_teacher.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('الانضمام للكادر التدريسي');
$PAGE->set_heading('كن جزءاً من نخبة التدريسيين في المنصة');

$submitted = false;
$error_msg = '';

// إعدادات الحماية من الطلبات المزعجة (Spam)
$max_per_ip = 3;          // أقصى عدد طلبات من نفس عنوان IP
$window = HOURSECS;       // خلال ساعة واحدة
$min_fill_seconds = 5;    // أقل وقت معقول لملء النموذج (الروبوتات ترسل فوراً)

$ratecache = cache::make_from_params(cache_store::MODE_APPLICATION, 'core', 'platform_join_teacher',
    [], ['simplekeys' => true]);
$ipkey = md5(getremoteaddr());

// معالجة نموذج طلب الانضمام
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    require_sesskey(); // حماية أمنية مدمجة في Moodle

    $fullname = trim(required_param('fullname', PARAM_TEXT));
    $email = trim(required_param('email', PARAM_EMAIL));
    $degree = trim(required_param('degree', PARAM_TEXT));
    $specialty = trim(required_param('specialty', PARAM_TEXT));
    $bio = trim(optional_param('bio', '', PARAM_TEXT));
    $honeypot = optional_param('website', '', PARAM_RAW); // حقل مخفي لا يملؤه إلا الروبوت

    // سجل الطلبات السابقة من نفس الـ IP خلال النافذة الزمنية
    $attempts = $ratecache->get($ipkey) ?: [];
    $attempts = array_filter($attempts, fn($t) => $t > time() - $window);
    $formtime = $SESSION->join_teacher_formtime ?? 0;

    if ($honeypot !== '' || !$formtime || time() - $formtime < $min_fill_seconds) {
        // روبوت على الأغلب: نتجاهل الطلب بصمت ونعرض رسالة النجاح حتى لا يعرف أنه كُشف
        $submitted = true;
    } else if (count($attempts) >= $max_per_ip) {
        $error_msg = 'تم استلام عدة طلبات من جهازك مؤخراً. يرجى المحاولة مرة أخرى بعد ساعة.';
    } else if ($fullname === '' || $email === '' || $specialty === '' || !validate_email($email)) {
        $error_msg = 'يرجى ملء جميع الحقول المطلوبة وإدخال بريد إلكتروني صحيح.';
    } else if (core_text::strlen($fullname) > 150 || core_text::strlen($specialty) > 200
            || core_text::strlen($degree) > 150 || core_text::strlen($bio) > 2000) {
        $error_msg = 'بعض الحقول أطول من المسموح. يرجى اختصار النص.';
    } else {
        // إرسال إشعار لمدير النظام (Admin) بالبريد الإلكتروني
        $admin = get_admin();
        $subject = "طلب انضمام كمعلم جديد: " . $fullname;
        $messagetext = "تفاصيل المتقدم للانضمام إلى الكادر التدريسي:\n\n"
                     . "الاسم الكامل: $fullname\n"
                     . "البريد الإلكتروني: $email\n"
                     . "اللقب العلمي / الشهادة: $degree\n"
                     . "التخصص الدقيق: $specialty\n"
                     . "نبذة ومقترح المقرر: $bio\n";

        if (email_to_user($admin, $admin, $subject, $messagetext)) {
            $attempts[] = time();
            $ratecache->set($ipkey, array_values($attempts));
            unset($SESSION->join_teacher_formtime);
            $submitted = true;
        } else {
            $error_msg = 'تعذّر إرسال الطلب حالياً. يرجى المحاولة لاحقاً.';
        }
    }
}

// تسجيل وقت عرض النموذج (يُستخدم لكشف الإرسال الآلي السريع)
if (!$submitted) {
    $SESSION->join_teacher_formtime = time();
}

echo $OUTPUT->header();
?>

<style>
    .join-container {
        font-family: 'Cairo', sans-serif;
        padding: 20px 0;
        max-width: 1000px;
        margin: 0 auto;
    }
    .join-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 30px;
        align-items: start;
    }
    @media (max-width: 768px) {
        .join-grid { grid-template-columns: 1fr; }
    }
    .login-box, .apply-box {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-top: 4px solid #347C2C;
        border-radius: 12px;
        padding: 28px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .login-box {
        background: #f9fafb;
        border-top-color: #556B2F;
    }
    .box-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 12px;
    }
    .box-desc {
        font-size: 0.9rem;
        color: #4b5563;
        line-height: 1.6;
        margin-bottom: 20px;
    }
    .form-group {
        margin-bottom: 16px;
        text-align: right;
    }
    .form-group label {
        display: block;
        font-size: 0.9rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }
    .form-control-custom {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-family: 'Cairo', sans-serif;
        font-size: 0.95rem;
        transition: border-color 0.2s;
    }
    .form-control-custom:focus {
        outline: none;
        border-color: #347C2C;
    }
    .btn-primary-custom {
        display: block;
        width: 100%;
        background-color: #347C2C;
        color: #ffffff !important;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-family: 'Cairo', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-primary-custom:hover {
        background-color: #556B2F;
    }
    .btn-secondary-custom {
        display: block;
        width: 100%;
        background-color: #556B2F;
        color: #ffffff !important;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
        transition: background-color 0.2s;
    }
    .btn-secondary-custom:hover {
        background-color: #347C2C;
    }
</style>

<div class="join-container">
    <div class="join-grid">
        <!-- القسم الأول: تسجيل الدخول للمعلمين المسجلين مسبقاً -->
        <div class="login-box">
            <div class="box-title">هل لديك حساب تدريسي؟</div>
            <div class="box-desc">
                إذا كنت عضواً مسجلاً بالفعل ضمن الكادر التدريسي في المنصة، يمكنك تسجيل الدخول مباشرة للوصول إلى مقرراتك الدراسية وإدارة المحتوى التعليمي.
            </div>
            
            <?php if (isloggedin() && !isguestuser()): ?>
                <div class="alert alert-success" style="font-size: 0.9rem;">
                    أنت مسجل الدخول حالياً باسم <strong><?php echo s(fullname($USER)); ?></strong>.
                </div>
                <a href="<?php echo $CFG->wwwroot; ?>/my/" class="btn-secondary-custom">الذهاب إلى لوحة التحكم والمقررات</a>
            <?php else: ?>
                <form action="<?php echo $CFG->wwwroot; ?>/login/index.php" method="post">
                    <input type="hidden" name="logintoken" value="<?php echo \core\session\manager::get_login_token(); ?>">
                    <div class="form-group">
                        <label>اسم المستخدم أو البريد الإلكتروني</label>
                        <input type="text" name="username" class="form-control-custom" required>
                    </div>
                    <div class="form-group">
                        <label>كلمة المرور</label>
                        <input type="password" name="password" class="form-control-custom" required>
                    </div>
                    <button type="submit" class="btn-secondary-custom" style="border:none; cursor:pointer; font-family:'Cairo',sans-serif;">تسجيل الدخول كمعلم</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- القسم الثاني: نموذج طلب الانضمام كمعلم جديد -->
        <div class="apply-box">
            <div class="box-title">طلب الانضمام كمعلم جديد</div>
            <div class="box-desc">
                املأ البيانات التالية لتقديم طلب الانضمام إلى هيئة التدريس، وسيقوم فريق الإدارة الأكاديمية بمراجعة طلبك وتفعيل صلاحياتك.
            </div>

            <?php if ($submitted): ?>
                <div class="alert alert-success">
                    تم إرسال طلبك بنجاح! ستتواصل معك إدارة المنصة قريباً عبر البريد الإلكتروني.
                </div>
            <?php else: ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger"><?php echo s($error_msg); ?></div>
                <?php endif; ?>

                <form method="post" action="">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <!-- حقل مخفي لاصطياد الروبوتات: يبقى فارغاً عند المستخدم الحقيقي -->
                    <div style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
                        <label>Website</label>
                        <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                    </div>
                    
                    <div class="form-group">
                        <label>الاسم الثلاثي واللقب *</label>
                        <input type="text" name="fullname" maxlength="150" class="form-control-custom" placeholder="مثال: د. أحمد محمد علي" required>
                    </div>

                    <div class="form-group">
                        <label>البريد الإلكتروني الرسمي أو الشخصي *</label>
                        <input type="email" name="email" class="form-control-custom" placeholder="name@example.com" required>
                    </div>

                    <div class="form-group">
                        <label>اللقب العلمي أو المؤهل الأكاديمي *</label>
                        <select name="degree" class="form-control-custom">
                            <option value="أستاذ (Professor)">أستاذ (Professor)</option>
                            <option value="أستاذ مساعد (Assistant Professor)">أستاذ مساعد (Assistant Professor)</option>
                            <option value="مدرس (Lecturer)">مدرس (Lecturer)</option>
                            <option value="مدرس مساعد (Assistant Lecturer)">مدرس مساعد (Assistant Lecturer)</option>
                            <option value="مدرب معتمد / خبير مهني">مدرب معتمد / خبير مهني</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>التخصص الدقيق *</label>
                        <input type="text" name="specialty" maxlength="200" class="form-control-custom" placeholder="مثال: علوم الحاسوب، إدارة الأعمال..." required>
                    </div>

                    <div class="form-group">
                        <label>نبذة مختصرة والمقررات المقترح تدريسها</label>
                        <textarea name="bio" rows="3" maxlength="2000" class="form-control-custom" placeholder="اكتب نبذة عن خبراتك والمواد التي ترغب بطرحها..."></textarea>
                    </div>

                    <button type="submit" name="submit_application" class="btn-primary-custom">إرسال طلب الانضمام</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();