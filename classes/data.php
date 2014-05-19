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
 * Data class for studentactivity report
 *
 * @package    report
 * @subpackage studentactivity
 * @copyright  2014 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_studentactivity;

defined('MOODLE_INTERNAL') || die();

class data
{
    /** Cache def */
    private $cache;

    /** Course category */
    private $category;

    /**
     * Constructor
     */
    public function __construct() {
        $this->cache = \cache::make('report_studentactivity', 'sareportdata');
    }

    /**
     * Sets the course category.
     */
    public function set_category($id) {
        $this->category = $id;
    }

    /**
     * Returns a list of courses, with count
     * data attached.
     */
    public function get_courses() {
        global $DB;

        $result = $this->cache->get("courses");
        if ($result !== false) {
            return $result;
        }

        $sql = "SELECT c.id, c.shortname FROM {course} c";

        $params = array();
        if (isset($this->category)) {
            $sql .= " INNER JOIN {course_categories} cc ON cc.id=c.category";
            $sql .= " WHERE cc.path LIKE :cata OR cc.path LIKE :catb";
            $params['cata'] = "%/" . $this->category . "/%";
            $params['catb'] = "%/" . $this->category;
        }

        $sql .= " ORDER BY c.shortname DESC";

        $rows = $DB->get_records_sql($sql, $params);

        // Build courses.
        $courses = array();
        foreach ($rows as $row) {
            if ($row->id === '1') {
                continue;
            }

            $row->qa_count = $this->qa_count($row->id);
            $row->fp_count = $this->fp_count($row->id);
            $row->ts_count = $this->ts_count($row->id);
            $row->scorm_count = $this->scorm_count($row->id);
            $row->total_count = $row->qa_count + $row->fp_count + $row->ts_count + $row->scorm_count;

            $courses[] = $row;
        }

        $this->cache->set("courses", $courses);

        return $courses;
    }

    /**
     * Returns quiz attempt counts by course.
     */
    private function grab_data($cachekey, $sql) {
        global $DB;

        $result = $this->cache->get($cachekey);

        if ($result === false) {
            $result = array();
            $data = $DB->get_records_sql($sql);
            foreach ($data as $row) {
                $result[(int)$row->course] = (int)$row->cnt;
            }
            $this->cache->set($cachekey, $result);
        }

        return $result;
    }

    /**
     * Returns quiz attempt counts by course.
     */
    public function qa_counts() {
        $sql = <<<SQL
        SELECT q.course, COUNT(qa.id) cnt
        FROM {quiz_attempts} qa
        INNER JOIN {quiz} q ON q.id=qa.quiz
        GROUP BY q.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("qa_counts", $sql);
    }

    /**
     * Returns quiz attempt counts for a course
     */
    public function qa_count($courseid) {
        $counts = $this->qa_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }

    /**
     * Returns forum post counts by course.
     */
    public function fp_counts() {
        $sql = <<<SQL
        SELECT f.course, COUNT(fp.id) cnt
        FROM {forum_posts} fp
        INNER JOIN {forum_discussions} fd ON fd.id=fp.discussion
        INNER JOIN {forum} f ON f.id=fd.forum
        GROUP BY f.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("fp_counts", $sql);
    }

    /**
     * Returns forum post counts for a course
     */
    public function fp_count($courseid) {
        $counts = $this->fp_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }

    /**
     * Returns turnitin submission counts by course.
     */
    public function ts_counts() {
        $sql = <<<SQL
        SELECT t.course, COUNT(ts.id) cnt
        FROM {turnitintool_submissions} ts
        INNER JOIN {turnitintool} t ON t.id=ts.turnitintoolid
        GROUP BY t.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("ts_counts", $sql);
    }

    /**
     * Returns turnitin submission counts for a course
     */
    public function ts_count($courseid) {
        $counts = $this->ts_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }

    /**
     * Returns scorm attempt counts by course.
     */
    public function scorm_counts() {
        $sql = <<<SQL
        SELECT s.course, SUM(sst.attempts) as cnt
        FROM {scorm} s
        INNER JOIN (
            SELECT userid,scormid,MAX(attempt) attempts
            FROM {scorm_scoes_track}
            GROUP BY userid,scormid
        ) sst ON sst.scormid = s.id
        GROUP BY s.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("scorm_counts", $sql);
    }

    /**
     * Returns scorm attempt counts for a course
     */
    public function scorm_count($courseid) {
        $counts = $this->scorm_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }
}