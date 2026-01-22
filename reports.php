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
 * Reports page
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check permissions
require_login();
$context = context_system::instance();
require_capability('local/annualtrainingforecast:viewforecast', $context);

// Get selected year (default to current year)
$year = optional_param('year', date('Y'), PARAM_INT);
// Validate year (allow from 2000 to 10 years in the future)
$currentyear = (int)date('Y');
if ($year < 2000 || $year > ($currentyear + 10)) {
    $year = $currentyear;
}

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/annualtrainingforecast/reports.php', ['year' => $year]));
$PAGE->set_title(get_string('reports', 'local_annualtrainingforecast'));
$PAGE->set_heading(get_string('reports', 'local_annualtrainingforecast'));
$PAGE->set_pagelayout('admin');

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

echo $OUTPUT->tabtree($tabs, 'reports');

// Main container
echo html_writer::start_div('container-fluid mt-4');

// Year navigation
echo html_writer::start_div('row mb-3');
echo html_writer::start_div('col-12 text-center year-navigation');
$previousyear = $year - 1;
$nextyear = $year + 1;
$previousurl = new moodle_url('/local/annualtrainingforecast/reports.php', ['year' => $previousyear]);
$nexturl = new moodle_url('/local/annualtrainingforecast/reports.php', ['year' => $nextyear]);
$currentyearurl = new moodle_url('/local/annualtrainingforecast/reports.php', ['year' => $currentyear]);

echo html_writer::start_tag('div', ['class' => 'btn-group', 'role' => 'group', 'style' => 'display: inline-flex; align-items: center;']);
echo html_writer::link($previousurl, '<i class="fa fa-chevron-left"></i>', ['class' => 'btn btn-outline-secondary', 'title' => get_string('previousyear', 'local_annualtrainingforecast')]);
echo html_writer::tag('span', $year, ['class' => 'btn btn-primary disabled', 'style' => 'min-width: 100px; font-weight: bold; font-size: 1.1em;']);
echo html_writer::link($nexturl, '<i class="fa fa-chevron-right"></i>', ['class' => 'btn btn-outline-secondary', 'title' => get_string('nextyear', 'local_annualtrainingforecast')]);
echo html_writer::end_tag('div');

// Add "Current Year" button if not viewing current year
if ($year != $currentyear) {
    echo ' ';
    echo html_writer::link($currentyearurl, get_string('currentyear', 'local_annualtrainingforecast'), ['class' => 'btn btn-info ml-2']);
}

echo html_writer::end_div(); // year-navigation
echo html_writer::end_div(); // row

// Get report data
global $DB;

// Calculate year boundaries for filtering
$yearstart = mktime(0, 0, 0, 1, 1, $year);
$yearend = mktime(23, 59, 59, 12, 31, $year);

// Status summary
$sql = "SELECT status, COUNT(*) as count
        FROM {local_atf_iterations}
        WHERE startdate >= :yearstart AND startdate <= :yearend
        GROUP BY status
        ORDER BY status";
$statusSummary = $DB->get_records_sql($sql, ['yearstart' => $yearstart, 'yearend' => $yearend]);

// Completion summary
$sql = "SELECT completed, COUNT(*) as count
        FROM {local_atf_iterations}
        WHERE startdate >= :yearstart AND startdate <= :yearend
        GROUP BY completed
        ORDER BY completed";
$completionSummary = $DB->get_records_sql($sql, ['yearstart' => $yearstart, 'yearend' => $yearend]);

// Monthly distribution - Cross-database compatible
$sql = "SELECT id, startdate
        FROM {local_atf_iterations}
        WHERE startdate >= :yearstart AND startdate <= :yearend";
$iterations = $DB->get_records_sql($sql, ['yearstart' => $yearstart, 'yearend' => $yearend]);
$monthlyData = [];

foreach ($iterations as $iteration) {
    if ($iteration->startdate > 0) {
        $month = date('Y-m', $iteration->startdate);
        if (!isset($monthlyData[$month])) {
            $monthlyData[$month] = 0;
        }
        $monthlyData[$month]++;
    }
}

// Convert to objects for consistency with other queries
$monthlyDistribution = [];
foreach ($monthlyData as $month => $count) {
    $obj = new stdClass();
    $obj->month = $month;
    $obj->count = $count;
    $monthlyDistribution[] = $obj;
}

// Sort by month
usort($monthlyDistribution, function($a, $b) {
    return strcmp($a->month, $b->month);
});

// Display reports
echo html_writer::start_div('reports-container');

// Status summary
echo html_writer::tag('h3', get_string('statussummary', 'local_annualtrainingforecast'));
echo html_writer::start_tag('table', ['class' => 'table table-striped']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('status', 'local_annualtrainingforecast'));
echo html_writer::tag('th', get_string('count', 'local_annualtrainingforecast'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
$statusStrings = [
    0 => get_string('status_upcoming', 'local_annualtrainingforecast'),
    1 => get_string('status_inprogress', 'local_annualtrainingforecast'),
    2 => get_string('status_completed', 'local_annualtrainingforecast'),
    3 => get_string('status_cancelled', 'local_annualtrainingforecast')
];

foreach ($statusStrings as $statusId => $statusName) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $statusName);
    echo html_writer::tag('td', isset($statusSummary[$statusId]) ? $statusSummary[$statusId]->count : 0);
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// Completion summary
echo html_writer::tag('h3', get_string('completionsummary', 'local_annualtrainingforecast'));
echo html_writer::start_tag('table', ['class' => 'table table-striped']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('completed', 'local_annualtrainingforecast'));
echo html_writer::tag('th', get_string('count', 'local_annualtrainingforecast'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
$completionStrings = [
    0 => get_string('notcompleted', 'local_annualtrainingforecast'),
    1 => get_string('completed', 'local_annualtrainingforecast')
];

foreach ($completionStrings as $completionId => $completionName) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $completionName);
    echo html_writer::tag('td', isset($completionSummary[$completionId]) ? $completionSummary[$completionId]->count : 0);
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// Monthly distribution
echo html_writer::tag('h3', get_string('monthlydistribution', 'local_annualtrainingforecast'));
echo html_writer::start_tag('table', ['class' => 'table table-striped']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('month', 'local_annualtrainingforecast'));
echo html_writer::tag('th', get_string('count', 'local_annualtrainingforecast'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($monthlyDistribution as $month) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $month->month);
    echo html_writer::tag('td', $month->count);
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::end_div(); // reports-container

echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();
