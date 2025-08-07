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
 * Plugin upgrade steps are defined here.
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_annualtrainingforecast upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_annualtrainingforecast_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025032016) {
        // Add moodlecourseid field to local_atf_courses table
        $table = new xmldb_table('local_atf_courses');
        $field = new xmldb_field('moodlecourseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'duration');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            
            // Add foreign key
            $key = new xmldb_key('moodlecourseid', XMLDB_KEY_FOREIGN, ['moodlecourseid'], 'course', ['id']);
            $dbman->add_key($table, $key);
        }

        // Add moodlecourseid field to local_atf_iterations table
        $table = new xmldb_table('local_atf_iterations');
        $field = new xmldb_field('moodlecourseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'completed');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            
            // Add foreign key
            $key = new xmldb_key('moodlecourseid', XMLDB_KEY_FOREIGN, ['moodlecourseid'], 'course', ['id']);
            $dbman->add_key($table, $key);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2025032016, 'local', 'annualtrainingforecast');
    }

    return true;
}
