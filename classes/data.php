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
    /** Types of data we store */
    public static $types = array("quiz", "forum", "turnitin", "scorm", "wiki", "choice", "questionnaire", "total");

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

            $total = 0;
            foreach (static::$types as $type) {
                if ($type == "total") {
                    continue;
                }

                $ref = "{$type}_count";
                $row->$ref = $this->$ref($row->id);
                $total += $row->$ref;
            }

            $row->total_count = $total;

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
    public function quiz_counts() {
        $sql = <<<SQL
        SELECT q.course, COUNT(qa.id) cnt
        FROM {quiz_attempts} qa
        INNER JOIN {quiz} q ON q.id=qa.quiz
        GROUP BY q.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("quiz_counts", $sql);
    }

    /**
     * Returns quiz attempt counts for a course
     */
    public function quiz_count($courseid) {
        $counts = $this->quiz_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }

    /**
     * Returns forum post counts by course.
     */
    public function forum_counts() {
        $sql = <<<SQL
        SELECT f.course, COUNT(fp.id) cnt
        FROM {forum_posts} fp
        INNER JOIN {forum_discussions} fd ON fd.id=fp.discussion
        INNER JOIN {forum} f ON f.id=fd.forum
        GROUP BY f.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("forum_counts", $sql);
    }

    /**
     * Returns forum post counts for a course
     */
    public function forum_count($courseid) {
        $counts = $this->forum_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }

    /**
     * Returns turnitin submission counts by course.
     */
    public function turnitin_counts() {
        $sql = <<<SQL
        SELECT t.course, COUNT(ts.id) cnt
        FROM {turnitintool_submissions} ts
        INNER JOIN {turnitintool} t ON t.id=ts.turnitintoolid
        GROUP BY t.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("turnitin_counts", $sql);
    }

    /**
     * Returns turnitin submission counts for a course
     */
    public function turnitin_count($courseid) {
        $counts = $this->turnitin_counts();
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

    /**
     * Returns wiki edit counts by course.
     */
    public function wiki_counts() {
        $sql = <<<SQL
        SELECT o.course, COUNT(ov.id) as cnt
        FROM {ouwiki_versions} ov
        INNER JOIN {ouwiki_pages} op ON op.id=ov.pageid
        INNER JOIN {ouwiki_subwikis} os ON os.id=op.subwikiid
        INNER JOIN {ouwiki} o ON o.id=os.wikiid
        GROUP BY o.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("wiki_counts", $sql);
    }

    /**
     * Returns wiki edit counts for a course
     */
    public function wiki_count($courseid) {
        $counts = $this->wiki_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }

    /**
     * Returns choice edit counts by course.
     */
    public function choice_counts() {
        $sql = <<<SQL
        SELECT c.course, COUNT(ca.id) as cnt
        FROM {choice_answers} ca
        INNER JOIN {choice} c ON c.id=ca.choiceid
        GROUP BY c.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("choice_counts", $sql);
    }

    /**
     * Returns choice edit counts for a course
     */
    public function choice_count($courseid) {
        $counts = $this->choice_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }

    /**
     * Returns questionnaire response counts by course.
     */
    public function questionnaire_counts() {
        $sql = <<<SQL
        SELECT q.course, COUNT(qr.id) as cnt
        FROM {questionnaire_response} qr
        INNER JOIN {questionnaire} q ON q.sid=qr.survey_id
        GROUP BY q.course
        ORDER BY cnt DESC
SQL;

        return $this->grab_data("questionnaire_counts", $sql);
    }

    /**
     * Returns questionnaire response counts for a course
     */
    public function questionnaire_count($courseid) {
        $counts = $this->questionnaire_counts();
        return isset($counts[$courseid]) ? $counts[$courseid] : 0;
    }
}