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
 * English language strings for Annual Training Forecast plugin
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Annual Training Forecast';

// Navigation
$string['ganttview'] = 'Gantt View';
$string['managecourses'] = 'Manage Courses';
$string['reports'] = 'Reports';

// Course management
$string['addparentcourse'] = 'Add Parent Course';
$string['addcourseinstance'] = 'Add Course Instance';
$string['editcourse'] = 'Edit Course';
$string['deletecourse'] = 'Delete Course';
$string['coursename'] = 'Course Name';
$string['coursedescription'] = 'Course Description';
$string['courseduration'] = 'Course Duration (Days)';
$string['startdate'] = 'Start Date';
$string['enddate'] = 'End Date';
$string['status'] = 'Status';
$string['completed'] = 'Completed';
$string['notcompleted'] = 'Not Completed';

// Course source
$string['coursesource'] = 'Course Source';
$string['createnewcourse'] = 'Create a new course';
$string['selectexistingcourse'] = 'Select an existing course';
$string['newcoursedetails'] = 'New Course Details';
$string['existingcoursedetails'] = 'Existing Course Details';
$string['selectcourse'] = 'Select Course';

// Status options
$string['status_upcoming'] = 'Upcoming';
$string['status_inprogress'] = 'In Progress';
$string['status_completed'] = 'Completed';
$string['status_cancelled'] = 'Cancelled';

// Messages
$string['courseadded'] = 'Course added successfully';
$string['courseupdated'] = 'Course updated successfully';
$string['coursedeleted'] = 'Course deleted successfully';
$string['instanceadded'] = 'Course instance added successfully';
$string['instanceupdated'] = 'Course instance updated successfully';
$string['instancedeleted'] = 'Course instance deleted successfully';
$string['nocourses'] = 'No courses found. Add a parent course to get started.';
$string['noiterations'] = 'No course instances found for this course.';
$string['cannotdeletecourse'] = 'Cannot delete course: it has course instances. Delete all instances first.';

// Confirmation messages
$string['confirmdelete'] = 'Are you sure you want to delete';
$string['deletecoursewarning'] = 'This will remove the course from the Annual Training Forecast system, but the associated Moodle course will remain unchanged.';
$string['deleteiterationwarning'] = 'This will remove the course instance from the Annual Training Forecast system, but the associated Moodle course will remain unchanged.';

// Missing strings that are used in code
$string['parentcourse'] = 'Parent Course';
$string['yearview'] = 'Year View';
$string['halfyearview'] = 'Half Year View';
$string['quarterlyview'] = 'Quarterly View';
$string['updatefailed'] = 'Update failed';
$string['enddatebeforestartdate'] = 'End date cannot be before start date';
$string['exportexcel'] = 'Export Excel';
$string['exportpdf'] = 'Export PDF';
$string['statussummary'] = 'Status Summary';
$string['count'] = 'Count';
$string['completionsummary'] = 'Completion Summary';
$string['monthlydistribution'] = 'Monthly Distribution';
$string['month'] = 'Month';
