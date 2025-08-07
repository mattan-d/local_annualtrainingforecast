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
 * API class for Annual Training Forecast
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * API class
 */
class api {
    /**
     * Get all course iterations for Gantt chart
     *
     * @param string $viewtype year, halfyear, quarter
     * @return array
     */
    public static function get_gantt_data($viewtype = 'year') {
        global $DB;

        // Determine date range based on view type
        $now = time();
        $startdate = $now;
        $enddate = $now;

        switch ($viewtype) {
            case 'year':
                // Start from beginning of current year
                $startdate = strtotime('first day of January this year 00:00:00', $now);
                $enddate = strtotime('last day of December this year 23:59:59', $now);
                break;
            case 'halfyear':
                // Start from beginning of current half-year
                $month = date('n', $now);
                if ($month <= 6) {
                    $startdate = strtotime('first day of January this year 00:00:00', $now);
                    $enddate = strtotime('last day of June this year 23:59:59', $now);
                } else {
                    $startdate = strtotime('first day of July this year 00:00:00', $now);
                    $enddate = strtotime('last day of December this year 23:59:59', $now);
                }
                break;
            case 'quarter':
                // Start from beginning of current quarter
                $month = date('n', $now);
                if ($month <= 3) {
                    $startdate = strtotime('first day of January this year 00:00:00', $now);
                    $enddate = strtotime('last day of March this year 23:59:59', $now);
                } else if ($month <= 6) {
                    $startdate = strtotime('first day of April this year 00:00:00', $now);
                    $enddate = strtotime('last day of June this year 23:59:59', $now);
                } else if ($month <= 9) {
                    $startdate = strtotime('first day of July this year 00:00:00', $now);
                    $enddate = strtotime('last day of September this year 23:59:59', $now);
                } else {
                    $startdate = strtotime('first day of October this year 00:00:00', $now);
                    $enddate = strtotime('last day of December this year 23:59:59', $now);
                }
                break;
        }

        // Get all iterations within the date range
        $sql = "SELECT i.*, c.name as parentname, mc.startdate as moodle_startdate, mc.enddate as moodle_enddate
                FROM {local_atf_iterations} i
                JOIN {local_atf_courses} c ON i.parentid = c.id
                LEFT JOIN {course} mc ON i.moodlecourseid = mc.id
                WHERE (i.startdate BETWEEN :startdate1 AND :enddate1)
                   OR (i.enddate BETWEEN :startdate2 AND :enddate2)
                   OR (i.startdate <= :startdate3 AND i.enddate >= :enddate3)
                ORDER BY i.startdate ASC";

        $params = [
            'startdate1' => $startdate,
            'enddate1' => $enddate,
            'startdate2' => $startdate,
            'enddate2' => $enddate,
            'startdate3' => $startdate,
            'enddate3' => $enddate
        ];

        $iterations = $DB->get_records_sql($sql, $params);

        // Format data for Gantt chart
        $result = [
            'timerange' => [
                'start' => $startdate,
                'end' => $enddate
            ],
            'items' => []
        ];

        foreach ($iterations as $iteration) {
            $statusclasses = [
                0 => 'upcoming',
                1 => 'inprogress',
                2 => 'completed',
                3 => 'cancelled'
            ];

            // Use Moodle course dates if available, otherwise use the ones from our table
            $itemStartDate = !empty($iteration->moodle_startdate) ? $iteration->moodle_startdate : $iteration->startdate;
            $itemEndDate = !empty($iteration->moodle_enddate) ? $iteration->moodle_enddate : $iteration->enddate;

            // If end date is not set or is before start date, calculate it based on start date and duration
            if (empty($itemEndDate) || $itemEndDate <= $itemStartDate) {
                // Get parent course to get duration
                $parentCourse = $DB->get_record('local_atf_courses', ['id' => $iteration->parentid]);
                if ($parentCourse && !empty($parentCourse->duration)) {
                    // Set end date to be exactly the duration days after start date
                    // Use start of day for consistent calculations
                    $startDay = strtotime(date('Y-m-d 00:00:00', $itemStartDate));
                    $itemEndDate = strtotime('+' . ($parentCourse->duration - 1) . ' days', $startDay);
                    // Set to end of day
                    $itemEndDate = strtotime('23:59:59', $itemEndDate);
                } else {
                    // Default to 1 day if no duration is set
                    $startDay = strtotime(date('Y-m-d 00:00:00', $itemStartDate));
                    $itemEndDate = $startDay;
                    $itemEndDate = strtotime('23:59:59', $itemEndDate);
                }
            }

            // Normalize dates to start/end of day for consistent calculations
            $itemStartDate = strtotime(date('Y-m-d 00:00:00', $itemStartDate));
            $itemEndDate = strtotime(date('Y-m-d 23:59:59', $itemEndDate));

            // Ensure dates are valid
            if (empty($itemStartDate)) {
                $itemStartDate = time();
                $itemStartDate = strtotime(date('Y-m-d 00:00:00', $itemStartDate));
            }

            if (empty($itemEndDate) || $itemEndDate < $itemStartDate) {
                $itemEndDate = $itemStartDate;
                $itemEndDate = strtotime(date('Y-m-d 23:59:59', $itemEndDate));
            }

            // Update the iteration record with the correct dates if they've changed
            if ($itemStartDate != $iteration->startdate || $itemEndDate != $iteration->enddate) {
                $updateRecord = new \stdClass();
                $updateRecord->id = $iteration->id;
                $updateRecord->startdate = $itemStartDate;
                $updateRecord->enddate = $itemEndDate;
                $updateRecord->timemodified = time();
                $DB->update_record('local_atf_iterations', $updateRecord);

                // Update the iteration object for use in this function
                $iteration->startdate = $itemStartDate;
                $iteration->enddate = $itemEndDate;
            }

            // Ensure the item is within the view range
            $visibleStartDate = max($startdate, $itemStartDate);
            $visibleEndDate = min($enddate, $itemEndDate);

            // Only add the item if it's at least partially visible in the current view
            if ($visibleEndDate >= $visibleStartDate) {
                $result['items'][] = [
                    'id' => $iteration->id,
                    'parentid' => $iteration->parentid,
                    'name' => $iteration->name,
                    'parentname' => $iteration->parentname,
                    'start' => $itemStartDate,
                    'end' => $itemEndDate,
                    'status' => $iteration->status,
                    'completed' => $iteration->completed,
                    'statusclass' => $statusclasses[$iteration->status]
                ];
            }
        }

        return $result;
    }

