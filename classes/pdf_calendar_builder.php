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
 * Builds HTML for the year-calendar style PDF export (matches on-screen calendar).
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * PDF calendar HTML builder.
 */
class pdf_calendar_builder {

    /** @var int Max event lines per day cell in PDF */
    const MAX_CHIPS = 3;

    /** @var int Max characters per chip title */
    const MAX_TITLE_LEN = 22;

    /**
     * Status background colours (aligned with styles.css chips).
     *
     * @return array<int|string, string>
     */
    protected static function status_colours(): array {
        return [
            0 => '#e8f1ff',
            1 => '#fff4d6',
            2 => '#e6f4ea',
            3 => '#fce8e6',
            'general' => '#e8eaed',
        ];
    }

    /**
     * Day key YYYY-MM-DD in user's calendar.
     *
     * @param int $timestamp
     * @return string
     */
    protected static function day_key(int $timestamp): string {
        return userdate($timestamp, '%Y-%m-%d', 99, true, false);
    }

    /**
     * Map each day in range to items active that day (same logic as gantt.js buildDayEventsMap).
     *
     * @param array $items
     * @param int $rangestart
     * @param int $rangeend
     * @return array<string, array<int, array>>
     */
    protected static function build_day_events_map(array $items, int $rangestart, int $rangeend): array {
        $map = [];
        $rs = usergetmidnight($rangestart);
        $re = usergetmidnight($rangeend);

        foreach ($items as $item) {
            $itemstart = usergetmidnight((int) $item['start']);
            $itemend = usergetmidnight((int) $item['end']);
            if ($itemend < $itemstart) {
                $itemend = $itemstart;
            }
            if ($itemend < $rs || $itemstart > $re) {
                continue;
            }
            if ($itemstart < $rs) {
                $itemstart = $rs;
            }
            if ($itemend > $re) {
                $itemend = $re;
            }

            for ($t = $itemstart; $t <= $itemend; $t += DAYSECS) {
                $key = self::day_key($t);
                if (!isset($map[$key])) {
                    $map[$key] = [];
                }
                $map[$key][] = $item;
            }
        }

        foreach ($map as $key => $dayitems) {
            usort($map[$key], function($a, $b) {
                return ((int) $a['start']) <=> ((int) $b['start']);
            });
        }

        return $map;
    }

    /**
     * Truncate title for a small PDF cell.
     *
     * @param string $name
     * @return string
     */
    protected static function truncate_title(string $name): string {
        $name = trim($name);
        if (core_text::strlen($name) <= self::MAX_TITLE_LEN) {
            return $name;
        }
        return core_text::substr($name, 0, self::MAX_TITLE_LEN - 1) . '…';
    }

    /**
     * Background colour for one item.
     *
     * @param array $item
     * @param array<int|string, string> $colours
     * @return string
     */
    protected static function item_bg(array $item, array $colours): string {
        if (!empty($item['isgeneralevent'])) {
            return $colours['general'];
        }
        $st = (int) ($item['status'] ?? 0);
        return $colours[$st] ?? $colours[0];
    }

