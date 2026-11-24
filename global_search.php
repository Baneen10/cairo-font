<?php
require_once(__DIR__ . '/config.php');

$query = optional_param('q', '', PARAM_TEXT);
$filter = optional_param('filter', 'all', PARAM_ALPHA); // all, courses, teachers, students

$PAGE->set_url(new moodle_url('/global_search.php', ['q' => $query, 'filter' => $filter]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('نتائج البحث في المنصة');
$PAGE->set_heading('البحث الشامل في المنصة');

/**
 * 1. توحيد الحروف العربية وتنظيف النص
 */
function normalize_search_text($text) {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text); // إزالة التشكيل
    $text = preg_replace('/[أإآ]/u', 'ا', $text); // توحيد الألف
    $text = str_replace('ة', 'ه', $text); // توحيد التاء المربوطة
    $text = str_replace('ى', 'ي', $text); // توحيد الياء
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text); // إزالة الرموز
    return preg_replace('/\s+/u', ' ', trim($text));
}

/**
 * 2. قاموس المرادفات ثنائي اللغة (عربي <-> إنجليزي)
 * يمكنك إضافة أي اختصارات تقنية هنا مستقبلاً في سطر واحد
 */
function get_search_variations($term) {
    $norm = normalize_search_text($term);
    $variations = [$norm];

    $synonyms = [
        ['اكسل', 'excel', 'xls', 'جداول'],
        ['وورد', 'word', 'مستندات'],
        ['باوربوينت', 'powerpoint', 'عروض'],
        ['ذكاء اصطناعي', 'الذكاء الاصطناعي', 'ai', 'artificial intelligence', 'chatgpt'],
        ['انترنت الاشياء', 'iot', 'internet of things'],
        ['برمجه', 'programming', 'coding', 'كود'],
        ['تصميم', 'design', 'photoshop', 'فوتوشوب', 'جرافيك'],
        ['انجليزي', 'انكليزي', 'english', 'لغه انجليزيه'],
        ['حاسوب', 'كمبيوتر', 'computer', 'icdl'],
        ['شبكات', 'networking', 'cisco', 'سيسكو'],
        ['امن سيبراني', 'cyber security', 'cybersecurity', 'حمايه']
    ];

    foreach ($synonyms as $group) {
        $normalized_group = array_map('normalize_search_text', $group);
        foreach ($normalized_group as $word) {
            if ($norm === $word || (mb_strlen($norm) >= 3 && mb_strpos($word, $norm) !== false)) {
                $variations = array_merge($variations, $normalized_group);
                break;
            }
        }
    }

    return array_unique($variations);
}

/**
 * 3. محرك المطابقة الذكية التلقائية (يكتشف الأخطاء الإملائية لأي اسم تدريسي أو مقرر تلقائياً)
 */
function smart_match($haystack, $search_variations) {
    $clean_haystack = normalize_search_text($haystack);
    $haystack_words = explode(' ', $clean_haystack);

    foreach ($search_variations as $variant) {
        if (empty($variant)) continue;

        // أ. تطابق مباشر أو جزئي (مثل "كسل" داخل "اكسل" أو "iot")
        if (mb_strpos($clean_haystack, $variant) !== false) {
            return true;
        }

        // ب. تطابق تقريبي للأخطاء الإملائية في الأسماء والمقررات (Fuzzy Matching)
        $variant_words = explode(' ', $variant);
        foreach ($variant_words as $v_word) {
            $v_len = mb_strlen($v_word, 'UTF-8');
            if ($v_len < 3) continue; // تجاهل الكلمات القصيرة جداً

            foreach ($haystack_words as $h_word) {
                $h_len = mb_strlen($h_word, 'UTF-8');
                if (abs($v_len - $h_len) > 2) continue;

                // حساب نسبة التشابه بين الكلمتين
                similar_text($v_word, $h_word, $percent);
                // إذا كانت نسبة التشابه 75% فأكثر (مثلاً خطأ بحرف أو حرفين في اسم أستاذ أو مقرر)
                if ($percent >= 75) {
                    return true;
                }
            }
        }
    }
    return false;
}

$courses = [];
$teachers = [];
$students = [];

$search_term = trim($query);

if (!empty($search_term)) {
    $search_variations = get_search_variations($search_term);

    // 1. البحث في المقررات الدراسية
    if ($filter === 'all' || $filter === 'courses') {
        $all_courses = $DB->get_records_select('course', 'id != 1 AND visible = 1', null, 'fullname ASC');
        foreach ($all_courses as $course) {
            $text_to_search = $course->fullname . ' ' . $course->shortname . ' ' . strip_tags($course->summary);
            if (smart_match($text_to_search, $search_variations)) {
                $courses[$course->id] = $course;
            }
        }
    }

    // 2. البحث في التدريسيين
    if ($filter === 'all' || $filter === 'teachers') {
        $sql_teachers = "SELECT DISTINCT u.* 
                         FROM {user} u
                         JOIN {role_assignments} ra ON ra.userid = u.id
                         JOIN {role} r ON r.id = ra.roleid
                         WHERE u.deleted = 0 AND u.suspended = 0
                         AND r.shortname IN ('editingteacher', 'teacher')
                         ORDER BY u.firstname ASC";
        $all_teachers = $DB->get_records_sql($sql_teachers);
        foreach ($all_teachers as $teacher) {
            $text_to_search = fullname($teacher) . ' ' . $teacher->username . ' ' . strip_tags($teacher->description);
            if (smart_match($text_to_search, $search_variations)) {
                $teachers[$teacher->id] = $teacher;
            }
        }
    }

    // 3. البحث في الطلاب
    if ($filter === 'all' || $filter === 'students') {
        $sql_students = "SELECT DISTINCT u.* 
                         FROM {user} u
                         JOIN {role_assignments} ra ON ra.userid = u.id
                         JOIN {role} r ON r.id = ra.roleid
                         WHERE u.deleted = 0 AND u.suspended = 0
                         AND r.shortname = 'student'
                         ORDER BY u.firstname ASC";
        $all_students = $DB->get_records_sql($sql_students);
        foreach ($all_students as $student) {
            $text_to_search = fullname($student) . ' ' . $student->username;
            if (smart_match($text_to_search, $search_variations)) {
                $students[$student->id] = $student;
            }
        }
    }
}

