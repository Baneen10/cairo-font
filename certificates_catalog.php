<?php
require_once(__DIR__ . '/config.php');

$PAGE->set_url(new moodle_url('/certificates_catalog.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('الشهادات المتاحة في المنصة');
$PAGE->set_heading('شهادات ترفع قيمتك المهنية والأكاديمية');

// جلب الشهادات المضافة داخل المقررات تلقائياً
// يدعم إضافات الشهادات الشهيرة: customcert, certificate, simplecertificate
$certificates = [];
$dbman = $DB->get_manager();

$cert_tables = [
    'customcert' => 'customcert',
    'certificate' => 'certificate',
    'simplecertificate' => 'simplecertificate'
];

foreach ($cert_tables as $modname => $table) {
    if ($dbman->table_exists($table)) {
        $sql = "SELECT cert.id, cert.name AS certname, cert.intro, cert.introformat,
                       c.id AS courseid, c.fullname AS coursename, cm.id AS cmid
                FROM {{$table}} cert
                JOIN {course} c ON c.id = cert.course
                JOIN {course_modules} cm ON cm.instance = cert.id
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                WHERE c.visible = 1 AND cm.visible = 1
                ORDER BY c.fullname ASC";
        
        $records = $DB->get_records_sql($sql, ['modname' => $modname]);
        foreach ($records as $rec) {
            $rec->modname = $modname;
            $certificates[] = $rec;
        }
    }
}

echo $OUTPUT->header();
?>

<style>
    .cert-container { font-family: 'Cairo', sans-serif; padding: 20px 0; }
    .cert-intro {
        background: #f9fafb;
        border-right: 4px solid #347C2C;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        color: #4b5563;
        font-size: 1rem;
        line-height: 1.6;
    }
    .cert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
    .cert-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-top: 4px solid #347C2C;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .cert-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .cert-badge {
        display: inline-block;
        background-color: #e8f5e9;
        color: #347C2C;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 12px;
    }
    .cert-title { font-size: 1.2rem; font-weight: 700; color: #1f2937; margin-bottom: 8px; }
    .cert-course { font-size: 0.9rem; font-weight: 600; color: #556B2F; margin-bottom: 12px; }
    .cert-desc { font-size: 0.9rem; color: #4b5563; line-height: 1.6; margin-bottom: 20px; }
    .cert-btn {
        display: block;
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
    .cert-btn:hover { background-color: #556B2F; }
</style>

<div class="cert-container">
    <div class="cert-intro">
        شهادات موثقة تعكس حجم التحصيل العلمي والمهاري، وتضيف قيمة حقيقية لسيرتك الذاتية أمام جهات التوظيف. اشترك في المقررات أدناه وأتمّ متطلباتها للحصول على شهادتك المعتمدة تلقائياً.
    </div>

    <?php if (empty($certificates)): ?>
        <div class="alert alert-info">لا توجد شهادات مفعلة داخل المقررات حالياً.</div>
    <?php else: ?>
        <div class="cert-grid">
            <?php foreach ($certificates as $cert): 
                $courseurl = new moodle_url('/course/view.php', ['id' => $cert->courseid]);
                $intro = format_text($cert->intro, $cert->introformat);
            ?>
                <div class="cert-card">
                    <div>
                        <span class="cert-badge">شهادة معتمدة</span>
                        <div class="cert-title"><?php echo format_string($cert->certname); ?></div>
                        <div class="cert-course">المقرر: <?php echo format_string($cert->coursename); ?></div>
                        <div class="cert-desc">
                            <?php echo !empty(strip_tags($intro)) ? strip_tags($intro) : 'تُمنح هذه الشهادة للطالب تلقائياً عند إتمام كافة متطلبات واجتياز اختبارات هذا المقرر بنجاح.'; ?>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo $courseurl; ?>" class="cert-btn">الذهاب إلى المقرر</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();