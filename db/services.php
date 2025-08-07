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
 * External functions and service definitions
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_annualtrainingforecast_get_gantt_data' => [
        'classname'     => 'local_annualtrainingforecast_external',
        'methodname'    => 'get_gantt_data',
        'classpath'     => 'local/annualtrainingforecast/externallib.php',
        'description'   => 'Get Gantt chart data',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/annualtrainingforecast:viewforecast'
    ],
    'local_annualtrainingforecast_update_iteration_status' => [
        'classname'     => 'local_annualtrainingforecast_external',
        'methodname'    => 'update_iteration_status',
        'classpath'     => 'local/annualtrainingforecast/externallib.php',
        'description'   => 'Update iteration status',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/annualtrainingforecast:updatestatus'
    ]
];

$services = [
    'Annual Training Forecast' => [
        'functions' => [
            'local_annualtrainingforecast_get_gantt_data',
            'local_annualtrainingforecast_update_iteration_status'
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'annualtrainingforecast'
    ]
];
