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
 * Course manager class for handling course operations
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

/**
 * Course manager class
 */
class course_manager {

    /**
     * Get or create the Annual Training category
     *
     * @return int Category ID
     */
    public static function get_annual_training_category() {
        global $DB;

        // Check if the Annual Training category exists
        $category = $DB->get_record('course_categories', ['name' => 'Annual Training']);

        if (!$category) {
            // Create the category if it doesn't exist
            $data = new \stdClass();
            $data->name = 'Annual Training';
            $data->description = 'Category for Annual Training Forecast courses';
            $data->descriptionformat = FORMAT_HTML;
            $data->parent = 0; // Top level category
            $data->visible = 1;

            // Use core_course_category for Moodle 4+
            $category = \core_course_category::create($data);
            return $category->id;
        }

        return $category->id;
    }

    /**
     * Create a parent course in Moodle
     *
     * @param stdClass $data Form data
     * @return int The created course ID
     * @throws Exception
     */
    public static function create_parent_course($data) {
        global $DB, $USER;

        // Validate category
        $category = \core_course_category::get($data->category, MUST_EXIST);
        if (!$category->can_create_course()) {
            throw new \moodle_exception('cannotcreatecourse', 'error');
        }

        // Create course object
        $coursedata = new \stdClass();
        $coursedata->fullname = $data->name;
        $coursedata->shortname = 'ATF_' . time() . '_' . substr(md5($data->name), 0, 8);
        $coursedata->summary = $data->description;
        $coursedata->summaryformat = FORMAT_HTML;
        $coursedata->category = $data->category;
        $coursedata->visible = 1;
        $coursedata->startdate = time();
        $coursedata->enddate = 0;
        $coursedata->format = 'topics';
        $coursedata->numsections = 10;
        $coursedata->newsitems = 5;
        $coursedata->showgrades = 1;
        $coursedata->showreports = 0;
        $coursedata->maxbytes = 0;
        $coursedata->groupmode = 0;
        $coursedata->groupmodeforce = 0;
        $coursedata->enablecompletion = 0;
        $coursedata->completionnotify = 0;

        // Create the course
        $course = create_course($coursedata);

        return $course->id;
    }

    /**
     * Create a new Moodle course for a course instance by cloning the parent course
     *
     * @param object $data Form data
     * @param int $parentcourseid Parent Moodle course ID
     * @param bool $copymaterials Whether to copy materials from parent course
     * @return int New course ID
     */
    public static function create_course_instance($data, $parentcourseid, $copymaterials = true) {
        global $CFG, $DB, $USER;

        // Get the parent course
        $parentcourse = $DB->get_record('course', ['id' => $parentcourseid], '*', MUST_EXIST);

        // Use the category from form data if provided, otherwise use parent's category
        $categoryid = !empty($data->category) ? $data->category : $parentcourse->category;
        
        // Validate the category
        $category = \core_course_category::get($categoryid, MUST_EXIST);
        if (!$category->can_create_course()) {
            throw new \moodle_exception('cannotcreatecourse', 'error');
        }

        // Create a new course with basic settings
        $coursedata = new \stdClass();
        $coursedata->fullname = $data->name;
        $coursedata->shortname = 'ATF_INST_' . time() . '_' . substr(md5($data->name), 0, 8);
        $coursedata->category = $categoryid;
        $coursedata->summary = $parentcourse->summary;
        $coursedata->summaryformat = $parentcourse->summaryformat;
        $coursedata->visible = 1;
        $coursedata->startdate = $data->startdate;
        $coursedata->enddate = $data->enddate;
        $coursedata->format = $parentcourse->format;
        $coursedata->timecreated = time();
        $coursedata->timemodified = time();
        
        // Copy additional settings from parent course
        $coursedata->numsections = $parentcourse->numsections ?? 10;
        $coursedata->newsitems = $parentcourse->newsitems ?? 5;
        $coursedata->showgrades = $parentcourse->showgrades ?? 1;
        $coursedata->showreports = $parentcourse->showreports ?? 0;
        $coursedata->maxbytes = $parentcourse->maxbytes ?? 0;
        $coursedata->groupmode = $parentcourse->groupmode ?? 0;
        $coursedata->groupmodeforce = $parentcourse->groupmodeforce ?? 0;
        $coursedata->enablecompletion = $parentcourse->enablecompletion ?? 0;
        $coursedata->completionnotify = $parentcourse->completionnotify ?? 0;

        // Create the course
        $newcourse = create_course($coursedata);

        if ($copymaterials) {
            // Now copy content from the parent course
            self::copy_course_content($parentcourseid, $newcourse->id);
        }

        return $newcourse->id;
    }

