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
 * Library functions for local_gradeitemdetail.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve files embedded in a grade item detail text.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context  $context
 * @param string   $filearea
 * @param array    $args first element is the grade item id
 * @param bool     $forcedownload
 * @param array    $options
 * @return bool false if the file could not be served
 */
function local_gradeitemdetail_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload,
        array $options = []) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }
    if ($filearea !== \local_gradeitemdetail\api::FILEAREA) {
        return false;
    }

    require_login($course, false, $cm);

    require_once($CFG->libdir . '/gradelib.php');

    // Same gate as the participant overview, which is the only place these
    // files are shown: the editor serves its own copies from the draft area.
    if (!get_config('local_gradeitemdetail', 'showinuserreport')) {
        return false;
    }
    if (!has_any_capability(['moodle/grade:view', 'moodle/grade:viewall'], $context)) {
        return false;
    }

    $gradeitemid = (int)array_shift($args);
    $gradeitem = grade_item::fetch(['id' => $gradeitemid, 'courseid' => $context->instanceid]);
    if (!\local_gradeitemdetail\api::supports_item($gradeitem ?: null)) {
        return false;
    }
    if ($gradeitem->is_hidden() && !has_capability('moodle/grade:viewhidden', $context)) {
        return false;
    }
    if (!\local_gradeitemdetail\api::has_detail(\local_gradeitemdetail\api::get_record($gradeitemid))) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file(
        $context->id,
        'local_gradeitemdetail',
        \local_gradeitemdetail\api::FILEAREA,
        $gradeitemid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