    /**
     * Update iteration status
     *
     * @param int $id Iteration ID
     * @param int $status New status
     * @param int $completed Completion status
     * @return bool
     */
    public static function update_iteration_status($id, $status, $completed) {
        global $DB, $USER;

        $iteration = $DB->get_record('local_atf_iterations', ['id' => $id], '*', MUST_EXIST);

        $iteration->status = $status;
        $iteration->completed = $completed;
        $iteration->timemodified = time();
        $iteration->modifiedby = $USER->id;

        return $DB->update_record('local_atf_iterations', $iteration);
    }

    /**
     * Export Gantt data to Excel
     *
     * @param string $viewtype
     * @return string Path to the generated Excel file
     */
    public static function export_to_excel($viewtype) {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');

        $data = self::get_gantt_data($viewtype);

        // Create workbook
        $workbook = new \MoodleExcelWorkbook('training_forecast_' . $viewtype . '_' . date('Y-m-d'));
        $worksheet = $workbook->add_worksheet(get_string('pluginname', 'local_annualtrainingforecast'));

        // Add headers
        $headers = [
            get_string('coursename', 'local_annualtrainingforecast'),
            get_string('parentcourse', 'local_annualtrainingforecast'),
            get_string('startdate', 'local_annualtrainingforecast'),
            get_string('enddate', 'local_annualtrainingforecast'),
            get_string('status', 'local_annualtrainingforecast'),
            get_string('completed', 'local_annualtrainingforecast')
        ];

        $col = 0;
        foreach ($headers as $header) {
            $worksheet->write(0, $col, $header);
            $col++;
        }

        // Add data
        $row = 1;
        foreach ($data['items'] as $item) {
            $statusstrings = [
                0 => get_string('status_upcoming', 'local_annualtrainingforecast'),
                1 => get_string('status_inprogress', 'local_annualtrainingforecast'),
                2 => get_string('status_completed', 'local_annualtrainingforecast'),
                3 => get_string('status_cancelled', 'local_annualtrainingforecast')
            ];

            $completedtext = $item['completed'] ?
                get_string('completed', 'local_annualtrainingforecast') :
                get_string('notcompleted', 'local_annualtrainingforecast');

            $worksheet->write($row, 0, $item['name']);
            $worksheet->write($row, 1, $item['parentname']);
            $worksheet->write($row, 2, userdate($item['start'], get_string('strftimedatefullshort', 'core_langconfig')));
            $worksheet->write($row, 3, userdate($item['end'], get_string('strftimedatefullshort', 'core_langconfig')));
            $worksheet->write($row, 4, $statusstrings[$item['status']]);
            $worksheet->write($row, 5, $completedtext);

            $row++;
        }

        // Close workbook and return path
        $workbook->close();
        return $workbook->get_filepath();
    }
}
