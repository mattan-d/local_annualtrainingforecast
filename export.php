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
 * Export functionality
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/annualtrainingforecast/classes/api.php');
require_once($CFG->dirroot . '/local/annualtrainingforecast/classes/pdf_calendar_builder.php');

require_once(__DIR__ . '/external/autoload.php');

// Check permissions
require_login();
$context = context_system::instance();
require_capability('local/annualtrainingforecast:viewforecast', $context);

// Get parameters
$format = required_param('format', PARAM_ALPHA);
$viewtype = optional_param('view', 'year', PARAM_ALPHA);
$year = optional_param('year', date('Y'), PARAM_INT);

// Validate format
if (!in_array($format, ['pdf', 'excel'])) {
    throw new moodle_exception('invalidformat', 'local_annualtrainingforecast');
}

// Validate view type
$validviews = ['year', 'halfyear', 'quarter'];
if (!in_array($viewtype, $validviews)) {
    $viewtype = 'year';
}

// Validate year
$currentyear = (int)date('Y');
if ($year < 2000 || $year > ($currentyear + 10)) {
    $year = $currentyear;
}

// Export based on format
if ($format === 'excel') {
    \api::export_to_excel($viewtype, $year);
    exit;
} else if ($format === 'pdf') {
    // PDF: summary table + year-calendar style grid (matches on-screen forecast calendar).
    $data = \api::get_gantt_data($viewtype, $year);

    // Set up the page for PDF generation
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/annualtrainingforecast/export.php', [
        'format' => $format,
        'view' => $viewtype,
        'year' => $year
    ]));
    $PAGE->set_title(get_string('pluginname', 'local_annualtrainingforecast'));
    $PAGE->set_heading(get_string('pluginname', 'local_annualtrainingforecast'));
    $PAGE->set_pagelayout('print');

    $statusStrings = [
        0 => get_string('status_upcoming', 'local_annualtrainingforecast'),
        1 => get_string('status_inprogress', 'local_annualtrainingforecast'),
        2 => get_string('status_completed', 'local_annualtrainingforecast'),
        3 => get_string('status_cancelled', 'local_annualtrainingforecast')
    ];

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_header' => 0,
        'margin_footer' => 0,
        'autoScriptToLang' => true,
        'autoLangToFont' => true,
        'tempDir' => make_temp_directory('mpdf')
    ]);

    // Build HTML content directly
    $isrtl = (current_language() === 'he' || current_language() === 'ar');
    $htmldir = $isrtl ? 'rtl' : 'ltr';
    $html = '
    <style>
        body {
            direction: ' . $htmldir . ';
            font-size: 10pt;
            line-height: 1.2;
        }
        table.atf-pdf-data-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 10px;
        }
        table.atf-pdf-data-table th,
        table.atf-pdf-data-table td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: ' . ($isrtl ? 'right' : 'left') . ';
            font-size: 9pt;
        }
        table.atf-pdf-data-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .center {
            text-align: center;
        }
        .title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 12pt;
            margin-bottom: 5px;
        }
        .atf-pdf-calendar-block {
            direction: ltr;
            unicode-bidi: isolate;
            margin-top: 4px;
        }
        .atf-pdf-legend {
            margin-bottom: 8px;
            font-size: 8pt;
            text-align: center;
        }
        .atf-pdf-legend-label {
            font-weight: bold;
        }
        .atf-pdf-legend-chip {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            margin: 1px 3px;
            font-size: 7pt;
            border: 1px solid #dadce0;
        }
        .atf-pdf-months-outer {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 0;
        }
        .atf-pdf-months-outer > tbody > tr > td {
            border: none;
            padding: 4px;
            vertical-align: top;
        }
        .atf-pdf-month {
            page-break-inside: avoid;
            margin-bottom: 4px;
        }
        .atf-pdf-month-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 2px 0 4px;
            text-align: center;
        }
        .atf-pdf-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
            table-layout: fixed;
            margin-bottom: 0;
        }
        .atf-pdf-grid th {
            background-color: #f1f3f4;
            padding: 2px 1px;
            border: 1px solid #ddd;
            font-size: 6pt;
            text-align: center;
            font-weight: bold;
        }
        .atf-pdf-grid td {
            border: 1px solid #e8eaed;
            vertical-align: top;
            padding: 2px;
            min-height: 36pt;
            text-align: center;
        }
        .atf-pdf-day.atf-pdf-empty {
            background: #fafafa;
            border: 1px solid #eee;
        }
        .atf-pdf-weekend {
            background: #f8f9fa;
        }
        .atf-pdf-today {
            border: 2px solid #1a73e8 !important;
        }
        .atf-pdf-outside {
            opacity: 0.45;
        }
        .atf-pdf-daynum {
            font-weight: bold;
            font-size: 7pt;
            margin-bottom: 2px;
            text-align: center;
        }
        .atf-pdf-chip {
            font-size: 5.5pt;
            line-height: 1.15;
            padding: 1px 2px;
            margin: 0 0 1px 0;
            border-radius: 2px;
            word-wrap: break-word;
            text-align: center;
        }
        .atf-pdf-more {
            font-size: 5.5pt;
            color: #70757a;
            font-weight: bold;
            margin-top: 1px;
        }
    </style>';

    // Header
    $html .= '<div class="center">';
    $html .= '<div class="title">' . get_string('pluginname', 'local_annualtrainingforecast') . '</div>';

    // View type heading
    $viewtypestrings = [
        'year' => get_string('yearview', 'local_annualtrainingforecast'),
        'halfyear' => get_string('halfyearview', 'local_annualtrainingforecast'),
        'quarter' => get_string('quarterlyview', 'local_annualtrainingforecast')
    ];
    $html .= '<div class="subtitle">' . $viewtypestrings[$viewtype] . '</div>';

    // Date range
    $startdate = userdate($data['timerange']['start'], get_string('strftimedatefullshort', 'core_langconfig'));
    $enddate = userdate($data['timerange']['end'], get_string('strftimedatefullshort', 'core_langconfig'));
    $html .= '<div>' . $startdate . ' - ' . $enddate . '</div>';
    $html .= '</div>';

    $html .= '<br>';

    // Summary table of courses / events.
    $html .= '<table class="atf-pdf-data-table">';
    $html .= '<tr>';
    $html .= '<th>' . get_string('coursename', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('parentcourse', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('startdate', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('enddate', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('status', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('completed', 'local_annualtrainingforecast') . '</th>';
    $html .= '</tr>';

    $generallabel = get_string('generalevent', 'local_annualtrainingforecast');

    foreach ($data['items'] as $item) {
        $isgeneral = !empty($item['isgeneralevent']);

        $completedtext = $isgeneral ? '—' : (
            $item['completed'] ?
            get_string('completed', 'local_annualtrainingforecast') :
            get_string('notcompleted', 'local_annualtrainingforecast')
        );

        $statustext = $isgeneral ? $generallabel : $statusStrings[$item['status']];
        $parentcell = $isgeneral ? $generallabel : htmlspecialchars($item['parentname']);

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($item['name']) . '</td>';
        $html .= '<td>' . $parentcell . '</td>';
        $html .= '<td>' . userdate($item['start'], get_string('strftimedatefullshort', 'core_langconfig')) . '</td>';
        $html .= '<td>' . userdate($item['end'], get_string('strftimedatefullshort', 'core_langconfig')) . '</td>';
        $html .= '<td>' . $statustext . '</td>';
        $html .= '<td>' . $completedtext . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    $html .= pdf_calendar_builder::build_html($data);

    $mpdf->WriteHTML($html);

    $filename = 'training_forecast_' . $viewtype . '_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($filename, 'D');
    exit;
}
