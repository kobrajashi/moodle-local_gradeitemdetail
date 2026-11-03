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

namespace local_gradeitemdetail;

use context_course;
use core\event\grade_item_created;
use core\event\grade_item_deleted;
use core\event\grade_item_updated;

/**
 * Event observers that persist and clean up detail texts.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A grade item was just created - store the submitted detail text, if any.
     *
     * @param grade_item_created $event
     * @return void
     */
    public static function grade_item_created(grade_item_created $event): void {
        self::persist((int)$event->objectid, (int)$event->courseid, true);
    }

    /**
     * A grade item was just updated - store the submitted detail text, if any.
     *
     * @param grade_item_updated $event
     * @return void
     */
    public static function grade_item_updated(grade_item_updated $event): void {
        self::persist((int)$event->objectid, (int)$event->courseid, false);
    }

    /**
     * A grade item was deleted - drop its detail text and embedded files.
     *
     * @param grade_item_deleted $event
     * @return void
     */
    public static function grade_item_deleted(grade_item_deleted $event): void {
        $context = context_course::instance((int)$event->courseid, IGNORE_MISSING) ?: null;
        api::delete((int)$event->objectid, $context);
    }

    /**
     * Save the detail text that was posted along with the core grade item form.
     *
     * Does nothing unless the injected editor was actually part of the request,
     * so every other code path that saves grade items (imports, bulk actions,
     * activity updates) is unaffected.
     *
     * @param int  $gradeitemid
     * @param int  $courseid
     * @param bool $created true for grade_item_created, false for grade_item_updated
     * @return void
     */
    private static function persist(int $gradeitemid, int $courseid, bool $created): void {
        global $CFG;

        $submitted = optional_param_array(api::ELEMENT, null, PARAM_RAW);
        if (!is_array($submitted) || !array_key_exists('text', $submitted)) {
            return;
        }
        if ($gradeitemid <= 0 || $courseid <= 0) {
            return;
        }

        // Only the item the form was about: item.php posts its id, 0 when it
        // creates a new one. Any other item saved in the same request keeps
        // its own text.
        $formitemid = optional_param('id', 0, PARAM_INT);
        if ($created ? $formitemid !== 0 : $formitemid !== $gradeitemid) {
            return;
        }

        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !has_capability('moodle/grade:manage', $context)) {
            return;
        }

        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch(['id' => $gradeitemid, 'courseid' => $courseid]);
        if (!api::supports_item($gradeitem ?: null)) {
            return;
        }

        $text        = (string)$submitted['text'];
        $format      = isset($submitted['format']) ? (int)$submitted['format'] : FORMAT_HTML;
        $draftitemid = isset($submitted['itemid']) ? (int)$submitted['itemid'] : 0;

        $options = api::editor_options($context);
        if ($draftitemid > 0) {
            $text = file_save_draft_area_files(
                $draftitemid,
                $context->id,
                'local_gradeitemdetail',
                api::FILEAREA,
                $gradeitemid,
                $options,
                $text
            );
        }

        api::save($gradeitemid, $courseid, $text, $format);
    }
}