    /**
     * Short weekday labels (Sunday first), using user's locale via userdate.
     *
     * @return string[]
     */
    protected static function weekday_labels(): array {
        // Anchor: 2023-01-01 is a Sunday (matches gantt.js).
        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $ts = strtotime('2023-01-01 +' . $i . ' days');
            $labels[] = userdate($ts, '%a', 99, true, true);
        }
        return $labels;
    }

    /**
     * Render one month grid.
     *
     * @param int $year
     * @param int $month 1–12
     * @param int $range_day_start usergetmidnight of view start
     * @param int $range_day_end usergetmidnight of view end
     * @param array $dayevents
     * @param array<int|string, string> $colours
     * @return string HTML
     */
    protected static function render_month(
        int $year,
        int $month,
        int $range_day_start,
        int $range_day_end,
        array $dayevents,
        array $colours,
        array $weekdaylabels
    ): string {
        $midmonth = mktime(12, 0, 0, $month, 1, $year);
        $monthtitle = userdate($midmonth, get_string('strftimemonthyear', 'langconfig'), 99, true, false);

        $firstinfo = usergetdate(mktime(12, 0, 0, $month, 1, $year));
        $daysinmonth = (int) date('t', mktime(12, 0, 0, $month, 1, $year));
        $pad = (int) $firstinfo['wday'];

        $html = '<div class="atf-pdf-month">';
        $html .= '<div class="atf-pdf-month-title">' . htmlspecialchars($monthtitle) . '</div>';
        $html .= '<table class="atf-pdf-grid"><tr class="atf-pdf-wd">';
        foreach ($weekdaylabels as $wd) {
            $html .= '<th>' . htmlspecialchars($wd) . '</th>';
        }
        $html .= '</tr><tr>';

        $col = 0;
        for ($p = 0; $p < $pad; $p++) {
            $html .= '<td class="atf-pdf-day atf-pdf-empty"></td>';
            $col++;
        }

        $todayinfo = usergetdate(time());
        for ($d = 1; $d <= $daysinmonth; $d++) {
            $cellmid = mktime(12, 0, 0, $month, $d, $year);
            $key = self::day_key($cellmid);
            $events = $dayevents[$key] ?? [];

            $classes = 'atf-pdf-day';
            $dinfo = usergetdate($cellmid);
            if ($dinfo['wday'] === 0 || $dinfo['wday'] === 6) {
                $classes .= ' atf-pdf-weekend';
            }
            if ($dinfo['year'] === $todayinfo['year'] && $dinfo['mon'] === $todayinfo['mon'] && $dinfo['mday'] === $todayinfo['mday']) {
                $classes .= ' atf-pdf-today';
            }
            $cellstart = usergetmidnight($cellmid);
            if ($cellstart < $range_day_start || $cellstart > $range_day_end) {
                $classes .= ' atf-pdf-outside';
            }

            $html .= '<td class="' . $classes . '">';
            $html .= '<div class="atf-pdf-daynum">' . $d . '</div>';

            $shown = array_slice($events, 0, self::MAX_CHIPS);
            foreach ($shown as $ev) {
                $bg = self::item_bg($ev, $colours);
                $title = htmlspecialchars(self::truncate_title($ev['name']));
                $html .= '<div class="atf-pdf-chip" style="background-color:' . $bg . ';">' . $title . '</div>';
            }
            if (count($events) > self::MAX_CHIPS) {
                $extra = count($events) - self::MAX_CHIPS;
                $more = get_string('moreactivities', 'local_annualtrainingforecast', $extra);
                $html .= '<div class="atf-pdf-more">' . htmlspecialchars($more) . '</div>';
            }
            $html .= '</td>';

            $col++;
            if ($col % 7 === 0 && $d < $daysinmonth) {
                $html .= '</tr><tr>';
            }
        }

        $trailing = (7 - ($col % 7)) % 7;
        for ($t = 0; $t < $trailing; $t++) {
            $html .= '<td class="atf-pdf-day atf-pdf-empty"></td>';
        }
        $html .= '</tr></table></div>';

        return $html;
    }

    /**
     * Full calendar + legend HTML for mPDF.
     *
     * @param array $data api::get_gantt_data structure
     * @return string
     */
    public static function build_html(array $data): string {
        $items = $data['items'] ?? [];
        $rangestart = (int) $data['timerange']['start'];
        $rangeend = (int) $data['timerange']['end'];

        $range_day_start = usergetmidnight($rangestart);
        $range_day_end = usergetmidnight($rangeend);

        $dayevents = self::build_day_events_map($items, $rangestart, $rangeend);
        $colours = self::status_colours();

        $statusstrings = [
            0 => get_string('status_upcoming', 'local_annualtrainingforecast'),
            1 => get_string('status_inprogress', 'local_annualtrainingforecast'),
            2 => get_string('status_completed', 'local_annualtrainingforecast'),
            3 => get_string('status_cancelled', 'local_annualtrainingforecast'),
        ];
        $generallabel = get_string('generalevent', 'local_annualtrainingforecast');

        $html = '<div class="atf-pdf-calendar-block">';
        $html .= '<div class="atf-pdf-legend">';
        $html .= '<span class="atf-pdf-legend-label">' . htmlspecialchars(get_string('calendarlegend', 'local_annualtrainingforecast')) . '</span> ';
        foreach ([0, 1, 2, 3] as $st) {
            $html .= '<span class="atf-pdf-legend-chip" style="background-color:' . $colours[$st] . ';">'
                . htmlspecialchars($statusstrings[$st]) . '</span> ';
        }
        $html .= '<span class="atf-pdf-legend-chip" style="background-color:' . $colours['general'] . ';">'
            . htmlspecialchars($generallabel) . '</span>';
        $html .= '</div>';

        $startinfo = usergetdate($range_day_start);
        $endinfo = usergetdate($range_day_end);

        $y = (int) $startinfo['year'];
        $m = (int) $startinfo['mon'];
        $endy = (int) $endinfo['year'];
        $endm = (int) $endinfo['mon'];

        $weekdaylabels = self::weekday_labels();

        $html .= '<table class="atf-pdf-months-outer" width="100%"><tr>';
        $pair = 0;
        while ($y < $endy || ($y === $endy && $m <= $endm)) {
            if ($pair > 0 && $pair % 2 === 0) {
                $html .= '</tr><tr>';
            }
            $html .= '<td class="atf-pdf-month-cell" width="50%" valign="top">';
            $html .= self::render_month($y, $m, $range_day_start, $range_day_end, $dayevents, $colours, $weekdaylabels);
            $html .= '</td>';
            $pair++;
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
        }
        if ($pair % 2 === 1) {
            $html .= '<td class="atf-pdf-month-cell" width="50%"></td>';
        }
        $html .= '</tr></table>';
        $html .= '</div>';

        return $html;
    }
}
