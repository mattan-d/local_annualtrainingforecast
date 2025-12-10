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
 * Course management page
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/annualtrainingforecast/classes/forms/course_form.php');
require_once($CFG->dirroot . '/local/annualtrainingforecast/classes/forms/iteration_form.php');
require_once($CFG->dirroot . '/local/annualtrainingforecast/classes/course_manager.php');

// Check permissions
require_login();
$context = context_system::instance();
require_capability('local/annualtrainingforecast:managecourses', $context);

// Get parameters
$id = optional_param('id', 0, PARAM_INT); // Course or iteration ID
$parentid = optional_param('parentid', 0, PARAM_INT); // Parent course ID for iterations
$action = optional_param('action', '', PARAM_ALPHA); // add, edit, delete
$type = optional_param('type', 'course', PARAM_ALPHA); // course or iteration
$confirm = optional_param('confirm', 0, PARAM_BOOL); // Confirmation for delete
$preview = optional_param('preview', 0, PARAM_INT); // Preview mode: 0=no preview, 1=show preview, 2=confirmed
$previewdata = optional_param('previewdata', '', PARAM_RAW); // Serialized preview data

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/annualtrainingforecast/manage.php', [
    'id' => $id,
    'parentid' => $parentid,
    'action' => $action,
    'type' => $type,
    'preview' => $preview,
    'previewdata' => $previewdata
]));
$PAGE->set_title(get_string('managecourses', 'local_annualtrainingforecast'));
$PAGE->set_heading(get_string('managecourses', 'local_annualtrainingforecast'));
$PAGE->set_pagelayout('admin');

// Add required JavaScript
$PAGE->requires->js_call_amd('local_annualtrainingforecast/manage', 'init');

