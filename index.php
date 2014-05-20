<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Student Activity report
 *
 * @package    report
 * @subpackage studentactivity
 * @copyright  2014 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('reportstudentactivity', '', null, '', array('pagelayout' => 'report'));

$page    = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);
$sort    = optional_param('sort', 'total', PARAM_ALPHA);
if (!in_array($sort, \report_studentactivity\data::$types)) {
    $sort = 'total';
}

$baseurl = new moodle_url('/report/studentactivity/index.php', array(
    'perpage' => $perpage,
    'category' => $category,
    'sort' => $sort
));

$PAGE->requires->js_init_call('M.report_studentactivity.init', array(), false, array(
    'name' => 'report_studentactivity',
    'fullpath' => '/report/studentactivity/module.js'
));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pluginname', 'report_studentactivity'));

// Allow restriction by category.
$select = array(
    0 => "All"
);
$categories = $DB->get_records('course_categories', null, 'name', 'id,name');
foreach ($categories as $obj) {
    $select[$obj->id] = $obj->name;
}
echo html_writer::select($select, 'category', $category);

// Setup the table.
$table = new html_table();

$columns = array();
foreach (\report_studentactivity\data::$types as $type) {
    $columns[$type] = get_string("type_{$type}", 'report_studentactivity');
}
$columnicon = " <img src=\"" . $OUTPUT->pix_url('t/down') . "\" alt=\"Down Arrow\" />";

$table->head  = array('Course');
$table->colclasses = array('mdl-left course');
foreach ($columns as $name => $column) {
    if ($sort == $name) {
        $table->head[] = $column . $columnicon;
        continue;
    }

    $table->head[] = \html_writer::tag('a', $column, array(
        'href' => $baseurl->out(false, array('sort' => $name))
    ));

    $table->colclasses[] = "mdl-left col_{$name}";
}

$table->attributes = array('class' => 'admintable studentactivityreport generaltable');
$table->id = 'studentactivityreporttable';
$table->data  = array();

$core = new \report_studentactivity\data();

if ($category !== 0) {
    $core->set_category($category);
}

$courses = $core->get_courses();

// Ordering.
usort($courses, function($a, $b) use ($sort) {
    $var = "{$sort}_count";
    return $a->$var < $b->$var;
});

$courses = array_slice($courses, $page * $perpage, $perpage);

foreach ($courses as $course) {
    $cell = new \html_table_cell(\html_writer::tag('a', $course->shortname, array(
        'href' => $CFG->wwwroot . '/course/view.php?id=' . $course->id,
        'target' => '_blank'
    )));

    $data = array($cell);
    foreach (\report_studentactivity\data::$types as $type) {
        $var = "{$type}_count";
        $data[] = $course->$var;
    }

    $table->data[] = $data;
}

echo html_writer::table($table);

$totalsql = <<<SQL
    SELECT COUNT(c.id) as count
    FROM {course} c
SQL;
$params = array();

if ($category !== 0) {
    $totalsql .= " INNER JOIN {course_categories} cc ON cc.id=c.category";
    $totalsql .= " WHERE cc.path LIKE :cata OR cc.path LIKE :catb";
    $params['cata'] = "%/" . $category . "/%";
    $params['catb'] = "%/" . $category;
}
$total = $DB->count_records_sql($totalsql, $params);

echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();