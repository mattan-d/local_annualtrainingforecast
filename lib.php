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
 * Library functions for Annual Training Forecast
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation
 *
 * @param global_navigation $navigation
 */
function local_annualtrainingforecast_extend_navigation(global_navigation $navigation) {
    global $CFG, $PAGE;

    if (!has_capability('local/annualtrainingforecast:viewforecast', context_system::instance())) {
        return;
    }

    if ($PAGE->context->contextlevel == CONTEXT_SYSTEM) {
        $node = navigation_node::create(
            get_string('pluginname', 'local_annualtrainingforecast'),
            new moodle_url('/local/annualtrainingforecast/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'annualtrainingforecast',
            new pix_icon('i/calendar', '')
        );
        
        $navigation->add_node($node);
    }
}

/**
 * Add nodes to settings navigation
 *
 * @param settings_navigation $settingsnav
 */
function local_annualtrainingforecast_extend_settings_navigation(settings_navigation $settingsnav) {
    global $CFG, $PAGE;

    if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
        $strpluginname = get_string('pluginname', 'local_annualtrainingforecast');
        $url = new moodle_url('/local/annualtrainingforecast/index.php');
        $node = navigation_node::create(
            $strpluginname,
            $url,
            navigation_node::NODETYPE_LEAF,
            'annualtrainingforecast',
            'annualtrainingforecast',
            new pix_icon('i/calendar', $strpluginname)
        );
        
        if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
            $node->make_active();
        }
        
        $settingnode->add_node($node);
    }
}