// Handle form submissions and actions
if ($action == 'add' || $action == 'edit') {
    if ($type == 'course') {
        // Course form
        $course = null;
        if ($action == 'edit' && $id) {
            global $DB;
            $course = $DB->get_record('local_atf_courses', ['id' => $id], '*', MUST_EXIST);
        }

        $form = new forms\course_form(null, [
            'course' => $course,
            'action' => $action
        ]);

        if ($form->is_cancelled()) {
            redirect(new moodle_url('/local/annualtrainingforecast/manage.php'));
        } else if ($data = $form->get_data()) {
            if ($action == 'add' && $preview != 2) {
                // Show preview instead of creating immediately
                display_course_preview($data, $form);
                die();
            }
            
            global $DB, $USER;

            $now = time();

            if ($action == 'edit' && $id) {
                // Update existing course
                $record = new stdClass();
                $record->id = $id;

                if ($data->coursesource === 'new') {
                    $record->name = $data->name;
                    $record->description = $data->description;
                    $record->duration = $data->duration;

                    // Update the Moodle course if it exists
                    $moodlecourseid = \course_manager::get_moodle_course_id_for_parent($id);
                    if ($moodlecourseid) {
                        $moodlecourse = $DB->get_record('course', ['id' => $moodlecourseid]);
                        if ($moodlecourse) {
                            $moodlecourse->fullname = $data->name;
                            $moodlecourse->summary = $data->description;
                            $moodlecourse->timemodified = $now;
                            $DB->update_record('course', $moodlecourse);
                        }
                    }
                } else {
                    // Using an existing course
                    $existingcourse = $DB->get_record('course', ['id' => $data->existingcourseid], '*', MUST_EXIST);
                    $record->name = $existingcourse->fullname;
                    $record->description = $existingcourse->summary;
                    $record->duration = $data->existing_duration;
                    $record->moodlecourseid = $data->existingcourseid;
                }

                $record->timemodified = $now;
                $record->modifiedby = $USER->id;

                if ($DB->update_record('local_atf_courses', $record)) {
                    \core\notification::success(get_string('courseupdated', 'local_annualtrainingforecast'));
                } else {
                    \core\notification::error('Failed to update course');
                }
            } else {
                // Add new course
                try {
                    if ($data->coursesource === 'new') {
                        // Create a new Moodle course
                        $moodlecourseid = \course_manager::create_parent_course($data);

                        // Add record to our custom table
                        $record = new stdClass();
                        $record->name = $data->name;
                        $record->description = $data->description;
                        $record->duration = $data->duration;
                        $record->moodlecourseid = $moodlecourseid;
                    } else {
                        // Use an existing Moodle course
                        $existingcourse = $DB->get_record('course', ['id' => $data->existingcourseid], '*', MUST_EXIST);

                        // Add record to our custom table
                        $record = new stdClass();
                        $record->name = $existingcourse->fullname;
                        $record->description = $existingcourse->summary;
                        $record->duration = $data->existing_duration;
                        $record->moodlecourseid = $data->existingcourseid;
                    }

                    $record->timecreated = $now;
                    $record->timemodified = $now;
                    $record->createdby = $USER->id;
                    $record->modifiedby = $USER->id;

                    if ($newid = $DB->insert_record('local_atf_courses', $record)) {
                        \core\notification::success(get_string('courseadded', 'local_annualtrainingforecast'));
                    } else {
                        \core\notification::error('Failed to add course');
                        // If we failed to add to our table and created a new Moodle course, delete it
                        if ($data->coursesource === 'new' && isset($moodlecourseid)) {
                            delete_course($moodlecourseid, false);
                        }
                    }
                } catch (Exception $e) {
                    \core\notification::error('Error creating course: ' . $e->getMessage());
                }
            }

            redirect(new moodle_url('/local/annualtrainingforecast/manage.php'));
        }
    } else if ($type == 'iteration') {
        // Iteration form
        $iteration = null;
        if ($action == 'edit' && $id) {
            global $DB;
            $iteration = $DB->get_record('local_atf_iterations', ['id' => $id], '*', MUST_EXIST);
            $parentid = $iteration->parentid;
        }

        $form = new forms\iteration_form(null, [
            'iteration' => $iteration,
            'parentid' => $parentid,
            'action' => $action
        ]);

        if ($form->is_cancelled()) {
            redirect(new moodle_url('/local/annualtrainingforecast/manage.php'));
        } else if ($data = $form->get_data()) {
            if ($action == 'add' && $preview != 2) {
                // Show preview instead of creating immediately
                display_iteration_preview($data, $form, $parentid);
                die();
            }
            
            global $DB, $USER;

            $now = time();

            if ($action == 'edit' && $id) {
                // Update existing iteration
                $record = new stdClass();
                $record->id = $id;
                $record->name = $data->name;
                $record->startdate = $data->startdate;
                $record->enddate = $data->enddate;
                $record->status = $data->status;
                $record->completed = $data->completed;
                $record->timemodified = $now;
                $record->modifiedby = $USER->id;

                // Update the Moodle course if it exists
                $moodlecourseid = \course_manager::get_moodle_course_id_for_iteration($id);
                if ($moodlecourseid) {
                    $moodlecourse = $DB->get_record('course', ['id' => $moodlecourseid]);
                    if ($moodlecourse) {
                        $moodlecourse->fullname = $data->name;
                        $moodlecourse->startdate = $data->startdate;
                        $moodlecourse->enddate = $data->enddate;
                        $moodlecourse->timemodified = $now;
                        $DB->update_record('course', $moodlecourse);
                    }
                }

                if ($DB->update_record('local_atf_iterations', $record)) {
                    \core\notification::success(get_string('instanceupdated', 'local_annualtrainingforecast'));
                } else {
                    \core\notification::error('Failed to update course instance');
                }
            } else {
                // Add new iteration
                try {
                    if (!empty($data->linkexisting) && !empty($data->existingcourseid)) {
                        // Link to existing course
                        $moodlecourseid = $data->existingcourseid;
                        
                        // Verify the course exists
                        $existingcourse = $DB->get_record('course', ['id' => $moodlecourseid], '*', MUST_EXIST);
                        
                        // Update the course dates
                        $existingcourse->startdate = $data->startdate;
                        $existingcourse->enddate = $data->enddate;
                        $existingcourse->timemodified = $now;
                        $DB->update_record('course', $existingcourse);
                    } else {
                        // Create new course
                        // Get the parent course's Moodle course ID
                        $parentMoodleCourseId = \course_manager::get_moodle_course_id_for_parent($parentid);

                        if (!$parentMoodleCourseId) {
                            \core\notification::error('Parent course does not have an associated Moodle course');
                            redirect(new moodle_url('/local/annualtrainingforecast/manage.php'));
                        }

                        $copymaterials = !empty($data->copymaterials);
                        $moodlecourseid = \course_manager::create_course_instance($data, $parentMoodleCourseId, $copymaterials);
                    }

                    // Add record to our custom table
                    $record = new stdClass();
                    $record->parentid = $parentid;
                    $record->name = $data->name;
                    $record->startdate = $data->startdate;
                    $record->enddate = $data->enddate;
                    $record->status = $data->status;
                    $record->completed = $data->completed;
                    $record->moodlecourseid = $moodlecourseid;
                    $record->timecreated = $now;
                    $record->timemodified = $now;
                    $record->createdby = $USER->id;
                    $record->modifiedby = $USER->id;

                    if ($DB->insert_record('local_atf_iterations', $record)) {
                        \core\notification::success(get_string('instanceadded', 'local_annualtrainingforecast'));
                    } else {
                        \core\notification::error('Failed to add course instance');
                        if (empty($data->linkexisting) && $moodlecourseid) {
                            delete_course($moodlecourseid, false);
                        }
                    }
                } catch (Exception $e) {
                    debugging('Error creating course instance: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    \core\notification::error('Error creating course instance. See server logs for details.');
                }
            }

            redirect(new moodle_url('/local/annualtrainingforecast/manage.php'));
        }
    }
} else if ($action == 'delete') {
    global $DB;

    if ($type == 'course' && $id) {
        // Get course details for confirmation
        $course = $DB->get_record('local_atf_courses', ['id' => $id], '*', MUST_EXIST);

        if (!$confirm) {
            // Show confirmation page
            echo $OUTPUT->header();

            // Check if course has iterations
            $iterations = $DB->count_records('local_atf_iterations', ['parentid' => $id]);
            if ($iterations > 0) {
                \core\notification::error(get_string('cannotdeletecourse', 'local_annualtrainingforecast'));
                echo $OUTPUT->continue_button(new moodle_url('/local/annualtrainingforecast/manage.php'));
                echo $OUTPUT->footer();
                exit;
            }

            $confirmurl = new moodle_url('/local/annualtrainingforecast/manage.php', [
                'action' => 'delete',
                'type' => 'course',
                'id' => $id,
                'confirm' => 1
            ]);
            $cancelurl = new moodle_url('/local/annualtrainingforecast/manage.php');

            echo $OUTPUT->confirm(
                get_string('confirmdelete', 'local_annualtrainingforecast') . ' "' . format_string($course->name) . '"?<br><br>' .
                get_string('deletecoursewarning', 'local_annualtrainingforecast'),
                $confirmurl,
                $cancelurl
            );

            echo $OUTPUT->footer();
            exit;
        }

        // Check if course has iterations (double check)
        $iterations = $DB->count_records('local_atf_iterations', ['parentid' => $id]);
        if ($iterations > 0) {
            \core\notification::error(get_string('cannotdeletecourse', 'local_annualtrainingforecast'));
            redirect(new moodle_url('/local/annualtrainingforecast/manage.php'));
        }

        // Delete course from our custom table only (don't delete from Moodle)
        if ($DB->delete_records('local_atf_courses', ['id' => $id])) {
            \core\notification::success(get_string('coursedeleted', 'local_annualtrainingforecast'));
        } else {
            \core\notification::error('Failed to delete course');
        }

    } else if ($type == 'iteration' && $id) {
        // Get iteration details for confirmation
        $iteration = $DB->get_record('local_atf_iterations', ['id' => $id], '*', MUST_EXIST);

        if (!$confirm) {
            // Show confirmation page
            echo $OUTPUT->header();

            $confirmurl = new moodle_url('/local/annualtrainingforecast/manage.php', [
                'action' => 'delete',
                'type' => 'iteration',
                'id' => $id,
                'confirm' => 1
            ]);
            $cancelurl = new moodle_url('/local/annualtrainingforecast/manage.php');

            echo $OUTPUT->confirm(
                get_string('confirmdelete', 'local_annualtrainingforecast') . ' "' . format_string($iteration->name) . '"?<br><br>' .
                get_string('deleteiterationwarning', 'local_annualtrainingforecast'),
                $confirmurl,
                $cancelurl
            );

            echo $OUTPUT->footer();
            exit;
        }

        // Delete iteration from our custom table only (don't delete from Moodle)
        if ($DB->delete_records('local_atf_iterations', ['id' => $id])) {
            \core\notification::success(get_string('instancedeleted', 'local_annualtrainingforecast'));
        } else {
            \core\notification::error('Failed to delete course instance');
        }
    }

    redirect(new moodle_url('/local/annualtrainingforecast/manage.php'));
}

// Output starts here
echo $OUTPUT->header();

// Navigation tabs
$tabs = [
    new tabobject('gantt', new moodle_url('/local/annualtrainingforecast/index.php'),
        get_string('ganttview', 'local_annualtrainingforecast')),
    new tabobject('manage', new moodle_url('/local/annualtrainingforecast/manage.php'),
        get_string('managecourses', 'local_annualtrainingforecast')),
    new tabobject('reports', new moodle_url('/local/annualtrainingforecast/reports.php'),
        get_string('reports', 'local_annualtrainingforecast'))
];

echo $OUTPUT->tabtree($tabs, 'manage');

// Display form if adding or editing
if (($action == 'add' || $action == 'edit') && isset($form)) {
    $form->display();
} else {
    // Display management interface
    echo html_writer::start_div('course-management');

    // Add buttons
    echo html_writer::start_div('management-actions');
    $addcourseurl = new moodle_url('/local/annualtrainingforecast/manage.php',
        ['action' => 'add', 'type' => 'course']);
    echo html_writer::link($addcourseurl, get_string('addparentcourse', 'local_annualtrainingforecast'),
        ['class' => 'btn btn-primary']);
    echo html_writer::end_div();

    // List parent courses
    global $DB;
    $courses = $DB->get_records('local_atf_courses', null, 'name ASC');

    if (empty($courses)) {
        echo html_writer::div(get_string('nocourses', 'local_annualtrainingforecast'), 'alert alert-info');
    } else {
        foreach ($courses as $course) {
            echo html_writer::start_div('course-item card mb-3');
            echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
            echo html_writer::tag('h5', format_string($course->name), ['class' => 'mb-0']);

            // Course actions
            echo html_writer::start_div('course-actions');
            $editurl = new moodle_url('/local/annualtrainingforecast/manage.php',
                ['action' => 'edit', 'type' => 'course', 'id' => $course->id]);
            $deleteurl = new moodle_url('/local/annualtrainingforecast/manage.php',
                ['action' => 'delete', 'type' => 'course', 'id' => $course->id]);
            $additerurl = new moodle_url('/local/annualtrainingforecast/manage.php',
                ['action' => 'add', 'type' => 'iteration', 'parentid' => $course->id]);

            // Link to Moodle course if it exists
            if (!empty($course->moodlecourseid)) {
                $viewcourseurl = new moodle_url('/course/view.php', ['id' => $course->moodlecourseid]);
                echo html_writer::link($viewcourseurl, $OUTPUT->pix_icon('i/course', get_string('viewcourse', 'local_annualtrainingforecast')),
                    ['class' => 'btn btn-sm btn-info', 'title' => get_string('viewcourse', 'local_annualtrainingforecast'), 'target' => '_blank']);
                echo ' ';
            }

            echo html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')),
                ['class' => 'btn btn-sm btn-secondary', 'title' => get_string('editcourse', 'local_annualtrainingforecast')]);
            echo ' ';
            echo html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')),
                ['class' => 'btn btn-sm btn-danger delete-course', 'title' => get_string('deletecourse', 'local_annualtrainingforecast')]);
            echo ' ';
            echo html_writer::link($additerurl, get_string('addcourseinstance', 'local_annualtrainingforecast'),
                ['class' => 'btn btn-sm btn-success']);
            echo html_writer::end_div(); // course-actions

            echo html_writer::end_div(); // card-header

            echo html_writer::start_div('card-body');
            if (!empty($course->description)) {
                echo html_writer::div(format_text($course->description), 'course-description');
            }
            echo html_writer::div(get_string('courseduration', 'local_annualtrainingforecast') . ': ' . $course->duration . ' ' .
                get_string('days'), 'course-duration');

            // List iterations
            $iterations = $DB->get_records('local_atf_iterations', ['parentid' => $course->id], 'startdate DESC');

            if (!empty($iterations)) {
                echo html_writer::start_tag('table', ['class' => 'table table-striped table-sm iterations-table']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', get_string('coursename', 'local_annualtrainingforecast'));
                echo html_writer::tag('th', get_string('startdate', 'local_annualtrainingforecast'));
                echo html_writer::tag('th', get_string('enddate', 'local_annualtrainingforecast'));
                echo html_writer::tag('th', get_string('status', 'local_annualtrainingforecast'));
                echo html_writer::tag('th', get_string('completed', 'local_annualtrainingforecast'));
                echo html_writer::tag('th', get_string('actions', 'moodle'));
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');

                echo html_writer::start_tag('tbody');
                foreach ($iterations as $iteration) {
                    echo html_writer::start_tag('tr');
                    echo html_writer::tag('td', format_string($iteration->name));
                    echo html_writer::tag('td', userdate($iteration->startdate, get_string('strftimedatefullshort', 'core_langconfig')));
                    echo html_writer::tag('td', userdate($iteration->enddate, get_string('strftimedatefullshort', 'core_langconfig')));

                    // Status
                    $statusstrings = [
                        0 => get_string('status_upcoming', 'local_annualtrainingforecast'),
                        1 => get_string('status_inprogress', 'local_annualtrainingforecast'),
                        2 => get_string('status_completed', 'local_annualtrainingforecast'),
                        3 => get_string('status_cancelled', 'local_annualtrainingforecast')
                    ];
                    $statusclasses = [
                        0 => 'badge badge-info',
                        1 => 'badge badge-warning',
                        2 => 'badge badge-success',
                        3 => 'badge badge-danger'
                    ];

                    echo html_writer::tag('td', html_writer::span(
                        $statusstrings[$iteration->status],
                        $statusclasses[$iteration->status]
                    ));

                    // Completed
                    $completedtext = $iteration->completed ?
                        get_string('completed', 'local_annualtrainingforecast') :
                        get_string('notcompleted', 'local_annualtrainingforecast');
                    $completedclass = $iteration->completed ? 'badge badge-success' : 'badge badge-secondary';
                    echo html_writer::tag('td', html_writer::span($completedtext, $completedclass));

                    // Actions
                    echo html_writer::start_tag('td');
                    $edititerurl = new moodle_url('/local/annualtrainingforecast/manage.php',
                        ['action' => 'edit', 'type' => 'iteration', 'id' => $iteration->id]);
                    $deleteiterurl = new moodle_url('/local/annualtrainingforecast/manage.php',
                        ['action' => 'delete', 'type' => 'iteration', 'id' => $iteration->id]);

                    // Link to Moodle course if it exists
                    if (!empty($iteration->moodlecourseid)) {
                        $viewcourseurl = new moodle_url('/course/view.php', ['id' => $iteration->moodlecourseid]);
                        echo html_writer::link($viewcourseurl, $OUTPUT->pix_icon('i/course', get_string('viewcourse', 'local_annualtrainingforecast')),
                            ['class' => 'btn btn-sm btn-info', 'title' => get_string('viewcourse', 'local_annualtrainingforecast'), 'target' => '_blank']);
                        echo ' ';
                    }

                    echo html_writer::link($edititerurl, $OUTPUT->pix_icon('t/edit', get_string('edit')),
                        ['class' => 'btn btn-sm btn-secondary', 'title' => get_string('edit')]);
                    echo ' ';
                    echo html_writer::link($deleteiterurl, $OUTPUT->pix_icon('t/delete', get_string('delete')),
                        ['class' => 'btn btn-sm btn-danger delete-iteration', 'title' => get_string('delete')]);
                    echo html_writer::end_tag('td');

                    echo html_writer::end_tag('tr');
                }
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
            } else {
                echo html_writer::div(get_string('noiterations', 'local_annualtrainingforecast'), 'alert alert-info');
            }

            echo html_writer::end_div(); // card-body
            echo html_writer::end_div(); // course-item
        }
    }

    echo html_writer::end_div(); // course-management
}

