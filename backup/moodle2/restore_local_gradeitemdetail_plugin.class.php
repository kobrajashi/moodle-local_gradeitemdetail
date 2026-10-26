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
 * Restore support for local_gradeitemdetail.
 *
 * Counterpart to backup_local_gradeitemdetail_plugin: reads the detail text
 * back from each grade item in gradebook.xml and writes it against the grade
 * item core has just restored or matched.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores the detail text of a grade item.
 */
class restore_local_gradeitemdetail_plugin extends restore_local_plugin {

    /**
     * Declare the path we expect inside each grade item of the gradebook.
     *
     * @return restore_path_element[]
     */
    protected function define_grade_item_plugin_structure() {
        return [
            new restore_path_element(
                'local_gradeitemdetail_detail',
                $this->get_pathfor('/gradeitemdetail')
            ),
        ];
    }

    /**
     * Write the detail text for the grade item currently being restored.
     *
     * Core processes the parent grade item first, so its new id is already
     * mapped. That holds for a freshly inserted item as well as for a manual
     * item core matched to an existing one in the target course.
     *
     * @param array|object $data The backed-up detail record.
     */
    public function process_local_gradeitemdetail_detail($data) {
        $data = (object) $data;

        $gradeitemid = (int) $this->get_new_parentid('grade_item');
        $courseid = (int) $this->task->get_courseid();
        if ($gradeitemid <= 0 || $courseid <= 0) {
            return;
        }

        $format = isset($data->detailformat) ? (int) $data->detailformat : FORMAT_HTML;
        \local_gradeitemdetail\api::save($gradeitemid, $courseid, (string) ($data->detail ?? ''), $format);
    }

    /**
     * Restore the embedded files once every grade item of the gradebook is mapped.
     *
     * @return void
     */
    protected function after_execute_grade_item() {
        $this->add_related_files('local_gradeitemdetail', 'detail', 'grade_item');
    }
}