    /**
     * Copy content from one course to another
     *
     * @param int $fromcourseid Source course ID
     * @param int $tocourseid Destination course ID
     * @return bool Success status
     */
    private static function copy_course_content($fromcourseid, $tocourseid) {
        global $CFG, $USER, $DB;

        try {
            // Get source and destination courses
            $fromcourse = $DB->get_record('course', ['id' => $fromcourseid], '*', MUST_EXIST);
            $tocourse = $DB->get_record('course', ['id' => $tocourseid], '*', MUST_EXIST);

            // Log what we're doing
            debugging("Copying content from course ID {$fromcourseid} to course ID {$tocourseid}", DEBUG_DEVELOPER);

            // First approach: Try using backup and restore
            debugging("Using backup and restore approach", DEBUG_DEVELOPER);
            $result = self::backup_restore_course($fromcourseid, $tocourseid);
            if ($result) {
                debugging("Successfully copied content using backup and restore", DEBUG_DEVELOPER);
                return true;
            }

            // Second approach: Direct SQL copy as last resort
            debugging("Using direct SQL copy approach", DEBUG_DEVELOPER);
            $result = self::direct_copy_course_modules($fromcourseid, $tocourseid);
            if ($result) {
                debugging("Successfully copied content using direct SQL", DEBUG_DEVELOPER);
                return true;
            }

            // If we got here, none of the methods worked
            debugging("All course content copy methods failed", DEBUG_DEVELOPER);
            return false;

        } catch (\Exception $e) {
            debugging('Course content copy error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Copy course content using backup and restore
     *
     * @param int $fromcourseid Source course ID
     * @param int $tocourseid Destination course ID
     * @return bool Success status
     */
    private static function backup_restore_course($fromcourseid, $tocourseid) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        try {
            // Create a backup of the source course
            debugging("Creating backup of course {$fromcourseid}", DEBUG_DEVELOPER);

            // Prepare unique filename
            $backupbasepath = $CFG->tempdir . '/backup/';
            if (!file_exists($backupbasepath)) {
                mkdir($backupbasepath, 0777, true);
            }
            $backupfilename = 'backup_' . time() . '_' . random_string(10) . '.mbz';
            $backupfilepath = $backupbasepath . $backupfilename;

            // Create the backup
            $bc = new \backup_controller(
                \backup::TYPE_1COURSE,
                $fromcourseid,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id
            );

            // Configure the backup settings
            $plan = $bc->get_plan();

            // Set the plan to include everything except users
            if ($plan->setting_exists('users')) {
                $plan->get_setting('users')->set_value(false);
            }
            if ($plan->setting_exists('anonymize')) {
                $plan->get_setting('anonymize')->set_value(false);
            }
            if ($plan->setting_exists('role_assignments')) {
                $plan->get_setting('role_assignments')->set_value(false);
            }
            if ($plan->setting_exists('activities')) {
                $plan->get_setting('activities')->set_value(true);
            }
            if ($plan->setting_exists('blocks')) {
                $plan->get_setting('blocks')->set_value(true);
            }
            if ($plan->setting_exists('filters')) {
                $plan->get_setting('filters')->set_value(true);
            }
            if ($plan->setting_exists('comments')) {
                $plan->get_setting('comments')->set_value(false);
            }
            if ($plan->setting_exists('badges')) {
                $plan->get_setting('badges')->set_value(false);
            }
            if ($plan->setting_exists('calendarevents')) {
                $plan->get_setting('calendarevents')->set_value(false);
            }
            if ($plan->setting_exists('userscompletion')) {
                $plan->get_setting('userscompletion')->set_value(false);
            }
            if ($plan->setting_exists('logs')) {
                $plan->get_setting('logs')->set_value(false);
            }
            if ($plan->setting_exists('grade_histories')) {
                $plan->get_setting('grade_histories')->set_value(false);
            }
            // Ensure question bank is included
            if ($plan->setting_exists('questionbank')) {
                $plan->get_setting('questionbank')->set_value(true);
                debugging("Question bank backup setting enabled", DEBUG_DEVELOPER);
            }
            if ($plan->setting_exists('groups')) {
                $plan->get_setting('groups')->set_value(false);
            }
            if ($plan->setting_exists('competencies')) {
                $plan->get_setting('competencies')->set_value(false);
            }

            foreach ($plan->get_tasks() as $task) {
                $settings = $task->get_settings();
                foreach ($settings as $setting) {
                    if ($setting->get_name() == 'questionbank' || 
                        $setting->get_name() == 'userinfo') {
                        if ($setting->get_name() == 'questionbank') {
                            $setting->set_value(true);
                            debugging("Activity-level question bank enabled for task: " . $task->get_name(), DEBUG_DEVELOPER);
                        } else if ($setting->get_name() == 'userinfo') {
                            $setting->set_value(false);
                        }
                    }
                }
            }

            // Execute the backup
            $bc->execute_plan();

            // Get the backup file
            $results = $bc->get_results();
            $file = $results['backup_destination'];

            // Save the backup file to the filesystem
            debugging("Saving backup file to {$backupfilepath}", DEBUG_DEVELOPER);
            $file->copy_content_to($backupfilepath);

            // Clean up the backup controller
            $bc->destroy();

            // Now restore to the destination course
            debugging("Restoring backup to course {$tocourseid}", DEBUG_DEVELOPER);
            $rc = new \restore_controller(
                $backupfilepath,
                $tocourseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_EXISTING_ADDING
            );

            // Check if the restore is possible
            if ($rc->get_status() == \backup::STATUS_REQUIRE_CONV) {
                $rc->convert();
            }

            // Configure the restore settings
            $plan = $rc->get_plan();

            if ($plan->setting_exists('users')) {
                $plan->get_setting('users')->set_value(false);
            }
            if ($plan->setting_exists('role_assignments')) {
                $plan->get_setting('role_assignments')->set_value(false);
            }
            if ($plan->setting_exists('activities')) {
                $plan->get_setting('activities')->set_value(true);
            }
            if ($plan->setting_exists('blocks')) {
                $plan->get_setting('blocks')->set_value(true);
            }
            if ($plan->setting_exists('filters')) {
                $plan->get_setting('filters')->set_value(true);
            }
            if ($plan->setting_exists('comments')) {
                $plan->get_setting('comments')->set_value(false);
            }
            if ($plan->setting_exists('badges')) {
                $plan->get_setting('badges')->set_value(false);
            }
            if ($plan->setting_exists('calendarevents')) {
                $plan->get_setting('calendarevents')->set_value(false);
            }
            if ($plan->setting_exists('userscompletion')) {
                $plan->get_setting('userscompletion')->set_value(false);
            }
            if ($plan->setting_exists('logs')) {
                $plan->get_setting('logs')->set_value(false);
            }
            if ($plan->setting_exists('grade_histories')) {
                $plan->get_setting('grade_histories')->set_value(false);
            }
            // Ensure question bank is restored
            if ($plan->setting_exists('questionbank')) {
                $plan->get_setting('questionbank')->set_value(true);
                debugging("Question bank restore setting enabled", DEBUG_DEVELOPER);
            }
            if ($plan->setting_exists('groups')) {
                $plan->get_setting('groups')->set_value(false);
            }
            if ($plan->setting_exists('competencies')) {
                $plan->get_setting('competencies')->set_value(false);
            }

            foreach ($plan->get_tasks() as $task) {
                $settings = $task->get_settings();
                foreach ($settings as $setting) {
                    if ($setting->get_name() == 'questionbank' || 
                        $setting->get_name() == 'userinfo') {
                        if ($setting->get_name() == 'questionbank') {
                            $setting->set_value(true);
                            debugging("Activity-level question bank restore enabled for task: " . $task->get_name(), DEBUG_DEVELOPER);
                        } else if ($setting->get_name() == 'userinfo') {
                            $setting->set_value(false);
                        }
                    }
                }
            }

            // Execute precheck
            if (!$rc->execute_precheck()) {
                $precheckresults = $rc->get_precheck_results();
                if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                    foreach ($precheckresults['errors'] as $error) {
                        debugging('Restore precheck error: ' . $error, DEBUG_DEVELOPER);
                    }
                }

                $rc->destroy();
                if (file_exists($backupfilepath)) {
                    unlink($backupfilepath);
                }
                return false;
            }

            // Execute the restore
            $rc->execute_plan();

            // Clean up the restore controller
            $rc->destroy();

            // Delete the temporary backup file
            if (file_exists($backupfilepath)) {
                unlink($backupfilepath);
            }

            // Rebuild course cache
            rebuild_course_cache($tocourseid);

            debugging("Course content and question bank copied successfully", DEBUG_DEVELOPER);
            return true;
        } catch (\Exception $e) {
            debugging('Backup/restore error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            // Clean up any temporary files
            if (isset($backupfilepath) && file_exists($backupfilepath)) {
                unlink($backupfilepath);
            }
            return false;
        }
    }

    /**
     * Direct copy of course modules from one course to another using SQL
     * This is a last resort method when other methods fail
     *
     * @param int $fromcourseid Source course ID
     * @param int $tocourseid Destination course ID
     * @return bool Success status
     */
    private static function direct_copy_course_modules($fromcourseid, $tocourseid) {
        global $DB;

        debugging("Performing direct SQL copy of modules from course {$fromcourseid} to {$tocourseid}", DEBUG_DEVELOPER);

        // Start a transaction to ensure data integrity
        $transaction = $DB->start_delegated_transaction();

        try {
            // Get all sections from source course
            $fromsections = $DB->get_records('course_sections', ['course' => $fromcourseid], 'section ASC');
            $tosections = $DB->get_records('course_sections', ['course' => $tocourseid], 'section ASC');

            // Create a mapping of section numbers to IDs for the destination course
            $sectionmap = [];
            foreach ($tosections as $section) {
                $sectionmap[$section->section] = $section->id;
            }

            // Create any missing sections in the destination course
            foreach ($fromsections as $section) {
                if (!isset($sectionmap[$section->section])) {
                    // Create this section in the destination course
                    $newsection = new \stdClass();
                    $newsection->course = $tocourseid;
                    $newsection->section = $section->section;
                    $newsection->name = $section->name;
                    $newsection->summary = $section->summary;
                    $newsection->summaryformat = $section->summaryformat;
                    $newsection->visible = $section->visible;
                    $newsection->availability = $section->availability;
                    $newsection->timemodified = time();

                    $sectionid = $DB->insert_record('course_sections', $newsection);
                    $sectionmap[$section->section] = $sectionid;
                    debugging("Created missing section {$section->section} with ID {$sectionid}", DEBUG_DEVELOPER);
                }
            }

            // Create a mapping of old module IDs to new module IDs
            $modulemap = [];

            // Create a mapping of section IDs to their sequence arrays
            $sectionsequences = [];
            foreach ($sectionmap as $sectionnum => $sectionid) {
                $sectionsequences[$sectionid] = [];
            }

            // Get all modules from source course, ordered by section and sequence
            $sql = "SELECT cm.*, cs.section as sectionnum, cs.sequence
                    FROM {course_modules} cm
                    JOIN {course_sections} cs ON cm.section = cs.id
                    WHERE cm.course = :courseid
                    ORDER BY cs.section";

            $modules = $DB->get_records_sql($sql, ['courseid' => $fromcourseid]);
            debugging("Found " . count($modules) . " modules to copy", DEBUG_DEVELOPER);

            // Process modules in the correct order
            foreach ($modules as $module) {
                // Get the module type
                $moduletype = $DB->get_field('modules', 'name', ['id' => $module->module]);
                debugging("Copying {$moduletype} module with ID {$module->id}", DEBUG_DEVELOPER);

                // Get the module instance
                $moduledata = $DB->get_record($moduletype, ['id' => $module->instance]);

                if (!$moduledata) {
                    debugging("Could not find {$moduletype} instance with ID {$module->instance}", DEBUG_DEVELOPER);
                    continue;
                }

                // Create a copy of the module instance
                unset($moduledata->id);
                $moduledata->course = $tocourseid;
                $moduledata->timemodified = time();

                $newinstanceid = $DB->insert_record($moduletype, $moduledata);
                debugging("Created new {$moduletype} instance with ID {$newinstanceid}", DEBUG_DEVELOPER);

                // Get the destination section ID
                $newsectionid = $sectionmap[$module->sectionnum] ?? reset($sectionmap);

                // Create a copy of the course module
                $newmodule = new \stdClass();
                $newmodule->course = $tocourseid;
                $newmodule->module = $module->module;
                $newmodule->instance = $newinstanceid;
                $newmodule->section = $newsectionid;
                $newmodule->idnumber = $module->idnumber;
                $newmodule->added = time();
                $newmodule->score = $module->score;
                $newmodule->indent = $module->indent;
                $newmodule->visible = $module->visible;
                $newmodule->visibleoncoursepage = $module->visibleoncoursepage;
                $newmodule->visibleold = $module->visibleold;
                $newmodule->groupmode = $module->groupmode;
                $newmodule->groupingid = $module->groupingid;
                $newmodule->completion = $module->completion;
                $newmodule->completiongradeitemnumber = $module->completiongradeitemnumber;
                $newmodule->completionview = $module->completionview;
                $newmodule->completionexpected = $module->completionexpected;
                $newmodule->showdescription = $module->showdescription;
                $newmodule->availability = $module->availability;

                $newmoduleid = $DB->insert_record('course_modules', $newmodule);
                debugging("Created new course module with ID {$newmoduleid}", DEBUG_DEVELOPER);

                // Store the mapping of old module ID to new module ID
                $modulemap[$module->id] = $newmoduleid;

                // Add the new module ID to the appropriate section sequence
                $sectionsequences[$newsectionid][] = $newmoduleid;
            }

            // Update all section sequences
            foreach ($sectionsequences as $sectionid => $sequence) {
                if (!empty($sequence)) {
                    $sequencestr = implode(',', $sequence);
                    $DB->set_field('course_sections', 'sequence', $sequencestr, ['id' => $sectionid]);
                    debugging("Updated section {$sectionid} sequence to: {$sequencestr}", DEBUG_DEVELOPER);
                }
            }

            // Commit the transaction
            $transaction->allow_commit();

            // Rebuild course cache
            rebuild_course_cache($tocourseid);

            return true;
        } catch (\Exception $e) {
            // Rollback the transaction
            $transaction->rollback($e);
            debugging('Direct copy error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Get the Moodle course ID associated with a parent course
     *
     * @param int $parentid Parent course ID in local_atf_courses
     * @return int|null Moodle course ID or null if not found
     */
    public static function get_moodle_course_id_for_parent($parentid) {
        global $DB;

        $record = $DB->get_record('local_atf_courses', ['id' => $parentid], 'moodlecourseid');
        return $record ? $record->moodlecourseid : null;
    }

    /**
     * Get the Moodle course ID associated with a course instance
     *
     * @param int $iterationid Iteration ID in local_atf_iterations
     * @return int|null Moodle course ID or null if not found
     */
    public static function get_moodle_course_id_for_iteration($iterationid) {
        global $DB;

        $record = $DB->get_record('local_atf_iterations', ['id' => $iterationid], 'moodlecourseid');
        return $record ? $record->moodlecourseid : null;
    }

    /**
     * Get all course data for Gantt chart
     *
     * @return array Array of course data
     */
    public static function get_gantt_data() {
        global $DB;

        $sql = "SELECT 
                    c.id as course_id,
                    c.fullname as course_name,
                    c.summary as course_description,
                    c.enddate - c.startdate as course_duration,
                    i.id as iteration_id,
                    i.name as iteration_name,
                    i.startdate,
                    i.enddate,
                    i.status,
                    i.completed
                FROM {course} c
                LEFT JOIN {local_atf_iterations} i ON c.id = i.parentid
                ORDER BY c.fullname, i.startdate";

        $records = $DB->get_records_sql($sql);

        $courses = [];
        foreach ($records as $record) {
            if (!isset($courses[$record->course_id])) {
                $courses[$record->course_id] = [
                    'id' => $record->course_id,
                    'name' => $record->course_name,
                    'description' => $record->course_description,
                    'duration' => $record->course_duration,
                    'iterations' => []
                ];
            }

            if ($record->iteration_id) {
                $courses[$record->course_id]['iterations'][] = [
                    'id' => $record->iteration_id,
                    'name' => $record->iteration_name,
                    'startdate' => $record->startdate,
                    'enddate' => $record->enddate,
                    'status' => $record->status,
                    'completed' => $record->completed
                ];
            }
        }

        return array_values($courses);
    }
}
