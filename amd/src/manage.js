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
 * Management JavaScript
 *
 * @module     local_annualtrainingforecast/manage
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/notification'], ($, Str, Notification) => {
  /**
   * Module initialization
   */
  var init = () => {
    // Handle course deletion with proper confirmation
    $('.delete-course').on('click', function(e) {
      e.preventDefault();
      var url = $(this).attr('href');
      var courseName = $(this).closest('.course-item').find('h5').text().trim();

      Str.get_strings([
        {key: 'confirmdelete', component: 'local_annualtrainingforecast'},
        {key: 'deletecourse', component: 'local_annualtrainingforecast'},
        {key: 'delete', component: 'core'},
        {key: 'cancel', component: 'core'},
      ]).done((strings) => {
        Notification.confirm(
            strings[1], // Delete course
            strings[0] + ' "' + courseName + '"?', // Confirm delete message
            strings[2], // Delete
            strings[3], // Cancel
            () => {
              // User confirmed - redirect to delete URL
              window.location.href = url;
            },
        );
      });
    });

    // Handle iteration deletion with proper confirmation
    $('.delete-iteration').on('click', function(e) {
      e.preventDefault();
      var url = $(this).attr('href');
      var iterationName = $(this).closest('tr').find('td:first').text().trim();

      Str.get_strings([
        {key: 'confirmdelete', component: 'local_annualtrainingforecast'},
        {key: 'deleteinstance', component: 'local_annualtrainingforecast'},
        {key: 'delete', component: 'core'},
        {key: 'cancel', component: 'core'},
      ]).done((strings) => {
        Notification.confirm(
            strings[1], // Delete instance
            strings[0] + ' "' + iterationName + '"?', // Confirm delete message
            strings[2], // Delete
            strings[3], // Cancel
            () => {
              // User confirmed - redirect to delete URL
              window.location.href = url;
            },
        );
      });
    });
  };

  return {
    init: init,
  };
});
