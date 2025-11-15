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

require_once(__DIR__ . '/vendor/autoload.php');

// Check permissions
require_login();
$context = context_system::instance();
require_capability('local/annualtrainingforecast:viewforecast', $context);

// Get parameters
$format = required_param('format', PARAM_ALPHA);
$viewtype = optional_param('view', 'year', PARAM_ALPHA);

// Validate format
if (!in_array($format, ['pdf', 'excel'])) {
    throw new moodle_exception('invalidformat', 'local_annualtrainingforecast');
}

// Validate view type
$validviews = ['year', 'halfyear', 'quarter'];
if (!in_array($viewtype, $validviews)) {
    $viewtype = 'year';
}

// Export based on format
if ($format === 'excel') {
    \api::export_to_excel($viewtype);
    exit;
} else if ($format === 'pdf') {
    // For PDF export, we'll render the Gantt chart to a PDF
    $data = \api::get_gantt_data($viewtype);

    // Set up the page for PDF generation
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/annualtrainingforecast/export.php', [
        'format' => $format,
        'view' => $viewtype
    ]));
    $PAGE->set_title(get_string('pluginname', 'local_annualtrainingforecast'));
    $PAGE->set_heading(get_string('pluginname', 'local_annualtrainingforecast'));
    $PAGE->set_pagelayout('print');

    // Calculate time range
    $startTimestamp = $data['timerange']['start'];
    $endTimestamp = $data['timerange']['end'];
    $totalDuration = $endTimestamp - $startTimestamp;

    // Generate month divisions
    $months = [];
    $currentDate = new \DateTime('@' . $startTimestamp);
    $endDate = new \DateTime('@' . $endTimestamp);
    $currentDate->setTime(0, 0, 0);
    $endDate->setTime(23, 59, 59);

    while ($currentDate <= $endDate) {
        $monthStart = $currentDate->getTimestamp();
        $monthLabel = $currentDate->format('M Y');

        // Move to next month
        $currentDate->modify('first day of next month');

        // Calculate width percentage
        $monthDuration = min($currentDate->getTimestamp(), $endTimestamp) - $monthStart;
        $widthPercentage = ($monthDuration / $totalDuration) * 100;

        $months[] = [
            'label' => $monthLabel,
            'width' => $widthPercentage
        ];
    }

    // Status colors
    $statusColors = [
        0 => '#cce5ff', // upcoming - light blue
        1 => '#fff3cd', // in progress - light yellow
        2 => '#d4edda', // completed - light green
        3 => '#f8d7da'  // cancelled - light red
    ];

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
        'default_font' => 'dejavusans'
    ]);

    // Build HTML content directly
    $html = '
    <style>
        body { 
            font-family: dejavusans, sans-serif; 
            font-size: 10pt;
            line-height: 1.2;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-bottom: 10px;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 5px; 
            text-align: left; 
            font-size: 9pt;
        }
        th { 
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
        .compact-table td, .compact-table th {
            padding: 3px;
            font-size: 8pt;
        }
        .legend-table {
            width: auto;
            margin: 5px auto;
        }
        .legend-table td {
            padding: 3px 10px;
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

    // Render table of courses
    $html .= '<table>';
    $html .= '<tr>';
    $html .= '<th>' . get_string('coursename', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('parentcourse', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('startdate', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('enddate', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('status', 'local_annualtrainingforecast') . '</th>';
    $html .= '<th>' . get_string('completed', 'local_annualtrainingforecast') . '</th>';
    $html .= '</tr>';

    foreach ($data['items'] as $item) {
        $completedtext = $item['completed'] ?
            get_string('completed', 'local_annualtrainingforecast') :
            get_string('notcompleted', 'local_annualtrainingforecast');

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($item['name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($item['parentname']) . '</td>';
        $html .= '<td>' . userdate($item['start'], get_string('strftimedatefullshort', 'core_langconfig')) . '</td>';
        $html .= '<td>' . userdate($item['end'], get_string('strftimedatefullshort', 'core_langconfig')) . '</td>';
        $html .= '<td>' . $statusStrings[$item['status']] . '</td>';
        $html .= '<td>' . $completedtext . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    $html .= '<div class="center subtitle">' . get_string('ganttview', 'local_annualtrainingforecast') . '</div>';

    // Legend - place it above the Gantt chart for better layout
    $html .= '<table class="legend-table">';
    $html .= '<tr>';
    $html .= '<td style="background-color: ' . $statusColors[0] . ';">' . $statusStrings[0] . '</td>';
    $html .= '<td style="background-color: ' . $statusColors[1] . ';">' . $statusStrings[1] . '</td>';
    $html .= '<td style="background-color: ' . $statusColors[2] . ';">' . $statusStrings[2] . '</td>';
    $html .= '<td style="background-color: ' . $statusColors[3] . ';">' . $statusStrings[3] . '</td>';
    $html .= '</tr>';
    $html .= '</table>';

    // Simple table-based Gantt chart
    $html .= '<table class="compact-table">';

    // Header row with months
    $html .= '<tr>';
    $html .= '<th style="width: 180px;">Course</th>';

    // Calculate column count based on months
    $columnCount = count($months);
    $columnWidth = (100 - 20) / $columnCount; // 20% for the course name column

    foreach ($months as $month) {
        $html .= '<th style="width: ' . $columnWidth . '%;">' . $month['label'] . '</th>';
    }
    $html .= '</tr>';

    // One row per course
    foreach ($data['items'] as $item) {
        $html .= '<tr>';

        // Course name cell
        $html .= '<td style="width: 180px;">';
        $html .= '<strong>' . htmlspecialchars($item['name']) . '</strong><br>';
        $html .= '<small>' . htmlspecialchars($item['parentname']) . '</small>';
        $html .= '</td>';

        // Calculate which cells should be colored
        $itemStart = max($item['start'], $startTimestamp);
        $itemEnd = min($item['end'], $endTimestamp);

        $currentMonthDate = new \DateTime('@' . $startTimestamp);
        $currentMonthDate->setTime(0, 0, 0);

        for ($i = 0; $i < $columnCount; $i++) {
            $monthStart = $currentMonthDate->getTimestamp();
            $currentMonthDate->modify('first day of next month');
            $monthEnd = $currentMonthDate->getTimestamp() - 1;

            // Check if this month overlaps with the course duration
            $isInRange = ($itemStart <= $monthEnd && $itemEnd >= $monthStart);

            if ($isInRange) {
                $html .= '<td style="background-color: ' . $statusColors[$item['status']] . ';">';

                // Only show dates if there's enough space
                if ($columnCount <= 12) {
                    // If this is the first or last month of the course, show the dates
                    if ($i == 0 || $monthStart <= $itemStart && $itemStart <= $monthEnd) {
                        $html .= '<small>' . userdate($itemStart, 'd M') . '</small>';
                    }

                    if ($i == ($columnCount - 1) || $monthStart <= $itemEnd && $itemEnd <= $monthEnd) {
                        if ($i == 0 || $monthStart <= $itemStart && $itemStart <= $monthEnd) {
                            $html .= ' - ';
                        }
                        $html .= '<small>' . userdate($itemEnd, 'd M') . '</small>';
                    }
                }

                $html .= '</td>';
            } else {
                $html .= '<td></td>';
            }
        }

        $html .= '</tr>';
    }
    $html .= '</table>';

    $mpdf->WriteHTML($html);

    $filename = 'training_forecast_' . $viewtype . '_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($filename, 'D');
    exit;
}
