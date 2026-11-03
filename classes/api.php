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

use context;
use stdClass;

/**
 * Storage and formatting helpers for grade item detail texts.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string Database table holding the detail texts. */
    public const TABLE = 'local_gradeitemdetail';

    /** @var string File area for images and files embedded in a detail text. */
    public const FILEAREA = 'detail';

    /**
     * @var string Form element name used for the injected editor.
     *
     * Deliberately free of underscores: it must survive optional_param_array(),
     * which validates array keys with PARAM_ALPHANUMEXT.
     */
    public const ELEMENT = 'localgradeitemdetail';

    /**
     * Only manual grade items get a detail text.
     *
     * Activity grade items already link to their activity in the participant
     * overview, so decorating them would collide with that link.
     *
     * @param \grade_item|null $gradeitem
     * @return bool
     */
    public static function supports_item(?\grade_item $gradeitem): bool {
        return $gradeitem !== null && $gradeitem->itemtype === 'manual';
    }

    /**
     * Editor options used for both rendering and saving. Must be identical in
     * both directions, otherwise embedded files are lost.
     *
     * @param context $context course context
     * @return array
     */
    public static function editor_options(context $context): array {
        global $CFG;

        // EDITOR_UNLIMITED_FILES lives in formslib.
        require_once($CFG->libdir . '/formslib.php');

        return [
            'subdirs'   => 0,
            'maxfiles'  => EDITOR_UNLIMITED_FILES,
            'maxbytes'  => $CFG->maxbytes,
            'trusttext' => false,
            'noclean'   => false,
            'context'   => $context,
        ];
    }

    /**
     * Fetch the stored record for a grade item.
     *
     * @param int $gradeitemid
     * @return stdClass|null
     */
    public static function get_record(int $gradeitemid): ?stdClass {
        global $DB;

        if ($gradeitemid <= 0) {
            return null;
        }
        $record = $DB->get_record(self::TABLE, ['gradeitemid' => $gradeitemid]);
        return $record ?: null;
    }

    /**
     * All detail records of a course, keyed by grade item id.
     *
     * @param int $courseid
     * @return stdClass[]
     */
    public static function get_course_records(int $courseid): array {
        global $DB;

        $records = $DB->get_records(self::TABLE, ['courseid' => $courseid]);
        $byitem = [];
        foreach ($records as $record) {
            $byitem[(int)$record->gradeitemid] = $record;
        }
        return $byitem;
    }

    /**
     * Does this record carry anything worth showing?
     *
     * Media-only texts count as content, so the tags that can render on their
     * own are kept while stripping the rest.
     *
     * @param stdClass|null $record
     * @return bool
     */
    public static function has_detail(?stdClass $record): bool {
        if ($record === null || $record->detail === null) {
            return false;
        }
        $stripped = strip_tags($record->detail, '<img><video><audio><iframe><object><embed>');
        $stripped = html_entity_decode($stripped, ENT_QUOTES, 'UTF-8');
        // Editors happily leave "<p>&nbsp;</p>" behind; that is not content.
        $stripped = str_replace("\xc2\xa0", ' ', $stripped);
        return trim($stripped) !== '';
    }

    /**
     * Create or update the detail text of a grade item.
     *
     * @param int    $gradeitemid
     * @param int    $courseid
     * @param string $text
     * @param int    $format
     * @return void
     */
    public static function save(int $gradeitemid, int $courseid, string $text, int $format): void {
        global $DB, $USER;

        $now = time();
        $existing = self::get_record($gradeitemid);

        if ($existing) {
            $existing->detail       = $text;
            $existing->detailformat = $format;
            $existing->courseid     = $courseid;
            $existing->usermodified = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return;
        }

        $record = (object)[
            'courseid'     => $courseid,
            'gradeitemid'  => $gradeitemid,
            'detail'       => $text,
            'detailformat' => $format,
            'usermodified' => $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Remove the detail text and every embedded file of a grade item.
     *
     * @param int          $gradeitemid
     * @param context|null $context course context, if already known
     * @return void
     */
    public static function delete(int $gradeitemid, ?context $context = null): void {
        global $DB;

        $record = self::get_record($gradeitemid);
        if (!$record) {
            return;
        }

        if ($context === null) {
            $context = \context_course::instance($record->courseid, IGNORE_MISSING) ?: null;
        }
        if ($context) {
            get_file_storage()->delete_area_files(
                $context->id,
                'local_gradeitemdetail',
                self::FILEAREA,
                $gradeitemid
            );
        }

        $DB->delete_records(self::TABLE, ['gradeitemid' => $gradeitemid]);
    }

    /**
     * Render a stored detail text for display.
     *
     * @param stdClass $record
     * @param context  $context course context
     * @return string HTML
     */
    public static function format_detail(stdClass $record, context $context): string {
        $text = file_rewrite_pluginfile_urls(
            $record->detail,
            'pluginfile.php',
            $context->id,
            'local_gradeitemdetail',
            self::FILEAREA,
            $record->gradeitemid
        );

        return format_text($text, $record->detailformat, ['context' => $context]);
    }
}
