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
 * Backup support for local_gradeitemdetail.
 *
 * The detail text belongs to one grade item, so it is attached to that item's
 * element in gradebook.xml. It travels with the gradebook in course backups,
 * restores and copies, and is skipped in exactly the cases core skips the
 * gradebook itself.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the detail text of a grade item to the gradebook backup.
 */
class backup_local_gradeitemdetail_plugin extends backup_local_plugin {
    /**
     * Attach the detail text to every backed-up grade item.
     *
     * @return backup_plugin_element
     */
    protected function define_grade_item_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        // The user who last changed the text is deliberately left out: it is
        // not needed to restore the text, and it would tie the backup to users.
        $detail = new backup_nested_element('gradeitemdetail', ['id'], [
            'gradeitemid', 'detail', 'detailformat', 'timecreated', 'timemodified',
        ]);
        $wrapper->add_child($detail);

        $detail->set_source_table('local_gradeitemdetail', [
            'gradeitemid' => backup::VAR_PARENTID,
        ]);

        // Embedded images and files, keyed by the grade item id like in the
        // live file area.
        $detail->annotate_files('local_gradeitemdetail', 'detail', 'gradeitemid');

        return $plugin;
    }
}
