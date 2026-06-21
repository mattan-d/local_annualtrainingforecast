<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_annualtrainingforecast\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

/**
 * Plugin renderer.
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the forecast dashboard page.
     *
     * @param forecast_page $page
     * @return string
     */
    public function render_forecast_page(forecast_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_annualtrainingforecast/forecast', $data);
    }
}
