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
 * Grade item detail viewer.
 *
 * Turns the name of every manual grade item that has a detail text into a
 * button in the participant grade overview. Clicking it opens the text in a
 * modal. Activity grade items are never touched - they already carry Moodle's
 * own link to the activity.
 *
 * @module     local_gradeitemdetail/viewer
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/modal', 'core/str', 'core/notification'], function(Modal, Str, Notification) {

    'use strict';

    /** Marks a cell as processed so a second init() cannot double-decorate it. */
    var PROCESSED = 'data-gradeitemdetail-done';

    /**
     * Find the name cells belonging to a grade item.
     *
     * The participant report gives every grade item cell the id
     * "row_<gradeitemid>_<userid>"; the trailing underscore in the prefix keeps
     * item 318 from matching item 31808.
     *
     * @param {number} gradeitemid
     * @returns {HTMLElement[]}
     */
    function findCells(gradeitemid) {
        var selector = '[id^="row_' + gradeitemid + '_"]:not([' + PROCESSED + '])';
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    /**
     * Open the detail text of one grade item in a modal.
     *
     * @param {object} item        {id, name, html}
     * @param {HTMLElement} trigger the button that was clicked
     * @returns {Promise}
     */
    function openModal(item, trigger) {
        return Modal.create({
            title: item.name,
            body: item.html,
            large: true,
            removeOnClose: true,
            returnElement: trigger
        }).then(function(modal) {
            modal.getRoot().addClass('local-gradeitemdetail-modal');
            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Replace the plain item title with a button that opens the modal.
     *
     * @param {HTMLElement} cell
     * @param {object}      item      {id, name, html}
     * @param {string}      ariaLabel localised label for screen readers
     */
    function decorate(cell, item, ariaLabel) {
        cell.setAttribute(PROCESSED, '1');

        var title = cell.querySelector('.rowtitle');
        if (!title || title.querySelector('a, button')) {
            // No title element, or something already made it interactive.
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link p-0 local-gradeitemdetail-link';
        button.setAttribute('aria-label', ariaLabel);
        // The existing markup comes from Moodle's own renderer and is safe.
        button.innerHTML = title.innerHTML;

        var icon = document.createElement('i');
        icon.className = 'icon fa fa-circle-info fa-fw';
        icon.setAttribute('aria-hidden', 'true');
        button.appendChild(icon);

        button.addEventListener('click', function(e) {
            e.preventDefault();
            openModal(item, button);
        });

        title.innerHTML = '';
        title.appendChild(button);
    }

    return {
        /**
         * Entry point, called from PHP with the detail texts of the course.
         *
         * @param {Array} items list of {id, name, html}
         */
        init: function(items) {
            if (!items || !items.length) {
                return;
            }

            Str.get_string('viewdetails', 'local_gradeitemdetail').then(function(label) {
                items.forEach(function(item) {
                    findCells(item.id).forEach(function(cell) {
                        decorate(cell, item, label);
                    });
                });
                return label;
            }).catch(Notification.exception);
        }
    };
});
