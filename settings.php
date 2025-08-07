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
 * Plugin administration pages are defined here.
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create the new category in the settings admin tree
    $ADMIN->add('localplugins', new admin_category('local_annualtrainingforecast_settings', 
        get_string('pluginname', 'local_annualtrainingforecast')));
    
    // Add links to the plugin's pages
    $ADMIN->add('local_annualtrainingforecast_settings', new admin_externalpage(
        'local_annualtrainingforecast_gantt',
        get_string('ganttview', 'local_annualtrainingforecast'),
        new moodle_url('/local/annualtrainingforecast/index.php')
    ));
    
    $ADMIN->add('local_annualtrainingforecast_settings', new admin_externalpage(
        'local_annualtrainingforecast_manage',
        get_string('managecourses', 'local_annualtrainingforecast'),
        new moodle_url('/local/annualtrainingforecast/manage.php')
    ));
    
    $ADMIN->add('local_annualtrainingforecast_settings', new admin_externalpage(
        'local_annualtrainingforecast_reports',
        get_string('reports', 'local_annualtrainingforecast'),
        new moodle_url('/local/annualtrainingforecast/reports.php')
    ));
    
    // Add a link to the main plugin page directly under local plugins for quick access
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_annualtrainingforecast',
        get_string('pluginname', 'local_annualtrainingforecast'),
        new moodle_url('/local/annualtrainingforecast/index.php')
    ));
}
