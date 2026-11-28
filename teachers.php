<?php
require_once(__DIR__ . '/config.php');

// إعدادات الصفحة الأساسية لكي ترث هيدر وفوتر وقالب المنصة تلقائياً
$PAGE->set_url(new moodle_url('/teachers.php'));

// الصفحة متاحة للمستخدمين المسجلين ولحساب الضيف (للتعرّف على المنصة فقط).
// الزائر غير المسجّل يُدخَل تلقائياً كضيف إذا كان دخول الضيف مفعّلاً في إعدادات الموقع.
// الضيف لا يرى روابط الملفات الشخصية ولا يستطيع البحث عن الطلاب.
require_login(null, true);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('الهيئة التدريسية');
$PAGE->set_heading('نخبة التدريسيين في المنصة');

// استعلام لجلب المستخدمين الذين لديهم دور (editingteacher) في سياق المقررات فقط
// CONTEXT_COURSE = 50
$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename, u.email, u.description, u.descriptionformat,
               u.picture, u.imagealt, u.department, u.institution
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        WHERE ctx.contextlevel = 50
          AND r.shortname = 'editingteacher'
          AND u.deleted = 0
          AND u.suspended = 0
        ORDER BY u.firstname ASC, u.lastname ASC";

$teachers = $DB->get_records_sql($sql);

// جلب المقررات التي يدرّسها كل أستاذ فعلياً (دور editingteacher داخل المقرر نفسه)،
// والظاهرة للطلاب فقط، باستعلام واحد لكل التدريسيين بدل استعلام لكل أستاذ.
$taught_courses = [];
if (!empty($teachers)) {
    list($usersql, $userparams) = $DB->get_in_or_equal(array_keys($teachers), SQL_PARAMS_NAMED);
    $userparams['courselevel'] = CONTEXT_COURSE;
    $userparams['siteid'] = SITEID;
    $rs = $DB->get_recordset_sql("
        SELECT DISTINCT ra.userid, c.id AS courseid, c.fullname
          FROM {role_assignments} ra
          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'editingteacher'
          JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :courselevel
          JOIN {course} c ON c.id = ctx.instanceid
         WHERE ra.userid $usersql
           AND c.visible = 1
           AND c.id <> :siteid
      ORDER BY c.fullname ASC", $userparams);
    foreach ($rs as $row) {
        $taught_courses[$row->userid][$row->courseid] = $row;
    }
    $rs->close();
}

// رابط الملف الشخصي يظهر فقط للمستخدمين المسجلين، لأن Moodle يحوّل الزوار إلى صفحة الدخول.
$show_profile_links = isloggedin() && !isguestuser();

echo $OUTPUT->header();
?>

<style>
    .teachers-container {
        font-family: 'Cairo', sans-serif;
        padding: 20px 0;
    }
    .teachers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
        margin-top: 20px;
    }
    .teacher-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-top: 4px solid #347C2C;
        border-radius: 12px;
        padding: 24px 20px;
        text-align: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .teacher-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .teacher-avatar img {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #556B2F;
        margin: 0 auto 15px;
    }
    .teacher-name {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 5px;
    }
    .teacher-meta {
        font-size: 0.85rem;
        color: #556B2F;
        font-weight: 600;
        margin-bottom: 12px;
    }
    .teacher-bio {
        font-size: 0.9rem;
        color: #4b5563;
        line-height: 1.6;
        margin-bottom: 15px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .teacher-courses {
        background: #f9fafb;
        border-radius: 8px;
        padding: 10px;
        font-size: 0.82rem;
        color: #374151;
        margin-bottom: 15px;
        text-align: right;
    }
    .teacher-btn {
        display: inline-block;
        background-color: #347C2C;
        color: #ffffff !important;
        padding: 8px 18px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        transition: background-color 0.2s;
    }
    .teacher-btn:hover {
        background-color: #556B2F;
    }
</style>

<div class="teachers-container">
    <?php if (empty($teachers)): ?>
        <div class="alert alert-info">لا يوجد تدريسيون مسجلون في المقررات حالياً.</div>
    <?php else: ?>
        <div class="teachers-grid">
            <?php foreach ($teachers as $teacher): 
                // جلب صورة المستخدم الرسمية من Moodle
                $userpicture = new user_picture($teacher);
                $userpicture->size = 100;
                $picurl = $userpicture->get_url($PAGE)->out(false);
                
                // المقررات التي يدرّسها هذا الأستاذ (محسوبة مسبقاً أعلى الصفحة)
                $courses = $taught_courses[$teacher->id] ?? [];
                $profileurl = new moodle_url('/user/profile.php', array('id' => $teacher->id));
                $fullname = fullname($teacher);
                $bio = format_text($teacher->description, $teacher->descriptionformat);
            ?>
                <div class="teacher-card">
                    <div>
                        <div class="teacher-avatar">
                            <img src="<?php echo $picurl; ?>" alt="<?php echo s($fullname); ?>">
                        </div>
                        <div class="teacher-name"><?php echo s($fullname); ?></div>
                        
                        <?php if (!empty($teacher->department) || !empty($teacher->institution)): ?>
                            <div class="teacher-meta">
                                <?php echo s(implode(' - ', array_filter([$teacher->department, $teacher->institution]))); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($bio)): ?>
                            <div class="teacher-bio"><?php echo strip_tags($bio); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($courses)): ?>
                            <div class="teacher-courses">
                                <strong>المقررات الدراسية:</strong>
                                <ul style="margin: 5px 0 0; padding-right: 18px;">
                                    <?php foreach ($courses as $course): ?>
                                        <li><?php echo format_string($course->fullname); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($show_profile_links): ?>
                    <div>
                        <a href="<?php echo $profileurl; ?>" class="teacher-btn">عرض الملف الشخصي</a>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();