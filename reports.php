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

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/annualtrainingforecast/reports.php'));
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

// Get report data
global $DB;

// Status summary
$sql = "SELECT status, COUNT(*) as count
        FROM {local_atf_iterations}
        GROUP BY status
        ORDER BY status";
$statusSummary = $DB->get_records_sql($sql);

// Completion summary
$sql = "SELECT completed, COUNT(*) as count
        FROM {local_atf_iterations}
        GROUP BY completed
        ORDER BY completed";
$completionSummary = $DB->get_records_sql($sql);

// Monthly distribution
$sql = "SELECT FROM_UNIXTIME(startdate, '%Y-%m') as month, COUNT(*) as count
        FROM {local_atf_iterations}
        GROUP BY month
        ORDER BY month";
$monthlyDistribution = $DB->get_records_sql($sql);

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

echo $OUTPUT->footer();
