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
use html_writer;

/**
 * Decides what this plugin adds to the page currently being rendered.
 *
 * Three entirely separate jobs live here:
 *
 *  - On /grade/edit/tree/item.php an extra editor field is added to the core
 *    "edit grade item" form (teacher side).
 *  - On the gradebook setup page and the grader report, the action menu of
 *    every manual grade item gets an entry that opens the detail text in a
 *    modal form of its own (teacher side).
 *  - On the participant grade overview the manual grade item names are turned
 *    into buttons that open the detail text in a modal (student side).
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class injector {

    /**
     * Entry point for the footer hook.
     *
     * @return string HTML to append to the page footer, or an empty string.
     */
    public static function run(): string {
        global $PAGE;

        // Page type rather than URL: it is derived from the running script, so
        // it is immune to subdirectory installs. Note that $PAGE->url must not
        // be tested with empty() - moodle_page exposes it through __get() and
        // implements no __isset(), so empty() reports true on every page.
        $pagetype = (string)$PAGE->pagetype;

        if ($pagetype === 'grade-edit-tree-item') {
            return self::edit_form_field();
        }

        // Both pages offer core's "Edit grade item" action menu entry, which
        // opens the modal form that has no room for the detail text.
        if ($pagetype === 'grade-edit-tree-index' || $pagetype === 'grade-report-grader-index') {
            self::add_menu_entries();
            return '';
        }

        // 'course-user' is the course level "Grades" page for one participant,
        // which embeds the very same user report.
        if ($pagetype === 'grade-report-user-index'
                || ($pagetype === 'course-user' && optional_param('mode', '', PARAM_ALPHA) === 'grade')) {
            self::decorate_user_report();
            return '';
        }

        return '';
    }

    // ------------------------------------------------------------------
    // Teacher side: extra field in the grade item edit form.
    // ------------------------------------------------------------------

    /**
     * Build the detail editor for /grade/edit/tree/item.php.
     *
     * The markup is emitted into the footer and relocated into the core form
     * by a small inline script (see move_script()). Everything below runs
     * before Moodle writes its end-of-page JavaScript, so the editor that
     * MoodleQuickForm_editor registers via $PAGE->requires is still picked up.
     *
     * @return string
     */
    private static function edit_form_field(): string {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/form/editor.php');

        $courseid = optional_param('courseid', 0, PARAM_INT);
        $itemid   = optional_param('id', 0, PARAM_INT);
        if ($courseid <= 0) {
            return '';
        }

        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !has_capability('moodle/grade:manage', $context)) {
            return '';
        }

        if ($itemid > 0) {
            // Existing item: only manual ones get a detail text. Activity,
            // category and course items are left untouched.
            $gradeitem = \grade_item::fetch(['id' => $itemid, 'courseid' => $courseid]);
            if (!api::supports_item($gradeitem ?: null)) {
                return '';
            }
        }
        // A missing id means item.php is creating a new item, and every item
        // created there is manual by definition.

        $options = api::editor_options($context);
        $record  = api::get_record($itemid);
        $text    = $record->detail ?? '';
        $format  = (int)($record->detailformat ?? FORMAT_HTML);

        // If the core form bounced back with a validation error, keep what the
        // user typed instead of silently reverting to the stored text.
        $submitted = optional_param_array(api::ELEMENT, null, PARAM_RAW);
        if (is_array($submitted) && array_key_exists('text', $submitted)) {
            $text = (string)$submitted['text'];
            if (isset($submitted['format'])) {
                $format = (int)$submitted['format'];
            }
        }

        $draftitemid = file_get_submitted_draft_itemid(api::ELEMENT);
        $text = file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_gradeitemdetail',
            api::FILEAREA,
            $itemid > 0 ? $itemid : null,
            $options,
            $text
        );

        $label = get_string('detailfield', 'local_gradeitemdetail');
        $elementid = 'id_' . api::ELEMENT;

        $editor = new \MoodleQuickForm_editor(
            api::ELEMENT,
            $label,
            ['id' => $elementid, 'rows' => 12],
            $options
        );
        $editor->setValue(['text' => $text, 'format' => $format, 'itemid' => $draftitemid]);
        $editorhtml = $editor->toHtml();

        $field  = html_writer::start_div('mb-3 row fitem', ['id' => 'fitem_' . $elementid]);
        $field .= html_writer::start_div('col-md-3 col-form-label d-flex pb-0 pe-md-0');
        $field .= html_writer::tag('label', $label, [
            'class' => 'd-inline word-break',
            'for'   => $elementid,
        ]);
        $field .= html_writer::end_div();
        $field .= html_writer::start_div('col-md-9 d-flex flex-wrap align-items-start felement', [
            'data-fieldtype' => 'editor',
        ]);
        $field .= $editorhtml;
        $field .= html_writer::div(
            get_string('detailfield_help', 'local_gradeitemdetail'),
            'form-text w-100'
        );
        $field .= html_writer::end_div();
        $field .= html_writer::end_div();

        return html_writer::div($field, '', ['id' => 'local-gradeitemdetail-wrap'])
            . self::move_script();
    }

    /**
     * Inline script that lifts the field out of the footer and drops it into
     * the core form, right below "Item info".
     *
     * This runs synchronously while the footer is parsed - that is the point:
     * it happens before any AMD module (the text editor included) boots, so
     * the editor is never re-parented after it has initialised, which would
     * reload its iframe and discard the content.
     *
     * @return string
     */
    private static function move_script(): string {
        $js = <<<'JS'
(function() {
    var wrap = document.getElementById('local-gradeitemdetail-wrap');
    if (!wrap) {
        return;
    }
    var field = wrap.firstElementChild;
    var anchor = document.getElementById('fitem_id_iteminfo')
        || document.getElementById('fitem_id_idnumber')
        || document.getElementById('fitem_id_itemname');
    if (field && anchor && anchor.parentNode) {
        anchor.parentNode.insertBefore(field, anchor.nextSibling);
        wrap.parentNode.removeChild(wrap);
        return;
    }
    // Without a known anchor the field must still end up inside the form:
    // left in the footer, whatever is typed into it is never submitted.
    var marker = document.querySelector('input[name="_qf__edit_item_form"]');
    var form = marker ? marker.form : null;
    if (field && form) {
        var buttons = document.getElementById('fgroup_id_buttonar');
        if (buttons && buttons.parentNode && form.contains(buttons)) {
            buttons.parentNode.insertBefore(field, buttons);
        } else {
            form.appendChild(field);
        }
        wrap.parentNode.removeChild(wrap);
        return;
    }
    // No form to put it in: hide the field rather than offer one that cannot save.
    wrap.style.display = 'none';
})();
JS;

        return html_writer::script($js);
    }

    // ------------------------------------------------------------------
    // Teacher side: action menu entry next to "Edit grade item".
    // ------------------------------------------------------------------

    /**
     * Queue the AMD module that adds the menu entry for each manual item.
     *
     * @return void
     */
    private static function add_menu_entries(): void {
        global $CFG, $PAGE;

        $course = $PAGE->course ?? null;
        if (!$course || $course->id == SITEID) {
            return;
        }
        if (!has_capability('moodle/grade:manage', context_course::instance($course->id))) {
            return;
        }

        require_once($CFG->libdir . '/gradelib.php');

        $gradeitems = \grade_item::fetch_all(['courseid' => $course->id, 'itemtype' => 'manual']) ?: [];
        if (!$gradeitems) {
            return;
        }

        $itemids = array_map('intval', array_keys($gradeitems));
        $PAGE->requires->js_call_amd('local_gradeitemdetail/setup', 'init', [$itemids]);
    }

    // ------------------------------------------------------------------
    // Participant side: clickable grade item names.
    // ------------------------------------------------------------------

    /**
     * Queue the AMD module that makes manual grade items with a detail text
     * clickable in the participant overview.
     *
     * The detail texts are rendered server side and handed to the module as
     * arguments - a course has a handful of manual items at most, so this
     * saves every viewer a web service round trip.
     *
     * @return void
     */
    private static function decorate_user_report(): void {
        global $CFG, $PAGE;

        if (!get_config('local_gradeitemdetail', 'showinuserreport')) {
            return;
        }

        $course = $PAGE->course ?? null;
        if (!$course || $course->id == SITEID) {
            return;
        }

        $records = api::get_course_records((int)$course->id);
        if (!$records) {
            return;
        }

        require_once($CFG->libdir . '/gradelib.php');

        $context = context_course::instance($course->id);
        $canviewhidden = has_capability('moodle/grade:viewhidden', $context);

        // One query for all manual items instead of one per record.
        $gradeitems = \grade_item::fetch_all(['courseid' => $course->id, 'itemtype' => 'manual']) ?: [];

        $items = [];
        foreach ($records as $gradeitemid => $record) {
            if (!api::has_detail($record)) {
                continue;
            }
            $gradeitem = $gradeitems[$gradeitemid] ?? null;
            if (!api::supports_item($gradeitem)) {
                continue;
            }
            if ($gradeitem->is_hidden() && !$canviewhidden) {
                continue;
            }
            $items[] = [
                'id'   => (int)$gradeitemid,
                'name' => format_string($gradeitem->get_name(), true, ['context' => $context]),
                'html' => api::format_detail($record, $context),
            ];
        }

        if (!$items) {
            return;
        }

        $PAGE->requires->js_call_amd('local_gradeitemdetail/viewer', 'init', [$items]);
    }
}
