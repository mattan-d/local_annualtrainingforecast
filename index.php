<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Main entry point for the plugin
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/annualtrainingforecast:viewforecast', $context);

$canmanage = has_capability('local/annualtrainingforecast:managecourses', $context);
$canaddevent = $canmanage;
$canreports = $canmanage;

$PAGE->set_url(new moodle_url('/local/annualtrainingforecast/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pageheading', 'local_annualtrainingforecast'));
$PAGE->set_heading(get_string('pageheading', 'local_annualtrainingforecast'));
$PAGE->set_pagelayout('base');

$PAGE->requires->css(new moodle_url('/local/annualtrainingforecast/styles.css'));
$PAGE->requires->js_call_amd('local_annualtrainingforecast/forecast', 'init', [
    $context->id,
    (int) $canmanage,
    (int) $canaddevent,
]);

$output = $PAGE->get_renderer('local_annualtrainingforecast');
$page = new \local_annualtrainingforecast\output\forecast_page($canmanage, $canaddevent, $canreports);

echo $output->header();
echo $output->render_forecast_page($page);
echo $output->footer();
