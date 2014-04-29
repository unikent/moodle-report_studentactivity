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
if ($sort !== 'total' && $sort !== 'quiz' && $sort !== 'forum' && $sort !== 'turnitin') {
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

$columns = array(
    "quiz" => "Quiz Attempts",
    "forum" => "Forum Posts",
    "turnitin" => "Turnitin Submissions",
    "total" => "Total Activity"
);
$columnicon = " <img src=\"" . $OUTPUT->pix_url('t/down') . "\" alt=\"Down Arrow\" />";

$table->head  = array("Course");
foreach ($columns as $name => $column) {
    if ($sort == $name) {
        $table->head[] = $column . $columnicon;
        continue;
    }

    $table->head[] = \html_writer::tag('a', $column, array(
        'href' => $baseurl->out(false, array('sort' => $name))
    ));
}

$table->colclasses = array('mdl-left course', 'mdl-left quiz', 'mdl-left forum', 'mdl-left turnitin', 'mdl-left total');
$table->attributes = array('class' => 'admintable studentactivityreport generaltable');
$table->id = 'studentactivityreporttable';
$table->data  = array();

$sql = <<<SQL
	SELECT c.id, c.shortname,
	       COALESCE(quiz.cnt, 0) as quizcnt,
	       COALESCE(forum.cnt, 0) as forumcnt,
	       COALESCE(turnitin.cnt, 0) as turnitincnt,
	       (COALESCE(quiz.cnt, 0) + COALESCE(forum.cnt, 0) + COALESCE(turnitin.cnt, 0)) as totalcnt

	FROM {course} c
	INNER JOIN {course_categories} cc ON cc.id=c.category

	# First, join in total number of quiz submissions
	LEFT OUTER JOIN (
		SELECT q.course, COUNT(q.id) cnt
		FROM {quiz_attempts} qa
		INNER JOIN {quiz} q ON q.id=qa.quiz
		GROUP BY q.course
	) quiz ON quiz.course=c.id

	# Then, join in total number of forum posts
	LEFT OUTER JOIN (
		SELECT f.course, COUNT(fp.id) cnt
		FROM {forum_posts} fp
		INNER JOIN {forum_discussions} fd ON fd.id=fp.discussion
		INNER JOIN {forum} f ON f.id=fd.forum
		GROUP BY f.course
	) forum ON forum.course=c.id

	# Finally, join in turnitintool submissions
	LEFT OUTER JOIN (
		SELECT t.course, COUNT(ts.id) cnt
		FROM {turnitintool_submissions} ts
		INNER JOIN {turnitintool} t ON t.id=ts.turnitintoolid
		GROUP BY t.course
	) turnitin ON turnitin.course=c.id
SQL;

$params = array();
if ($category !== 0) {
    $sql .= " WHERE cc.path LIKE :cata OR cc.path LIKE :catb";
    $params['cata'] = "%/" . $category . "/%";
    $params['catb'] = "%/" . $category;
}

$sql .= " ORDER BY {$sort}cnt DESC";

$rows = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
foreach ($rows as $row) {
    $course = new \html_table_cell(\html_writer::tag('a', $row->shortname, array(
        'href' => $CFG->wwwroot . '/course/view.php?id=' . $row->id,
        'target' => '_blank'
    )));

    $table->data[] = array($course, $row->quizcnt, $row->forumcnt, $row->turnitincnt, $row->totalcnt);
}

echo html_writer::table($table);

$totalsql = <<<SQL
	SELECT COUNT(c.id) as count
	FROM {course} c
	INNER JOIN {course_categories} cc ON cc.id=c.category
SQL;
if ($category !== 0) {
    $totalsql .= " WHERE cc.path LIKE :cata OR cc.path LIKE :catb";
}
$total = $DB->count_records_sql($totalsql, $params);

echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();