echo $OUTPUT->footer();

/**
 * Display course preview before creation
 *
 * @param stdClass $data Form data
 * @param forms\course_form $form The form instance
 */
function display_course_preview($data, $form) {
    global $PAGE, $OUTPUT, $DB;

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('previewcourse', 'local_annualtrainingforecast'));

    // Display preview information
    echo html_writer::start_tag('div', ['class' => 'course-preview']);
    
    echo html_writer::tag('h3', get_string('coursedetails', 'local_annualtrainingforecast'));
    
    $table = new html_table();
    $table->attributes['class'] = 'generaltable preview-table';
    
    if ($data->coursesource === 'new') {
        $table->data[] = [
            html_writer::tag('strong', get_string('coursename', 'local_annualtrainingforecast')),
            format_string($data->name)
        ];
        
        // Get category name
        $category = \core_course_category::get($data->category);
        $table->data[] = [
            html_writer::tag('strong', get_string('coursecategory')),
            $category->get_formatted_name()
        ];
        
        $table->data[] = [
            html_writer::tag('strong', get_string('coursedescription', 'local_annualtrainingforecast')),
            format_text($data->description, FORMAT_PLAIN)
        ];
    } else {
        // Existing course
        $existingcourse = $DB->get_record('course', ['id' => $data->existingcourseid]);
        $category = \core_course_category::get($existingcourse->category);
        
        $table->data[] = [
            html_writer::tag('strong', get_string('coursename', 'local_annualtrainingforecast')),
            format_string($existingcourse->fullname)
        ];
        
        $table->data[] = [
            html_writer::tag('strong', get_string('coursecategory')),
            $category->get_formatted_name()
        ];
        
        $table->data[] = [
            html_writer::tag('strong', get_string('shortname', 'moodle')),
            $existingcourse->shortname
        ];
    }
    
    $duration = $data->coursesource === 'new' ? $data->duration : $data->existing_duration;
    $table->data[] = [
        html_writer::tag('strong', get_string('courseduration', 'local_annualtrainingforecast')),
        get_string('dayscount', 'local_annualtrainingforecast', $duration)
    ];
    
    echo html_writer::table($table);
    
    // Create a confirmation form
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/annualtrainingforecast/manage.php'),
        'class' => 'mform preview-confirm-form'
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey()
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'action',
        'value' => 'add'
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'type',
        'value' => 'course'
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'preview',
        'value' => '2'
    ]);
    
    // Add all form data as hidden fields
    foreach ((array)$data as $key => $value) {
        if (!is_object($value) && !is_array($value)) {
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $key,
                'value' => $value
            ]);
        }
    }
    
    echo html_writer::start_tag('div', ['class' => 'form-buttons']);
    
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'submitbutton',
        'value' => get_string('createcourse', 'local_annualtrainingforecast'),
        'class' => 'btn btn-primary'
    ]);
    
    echo html_writer::tag('a', get_string('cancel'), [
        'href' => new moodle_url('/local/annualtrainingforecast/manage.php'),
        'class' => 'btn btn-secondary ml-2'
    ]);
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');

    echo $OUTPUT->footer();
}

