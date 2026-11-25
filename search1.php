<?php
require_once(__DIR__ . '/config.php');

// هذه الصفحة القديمة للبحث أصبحت مدمجة في صفحة البحث الشامل (global_search.php).
// نحوّل الزائر إليها تلقائياً مع الحفاظ على كلمة البحث والفلتر، حتى تبقى الروابط القديمة تعمل.
$query = optional_param('q', '', PARAM_TEXT);
$filter = optional_param('filter', 'all', PARAM_ALPHA); // القيم القديمة: all, course, teacher

$filtermap = ['all' => 'all', 'course' => 'courses', 'teacher' => 'teachers'];
$params = ['filter' => $filtermap[$filter] ?? 'all'];
if ($query !== '') {
    $params['q'] = $query;
}

redirect(new moodle_url('/global_search.php', $params));
