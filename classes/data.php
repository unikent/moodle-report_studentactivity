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

    /**
     * Constructor
     */
    public function __construct() {
        $this->cache = \cache::make('report_studentactivity', 'sareportdata');
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
        return $counts[$courseid];
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
        return $counts[$courseid];
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
        return $counts[$courseid];
    }

    /**
     * Returns total counts for a course
     */
    public function total_count($courseid) {
        return $this->qa_count($courseid) + $this->fp_count($courseid) + $this->ts_count($courseid);
    }
}