/**
 * Display iteration preview before creation
 *
 * @param stdClass $data Form data
 * @param forms\iteration_form $form The form instance
 * @param int $parentid Parent course ID
 */
function display_iteration_preview($data, $form, $parentid) {
    global $PAGE, $OUTPUT, $DB;

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('previewiteration', 'local_annualtrainingforecast'));

    // Get parent course
    $parentcourse = $DB->get_record('local_atf_courses', ['id' => $parentid], '*', MUST_EXIST);
    $parentmoodlecourseid = \course_manager::get_moodle_course_id_for_parent($parentid);

    // Display preview information
    echo html_writer::start_tag('div', ['class' => 'iteration-preview']);
    
    echo html_writer::tag('h3', get_string('iterationdetails', 'local_annualtrainingforecast'));
    
    $table = new html_table();
    $table->attributes['class'] = 'generaltable preview-table';
    
    $table->data[] = [
        html_writer::tag('strong', get_string('parentcourse', 'local_annualtrainingforecast')),
        format_string($parentcourse->name)
    ];
    
    $table->data[] = [
        html_writer::tag('strong', get_string('coursename', 'local_annualtrainingforecast')),
        format_string($data->name)
    ];
    
    // Get category name
    $category = \core_course_category::get($data->category);
    $table->data[] = [
        html_writer::tag('strong', get_string('coursecategory')),
        $category->get_formatted_name()
    ];
    
    $table->data[] = [
        html_writer::tag('strong', get_string('startdate', 'local_annualtrainingforecast')),
        userdate($data->startdate, get_string('strftimedatefullshort', 'langconfig'))
    ];
    
    $table->data[] = [
        html_writer::tag('strong', get_string('enddate', 'local_annualtrainingforecast')),
        userdate($data->enddate, get_string('strftimedatefullshort', 'langconfig'))
    ];
    
    $statusoptions = [
        0 => get_string('status_upcoming', 'local_annualtrainingforecast'),
        1 => get_string('status_inprogress', 'local_annualtrainingforecast'),
        2 => get_string('status_completed', 'local_annualtrainingforecast'),
        3 => get_string('status_cancelled', 'local_annualtrainingforecast')
    ];
    
    $table->data[] = [
        html_writer::tag('strong', get_string('status', 'local_annualtrainingforecast')),
        $statusoptions[$data->status]
    ];
    
    echo html_writer::table($table);
    
    // If we're copying materials, show what will be copied
    if (!empty($data->copymaterials) && $parentmoodlecourseid) {
        echo html_writer::tag('h4', get_string('contenttocopy', 'local_annualtrainingforecast'));
        
        $parentmoodlecourse = $DB->get_record('course', ['id' => $parentmoodlecourseid]);
        
        if ($parentmoodlecourse) {
            // Get course modules
            $modinfo = get_fast_modinfo($parentmoodlecourseid);
            $sections = $modinfo->get_section_info_all();
            
            $activitycount = 0;
            $activitylist = [];
            
            foreach ($sections as $section) {
                if (!empty($section->sequence)) {
                    $cmids = explode(',', $section->sequence);
                    foreach ($cmids as $cmid) {
                        $cm = $modinfo->get_cm($cmid);
                        $activitylist[] = $cm->get_formatted_name();
                        $activitycount++;
                    }
                }
            }
            
            // Get question bank statistics
            $sql = "SELECT COUNT(DISTINCT q.id) as questioncount
                    FROM {question} q
                    JOIN {question_versions} qv ON qv.questionid = q.id
                    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    JOIN {context} ctx ON ctx.id = qc.contextid
                    WHERE ctx.contextlevel = 50 AND ctx.instanceid = :courseid
                    AND q.parent = 0";
            
            $questionstats = $DB->get_record_sql($sql, ['courseid' => $parentmoodlecourseid]);
            $questioncount = $questionstats ? $questionstats->questioncount : 0;
            
            $previewtable = new html_table();
            $previewtable->attributes['class'] = 'generaltable content-preview-table';
            
            $previewtable->data[] = [
                html_writer::tag('strong', get_string('activities', 'moodle')),
                $activitycount
            ];
            
            $previewtable->data[] = [
                html_writer::tag('strong', get_string('questions', 'question')),
                $questioncount
            ];
            
            echo html_writer::table($previewtable);
            
            // Show activity list
            if (!empty($activitylist)) {
                echo html_writer::tag('h5', get_string('activitieslist', 'local_annualtrainingforecast'));
                echo html_writer::start_tag('ul', ['class' => 'activity-list']);
                foreach ($activitylist as $activityname) {
                    echo html_writer::tag('li', $activityname);
                }
                echo html_writer::end_tag('ul');
            }
        }
    } else if (!empty($data->linkexisting) && !empty($data->existingcourseid)) {
        echo html_writer::tag('p', get_string('linkingtoexisting', 'local_annualtrainingforecast'), ['class' => 'alert alert-info']);
    } else {
        echo html_writer::tag('p', get_string('nocontent', 'local_annualtrainingforecast'), ['class' => 'alert alert-warning']);
    }
    
    // Create a confirmation form
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/annualtrainingforecast/manage.php'),
        'class' => 'mform preview-confirm-form'
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey()
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'action',
        'value' => 'add'
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'type',
        'value' => 'iteration'
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'parentid',
        'value' => $parentid
    ]);
    
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'preview',
        'value' => '2'
    ]);
    
    // Add all form data as hidden fields
    foreach ((array)$data as $key => $value) {
        if (!is_object($value) && !is_array($value)) {
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $key,
                'value' => $value
            ]);
        }
    }
    
    echo html_writer::start_tag('div', ['class' => 'form-buttons']);
    
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'submitbutton',
        'value' => get_string('createinstance', 'local_annualtrainingforecast'),
        'class' => 'btn btn-primary'
    ]);
    
    echo html_writer::tag('a', get_string('cancel'), [
        'href' => new moodle_url('/local/annualtrainingforecast/manage.php'),
        'class' => 'btn btn-secondary ml-2'
    ]);
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');

    echo $OUTPUT->footer();
}
