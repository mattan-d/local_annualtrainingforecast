<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_annualtrainingforecast\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;

/**
 * Forecast dashboard page renderable.
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forecast_page implements renderable, templatable {

    /**
     * @param bool $canmanage
     * @param bool $canaddevent
     * @param bool $canreports
     */
    public function __construct(
        private bool $canmanage = false,
        private bool $canaddevent = false,
        private bool $canreports = false,
    ) {
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $s = fn(string $key) => get_string($key, 'local_annualtrainingforecast');

        $strings = [
            'pageheading' => $s('pageheading'),
            'today' => $s('today'),
            'previous' => $s('previous'),
            'next' => $s('next'),
            'month' => $s('view_month'),
            'quarter' => $s('view_quarter'),
            'halfyear' => $s('view_halfyear'),
            'year' => $s('view_year'),
            'search' => $s('searchplaceholder'),
            'allcats' => $s('allcategories'),
            'allstatuses' => $s('allstatuses'),
            'allmgrs' => $s('allmanagers'),
            'filtermanager' => $s('filtermanager'),
            'lbl_category' => $s('filtercategory'),
            'lbl_status' => $s('filterstatus'),
            'exportexcel' => $s('exportexcel'),
            'exportpdf' => $s('exportpdf'),
            'legend' => $s('legend'),
            'legend_hint' => $s('legend_hint'),
            'upcoming' => $s('status_upcoming'),
            'inprogress' => $s('status_inprogress'),
            'completed' => $s('status_completed'),
            'cancelled' => $s('status_cancelled'),
            'general' => $s('status_general'),
            'trainingcolumn' => $s('trainingcolumn'),
            'loading' => $s('loading'),
            'close' => $s('close'),
            'traininglist' => $s('traininglist'),
            'timelinearea' => $s('timelinearea'),
            'lbl_event_title' => $s('eventtitle'),
            'lbl_event_date' => $s('eventstartdate'),
            'eventenddate' => $s('eventenddate'),
            'lbl_description' => $s('eventdescription'),
            'ph_event_title' => $s('eventtitle'),
            'addtraining' => $s('addtraining'),
            'addevent' => $s('addevent'),
            'managecourses' => $s('managecourses'),
            'reports' => $s('reports'),
            'edittraining' => $s('editeration'),
            'editevent' => $s('addgeneralevent'),
            'save' => $s('save'),
            'cancel' => $s('cancel'),
            'delete' => $s('delete'),
        ];

        $jsstrings = [
            'wd' => [
                $s('wd_su'), $s('wd_mo'), $s('wd_tu'), $s('wd_we'),
                $s('wd_th'), $s('wd_fr'), $s('wd_sa'),
            ],
            'statuses' => [
                'upcoming' => $s('status_upcoming'),
                'inprogress' => $s('status_inprogress'),
                'completed' => $s('status_completed'),
                'cancelled' => $s('status_cancelled'),
                'general' => $s('status_general'),
            ],
            'tip' => [
                'category' => $s('tip_category'),
                'manager' => $s('tip_manager'),
                'start' => $s('tip_start'),
                'end' => $s('tip_end'),
                'duration' => $s('tip_duration'),
                'status' => $s('tip_status'),
                'date' => $s('tip_date'),
                'type' => $s('tip_type'),
                'note' => $s('tip_note'),
            ],
            'dur' => [$s('duration_day'), $s('duration_days')],
            'saving' => $s('saving'),
            'save' => $s('save'),
            'delTraining' => $s('confirmdelete'),
            'delEvent' => $s('confirmeventdelete'),
            'errName' => $s('eventtitlerequired'),
            'errStartDate' => $s('err_startdate_required'),
            'errEndDate' => $s('err_enddate_required'),
            'errDates' => $s('enddatebeforestartdate'),
            'errTitle' => $s('eventtitlerequired'),
            'errEventDate' => $s('err_eventdate_required'),
            'errGeneric' => $s('err_generic'),
            'noTrainings' => $s('no_trainings_found'),
            'navHint' => $s('navigate_hint'),
            'editTraining' => $s('editeration'),
            'addTraining' => $s('additeration'),
            'editEvent' => $s('addgeneralevent'),
            'addEvent' => $s('addevent'),
        ];

        $baseurl = new \moodle_url('/local/annualtrainingforecast/');

        return [
            'pageheading' => $s('pageheading'),
            'pluginurl' => $baseurl->out(false),
            'manageurl' => (new \moodle_url('/local/annualtrainingforecast/manage.php'))->out(false),
            'reportsurl' => (new \moodle_url('/local/annualtrainingforecast/reports.php'))->out(false),
            'exportexcel' => (new \moodle_url('/local/annualtrainingforecast/export.php', ['format' => 'excel']))->out(false),
            'exportpdf' => (new \moodle_url('/local/annualtrainingforecast/export.php', ['format' => 'pdf']))->out(false),
            'sesskey' => sesskey(),
            'canmanage' => $this->canmanage,
            'canaddevent' => $this->canaddevent,
            'canreports' => $this->canreports,
            'strings' => $strings,
            'stringsJson' => json_encode($jsstrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
        ];
    }
}
