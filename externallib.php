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
            'viewtype' => new external_value(PARAM_ALPHA, 'View type (year, halfyear, quarter)', VALUE_DEFAULT, 'year')
        ]);
    }

    /**
     * Get Gantt data
     *
     * @param string $viewtype View type (year, halfyear, quarter)
     * @return array
     */
    public static function get_gantt_data($viewtype = 'year') {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::get_gantt_data_parameters(), [
            'viewtype' => $viewtype
        ]);

        // Context validation
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/annualtrainingforecast:viewforecast', $context);

        // Get data
        $data = \api::get_gantt_data($params['viewtype']);

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
                    'statusclass' => new external_value(PARAM_TEXT, 'Status CSS class')
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
}
