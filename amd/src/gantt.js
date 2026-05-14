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
 * Year calendar view (day cells with activities).
 *
 * @module     local_annualtrainingforecast/gantt
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/templates', 'core/config'], (
    $,
    Ajax,
    Notification,
    Str,
    Templates,
    Cfg,
) => {
    const MAX_CHIPS = 4;

    /**
     * BCP 47 locale for Intl from Moodle page language (M.cfg.language).
     *
     * @param {Object} cfg M.cfg from core/config
     * @returns {string}
     */
    const localeFromMoodle = (cfg) => {
        const raw = cfg && (cfg.language || cfg.lang) ? String(cfg.language || cfg.lang) : 'en';
        const parts = raw.split(/[-_]/);
        let primary = (parts[0] || 'en').toLowerCase();
        if (primary === 'iw') {
            primary = 'he';
        }
        if (primary === 'he') {
            return 'he-IL';
        }
        return primary || 'en';
    };

    /**
     * Strip time to local calendar day.
     *
     * @param {Date} d
     * @returns {Date}
     */
    const stripDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

    /**
     * YYYY-MM-DD key for maps.
     *
     * @param {Date} d
     * @returns {string}
     */
    const dayKey = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    };

    /**
     * Escape text for HTML.
     *
     * @param {string} s
     * @returns {string}
     */
    const escapeHtml = (s) => {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    };

    /**
     * Truncate label for chip.
     *
     * @param {string} name
     * @returns {string}
     */
    const truncate = (name) => {
        if (name.length <= 26) {
            return name;
        }
        return name.slice(0, 24) + '…';
    };

    /**
     * HTML date input value (YYYY-MM-DD) from a Date (local).
     *
     * @param {Date} d
     * @returns {string}
     */
    const dateInputFromDate = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    };

    /**
     * Start-of-day unix from date input string.
     *
     * @param {string} dateStr
     * @returns {number}
     */
    const startTsFromDateInput = (dateStr) => {
        const p = dateStr.split('-');
        const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10), 0, 0, 0, 0);
        return Math.floor(d.getTime() / 1000);
    };

    /**
     * End-of-day unix from date input string.
     *
     * @param {string} dateStr
     * @returns {number}
     */
    const endTsFromDateInput = (dateStr) => {
        const p = dateStr.split('-');
        const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10), 23, 59, 59, 0);
        return Math.floor(d.getTime() / 1000);
    };

    /**
     * Read iteration rows from the template.
     *
     * @returns {Array<Object>}
     */
    const collectItems = () => {
        const items = [];
        $('.gantt-item-data').each(function() {
            const el = $(this);
            items.push({
                id: parseInt(el.data('id'), 10),
                parentid: parseInt(el.data('parentid'), 10),
                name: String(el.data('name') || ''),
                parentname: String(el.data('parentname') || ''),
                start: parseInt(el.data('start'), 10),
                end: parseInt(el.data('end'), 10),
                status: parseInt(el.data('status'), 10),
                completed: parseInt(el.data('completed'), 10) || 0,
                statusclass: String(el.data('statusclass') || 'upcoming'),
                isgeneralevent: el.attr('data-isgeneralevent') === '1',
                description: el.find('.atf-item-desc-payload').first().text(),
            });
        });
        return items;
    };

    /**
     * Map each day in range to the iterations active that day.
     *
     * @param {Array<Object>} items
     * @param {number} rangeStartTs
     * @param {number} rangeEndTs
     * @returns {Object<string, Array<Object>>}
     */
    const buildDayEventsMap = (items, rangeStartTs, rangeEndTs) => {
        const map = Object.create(null);
        const rs = stripDay(new Date(rangeStartTs * 1000));
        const re = stripDay(new Date(rangeEndTs * 1000));

        items.forEach((item) => {
            let itemStart = stripDay(new Date(item.start * 1000));
            let itemEnd = stripDay(new Date(item.end * 1000));
            if (itemEnd < itemStart) {
                itemEnd = itemStart;
            }
            if (itemEnd < rs || itemStart > re) {
                return;
            }
            if (itemStart < rs) {
                itemStart = new Date(rs);
            }
            if (itemEnd > re) {
                itemEnd = new Date(re);
            }
            const cursor = new Date(itemStart);
            while (cursor.getTime() <= itemEnd.getTime()) {
                const key = dayKey(cursor);
                if (!map[key]) {
                    map[key] = [];
                }
                map[key].push(item);
                cursor.setDate(cursor.getDate() + 1);
            }
        });
        return map;
    };

    /**
     * Weekday headers (Sunday first).
     *
     * @param {string} localeTag
     * @returns {Array<string>}
     */
    const getWeekdayLabels = (localeTag) => {
        const refSunday = new Date(2023, 0, 1);
        const labels = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(refSunday);
            d.setDate(refSunday.getDate() + i);
            labels.push(d.toLocaleDateString(localeTag, {weekday: 'short'}));
        }
        return labels;
    };

    /**
     * Build HTML for one month card.
     *
     * @param {number} year
     * @param {number} month 0-11
     * @param {Date} rangeStartDay
     * @param {Date} rangeEndDay
     * @param {Object<string, Array<Object>>} dayEvents
     * @param {Object} strings
     * @param {string} localeTag
     * @param {boolean} canManageGeneral
     * @returns {string}
     */
    const renderMonthCard = (year, month, rangeStartDay, rangeEndDay, dayEvents, strings, localeTag, canManageGeneral) => {
        const first = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const pad = first.getDay();
        const monthTitle = new Date(year, month, 1).toLocaleDateString(localeTag, {
            month: 'long',
            year: 'numeric',
        });

        let html = '<section class="atf-month-card">';
        html += '<header class="atf-month-card-title">' + escapeHtml(monthTitle) + '</header>';
        html += '<div class="atf-month-body">';
        html += '<div class="atf-weekday-row">';
        const wlabels = getWeekdayLabels(localeTag);
        for (let wi = 0; wi < wlabels.length; wi++) {
            html += '<span class="atf-weekday-cell">' + escapeHtml(wlabels[wi]) + '</span>';
        }
        html += '</div>';
        html += '<div class="atf-days-grid">';

        for (let p = 0; p < pad; p++) {
            html += '<div class="atf-day atf-day-empty" aria-hidden="true"></div>';
        }

        for (let dayNum = 1; dayNum <= daysInMonth; dayNum++) {
            const cellDate = new Date(year, month, dayNum);
            const key = dayKey(cellDate);
            const events = (dayEvents[key] || []).slice().sort((a, b) => a.start - b.start);

            let classes = 'atf-day';
            const dow = cellDate.getDay();
            if (dow === 0 || dow === 6) {
                classes += ' atf-day-weekend';
            }
            const today = new Date();
            if (
                cellDate.getDate() === today.getDate() &&
                cellDate.getMonth() === today.getMonth() &&
                cellDate.getFullYear() === today.getFullYear()
            ) {
                classes += ' atf-day-today';
            }
            if (cellDate < rangeStartDay || cellDate > rangeEndDay) {
                classes += ' atf-day-outside-range';
            } else if (canManageGeneral) {
                classes += ' atf-day-clickable';
            }

            const titles = events.map((ev) => ev.name).join(', ');
            html += '<div class="' + classes + '" data-date="' + escapeHtml(key) + '" title="' + escapeHtml(titles) + '">';
            html += '<div class="atf-day-num">' + dayNum + '</div>';
            html += '<div class="atf-day-events">';

            const visible = events.slice(0, MAX_CHIPS);
            for (let vi = 0; vi < visible.length; vi++) {
                const ev = visible[vi];
                html +=
                    '<button type="button" class="atf-event-chip status-' +
                    escapeHtml(ev.statusclass) +
                    '" data-id="' +
                    ev.id +
                    '">';
                html += '<span class="atf-chip-title">' + escapeHtml(truncate(ev.name)) + '</span>';
                html += '</button>';
            }

            if (events.length > MAX_CHIPS) {
                const extra = events.length - MAX_CHIPS;
                const moreLabel = strings.moreactivities.replace('{$a}', String(extra));
                html += '<div class="atf-more-activities">' + escapeHtml(moreLabel) + '</div>';
            }

            html += '</div></div>';
        }

        const used = pad + daysInMonth;
        const trailing = (7 - (used % 7)) % 7;
        for (let t = 0; t < trailing; t++) {
            html += '<div class="atf-day atf-day-empty" aria-hidden="true"></div>';
        }

        html += '</div></div></section>';
        return html;
    };

    /**
     * Full year / range calendar markup.
     *
     * @param {number} rangeStartTs
     * @param {number} rangeEndTs
     * @param {Array<Object>} items
     * @param {Object} strings
     * @param {string} localeTag
     * @param {boolean} canManageGeneral
     * @returns {string}
     */
    const buildCalendarHtml = (rangeStartTs, rangeEndTs, items, strings, localeTag, canManageGeneral) => {
        const rs = stripDay(new Date(rangeStartTs * 1000));
        const re = stripDay(new Date(rangeEndTs * 1000));
        const dayEvents = buildDayEventsMap(items, rangeStartTs, rangeEndTs);

        let html = '<div class="atf-year-toolbar">';
        if (canManageGeneral) {
            html +=
                '<p class="atf-dayclick-hint text-muted small mb-2">' +
                escapeHtml(strings.dayclickhint) +
                '</p>';
        }
        html += '<div class="atf-legend">';
        html += '<div class="atf-legend-inner">';
        html += '<span class="atf-legend-label">' + escapeHtml(strings.calendarlegend) + '</span>';
        html += '<span class="atf-legend-chip status-upcoming">' + escapeHtml(strings.status0) + '</span>';
        html += '<span class="atf-legend-chip status-inprogress">' + escapeHtml(strings.status1) + '</span>';
        html += '<span class="atf-legend-chip status-completed">' + escapeHtml(strings.status2) + '</span>';
        html += '<span class="atf-legend-chip status-cancelled">' + escapeHtml(strings.status3) + '</span>';
        html += '<span class="atf-legend-chip status-general">' + escapeHtml(strings.generalevent) + '</span>';
        html += '</div></div></div>';

        html += '<div class="atf-year-calendar">';

        const monthWalker = new Date(rs.getFullYear(), rs.getMonth(), 1);
        const endMonth = new Date(re.getFullYear(), re.getMonth(), 1);

        while (monthWalker.getTime() <= endMonth.getTime()) {
            html += renderMonthCard(
                monthWalker.getFullYear(),
                monthWalker.getMonth(),
                rs,
                re,
                dayEvents,
                strings,
                localeTag,
                canManageGeneral,
            );
            monthWalker.setMonth(monthWalker.getMonth() + 1);
        }

        html += '</div>';
        return html;
    };

    /**
     * Modal: create general event (prefilled from clicked day).
     *
     * @param {Date} initialDay
     * @param {Object} strings
     * @param {string} localeTag
     */
    const showCreateGeneralEventModal = (initialDay, strings, localeTag) => {
        const dayStr = dateInputFromDate(stripDay(initialDay));
        const modalHtml =
            '<div class="modal fade" id="atfGenCreateModal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">' +
            escapeHtml(strings.addgeneralevent) +
            '</h5>' +
            '<button type="button" class="close" data-dismiss="modal" aria-label="' +
            escapeHtml(strings.close) +
            '"><span aria-hidden="true">&times;</span></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="form-group">' +
            '<label for="atfNewGenName">' +
            escapeHtml(strings.eventtitle) +
            '</label>' +
            '<input type="text" class="form-control" id="atfNewGenName" maxlength="255">' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="atfNewGenDesc">' +
            escapeHtml(strings.eventdescription) +
            '</label>' +
            '<textarea class="form-control" id="atfNewGenDesc" rows="3"></textarea>' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="atfNewGenStart">' +
            escapeHtml(strings.eventstartdate) +
            '</label>' +
            '<input type="date" class="form-control" id="atfNewGenStart" value="' +
            escapeHtml(dayStr) +
            '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="atfNewGenEnd">' +
            escapeHtml(strings.eventenddate) +
            '</label>' +
            '<input type="date" class="form-control" id="atfNewGenEnd" value="' +
            escapeHtml(dayStr) +
            '">' +
            '</div>' +
            '<p class="small text-muted">' +
            stripDay(initialDay).toLocaleDateString(localeTag, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            }) +
            '</p>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-dismiss="modal">' +
            escapeHtml(strings.cancel) +
            '</button>' +
            '<button type="button" class="btn btn-primary" id="atfNewGenSave">' +
            escapeHtml(strings.savechanges) +
            '</button>' +
            '</div></div></div></div>';

        $('#atfGenCreateModal').remove();
        $('body').append(modalHtml);

        $('#atfNewGenSave').on('click', () => {
            const name = $('#atfNewGenName').val().trim();
            const desc = $('#atfNewGenDesc').val();
            const startStr = $('#atfNewGenStart').val();
            const endStr = $('#atfNewGenEnd').val();
            if (!name) {
                Notification.alert(strings.error, strings.eventtitlerequired, strings.close);
                return;
            }
            if (!startStr || !endStr) {
                Notification.alert(strings.error, strings.enddatebeforestartdate, strings.close);
                return;
            }
            const startTs = startTsFromDateInput(startStr);
            const endTs = endTsFromDateInput(endStr);
            if (startTs > endTs) {
                Notification.alert(strings.error, strings.enddatebeforestartdate, strings.close);
                return;
            }
            const promises = Ajax.call([
                {
                    methodname: 'local_annualtrainingforecast_create_generalevent',
                    args: {
                        name: name,
                        description: desc,
                        start: startTs,
                        end: endTs,
                    },
                },
            ]);
            promises[0]
                .done((response) => {
                    if (response.success) {
                        $('#atfGenCreateModal').modal('hide');
                        window.location.reload();
                    } else {
                        Notification.alert(strings.error, response.message || strings.updatefailed, strings.close);
                    }
                })
                .fail((err) => {
                    Notification.exception(err);
                });
        });

        $('#atfGenCreateModal').modal('show');
    };

    /**
     * Modal: view / edit / delete general event.
     *
     * @param {Object} item
     * @param {Object} strings
     * @param {boolean} canManageGeneral
     */
    const showGeneralItemModal = (item, strings, canManageGeneral) => {
        const ro = canManageGeneral ? '' : ' readonly disabled';
        const modalHtml =
            '<div class="modal fade" id="atfGenEditModal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">' +
            escapeHtml(strings.generalevent) +
            '</h5>' +
            '<button type="button" class="close" data-dismiss="modal" aria-label="' +
            escapeHtml(strings.close) +
            '"><span aria-hidden="true">&times;</span></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="form-group">' +
            '<label for="atfEditGenName">' +
            escapeHtml(strings.eventtitle) +
            '</label>' +
            '<input type="text" class="form-control" id="atfEditGenName" maxlength="255"' +
            ro +
            '>' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="atfEditGenDesc">' +
            escapeHtml(strings.eventdescription) +
            '</label>' +
            '<textarea class="form-control" id="atfEditGenDesc" rows="3"' +
            ro +
            '></textarea>' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="atfEditGenStart">' +
            escapeHtml(strings.eventstartdate) +
            '</label>' +
            '<input type="date" class="form-control" id="atfEditGenStart"' +
            ro +
            '>' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="atfEditGenEnd">' +
            escapeHtml(strings.eventenddate) +
            '</label>' +
            '<input type="date" class="form-control" id="atfEditGenEnd"' +
            ro +
            '>' +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            (canManageGeneral ?
                '<button type="button" class="btn btn-danger mr-auto" id="atfEditGenDelete">' +
                    escapeHtml(strings.deletegeneralevent) +
                    '</button>' :
                '') +
            '<button type="button" class="btn btn-secondary" data-dismiss="modal">' +
            escapeHtml(strings.close) +
            '</button>' +
            (canManageGeneral ?
                '<button type="button" class="btn btn-primary" id="atfEditGenSave">' +
                    escapeHtml(strings.savechanges) +
                    '</button>' :
                '') +
            '</div></div></div></div>';

        $('#atfGenEditModal').remove();
        $('body').append(modalHtml);

        $('#atfEditGenName').val(item.name);
        $('#atfEditGenDesc').val(item.description || '');
        $('#atfEditGenStart').val(dateInputFromDate(stripDay(new Date(item.start * 1000))));
        $('#atfEditGenEnd').val(dateInputFromDate(stripDay(new Date(item.end * 1000))));

        if (canManageGeneral) {
            $('#atfEditGenSave').on('click', () => {
                const name = $('#atfEditGenName').val().trim();
                const desc = $('#atfEditGenDesc').val();
                const startStr = $('#atfEditGenStart').val();
                const endStr = $('#atfEditGenEnd').val();
                if (!name) {
                    Notification.alert(strings.error, strings.eventtitlerequired, strings.close);
                    return;
                }
                const startTs = startTsFromDateInput(startStr);
                const endTs = endTsFromDateInput(endStr);
                if (startTs > endTs) {
                    Notification.alert(strings.error, strings.enddatebeforestartdate, strings.close);
                    return;
                }
                const promises = Ajax.call([
                    {
                        methodname: 'local_annualtrainingforecast_update_generalevent',
                        args: {
                            id: item.id,
                            name: name,
                            description: desc,
                            start: startTs,
                            end: endTs,
                        },
                    },
                ]);
                promises[0]
                    .done((response) => {
                        if (response.success) {
                            $('#atfGenEditModal').modal('hide');
                            window.location.reload();
                        } else {
                            Notification.alert(strings.error, response.message || strings.updatefailed, strings.close);
                        }
                    })
                    .fail((err) => {
                        Notification.exception(err);
                    });
            });

            $('#atfEditGenDelete').on('click', () => {
                Notification.confirm(
                    strings.deletegeneralevent,
                    strings.confirmeventdelete,
                    strings.delete,
                    strings.cancel,
                    () => {
                        const promises = Ajax.call([
                            {
                                methodname: 'local_annualtrainingforecast_delete_generalevent',
                                args: {
                                    id: item.id,
                                },
                            },
                        ]);
                        promises[0]
                            .done((response) => {
                                if (response.success) {
                                    $('#atfGenEditModal').modal('hide');
                                    window.location.reload();
                                } else {
                                    Notification.alert(
                                        strings.error,
                                        response.message || strings.updatefailed,
                                        strings.close,
                                    );
                                }
                            })
                            .fail((err) => {
                                Notification.exception(err);
                            });
                    },
                );
            });
        }

        $('#atfGenEditModal').modal('show');
    };

    /**
     * Show iteration details / status modal.
     *
     * @param {Object} item
     * @param {Object} strings
     * @param {boolean} canUpdate
     * @param {string} localeTag
     */
    const showItemModal = (item, strings, canUpdate, localeTag) => {
        const disabledAttr = canUpdate ? '' : ' disabled';
        const modalHtml =
            '<div class="modal fade" id="atfCourseModal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">' +
            escapeHtml(item.name) +
            '</h5>' +
            '<button type="button" class="close" data-dismiss="modal" aria-label="' +
            escapeHtml(strings.close) +
            '">' +
            '<span aria-hidden="true">&times;</span></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p><strong>' +
            escapeHtml(strings.parentcourse) +
            ':</strong> ' +
            escapeHtml(item.parentname) +
            '</p>' +
            '<p>' +
            stripDay(new Date(item.start * 1000)).toLocaleDateString(localeTag) +
            ' – ' +
            stripDay(new Date(item.end * 1000)).toLocaleDateString(localeTag) +
            '</p>' +
            '<div class="form-group">' +
            '<label for="atfCourseStatus">' +
            escapeHtml(strings.statuslabel) +
            '</label>' +
            '<select class="form-control" id="atfCourseStatus"' +
            disabledAttr +
            '>' +
            '<option value="0"' +
            (item.status === 0 ? ' selected' : '') +
            '>' +
            escapeHtml(strings.status0) +
            '</option>' +
            '<option value="1"' +
            (item.status === 1 ? ' selected' : '') +
            '>' +
            escapeHtml(strings.status1) +
            '</option>' +
            '<option value="2"' +
            (item.status === 2 ? ' selected' : '') +
            '>' +
            escapeHtml(strings.status2) +
            '</option>' +
            '<option value="3"' +
            (item.status === 3 ? ' selected' : '') +
            '>' +
            escapeHtml(strings.status3) +
            '</option>' +
            '</select></div>' +
            '<div class="form-group form-check">' +
            '<input type="checkbox" class="form-check-input" id="atfCourseCompleted"' +
            (item.completed ? ' checked' : '') +
            disabledAttr +
            '>' +
            '<label class="form-check-label" for="atfCourseCompleted">' +
            escapeHtml(strings.completed) +
            '</label></div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-dismiss="modal">' +
            escapeHtml(strings.close) +
            '</button>' +
            (canUpdate ?
                '<button type="button" class="btn btn-primary" id="atfSaveCourse">' +
                    escapeHtml(strings.savechanges) +
                    '</button>' :
                '') +
            '</div></div></div></div>';

        $('#atfCourseModal').remove();
        $('body').append(modalHtml);

        if (canUpdate) {
            $('#atfSaveCourse').on('click', () => {
                const newStatus = parseInt($('#atfCourseStatus').val(), 10);
                const newCompleted = $('#atfCourseCompleted').is(':checked') ? 1 : 0;
                const promises = Ajax.call([
                    {
                        methodname: 'local_annualtrainingforecast_update_iteration_status',
                        args: {
                            id: item.id,
                            status: newStatus,
                            completed: newCompleted,
                        },
                    },
                ]);
                promises[0]
                    .done((response) => {
                        if (response.success) {
                            $('#atfCourseModal').modal('hide');
                            window.location.reload();
                        } else {
                            Notification.alert(strings.error, response.message || strings.updatefailed, strings.close);
                        }
                    })
                    .fail((err) => {
                        Notification.exception(err);
                    });
            });
        }

        $('#atfCourseModal').modal('show');
    };

    /**
     * Wire delegated handlers on the calendar root.
     *
     * @param {jQuery} root
     * @param {Array<Object>} items
     * @param {Object} strings
     * @param {boolean} canUpdate
     * @param {string} localeTag
     * @param {boolean} canManageGeneral
     */
    const bindCalendarEvents = (root, items, strings, canUpdate, localeTag, canManageGeneral) => {
        root.off('click.atfchip').on('click.atfchip', '.atf-event-chip', function(ev) {
            ev.preventDefault();
            const id = parseInt($(this).data('id'), 10);
            const found = items.filter((it) => it.id === id);
            if (!found.length) {
                return;
            }
            const item = found[0];
            if (item.isgeneralevent) {
                showGeneralItemModal(item, strings, canManageGeneral);
            } else {
                showItemModal(item, strings, canUpdate, localeTag);
            }
        });

        if (canManageGeneral) {
            root.off('click.atfday').on('click.atfday', '.atf-day.atf-day-clickable', function(ev) {
                if ($(ev.target).closest('.atf-event-chip').length) {
                    return;
                }
                const key = $(this).data('date');
                if (!key || typeof key !== 'string') {
                    return;
                }
                const parts = key.split('-');
                if (parts.length !== 3) {
                    return;
                }
                const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                showCreateGeneralEventModal(d, strings, localeTag);
            });
        }
    };

    /**
     * Load strings used by the calendar and modal.
     *
     * @returns {JQuery.Promise<Object>}
     */
    const loadStrings = () =>
        Str.get_strings([
            {key: 'status_upcoming', component: 'local_annualtrainingforecast'},
            {key: 'status_inprogress', component: 'local_annualtrainingforecast'},
            {key: 'status_completed', component: 'local_annualtrainingforecast'},
            {key: 'status_cancelled', component: 'local_annualtrainingforecast'},
            {key: 'parentcourse', component: 'local_annualtrainingforecast'},
            {key: 'completed', component: 'local_annualtrainingforecast'},
            {key: 'updatefailed', component: 'local_annualtrainingforecast'},
            {key: 'moreactivities', component: 'local_annualtrainingforecast'},
            {key: 'calendarlegend', component: 'local_annualtrainingforecast'},
            {key: 'savechanges', component: 'moodle'},
            {key: 'close', component: 'admin'},
            {key: 'error', component: 'moodle'},
            {key: 'status', component: 'local_annualtrainingforecast'},
            {key: 'generalevent', component: 'local_annualtrainingforecast'},
            {key: 'addgeneralevent', component: 'local_annualtrainingforecast'},
            {key: 'eventtitle', component: 'local_annualtrainingforecast'},
            {key: 'eventtitlerequired', component: 'local_annualtrainingforecast'},
            {key: 'eventdescription', component: 'local_annualtrainingforecast'},
            {key: 'eventstartdate', component: 'local_annualtrainingforecast'},
            {key: 'eventenddate', component: 'local_annualtrainingforecast'},
            {key: 'deletegeneralevent', component: 'local_annualtrainingforecast'},
            {key: 'confirmeventdelete', component: 'local_annualtrainingforecast'},
            {key: 'dayclickhint', component: 'local_annualtrainingforecast'},
            {key: 'delete', component: 'moodle'},
            {key: 'cancel', component: 'moodle'},
            {key: 'enddatebeforestartdate', component: 'local_annualtrainingforecast'},
        ]).then((s) => ({
            status0: s[0],
            status1: s[1],
            status2: s[2],
            status3: s[3],
            parentcourse: s[4],
            completed: s[5],
            updatefailed: s[6],
            moreactivities: s[7],
            calendarlegend: s[8],
            savechanges: s[9],
            close: s[10],
            error: s[11],
            statuslabel: s[12],
            generalevent: s[13],
            addgeneralevent: s[14],
            eventtitle: s[15],
            eventtitlerequired: s[16],
            eventdescription: s[17],
            eventstartdate: s[18],
            eventenddate: s[19],
            deletegeneralevent: s[20],
            confirmeventdelete: s[21],
            dayclickhint: s[22],
            delete: s[23],
            cancel: s[24],
            enddatebeforestartdate: s[25],
        }));

    /**
     * Render template and inject year calendar.
     *
     * @param {Object} data
     */
    const renderCalendar = (data) => {
        const container = $('#gantt-chart');
        const templateData = {
            startdate: data.timerange.start,
            enddate: data.timerange.end,
            totaldays: Math.ceil((data.timerange.end - data.timerange.start) / (24 * 60 * 60)),
            items: data.items,
        };

        Templates.render('local_annualtrainingforecast/gantt_chart', templateData)
            .done((html) => {
                container.html(html);
                const items = collectItems();
                const canUpdate = container.data('can-update') === 1 || container.data('can-update') === '1';
                const canManageGeneral =
                    container.data('can-manage-general') === 1 || container.data('can-manage-general') === '1';
                const root = $('#atf-year-calendar-root');
                const localeTag = localeFromMoodle(Cfg);

                loadStrings().done((strings) => {
                    const calendarHtml = buildCalendarHtml(
                        data.timerange.start,
                        data.timerange.end,
                        items,
                        strings,
                        localeTag,
                        canManageGeneral,
                    );
                    root.html(calendarHtml);
                    bindCalendarEvents(root, items, strings, canUpdate, localeTag, canManageGeneral);
                });
            })
            .fail((error) => {
                Notification.exception(error);
            });
    };

    /**
     * Fetch data and render.
     *
     * @param {string} viewType
     * @param {number} year
     */
    const loadGanttData = (viewType, year) => {
        $('#gantt-loading').show();

        const promises = Ajax.call([
            {
                methodname: 'local_annualtrainingforecast_get_gantt_data',
                args: {
                    viewtype: viewType,
                    year: year,
                },
            },
        ]);

        promises[0]
            .done((response) => {
                renderCalendar(response);
                $('#gantt-loading').hide();
            })
            .fail((error) => {
                $('#gantt-loading').hide();
                Notification.exception(error);
            });
    };

    /**
     * Module init.
     */
    const init = () => {
        const container = $('#gantt-chart');
        if (container.length === 0) {
            return;
        }
        const viewType = container.data('view');
        const year = container.data('year');
        loadGanttData(viewType, year);
    };

    return {
        init: init,
    };
});
