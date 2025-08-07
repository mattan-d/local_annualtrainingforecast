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
 * Course form
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annualtrainingforecast\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Course form class
 */
class course_form extends \moodleform {
    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $DB;
        
        $mform = $this->_form;
        $course = $this->_customdata['course'] ?? null;
        $action = $this->_customdata['action'] ?? 'add';

        // Add a radio button to choose between creating a new course or selecting an existing one
        $courseOptions = [
            'new' => get_string('createnewcourse', 'local_annualtrainingforecast'),
            'existing' => get_string('selectexistingcourse', 'local_annualtrainingforecast')
        ];
        
        $mform->addElement('header', 'coursesourcehdr', get_string('coursesource', 'local_annualtrainingforecast'));
        $mform->addElement('select', 'coursesource', get_string('coursesource', 'local_annualtrainingforecast'), $courseOptions);
        $mform->setDefault('coursesource', 'new');
        
        // New course section
        $mform->addElement('header', 'newcoursehdr', get_string('newcoursedetails', 'local_annualtrainingforecast'));
        
        // Course name
        $mform->addElement('text', 'name', get_string('coursename', 'local_annualtrainingforecast'), 
            ['size' => '64', 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        
        // Description - using a simple textarea instead of editor to avoid complexity
        $mform->addElement('textarea', 'description', get_string('coursedescription', 'local_annualtrainingforecast'), 
            ['rows' => 10, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        
        // Duration
        $mform->addElement('text', 'duration', get_string('courseduration', 'local_annualtrainingforecast'));
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', 1);
        $mform->addHelpButton('duration', 'courseduration', 'local_annualtrainingforecast');
        
        // Existing course section
        $mform->addElement('header', 'existingcoursehdr', get_string('existingcoursedetails', 'local_annualtrainingforecast'));
        
        // Get all available courses
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, cc.name as categoryname
             FROM {course} c
             JOIN {course_categories} cc ON c.category = cc.id
             WHERE c.id != :siteid
             ORDER BY cc.name, c.fullname",
            ['siteid' => SITEID]
        );
        
        $courseOptions = ['' => get_string('choosedots')];
        foreach ($courses as $c) {
            $courseOptions[$c->id] = $c->categoryname . ' / ' . $c->fullname . ' (' . $c->shortname . ')';
        }
        
        $mform->addElement('select', 'existingcourseid', get_string('selectcourse', 'local_annualtrainingforecast'), $courseOptions);
        
        // Duration for existing course
        $mform->addElement('text', 'existing_duration', get_string('courseduration', 'local_annualtrainingforecast'));
        $mform->setType('existing_duration', PARAM_INT);
        $mform->setDefault('existing_duration', 1);
        $mform->addHelpButton('existing_duration', 'courseduration', 'local_annualtrainingforecast');
        
        // Hidden fields
        if (!empty($course)) {
            $mform->addElement('hidden', 'id', $course->id);
            $mform->setType('id', PARAM_INT);
        }
        
        // Add hidden fields for action and type
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ALPHA);
        
        $mform->addElement('hidden', 'type', 'course');
        $mform->setType('type', PARAM_ALPHA);
        
        // Action buttons
        $this->add_action_buttons();
        
        // Set default data
        if (!empty($course)) {
            $data = [
                'name' => $course->name,
                'description' => $course->description,
                'duration' => $course->duration
            ];
            
            // If this is an existing Moodle course
            if (!empty($course->moodlecourseid)) {
                $existingcourse = $DB->get_record('course', ['id' => $course->moodlecourseid]);
                if ($existingcourse && $existingcourse->shortname && !preg_match('/^ATF_/', $existingcourse->shortname)) {
                    // This appears to be an existing course (not created by our plugin)
                    $data['coursesource'] = 'existing';
                    $data['existingcourseid'] = $course->moodlecourseid;
                    $data['existing_duration'] = $course->duration;
                } else {
                    // This was created as a new course by our plugin
                    $data['coursesource'] = 'new';
                }
            }
            
            $this->set_data($data);
        }
        
        // JavaScript to handle form sections
        $this->add_form_javascript();
    }
    
    /**
     * Add JavaScript for form behavior
     */
    private function add_form_javascript() {
        global $PAGE;
        
        $js = "
        require(['jquery'], function($) {
            function toggleCourseSections() {
                var courseSource = $('#id_coursesource').val();
                
                if (courseSource === 'new') {
                    // Show new course section, hide existing course section
                    $('#fgroup_id_newcoursehdr').show();
                    $('#fitem_id_name').show();
                    $('#fitem_id_description').show();
                    $('#fitem_id_duration').show();
                    
                    $('#fgroup_id_existingcoursehdr').hide();
                    $('#fitem_id_existingcourseid').hide();
                    $('#fitem_id_existing_duration').hide();
                } else {
                    // Show existing course section, hide new course section
                    $('#fgroup_id_newcoursehdr').hide();
                    $('#fitem_id_name').hide();
                    $('#fitem_id_description').hide();
                    $('#fitem_id_duration').hide();
                    
                    $('#fgroup_id_existingcoursehdr').show();
                    $('#fitem_id_existingcourseid').show();
                    $('#fitem_id_existing_duration').show();
                }
            }
            
            // Initial setup
            toggleCourseSections();
            
            // Update on change
            $('#id_coursesource').change(function() {
                toggleCourseSections();
            });
        });
        ";
        
        $PAGE->requires->js_amd_inline($js);
    }

    /**
     * Validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        // Only validate fields based on the selected course source
        if (isset($data['coursesource']) && $data['coursesource'] === 'new') {
            // Validate new course fields only
            if (empty($data['name'])) {
                $errors['name'] = get_string('required');
            }
            
            if (empty($data['duration']) || !is_numeric($data['duration']) || $data['duration'] <= 0) {
                $errors['duration'] = get_string('required');
            }
        } elseif (isset($data['coursesource']) && $data['coursesource'] === 'existing') {
            // Validate existing course fields only
            if (empty($data['existingcourseid'])) {
                $errors['existingcourseid'] = get_string('required');
            }
            
            if (empty($data['existing_duration']) || !is_numeric($data['existing_duration']) || $data['existing_duration'] <= 0) {
                $errors['existing_duration'] = get_string('required');
            }
        }
        
        return $errors;
    }
}
