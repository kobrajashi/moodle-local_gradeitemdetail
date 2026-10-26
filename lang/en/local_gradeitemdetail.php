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
 * English strings for local_gradeitemdetail.
 *
 * @package    local_gradeitemdetail
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Grade item details';

$string['detailfield'] = 'Details for participants';
$string['detailfield_help'] = 'Shown to participants in the grade overview: the name of this grade item becomes clickable and opens this text in a pop-up. Leave empty to keep the name plain. Only available for manual grade items.';

$string['viewdetails'] = 'Show details on this grade item';

$string['showinuserreport'] = 'Show details in the participant overview';
$string['showinuserreport_desc'] = 'Make the names of manual grade items that have a detail text clickable in the participant grade overview.';

$string['privacy:metadata:local_gradeitemdetail'] = 'Detail texts written by teachers for manual grade items. The only personal data is who last changed a text.';
$string['privacy:metadata:local_gradeitemdetail:detail'] = 'The detail text shown to participants.';
$string['privacy:metadata:local_gradeitemdetail:gradeitemid'] = 'The grade item the text belongs to.';
$string['privacy:metadata:local_gradeitemdetail:timemodified'] = 'When the text was last changed.';
$string['privacy:metadata:local_gradeitemdetail:usermodified'] = 'The user who last changed the text.';
