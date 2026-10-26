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
 * Action menu entry for editing grade item details.
 *
 * Adds "Edit details for participants" right below core's "Edit grade item"
 * entry of every manual grade item, on the gradebook setup page and in the
 * grader report. The entry opens local_gradeitemdetail\form\detail in a modal.
 *
 * @module     local_gradeitemdetail/setup
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_form/modalform', 'core/str', 'core/toast', 'core/notification'],
        function(ModalForm, Str, Toast, Notification) {

    'use strict';

    /** Trigger attribute of the entries this module adds. */
    var TRIGGER = 'local-gradeitemdetail-form';

    /** Marks a core edit entry that already has our entry next to it. */
    var PROCESSED = 'data-gradeitemdetail-menu';

    /**
     * Put our entry after core's edit entry of each given grade item.
     *
     * Core renders that entry as
     * <a class="dropdown-item" data-trigger="add-item-form" data-itemid="…">.
     *
     * @param {number[]} itemids manual grade items of the course
     * @param {string}   label   localised menu label
     */
    function addMenuEntries(itemids, label) {
        itemids.forEach(function(id) {
            var selector = '[data-trigger="add-item-form"][data-itemid="' + id + '"]:not([' + PROCESSED + '])';
            document.querySelectorAll(selector).forEach(function(edit) {
                edit.setAttribute(PROCESSED, '1');

                var entry = document.createElement('a');
                entry.href = '#';
                entry.className = 'dropdown-item';
                entry.setAttribute('role', 'menuitem');
                entry.setAttribute('data-trigger', TRIGGER);
                entry.setAttribute('data-itemid', String(id));
                entry.setAttribute('data-courseid', edit.getAttribute('data-courseid'));
                entry.textContent = label;

                edit.parentNode.insertBefore(entry, edit.nextSibling);
            });
        });
    }

    /**
     * Open the detail form for the grade item of a clicked entry.
     *
     * @param {HTMLElement} trigger
     */
    function openForm(trigger) {
        var modalForm = new ModalForm({
            modalConfig: {
                title: Str.getString('editdetail', 'local_gradeitemdetail'),
                large: true
            },
            formClass: 'local_gradeitemdetail\\form\\detail',
            args: {
                courseid: trigger.getAttribute('data-courseid'),
                itemid: trigger.getAttribute('data-itemid')
            },
            saveButtonText: Str.getString('savechanges', 'core'),
            returnFocus: trigger
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, function() {
            Str.getString('detailsaved', 'local_gradeitemdetail')
                .then(Toast.add)
                .catch(Notification.exception);
        });

        modalForm.show();
    }

    return {
        /**
         * Entry point, called from PHP with the manual grade items of the course.
         *
         * @param {number[]} itemids
         */
        init: function(itemids) {
            if (!itemids || !itemids.length) {
                return;
            }

            document.addEventListener('click', function(e) {
                var trigger = e.target.closest('[data-trigger="' + TRIGGER + '"]');
                if (trigger) {
                    e.preventDefault();
                    openForm(trigger);
                }
            });

            Str.getString('editdetail', 'local_gradeitemdetail').then(function(label) {
                addMenuEntries(itemids, label);

                // The grader report re-renders parts of its table (e.g. when
                // columns are collapsed), which brings back plain menus.
                var timer = null;
                new MutationObserver(function() {
                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        addMenuEntries(itemids, label);
                    }, 200);
                }).observe(document.body, {childList: true, subtree: true});

                return label;
            }).catch(Notification.exception);
        }
    };
});
