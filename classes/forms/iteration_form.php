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
 * Iteration form
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Iteration form class
 */
class iteration_form extends \moodleform {
    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;
        $iteration = $this->_customdata['iteration'];
        $parentid = $this->_customdata['parentid'];

        // Get parent course
        $parentcourse = $DB->get_record('local_atf_courses', ['id' => $parentid], '*', MUST_EXIST);

        // Parent course (display only)
        $mform->addElement('static', 'parentcourse', get_string('parentcourse', 'local_annualtrainingforecast'),
            format_string($parentcourse->name));

        // Iteration name
        $mform->addElement('text', 'name', get_string('coursename', 'local_annualtrainingforecast'),
            ['size' => '64', 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // If new iteration, set default name based on parent course
        if (empty($iteration)) {
            $mform->setDefault('name', $parentcourse->name);
        }

        // Course category selector
        $categories = \core_course_category::make_categories_list('moodle/course:create');
        $mform->addElement('autocomplete', 'category', get_string('category'), $categories);
        $mform->addHelpButton('category', 'category', 'local_annualtrainingforecast');
        $mform->addRule('category', get_string('required'), 'required', null, 'client');
        
        // Set default category
        if (empty($iteration)) {
            // Try to get the parent course's category first
            if (!empty($parentcourse->moodlecourseid)) {
                $parentmoodlecourse = $DB->get_record('course', ['id' => $parentcourse->moodlecourseid], 'category');
                if ($parentmoodlecourse) {
                    $mform->setDefault('category', $parentmoodlecourse->category);
                } else {
                    $mform->setDefault('category', get_config('moodlecourse', 'category'));
                }
            } else {
                // Fall back to default category
                $mform->setDefault('category', get_config('moodlecourse', 'category'));
            }
        } else if (!empty($iteration->moodlecourseid)) {
            // For existing iterations, get the current course category
            $iterationmoodlecourse = $DB->get_record('course', ['id' => $iteration->moodlecourseid], 'category');
            if ($iterationmoodlecourse) {
                $mform->setDefault('category', $iterationmoodlecourse->category);
            }
        }

        $mform->addElement('advcheckbox', 'copymaterials', 
            get_string('copymaterials', 'local_annualtrainingforecast'),
            get_string('copymaterials_help', 'local_annualtrainingforecast'));
        $mform->setDefault('copymaterials', 1); // Default to checked
        
        $mform->addElement('advcheckbox', 'linkexisting', 
            get_string('linkexisting', 'local_annualtrainingforecast'),
            get_string('linkexisting_help', 'local_annualtrainingforecast'));
        $mform->setDefault('linkexisting', 0);
        
        // Get all available courses (excluding site course)
        $courses = $DB->get_records_sql(
            "SELECT id, fullname, shortname, category 
             FROM {course} 
             WHERE id > 1 
             ORDER BY fullname"
        );
        
        $courseoptions = [0 => get_string('choosedots')];
        foreach ($courses as $c) {
            $category = \core_course_category::get($c->category, IGNORE_MISSING);
            $categoryname = $category ? $category->get_formatted_name() : '';
            $courseoptions[$c->id] = $categoryname . ' / ' . format_string($c->fullname) . ' (' . $c->shortname . ')';
        }
        
        $mform->addElement('autocomplete', 'existingcourseid', 
            get_string('existingcourse', 'local_annualtrainingforecast'), 
            $courseoptions);
        $mform->hideIf('existingcourseid', 'linkexisting', 'notchecked');
        
        $mform->hideIf('copymaterials', 'linkexisting', 'checked');

        // Start date
        $mform->addElement('date_selector', 'startdate', get_string('startdate', 'local_annualtrainingforecast'));
        $mform->addRule('startdate', get_string('required'), 'required', null, 'client');

        // End date
        $mform->addElement('date_selector', 'enddate', get_string('enddate', 'local_annualtrainingforecast'));
        $mform->addRule('enddate', get_string('required'), 'required', null, 'client');

        // If new iteration and parent course has duration, calculate end date
        if (empty($iteration) && !empty($parentcourse->duration)) {
            $mform->setDefault('enddate', strtotime('+' . $parentcourse->duration . ' days', time()));
        }

        // Status
        $statusoptions = [
            0 => get_string('status_upcoming', 'local_annualtrainingforecast'),
            1 => get_string('status_inprogress', 'local_annualtrainingforecast'),
            2 => get_string('status_completed', 'local_annualtrainingforecast'),
            3 => get_string('status_cancelled', 'local_annualtrainingforecast')
        ];
        $mform->addElement('select', 'status', get_string('status', 'local_annualtrainingforecast'), $statusoptions);
        $mform->setDefault('status', 0);

        // Completed
        $completedoptions = [
            0 => get_string('notcompleted', 'local_annualtrainingforecast'),
            1 => get_string('completed', 'local_annualtrainingforecast')
        ];
        $mform->addElement('select', 'completed', get_string('completed', 'local_annualtrainingforecast'), $completedoptions);
        $mform->setDefault('completed', 0);

        // Hidden fields
        if (!empty($iteration)) {
            $mform->addElement('hidden', 'id', $iteration->id);
            $mform->setType('id', PARAM_INT);
        }

        $mform->addElement('hidden', 'parentid', $parentid);
        $mform->setType('parentid', PARAM_INT);

        // Add hidden fields for action and type
        $mform->addElement('hidden', 'action', $this->_customdata['action'] ?? 'add');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('hidden', 'type', 'iteration');
        $mform->setType('type', PARAM_ALPHA);

        // Action buttons
        $this->add_action_buttons();

        // Set default data
        if (!empty($iteration)) {
            $data = [
                'name' => $iteration->name,
                'startdate' => $iteration->startdate,
                'enddate' => $iteration->enddate,
                'status' => $iteration->status,
                'completed' => $iteration->completed
            ];

            $this->set_data($data);
        }
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

        if (empty($data['name'])) {
            $errors['name'] = get_string('required');
        }

        if ($data['enddate'] < $data['startdate']) {
            $errors['enddate'] = get_string('enddatebeforestartdate', 'local_annualtrainingforecast');
        }

        // Validate category
        if (!empty($data['category'])) {
            try {
                $category = \core_course_category::get($data['category'], IGNORE_MISSING);
                if (!$category) {
                    $errors['category'] = get_string('invalidcategoryid', 'error');
                } else if (!$category->can_create_course()) {
                    $errors['category'] = get_string('nocreateincategory', 'error');
                }
            } catch (\Exception $e) {
                $errors['category'] = get_string('invalidcategoryid', 'error');
            }
        }
        
        if (!empty($data['linkexisting']) && empty($data['existingcourseid'])) {
            $errors['existingcourseid'] = get_string('required');
        }

        return $errors;
    }
}
