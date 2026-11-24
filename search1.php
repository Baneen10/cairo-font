<?php
require_once(__DIR__ . '/../config.php');

// إعدادات الصفحة وربطها بهوية وتصميم قالب المنصة (Academi)
$PAGE->set_url(new moodle_url('/custom_search/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('البحث المتقدم - منصة أثر الأسباط');
$PAGE->set_heading('البحث عن مقرر دراسي أو أستاذ');

// استقبال مدخلات البحث والفلتر بأمان
$query = optional_param('q', '', PARAM_TEXT);
$filter = optional_param('filter', 'all', PARAM_ALPHA); // القيم: all, course, teacher

echo $OUTPUT->header();
?>

<style>
    .search-container {
        max-width: 900px;
        margin: 20px auto;
        direction: rtl;
        text-align: right;
    }
    .search-box-card {
        background: #ffffff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
    }
    .search-form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .search-input {
        flex: 1 1 300px;
        padding: 12px 15px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 16px;
    }
    .search-select {
        flex: 0 1 180px;
        padding: 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 15px;
        background-color: #f8fafc;
    }
    .search-btn {
        padding: 12px 28px;
        background-color: #1e3a5f;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
    }
    .search-btn:hover {
        background-color: #2c5282;
    }
    .result-section-title {
        font-size: 19px;
        font-weight: bold;
        color: #1e3a5f;
        margin: 20px 0 12px;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 6px;
    }
    .result-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: transform 0.15s ease;
    }
    .result-card:hover {
        border-color: #94a3b8;
        transform: translateY(-2px);
    }
    .result-title {
        font-size: 17px;
        font-weight: bold;
        color: #0f172a;
        margin: 0 0 5px;
    }
    .result-meta {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }
    .result-link {
        padding: 8px 16px;
        background-color: #f1f5f9;
        color: #1e3a5f;
        border-radius: 6px;
        text-decoration: none;
        font-weight: bold;
        font-size: 14px;
    }
    .result-link:hover {
        background-color: #1e3a5f;
        color: #ffffff;
    }
</style>

<div class="search-container">
    <div class="search-box-card">
        <form method="get" action="" class="search-form-row">
            <input type="text" name="q" class="search-input" 
                   placeholder="اكتب اسم المقرر الدراسي أو اسم الأستاذ..." 
                   value="<?php echo s($query); ?>" required>
            
            <select name="filter" class="search-select">
                <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>الكل (مقررات وأساتذة)</option>
                <option value="course" <?php echo ($filter === 'course') ? 'selected' : ''; ?>>المقررات الدراسية فقط</option>
                <option value="teacher" <?php echo ($filter === 'teacher') ? 'selected' : ''; ?>>الأساتذة فقط</option>
            </select>
            
            <button type="submit" class="search-btn">بحث</button>
        </form>
    </div>

    <?php if (!empty(trim($query))): ?>
        <?php
        $searchparam = '%' . $DB->sql_like_escape(trim($query)) . '%';

        // 1. البحث في المقررات الدراسية
        if ($filter === 'all' || $filter === 'course') {
            $sql_courses = "SELECT c.id, c.fullname, c.shortname, cc.name AS categoryname
                            FROM {course} c
                            LEFT JOIN {course_categories} cc ON c.category = cc.id
                            WHERE c.id != :siteid 
                              AND c.visible = 1
                              AND (" . $DB->sql_like('c.fullname', ':q1', false) . " 
                                   OR " . $DB->sql_like('c.shortname', ':q2', false) . ")
                            ORDER BY c.fullname ASC";
            
            $courses = $DB->get_records_sql($sql_courses, [
                'siteid' => SITEID,
                'q1' => $searchparam,
                'q2' => $searchparam
            ]);

            echo '<div class="result-section-title">نتائج المقررات الدراسية (' . count($courses) . ')</div>';
            if ($courses) {
                foreach ($courses as $course) {
                    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                    echo '<div class="result-card">';
                    echo ' <div>';
                    echo ' <div class="result-title">' . format_string($course->fullname) . '</div>';
                    echo ' <div class="result-meta">التصنيف: ' . format_string($course->categoryname) . '</div>';
                    echo ' </div>';
                    echo ' <a href="' . $courseurl . '" class="result-link">عرض المقرر</a>';
                    echo '</div>';
                }
            } else {
                echo '<p class="text-muted">لا توجد مقررات دراسية مطابقة لعبارة البحث.</p>';
            }
        }

        // 2. البحث في الأساتذة (من لديهم دور editingteacher أو teacher في أي مقرر)
        if ($filter === 'all' || $filter === 'teacher') {
            $fullname_sql = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
            $sql_teachers = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                             FROM {user} u
                             JOIN {role_assignments} ra ON ra.userid = u.id
                             JOIN {role} r ON r.id = ra.roleid
                             JOIN {context} ctx ON ctx.id = ra.contextid
                             WHERE u.deleted = 0 
                               AND u.suspended = 0
                               AND ctx.contextlevel = :courselevel
                               AND r.shortname IN ('editingteacher', 'teacher')
                               AND " . $DB->sql_like($fullname_sql, ':q_teacher', false) . "
                             ORDER BY u.firstname ASC";

            $teachers = $DB->get_records_sql($sql_teachers, [
                'courselevel' => CONTEXT_COURSE,
                'q_teacher' => $searchparam
            ]);

            echo '<div class="result-section-title">نتائج الأساتذة (' . count($teachers) . ')</div>';
            if ($teachers) {
                foreach ($teachers as $teacher) {
                    $profileurl = new moodle_url('/user/profile.php', ['id' => $teacher->id]);
                    // جلب المقررات التي يدرّسها هذا الأستاذ
                    $taught_courses = $DB->get_records_sql("
                        SELECT DISTINCT c.fullname 
                        FROM {course} c
                        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :lvl
                        JOIN {role_assignments} ra ON ra.contextid = ctx.id
                        JOIN {role} r ON r.id = ra.roleid
                        WHERE ra.userid = :uid AND r.shortname IN ('editingteacher', 'teacher') AND c.visible = 1
                    ", ['lvl' => CONTEXT_COURSE, 'uid' => $teacher->id]);

                    $course_names = array_map(fn($c) => format_string($c->fullname), $taught_courses);
                    $courses_list_text = !empty($course_names) ? implode(' ، ', $course_names) : 'لا توجد مقررات ظاهرة حالياً';

                    echo '<div class="result-card">';
                    echo ' <div>';
                    echo ' <div class="result-title">' . fullname($teacher) . '</div>';
                    echo ' <div class="result-meta">المقررات: ' . $courses_list_text . '</div>';
                    echo ' </div>';
                    echo ' <a href="' . $profileurl . '" class="result-link">ملف الأستاذ</a>';
                    echo '</div>';
                }
            } else {
                echo '<p class="text-muted">لا يوجد أساتذة مطابقون لعبارة البحث.</p>';
            }
        }
        ?>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
