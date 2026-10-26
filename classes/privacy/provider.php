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

namespace local_gradeitemdetail\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_gradeitemdetail\api;

/**
 * Privacy provider for local_gradeitemdetail.
 *
 * The detail texts are course content written by teachers. The only personal
 * data is usermodified - who last changed a text. Deleting a user therefore
 * anonymises that field instead of removing course content other people rely on.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(api::TABLE, [
            'gradeitemid'  => 'privacy:metadata:local_gradeitemdetail:gradeitemid',
            'detail'       => 'privacy:metadata:local_gradeitemdetail:detail',
            'usermodified' => 'privacy:metadata:local_gradeitemdetail:usermodified',
            'timemodified' => 'privacy:metadata:local_gradeitemdetail:timemodified',
        ], 'privacy:metadata:local_gradeitemdetail');

        return $collection;
    }

    /**
     * Course contexts in which the user last changed a detail text.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {" . api::TABLE . "} d ON d.courseid = ctx.instanceid AND ctx.contextlevel = :courselevel
                 WHERE d.usermodified = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users who last changed a detail text in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $sql = "SELECT usermodified
                  FROM {" . api::TABLE . "}
                 WHERE courseid = :courseid AND usermodified <> 0";
        $userlist->add_from_sql('usermodified', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Export the detail texts the user last changed.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $records = $DB->get_records(api::TABLE,
                ['courseid' => $context->instanceid, 'usermodified' => $userid], 'gradeitemid');
            if (!$records) {
                continue;
            }

            $writer = writer::with_context($context);
            $subcontext = [get_string('pluginname', 'local_gradeitemdetail')];
            $details = [];
            foreach ($records as $record) {
                $details[] = (object)[
                    'gradeitemid'  => $record->gradeitemid,
                    'detail'       => $writer->rewrite_pluginfile_urls($subcontext, 'local_gradeitemdetail',
                        api::FILEAREA, $record->gradeitemid, $record->detail ?? ''),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
                $writer->export_area_files($subcontext, 'local_gradeitemdetail', api::FILEAREA, $record->gradeitemid);
            }
            $writer->export_data($subcontext, (object)['details' => $details]);
        }
    }

    /**
     * Anonymise every detail text in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }
        $DB->set_field(api::TABLE, 'usermodified', 0, ['courseid' => $context->instanceid]);
    }

    /**
     * Anonymise the detail texts one user last changed.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $DB->set_field(api::TABLE, 'usermodified', 0,
                ['courseid' => $context->instanceid, 'usermodified' => $userid]);
        }
    }

    /**
     * Anonymise the detail texts several users last changed in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->set_field_select(api::TABLE, 'usermodified', 0, "courseid = :courseid AND usermodified $insql", $params);
    }
}