echo $OUTPUT->header();
?>

<style>
    .gsearch-container { font-family: 'Cairo', sans-serif; padding: 15px 0; }
    .gsearch-box {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-top: 4px solid #347C2C;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }
    .gsearch-form-page {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .gsearch-form-page input {
        flex: 2;
        min-width: 220px;
        padding: 10px 15px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-family: 'Cairo', sans-serif;
    }
    .gsearch-form-page select {
        flex: 1;
        min-width: 150px;
        padding: 10px 15px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-family: 'Cairo', sans-serif;
        background-color: #fff;
    }
    .gsearch-form-page button {
        background-color: #347C2C;
        color: #fff;
        border: none;
        padding: 10px 25px;
        border-radius: 8px;
        font-family: 'Cairo', sans-serif;
        font-weight: 700;
        cursor: pointer;
    }
    .gsearch-form-page button:hover { background-color: #556B2F; }
    .section-heading {
        font-size: 1.25rem;
        font-weight: 700;
        color: #347C2C;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 8px;
        margin: 25px 0 15px 0;
    }
    .results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
    .res-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-top: 3px solid #556B2F;
        border-radius: 10px;
        padding: 18px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .res-title { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin-bottom: 8px; }
    .res-meta { font-size: 0.85rem; color: #6b7280; margin-bottom: 12px; line-height: 1.5; }
    .res-btn {
        display: inline-block;
        background-color: #347C2C;
        color: #fff !important;
        padding: 6px 14px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        text-align: center;
    }
    .res-btn:hover { background-color: #556B2F; }
</style>

<div class="gsearch-container">
    <div class="gsearch-box">
        <form action="" method="get" class="gsearch-form-page">
            <input type="text" name="q" value="<?php echo s($query); ?>" placeholder="اكتب كلمة البحث (مثال: اكسل، IoT، اسم تدريسي...)" required>
            <select name="filter">
                <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>الكل (مقررات، أساتذة، طلاب)</option>
                <option value="courses" <?php echo ($filter === 'courses') ? 'selected' : ''; ?>>المقررات الدراسية فقط</option>
                <option value="teachers" <?php echo ($filter === 'teachers') ? 'selected' : ''; ?>>التدريسيين فقط</option>
                <option value="students" <?php echo ($filter === 'students') ? 'selected' : ''; ?>>الطلاب فقط</option>
            </select>
            <button type="submit">بحث</button>
        </form>
    </div>

    <?php if (!empty($search_term)): ?>
        <?php if (empty($courses) && empty($teachers) && empty($students)): ?>
            <div class="alert alert-warning">لم يتم العثور على نتائج مطابقة لعبارة البحث "<strong><?php echo s($search_term); ?></strong>".</div>
        <?php endif; ?>

        <?php if (!empty($courses)): ?>
            <div class="section-heading">المقررات الدراسية (<?php echo count($courses); ?>)</div>
            <div class="results-grid">
                <?php foreach ($courses as $course): 
                    $curl = new moodle_url('/course/view.php', ['id' => $course->id]);
                ?>
                    <div class="res-card">
                        <div>
                            <div class="res-title"><?php echo format_string($course->fullname); ?></div>
                            <div class="res-meta"><?php echo shorten_text(strip_tags($course->summary), 100); ?></div>
                        </div>
                        <a href="<?php echo $curl; ?>" class="res-btn">دخول المقرر</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($teachers)): ?>
            <div class="section-heading">الكادر التدريسي (<?php echo count($teachers); ?>)</div>
            <div class="results-grid">
                <?php foreach ($teachers as $teacher): 
                    $purl = new moodle_url('/user/profile.php', ['id' => $teacher->id]);
                ?>
                    <div class="res-card">
                        <div>
                            <div class="res-title"><?php echo fullname($teacher); ?></div>
                            <div class="res-meta"><?php echo !empty($teacher->description) ? shorten_text(strip_tags($teacher->description), 90) : 'عضو هيئة تدريس في المنصة'; ?></div>
                        </div>
                        <a href="<?php echo $purl; ?>" class="res-btn">الملف الشخصي</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($students)): ?>
            <div class="section-heading">الطلاب (<?php echo count($students); ?>)</div>
            <div class="results-grid">
                <?php foreach ($students as $student): 
                    $surl = new moodle_url('/user/profile.php', ['id' => $student->id]);
                ?>
                    <div class="res-card">
                        <div>
                            <div class="res-title"><?php echo fullname($student); ?></div>
                            <div class="res-meta">طالب مسجل في المنصة</div>
                        </div>
                        <a href="<?php echo $surl; ?>" class="res-btn">عرض الملف</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();