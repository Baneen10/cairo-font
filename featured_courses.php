<?php
require_once(__DIR__ . '/config.php');

$PAGE->set_url(new moodle_url('/featured_courses.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('مجالات متجددة تواكب العصر');
$PAGE->set_heading('برامج ومناهج أكاديمية تحدث دورياً');

// ضع هنا أرقام (IDs) المقررات التي تريد عرضها يدوياً
$selected_course_ids = array(2, 5, 8, 12); 

$courses = [];
if (!empty($selected_course_ids)) {
    list($insql, $inparams) = $DB->get_in_or_equal($selected_course_ids);
    $courses = $DB->get_records_select('course', "id $insql AND visible = 1", $inparams, 'fullname ASC');
}

echo $OUTPUT->header();
?>

<style>
    .courses-container { 
        font-family: 'Cairo', sans-serif; 
        padding: 20px 0; 
    }
    .courses-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
        gap: 24px; 
        margin-top: 20px; 
    }
    .course-card { 
        background: #fff; 
        border: 1px solid #e5e7eb; 
        border-top: 4px solid #347C2C; 
        border-radius: 12px; 
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        display: flex; 
        flex-direction: column; 
        justify-content: space-between; 
        transition: transform 0.2s, box-shadow 0.2s; 
    }
    .course-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
    }
    .course-image-container {
        width: 100%;
        height: 160px;
        background-color: #f3f4f6;
        overflow: hidden;
    }
    .course-image-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .course-content {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        justify-content: space-between;
    }
    .course-title { 
        font-size: 1.2rem; 
        font-weight: 700; 
        color: #1f2937; 
        margin-bottom: 10px; 
    }
    .course-desc { 
        font-size: 0.9rem; 
        color: #4b5563; 
        line-height: 1.6; 
        margin-bottom: 20px; 
    }
    .course-btn { 
        display: inline-block; 
        background-color: #347C2C; 
        color: #fff !important; 
        padding: 8px 18px; 
        border-radius: 6px; 
        text-decoration: none; 
        font-size: 0.9rem; 
        font-weight: 600; 
        text-align: center; 
        transition: background-color 0.2s;
    }
    .course-btn:hover { 
        background-color: #556B2F; 
    }
</style>

<div class="courses-container">
    <?php if (empty($courses)): ?>
        <div class="alert alert-info">لا توجد مقررات محددة للعرض حالياً.</div>
    <?php else: ?>
        <div class="courses-grid">
            <?php foreach ($courses as $course): 
                $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
                $summary = format_text($course->summary, $course->summaryformat, array('context' => context_course::instance($course->id)));
                
                // جلب صورة المقرر (Course Overview Image) المعتمدة في Moodle
                $courseimage = '';
                $context = context_course::instance($course->id);
                $fs = get_file_storage();
                $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'timemodified DESC', false);
                foreach ($files as $file) {
                    if ($file->is_valid_image()) {
                        $courseimage = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false);
                        break;
                    }
                }
                // صورة افتراضية في حال لم يقم المشرف برفع صورة للمقرر
                if (empty($courseimage)) {
                    $courseimage = $CFG->wwwroot . '/theme/image.php?theme=boost&component=course&image=f/default-2nd'; // أو رابط صورة افتراضية عامة
                }
            ?>
                <div class="course-card">
                    <div>
                        <!-- عرض صورة المقرر -->
                        <div class="course-image-container">
                            <img src="<?php echo $courseimage; ?>" alt="<?php echo s($course->fullname); ?>">
                        </div>
                        
                        <div class="course-content">
                            <div>
                                <div class="course-title"><?php echo format_string($course->fullname); ?></div>
                                <div class="course-desc">
                                    <?php echo !empty($summary) ? strip_tags($summary) : 'منهج أكاديمي متجدد يغطي أحدث المهارات والمعارف التقنية المطلوبة في سوق العمل الحديث.'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 0 20px 20px 20px;">
                        <a href="<?php echo $courseurl; ?>" class="course-btn" style="display: block;">دخول المقرّر</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();