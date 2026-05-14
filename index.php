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

// Get view type (year, half-year, quarter)
$viewtype = optional_param('view', 'year', PARAM_ALPHA);
$validviews = ['year', 'halfyear', 'quarter'];
if (!in_array($viewtype, $validviews)) {
    $viewtype = 'year';
}

// Get selected year (default to current year)
$year = optional_param('year', date('Y'), PARAM_INT);
// Validate year (allow from 2000 to 10 years in the future)
$currentyear = (int)date('Y');
if ($year < 2000 || $year > ($currentyear + 10)) {
    $year = $currentyear;
}

$canupdatestatus = has_capability('local/annualtrainingforecast:updatestatus', $context);
$canmanagegeneralevents = has_capability('local/annualtrainingforecast:managecourses', $context);

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/annualtrainingforecast/index.php', ['view' => $viewtype, 'year' => $year]));
$PAGE->set_title(get_string('pluginname', 'local_annualtrainingforecast'));
$PAGE->set_heading(get_string('pluginname', 'local_annualtrainingforecast'));
$PAGE->set_pagelayout('admin');

// Add required JavaScript and CSS
$PAGE->requires->css('/local/annualtrainingforecast/styles.css');
$PAGE->requires->js_call_amd('local_annualtrainingforecast/gantt', 'init');

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

// Year navigation
echo html_writer::start_div('row mb-3 atf-year-nav-row');
echo html_writer::start_div('col-12 year-navigation atf-year-nav');
echo html_writer::start_div('atf-year-nav-inner');

$previousyear = $year - 1;
$nextyear = $year + 1;
$previousurl = new moodle_url('/local/annualtrainingforecast/index.php', ['view' => $viewtype, 'year' => $previousyear]);
$nexturl = new moodle_url('/local/annualtrainingforecast/index.php', ['view' => $viewtype, 'year' => $nextyear]);
$currentyearurl = new moodle_url('/local/annualtrainingforecast/index.php', ['view' => $viewtype, 'year' => $currentyear]);

echo html_writer::start_tag('div', [
    'class' => 'atf-year-switcher',
    'role' => 'group',
    // Keep physical order: prev (left) → year → next (right); RTL page would mirror flex otherwise.
    'dir' => 'ltr',
]);
$previoustitle = get_string('previousyear', 'local_annualtrainingforecast');
$nexttitle = get_string('nextyear', 'local_annualtrainingforecast');
echo html_writer::link(
    $previousurl,
    '<span class="sr-only">' . $previoustitle . '</span><i class="fa fa-chevron-left" aria-hidden="true"></i>',
    [
        'class' => 'btn btn-outline-secondary atf-year-nav-btn atf-year-nav-prev',
        'title' => $previoustitle,
        'aria-label' => $previoustitle,
    ]
);
echo html_writer::tag('span', (string) $year, [
    'class' => 'btn btn-primary disabled atf-year-nav-current',
    'aria-current' => 'true',
]);
echo html_writer::link(
    $nexturl,
    '<span class="sr-only">' . $nexttitle . '</span><i class="fa fa-chevron-right" aria-hidden="true"></i>',
    [
        'class' => 'btn btn-outline-secondary atf-year-nav-btn atf-year-nav-next',
        'title' => $nexttitle,
        'aria-label' => $nexttitle,
    ]
);
echo html_writer::end_tag('div'); // atf-year-switcher

// Add "Current Year" button if not viewing current year
if ($year != $currentyear) {
    echo html_writer::link(
        $currentyearurl,
        get_string('currentyear', 'local_annualtrainingforecast'),
        ['class' => 'btn btn-outline-primary atf-year-current-btn']
    );
}

echo html_writer::end_div(); // atf-year-nav-inner
echo html_writer::end_div(); // year-navigation
echo html_writer::end_div(); // row

echo html_writer::start_div('row mb-3 align-items-lg-center atf-controls-row');

// View selector
echo html_writer::start_div('col-lg-7 col-12 view-selector atf-view-selector');
echo html_writer::start_tag('div', ['class' => 'atf-view-toggle', 'role' => 'group']);
$viewoptions = [
    'year' => get_string('yearview', 'local_annualtrainingforecast'),
    'halfyear' => get_string('halfyearview', 'local_annualtrainingforecast'),
    'quarter' => get_string('quarterlyview', 'local_annualtrainingforecast'),
];

foreach ($viewoptions as $key => $label) {
    $url = new moodle_url('/local/annualtrainingforecast/index.php', ['view' => $key, 'year' => $year]);
    $isactive = ($viewtype === $key);
    $class = 'btn atf-view-toggle-btn' . ($isactive ? ' atf-view-toggle-btn-active' : ' atf-view-toggle-btn-inactive');
    $attrs = ['class' => $class];
    if ($isactive) {
        $attrs['aria-current'] = 'true';
    }
    echo html_writer::link($url, $label, $attrs);
}
echo html_writer::end_tag('div');
echo html_writer::end_div(); // view-selector

// Export buttons
echo html_writer::start_div('col-lg-5 col-12 export-buttons atf-export-buttons text-lg-right text-center mt-3 mt-lg-0');
$exportpdfurl = new moodle_url('/local/annualtrainingforecast/export.php', ['format' => 'pdf', 'view' => $viewtype, 'year' => $year]);
$exportexcelurl = new moodle_url('/local/annualtrainingforecast/export.php', ['format' => 'excel', 'view' => $viewtype, 'year' => $year]);

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
echo html_writer::start_div('gantt-container', [
    'id' => 'gantt-chart',
    'data-view' => $viewtype,
    'data-year' => $year,
    'data-can-update' => $canupdatestatus ? '1' : '0',
    'data-can-manage-general' => $canmanagegeneralevents ? '1' : '0',
]);
echo html_writer::end_div(); // gantt-container
echo html_writer::end_div(); // col-12
echo html_writer::end_div(); // row

// Add loading indicator
echo html_writer::div('<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>', 
    'gantt-loading text-center', ['id' => 'gantt-loading']);

echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();
