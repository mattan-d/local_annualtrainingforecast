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
 * External API functions
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/annualtrainingforecast/classes/api.php');

/**
 * External API class
 */
class local_annualtrainingforecast_external extends external_api {

    /**
     * Get Gantt data parameters
     *
     * @return external_function_parameters
     */
    public static function get_gantt_data_parameters() {
        return new external_function_parameters([
            'viewtype' => new external_value(PARAM_ALPHA, 'View type (year, halfyear, quarter)', VALUE_DEFAULT, 'year'),
            'year' => new external_value(PARAM_INT, 'Year to display', VALUE_DEFAULT, null)
        ]);
    }

    /**
     * Get Gantt data
     *
     * @param string $viewtype View type (year, halfyear, quarter)
     * @param int $year Year to display (default: current year)
     * @return array
     */
    public static function get_gantt_data($viewtype = 'year', $year = null) {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::get_gantt_data_parameters(), [
            'viewtype' => $viewtype,
            'year' => $year
        ]);

        // Context validation
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/annualtrainingforecast:viewforecast', $context);

        // Get data
        $data = \api::get_gantt_data($params['viewtype'], $params['year']);

        return $data;
    }

    /**
     * Get Gantt data return definition
     *
     * @return external_description
     */
    public static function get_gantt_data_returns() {
        return new external_single_structure([
            'timerange' => new external_single_structure([
                'start' => new external_value(PARAM_INT, 'Start timestamp'),
                'end' => new external_value(PARAM_INT, 'End timestamp')
            ]),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Iteration ID'),
                    'parentid' => new external_value(PARAM_INT, 'Parent course ID'),
                    'name' => new external_value(PARAM_TEXT, 'Iteration name'),
                    'parentname' => new external_value(PARAM_TEXT, 'Parent course name'),
                    'start' => new external_value(PARAM_INT, 'Start timestamp'),
                    'end' => new external_value(PARAM_INT, 'End timestamp'),
                    'status' => new external_value(PARAM_INT, 'Status'),
                    'completed' => new external_value(PARAM_INT, 'Completion status'),
                    'statusclass' => new external_value(PARAM_TEXT, 'Status CSS class'),
                    'isgeneralevent' => new external_value(PARAM_BOOL, 'General calendar event', VALUE_DEFAULT, false),
                    'description' => new external_value(PARAM_RAW, 'Description (general events)', VALUE_DEFAULT, ''),
                ])
            )
        ]);
    }

    /**
     * Update iteration status parameters
     *
     * @return external_function_parameters
     */
    public static function update_iteration_status_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Iteration ID'),
            'status' => new external_value(PARAM_INT, 'Status'),
            'completed' => new external_value(PARAM_INT, 'Completion status')
        ]);
    }

    /**
     * Update iteration status
     *
     * @param int $id Iteration ID
     * @param int $status Status
     * @param int $completed Completion status
     * @return array
     */
    public static function update_iteration_status($id, $status, $completed) {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::update_iteration_status_parameters(), [
            'id' => $id,
            'status' => $status,
            'completed' => $completed
        ]);

        // Context validation
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/annualtrainingforecast:updatestatus', $context);

        // Update status
        $result = \api::update_iteration_status(
            $params['id'],
            $params['status'],
            $params['completed']
        );

        return [
            'success' => $result,
            'message' => $result ? '' : get_string('updatefailed', 'local_annualtrainingforecast')
        ];
    }

    /**
     * Create general calendar event parameters
     *
     * @return external_function_parameters
     */
    public static function create_generalevent_parameters() {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Event title'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'start' => new external_value(PARAM_INT, 'Start timestamp'),
            'end' => new external_value(PARAM_INT, 'End timestamp'),
        ]);
    }

    /**
     * Create general calendar event
     *
     * @param string $name
     * @param string $description
     * @param int $start
     * @param int $end
     * @return array
     */
    public static function create_generalevent($name, $description, $start, $end) {
        $params = self::validate_parameters(self::create_generalevent_parameters(), [
            'name' => $name,
            'description' => $description,
            'start' => $start,
            'end' => $end,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        return \api::create_generalevent(
            $params['name'],
            $params['description'],
            $params['start'],
            $params['end']
        );
    }

    /**
     * @return external_description
     */
    public static function create_generalevent_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Error message'),
            'id' => new external_value(PARAM_INT, 'New record id'),
        ]);
    }

    /**
     * Update general calendar event parameters
     *
     * @return external_function_parameters
     */
    public static function update_generalevent_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Event id'),
            'name' => new external_value(PARAM_TEXT, 'Event title'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'start' => new external_value(PARAM_INT, 'Start timestamp'),
            'end' => new external_value(PARAM_INT, 'End timestamp'),
        ]);
    }

    /**
     * Update general calendar event
     *
     * @param int $id
     * @param string $name
     * @param string $description
     * @param int $start
     * @param int $end
     * @return array
     */
    public static function update_generalevent($id, $name, $description, $start, $end) {
        $params = self::validate_parameters(self::update_generalevent_parameters(), [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'start' => $start,
            'end' => $end,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        return \api::update_generalevent(
            $params['id'],
            $params['name'],
            $params['description'],
            $params['start'],
            $params['end']
        );
    }

    /**
     * @return external_description
     */
    public static function update_generalevent_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Error message'),
        ]);
    }

    /**
     * Delete general calendar event parameters
     *
     * @return external_function_parameters
     */
    public static function delete_generalevent_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Event id'),
        ]);
    }

    /**
     * Delete general calendar event
     *
     * @param int $id
     * @return array
     */
    public static function delete_generalevent($id) {
        $params = self::validate_parameters(self::delete_generalevent_parameters(), [
            'id' => $id,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        return \api::delete_generalevent($params['id']);
    }

    /**
     * @return external_description
     */
    public static function delete_generalevent_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Error message'),
        ]);
    }

    /**
     * Update iteration status return definition
     *
     * @return external_description
     */
    public static function update_iteration_status_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Error message if any')
        ]);
    }

    /**
     * Get forecast data parameters
     *
     * @return external_function_parameters
     */
    public static function get_forecast_data_parameters() {
        return new external_function_parameters([
            'startdate' => new external_value(PARAM_INT, 'Range start timestamp'),
            'enddate' => new external_value(PARAM_INT, 'Range end timestamp'),
            'status' => new external_value(PARAM_ALPHA, 'Status filter', VALUE_DEFAULT, ''),
            'category' => new external_value(PARAM_TEXT, 'Category filter', VALUE_DEFAULT, ''),
            'managerid' => new external_value(PARAM_INT, 'Manager user id filter', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_TEXT, 'Search text', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Get forecast data for Gantt timeline
     *
     * @param int $startdate
     * @param int $enddate
     * @param string $status
     * @param string $category
     * @param int $managerid
     * @param string $search
     * @return array
     */
    public static function get_forecast_data(
        $startdate,
        $enddate,
        $status = '',
        $category = '',
        $managerid = 0,
        $search = ''
    ) {
        $params = self::validate_parameters(self::get_forecast_data_parameters(), [
            'startdate' => $startdate,
            'enddate' => $enddate,
            'status' => $status,
            'category' => $category,
            'managerid' => $managerid,
            'search' => $search,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/annualtrainingforecast:viewforecast', $context);

        return \api::get_forecast_data(
            $params['startdate'],
            $params['enddate'],
            $params['status'],
            $params['category'],
            $params['managerid'],
            $params['search']
        );
    }

    /**
     * Get forecast data return definition
     *
     * @return external_description
     */
    public static function get_forecast_data_returns() {
        $training = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Iteration id'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'category' => new external_value(PARAM_TEXT, 'Parent course'),
            'managername' => new external_value(PARAM_TEXT, 'Manager name'),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp'),
            'enddate' => new external_value(PARAM_INT, 'End timestamp'),
            'status' => new external_value(PARAM_ALPHA, 'Status class'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
        ]);
        $event = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Event id'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'eventdate' => new external_value(PARAM_INT, 'Start timestamp'),
            'enddate' => new external_value(PARAM_INT, 'End timestamp', VALUE_DEFAULT, 0),
            'eventtype' => new external_value(PARAM_ALPHA, 'Event type', VALUE_DEFAULT, 'general'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
        ]);
        return new external_single_structure([
            'trainings' => new external_multiple_structure($training),
            'events' => new external_multiple_structure($event),
        ]);
    }

    /**
     * Get filter options parameters
     *
     * @return external_function_parameters
     */
    public static function get_filters_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get filter dropdown options
     *
     * @return array
     */
    public static function get_filters() {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/annualtrainingforecast:viewforecast', $context);

        return \api::get_filter_options();
    }

    /**
     * Get filter options return definition
     *
     * @return external_description
     */
    public static function get_filters_returns() {
        $kv = new external_single_structure([
            'value' => new external_value(PARAM_RAW, 'Option value'),
            'label' => new external_value(PARAM_TEXT, 'Option label'),
        ]);
        return new external_single_structure([
            'categories' => new external_multiple_structure($kv),
            'managers' => new external_multiple_structure($kv),
            'statuses' => new external_multiple_structure($kv),
        ]);
    }
}
