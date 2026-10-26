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
namespace local_gradeitemdetail\form;

use context;
use context_course;
use core_form\dynamic_form;
use local_gradeitemdetail\api;
use moodle_url;

/**
 * Modal form to edit the detail text of one manual grade item.
 *
 * Core's own "Edit grade item" modal (core_grades\form\add_item) offers no
 * extension point, and it saves through a web service whose form data an
 * observer cannot reach. So the detail text gets a form of its own, opened
 * from an extra entry in the grade item's action menu.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detail extends dynamic_form {

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement(
            'editor',
            'detail_editor',
            get_string('detailfield', 'local_gradeitemdetail'),
            ['rows' => 12],
            api::editor_options($this->get_context_for_dynamic_submission())
        );
        $mform->setType('detail_editor', PARAM_RAW);
        $mform->addElement('static', 'detailhelp', '', get_string('detailfield_help', 'local_gradeitemdetail'));
    }

    /**
     * The grade item named in the form arguments, if it belongs to the course.
     *
     * @return \grade_item|null
     */
    private function get_gradeitem(): ?\grade_item {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch([
            'id'       => $this->optional_param('itemid', 0, PARAM_INT),
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
        ]);
        return $gradeitem ?: null;
    }

    /**
     * Course context of the grade item.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_course::instance($this->optional_param('courseid', 0, PARAM_INT));
    }

    /**
     * Same capability that guards core's grade item form, and manual items only.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/grade:manage', $this->get_context_for_dynamic_submission());

        if (!api::supports_item($this->get_gradeitem())) {
            throw new \moodle_exception('notmanualitem', 'local_gradeitemdetail');
        }
    }

    /**
     * Load the stored text, with its embedded files copied into a draft area.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $itemid = $this->optional_param('itemid', 0, PARAM_INT);
        $record = api::get_record($itemid);

        $data = (object) [
            'courseid'     => $this->optional_param('courseid', 0, PARAM_INT),
            'itemid'       => $itemid,
            'detail'       => $record->detail ?? '',
            'detailformat' => (int) ($record->detailformat ?? FORMAT_HTML),
        ];
        $data = file_prepare_standard_editor($data, 'detail', api::editor_options($context), $context,
            'local_gradeitemdetail', api::FILEAREA, $itemid);

        $this->set_data($data);
    }

    /**
     * Save the text and move its files from the draft area into the plugin's area.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $context = $this->get_context_for_dynamic_submission();
        $data = $this->get_data();
        $itemid = (int) $data->itemid;

        $data = file_postupdate_standard_editor($data, 'detail', api::editor_options($context), $context,
            'local_gradeitemdetail', api::FILEAREA, $itemid);
        api::save($itemid, (int) $data->courseid, (string) $data->detail, (int) $data->detailformat);

        return ['result' => true];
    }

    /**
     * Page the form belongs to when rendered or submitted via AJAX.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/grade/edit/tree/index.php', ['id' => $this->optional_param('courseid', 0, PARAM_INT)]);
    }
}
