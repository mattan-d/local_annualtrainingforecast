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
     * @param int $year The year to display (default: current year)
     * @return array
     */
    public static function get_gantt_data($viewtype = 'year', $year = null) {
        global $DB;

        // Determine date range based on view type
        $now = time();
        
        // If no year specified, use current year
        if ($year === null) {
            $year = (int)date('Y', $now);
        }
        
        $startdate = $now;
        $enddate = $now;

        switch ($viewtype) {
            case 'year':
                // Start from beginning of specified year
                $startdate = strtotime("first day of January $year 00:00:00");
                $enddate = strtotime("last day of December $year 23:59:59");
                break;
            case 'halfyear':
                // Start from beginning of current half-year (or specified year's half-year)
                if ($year == (int)date('Y', $now)) {
                    // For current year, determine current half-year
                    $month = date('n', $now);
                    if ($month <= 6) {
                        $startdate = strtotime("first day of January $year 00:00:00");
                        $enddate = strtotime("last day of June $year 23:59:59");
                    } else {
                        $startdate = strtotime("first day of July $year 00:00:00");
                        $enddate = strtotime("last day of December $year 23:59:59");
                    }
                } else {
                    // For other years, default to first half
                    $startdate = strtotime("first day of January $year 00:00:00");
                    $enddate = strtotime("last day of June $year 23:59:59");
                }
                break;
            case 'quarter':
                // Start from beginning of current quarter (or specified year's quarter)
                if ($year == (int)date('Y', $now)) {
                    // For current year, determine current quarter
                    $month = date('n', $now);
                    if ($month <= 3) {
                        $startdate = strtotime("first day of January $year 00:00:00");
                        $enddate = strtotime("last day of March $year 23:59:59");
                    } else if ($month <= 6) {
                        $startdate = strtotime("first day of April $year 00:00:00");
                        $enddate = strtotime("last day of June $year 23:59:59");
                    } else if ($month <= 9) {
                        $startdate = strtotime("first day of July $year 00:00:00");
                        $enddate = strtotime("last day of September $year 23:59:59");
                    } else {
                        $startdate = strtotime("first day of October $year 00:00:00");
                        $enddate = strtotime("last day of December $year 23:59:59");
                    }
                } else {
                    // For other years, default to first quarter
                    $startdate = strtotime("first day of January $year 00:00:00");
                    $enddate = strtotime("last day of March $year 23:59:59");
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
                    'statusclass' => $statusclasses[$iteration->status],
                    'isgeneralevent' => 0,
                    'description' => '',
                ];
            }
        }

        // General calendar events (same shape as items for the forecast view).
        $dbman = $DB->get_manager();
        $gentable = new \xmldb_table('local_atf_generalevents');
        if ($dbman->table_exists($gentable)) {
            $generals = $DB->get_records_select(
                'local_atf_generalevents',
                'startdate <= :vr_end AND enddate >= :vr_start',
                [
                    'vr_start' => $startdate,
                    'vr_end' => $enddate,
                ],
                'startdate ASC'
            );
            foreach ($generals as $ge) {
                $result['items'][] = [
                    'id' => (int) $ge->id,
                    'parentid' => 0,
                    'name' => $ge->name,
                    'parentname' => '',
                    'start' => (int) $ge->startdate,
                    'end' => (int) $ge->enddate,
                    'status' => 0,
                    'completed' => 0,
                    'statusclass' => 'general',
                    'isgeneralevent' => 1,
                    'description' => isset($ge->description) ? (string) $ge->description : '',
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
     * Create a general calendar event on the forecast view.
     *
     * @param string $name
     * @param string $description
     * @param int $start Unix timestamp (start of first day)
     * @param int $end Unix timestamp (end of last day)
     * @return array success, message, id
     */
    public static function create_generalevent($name, $description, $start, $end) {
        global $DB, $USER;

        $context = \context_system::instance();
        require_capability('local/annualtrainingforecast:managecourses', $context);

        $name = trim($name);
        if ($name === '') {
            return [
                'success' => false,
                'message' => get_string('eventtitlerequired', 'local_annualtrainingforecast'),
                'id' => 0,
            ];
        }
        if ($start > $end) {
            return [
                'success' => false,
                'message' => get_string('enddatebeforestartdate', 'local_annualtrainingforecast'),
                'id' => 0,
            ];
        }

        $now = time();
        $rec = (object) [
            'name' => $name,
            'description' => $description !== null ? $description : '',
            'startdate' => $start,
            'enddate' => $end,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $USER->id,
            'modifiedby' => $USER->id,
        ];
        $id = $DB->insert_record('local_atf_generalevents', $rec);

        return [
            'success' => true,
            'message' => '',
            'id' => (int) $id,
        ];
    }

    /**
     * Update a general calendar event.
     *
     * @param int $id
     * @param string $name
     * @param string $description
     * @param int $start
     * @param int $end
     * @return array success, message
     */
    public static function update_generalevent($id, $name, $description, $start, $end) {
        global $DB, $USER;

        $context = \context_system::instance();
        require_capability('local/annualtrainingforecast:managecourses', $context);

        $name = trim($name);
        if ($name === '') {
            return [
                'success' => false,
                'message' => get_string('eventtitlerequired', 'local_annualtrainingforecast'),
            ];
        }
        if ($start > $end) {
            return [
                'success' => false,
                'message' => get_string('enddatebeforestartdate', 'local_annualtrainingforecast'),
            ];
        }

        $ge = $DB->get_record('local_atf_generalevents', ['id' => $id], '*', MUST_EXIST);
        $ge->name = $name;
        $ge->description = $description !== null ? $description : '';
        $ge->startdate = $start;
        $ge->enddate = $end;
        $ge->timemodified = time();
        $ge->modifiedby = $USER->id;

        $DB->update_record('local_atf_generalevents', $ge);

        return ['success' => true, 'message' => ''];
    }

    /**
     * Delete a general calendar event.
     *
     * @param int $id
     * @return array success, message
     */
    public static function delete_generalevent($id) {
        global $DB;

        $context = \context_system::instance();
        require_capability('local/annualtrainingforecast:managecourses', $context);

        $DB->delete_records('local_atf_generalevents', ['id' => $id]);

        return ['success' => true, 'message' => ''];
    }

    /**
     * Export Gantt data to Excel
     *
     * @param string $viewtype
     * @param int $year The year to export (default: current year)
     * @return void Sends the file directly to the browser
     */
    public static function export_to_excel($viewtype, $year = null) {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');

        $data = self::get_gantt_data($viewtype, $year);

        $filename = 'training_forecast_' . $viewtype . '_' . date('Y-m-d') . '.xlsx';
        $workbook = new \MoodleExcelWorkbook($filename);
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

            $statustext = !empty($item['isgeneralevent']) ?
                get_string('generalevent', 'local_annualtrainingforecast') :
                $statusstrings[$item['status']];

            $completedcol = !empty($item['isgeneralevent']) ? '—' : $completedtext;

            $worksheet->write($row, 0, $item['name']);
            $worksheet->write($row, 1, !empty($item['isgeneralevent']) ?
                get_string('generalevent', 'local_annualtrainingforecast') : $item['parentname']);
            $worksheet->write($row, 2, userdate($item['start'], get_string('strftimedatefullshort', 'core_langconfig')));
            $worksheet->write($row, 3, userdate($item['end'], get_string('strftimedatefullshort', 'core_langconfig')));
            $worksheet->write($row, 4, $statustext);
            $worksheet->write($row, 5, $completedcol);

            $row++;
        }

        $workbook->close();
    }
}
