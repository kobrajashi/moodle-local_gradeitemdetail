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
 * Event observers for local_gradeitemdetail.
 *
 * Core offers no extension point in the grade item edit form, so the extra
 * field is injected into the form client-side and picked up again here, at
 * the exact moment core persists the grade item. That way the detail text is
 * only stored when the surrounding form actually validated and saved, and a
 * brand new grade item is handled too (its id only exists at that point).
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\grade_item_created',
        'callback'  => '\local_gradeitemdetail\observer::grade_item_created',
    ],
    [
        'eventname' => '\core\event\grade_item_updated',
        'callback'  => '\local_gradeitemdetail\observer::grade_item_updated',
    ],
    [
        'eventname' => '\core\event\grade_item_deleted',
        'callback'  => '\local_gradeitemdetail\observer::grade_item_deleted',
    ],
];
