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
 * Main entry point for the plugin
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
$PAGE->set_url(new moodle_url('/local/annualtrainingforecast/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_annualtrainingforecast'));
$PAGE->set_heading(get_string('pluginname', 'local_annualtrainingforecast'));
$PAGE->set_pagelayout('admin');

// Add required JavaScript and CSS
$PAGE->requires->css('/local/annualtrainingforecast/styles.css');
$PAGE->requires->js_call_amd('local_annualtrainingforecast/gantt', 'init');

// Get view type (year, half-year, quarter)
$viewtype = optional_param('view', 'year', PARAM_ALPHA);
$validviews = ['year', 'halfyear', 'quarter'];
if (!in_array($viewtype, $validviews)) {
    $viewtype = 'year';
}

// Output starts here
echo $OUTPUT->header();

// Navigation tabs
$tabs = [
    new tabobject('gantt', new moodle_url('/local/annualtrainingforecast/index.php'), 
        get_string('ganttview', 'local_annualtrainingforecast')),
];

if (has_capability('local/annualtrainingforecast:managecourses', $context)) {
    $tabs[] = new tabobject('manage', new moodle_url('/local/annualtrainingforecast/manage.php'), 
        get_string('managecourses', 'local_annualtrainingforecast'));
    $tabs[] = new tabobject('reports', new moodle_url('/local/annualtrainingforecast/reports.php'), 
        get_string('reports', 'local_annualtrainingforecast'));
}

echo $OUTPUT->tabtree($tabs, 'gantt');

// Main container
echo html_writer::start_div('container-fluid mt-4');
echo html_writer::start_div('row mb-3');

// View selector
echo html_writer::start_div('col-md-6 view-selector');
echo html_writer::start_tag('div', ['class' => 'btn-group']);
$viewoptions = [
    'year' => get_string('yearview', 'local_annualtrainingforecast'),
    'halfyear' => get_string('halfyearview', 'local_annualtrainingforecast'),
    'quarter' => get_string('quarterlyview', 'local_annualtrainingforecast')
];

foreach ($viewoptions as $key => $label) {
    $url = new moodle_url('/local/annualtrainingforecast/index.php', ['view' => $key]);
    $class = ($viewtype == $key) ? 'btn btn-primary' : 'btn btn-outline-secondary';
    echo html_writer::link($url, $label, ['class' => $class]);
}
echo html_writer::end_tag('div');
echo html_writer::end_div(); // view-selector

// Export buttons
echo html_writer::start_div('col-md-6 export-buttons text-right');
$exportpdfurl = new moodle_url('/local/annualtrainingforecast/export.php', ['format' => 'pdf', 'view' => $viewtype]);
$exportexcelurl = new moodle_url('/local/annualtrainingforecast/export.php', ['format' => 'excel', 'view' => $viewtype]);

echo html_writer::link($exportpdfurl, '<i class="fa fa-file-pdf-o"></i> ' . get_string('exportpdf', 'local_annualtrainingforecast'), 
    ['class' => 'btn btn-outline-danger']);
echo ' ';
echo html_writer::link($exportexcelurl, '<i class="fa fa-file-excel-o"></i> ' . get_string('exportexcel', 'local_annualtrainingforecast'), 
    ['class' => 'btn btn-outline-success']);
echo html_writer::end_div(); // export-buttons

echo html_writer::end_div(); // row

// Gantt chart container
echo html_writer::start_div('row');
echo html_writer::start_div('col-12');
echo html_writer::start_div('gantt-container', ['id' => 'gantt-chart', 'data-view' => $viewtype]);
echo html_writer::end_div(); // gantt-container
echo html_writer::end_div(); // col-12
echo html_writer::end_div(); // row

// Add loading indicator
echo html_writer::div('<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>', 
    'gantt-loading text-center', ['id' => 'gantt-loading']);

echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();
