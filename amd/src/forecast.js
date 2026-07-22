// AMD module: local_annualtrainingforecast/forecast
define(['core/ajax', 'core/notification', 'core/config'], function(Ajax, Notification, Cfg) {
    'use strict';

    const VIEW = { MONTH: 'month', QUARTER: 'quarter', HALFYEAR: 'halfyear', YEAR: 'year' };
    const ROW_H = 52; // must match --gantt-row-h in CSS
    const DAY_MIN_PX = 52;   // minimum column width when showing day columns (px)
    const WD_FALLBACK = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    // Column resolutions available via Ctrl+scroll zoom (coarse → fine)
    const ZOOM_RESOLUTIONS = ['months', 'weeks', 'days'];

    // Default column resolution for each view mode
    function defaultResolution(viewMode) {
        if (viewMode === VIEW.MONTH)   return 'days';
        if (viewMode === VIEW.QUARTER) return 'weeks';
        return 'months'; // halfyear, year
    }

    let state = {
        contextId:     0,
        canManage:     false,
        canAddEvent:   false,
        manageUrl:     '',
        viewMode:      VIEW.YEAR,
        colResolution: 'months', // 'months' | 'weeks' | 'days' — changed by Ctrl+scroll
        periodStart:   null,
        trainings:     [],
        events:        [],
        filters:       { status: '', category: '', managerid: 0, search: '' },
        debounceTimer: null,
        strings:       {},
    };

    // Shorthand: get a localized string with an English fallback
    function S(key, fallback) {
        return state.strings[key] || fallback || key;
    }

    // Localized weekday abbreviations (Sunday=0 … Saturday=6)
    function getWD() {
        const wd = state.strings.wd;
        return (Array.isArray(wd) && wd.length === 7) ? wd : WD_FALLBACK;
    }

    // =========================================================================
    // Entry point
    // =========================================================================

    const STORAGE_VIEW = 'atf_forecast_viewmode';
    const STORAGE_PERIOD = 'atf_forecast_periodstart';

    function init(contextId, canManage, canAddEvent) {
        state.contextId   = contextId;
        state.canManage   = !!canManage;
        state.canAddEvent = !!canAddEvent;
        state.manageUrl   = Cfg.wwwroot + '/local/annualtrainingforecast/manage.php';
        state.periodStart = periodStartFor(VIEW.YEAR, new Date());

        // Restore the last-used view mode / period so refreshing or returning
        // from the manage page keeps the user's chosen view.
        restoreViewState();

        // Load localized strings embedded by the template
        const strEl = document.getElementById('gantt-strings-data');
        if (strEl) {
            try { state.strings = JSON.parse(strEl.textContent) || {}; } catch (e) {}
        }

        setAppHeight();
        window.addEventListener('resize', () => { setAppHeight(); renderAll(); });

        bindControls();
        applyActiveViewButton();
        loadFilters();
        loadData();
    }

    function restoreViewState() {
        try {
            const validViews = [VIEW.MONTH, VIEW.QUARTER, VIEW.HALFYEAR, VIEW.YEAR];
            const savedView = window.localStorage.getItem(STORAGE_VIEW);
            if (savedView && validViews.indexOf(savedView) !== -1) {
                state.viewMode      = savedView;
                state.colResolution = defaultResolution(savedView);
            }
            const savedPeriod = window.localStorage.getItem(STORAGE_PERIOD);
            const ts = savedPeriod ? parseInt(savedPeriod, 10) : NaN;
            const anchor = !isNaN(ts) ? new Date(ts) : new Date();
            state.periodStart = periodStartFor(state.viewMode, anchor);
        } catch (e) {
            state.periodStart = periodStartFor(state.viewMode, new Date());
        }
    }

    function saveViewState() {
        try {
            window.localStorage.setItem(STORAGE_VIEW, state.viewMode);
            window.localStorage.setItem(STORAGE_PERIOD, String(state.periodStart.getTime()));
        } catch (e) {
            // Storage unavailable (private mode / quota) — non-fatal.
        }
    }

    function applyActiveViewButton() {
        document.querySelectorAll('.gantt-view-btn').forEach(btn =>
            btn.classList.toggle('gantt-view-btn--active', btn.dataset.view === state.viewMode));
    }

    // =========================================================================
    // Height: fill viewport from the app's top edge
    // =========================================================================

    function setAppHeight() {
        const app = document.getElementById('gantt-forecast-app');
        if (!app) return;
        const top = app.getBoundingClientRect().top;
        app.style.height = Math.max(420, window.innerHeight - top - 8) + 'px';
    }

    // =========================================================================
    // Percentage helper — all bar/column positions expressed as % of period
    // =========================================================================

    function pct(part, total) {
        if (!total) return '0%';
        return (part / total * 100).toFixed(6) + '%';
    }

    // =========================================================================
    // Controls
    // =========================================================================

    function bindControls() {
        document.getElementById('gantt-prev').addEventListener('click', () => navigate(-1));
        document.getElementById('gantt-next').addEventListener('click', () => navigate(1));
        document.getElementById('gantt-today').addEventListener('click', jumpToToday);

        document.querySelectorAll('.gantt-view-btn').forEach(btn =>
            btn.addEventListener('click', () => setViewMode(btn.dataset.view)));

        ['gantt-search', 'gantt-filter-status', 'gantt-filter-category', 'gantt-filter-manager']
            .forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input',  debounceReload);
                    el.addEventListener('change', debounceReload);
                }
            });

        const wrapper = document.getElementById('gantt-timeline-wrapper');
        if (wrapper) {
            wrapper.addEventListener('wheel', onWheel, { passive: false });
            const names = document.getElementById('gantt-names');
            if (names) {
                wrapper.addEventListener('scroll', () => { names.scrollTop = wrapper.scrollTop; });
            }
        }

        document.getElementById('gantt-export-excel')?.addEventListener('click', updateExportLinks);
        document.getElementById('gantt-export-pdf')?.addEventListener('click',   updateExportLinks);

        document.getElementById('gantt-add-event')?.addEventListener('click', () => openEventModal(null));

        // Event modal
        document.getElementById('gantt-event-close')?.addEventListener('click', closeEventModal);
        document.getElementById('event-cancel-btn')?.addEventListener('click',  closeEventModal);
        document.getElementById('event-save-btn')?.addEventListener('click',    saveEvent);
        document.getElementById('event-delete-btn')?.addEventListener('click',  deleteEventFromModal);
        document.getElementById('gantt-modal-event')?.addEventListener('click', e => {
            if (e.target === e.currentTarget) closeEventModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') { closeEventModal(); }
        });
    }

    // =========================================================================
    // Navigation
    // =========================================================================

    function navigate(dir) {
        const d = new Date(state.periodStart);
        switch (state.viewMode) {
            case VIEW.MONTH:    d.setMonth(d.getMonth() + dir);      break;
            case VIEW.QUARTER:  d.setMonth(d.getMonth() + dir * 3);  break;
            case VIEW.HALFYEAR: d.setMonth(d.getMonth() + dir * 6);  break;
            case VIEW.YEAR:     d.setFullYear(d.getFullYear() + dir); break;
        }
        state.periodStart = d;
        loadData();
    }

    function jumpToToday() {
        state.periodStart = periodStartFor(state.viewMode, new Date());
        loadData();
    }

    function setViewMode(view) {
        if (view === state.viewMode) return;
        state.viewMode      = view;
        state.colResolution = defaultResolution(view); // reset zoom when changing view
        state.periodStart   = periodStartFor(view, centerDate());
        document.querySelectorAll('.gantt-view-btn').forEach(btn =>
            btn.classList.toggle('gantt-view-btn--active', btn.dataset.view === view));
        loadData();
    }

    // =========================================================================
    // Zoom: Ctrl + scroll wheel → change column resolution within the current view
    //
    // Resolution ladder: months → weeks → days (finer on scroll-down/in).
    // Month view is already at the finest level — no-op there.
    // Quarter view starts at weeks and can only zoom in to days (not out to months).
    // Half Year and Year start at months and can zoom all the way to days.
    // =========================================================================

    function onWheel(e) {
        if (!e.ctrlKey) return;
        e.preventDefault();

        // Month view is already at day resolution — nothing finer to show.
        if (state.viewMode === VIEW.MONTH) return;

        // Quarter can't show months (too coarse for 3-month period with month cols).
        const minResIdx = state.viewMode === VIEW.QUARTER ? 1 : 0;
        const maxResIdx = ZOOM_RESOLUTIONS.length - 1;

        const curIdx = ZOOM_RESOLUTIONS.indexOf(state.colResolution);
        const delta  = e.deltaY > 0 ? 1 : -1; // scroll-down = zoom in = finer
        const newIdx = Math.min(Math.max(curIdx + delta, minResIdx), maxResIdx);
        if (newIdx === curIdx) return;

        // Remember which date fraction is under the pointer so we can scroll back to it.
        const wrapper = document.getElementById('gantt-timeline-wrapper');
        let pointerFrac = 0;
        if (wrapper) {
            const rect  = wrapper.getBoundingClientRect();
            const totalW = wrapper.scrollWidth || wrapper.clientWidth;
            pointerFrac  = Math.max(0, Math.min(1,
                (e.clientX - rect.left + wrapper.scrollLeft) / totalW));
        }

        state.colResolution = ZOOM_RESOLUTIONS[newIdx];
        renderAll();

        // After re-render scroll so the same date stays under the pointer.
        if (wrapper && wrapper.scrollWidth > wrapper.clientWidth) {
            wrapper.scrollLeft = pointerFrac * wrapper.scrollWidth - (e.clientX - wrapper.getBoundingClientRect().left);
        }
    }

    // =========================================================================
    // Data
    // =========================================================================

    function debounceReload() {
        clearTimeout(state.debounceTimer);
        state.debounceTimer = setTimeout(loadData, 300);
    }

    function loadFilters() {
        Ajax.call([{ methodname: 'local_annualtrainingforecast_get_filters', args: {} }])[0]
            .then(data => {
                populateSelect('gantt-filter-category', data.categories);
                populateSelect('gantt-filter-manager',  data.managers);
                populateCategoryDatalist(data.categories);
            }).catch(Notification.exception);
    }

    function populateSelect(id, items) {
        const sel = document.getElementById(id);
        if (!sel) return;
        while (sel.options.length > 1) sel.remove(1);
        items.forEach(item => sel.add(new Option(item.label, item.value)));
    }

    function populateCategoryDatalist(categories) {
        const dl = document.getElementById('gantt-categories-datalist');
        if (!dl) return;
        dl.innerHTML = '';
        categories.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.value;
            dl.appendChild(opt);
        });
    }

    function readFilters() {
        const get = id => { const el = document.getElementById(id); return el ? el.value : ''; };
        state.filters = {
            status:    get('gantt-filter-status'),
            category:  get('gantt-filter-category'),
            managerid: parseInt(get('gantt-filter-manager'), 10) || 0,
            search:    get('gantt-search'),
        };
    }

    function loadData() {
        readFilters();
        saveViewState();
        const range = visibleRange();
        showLoading(true);
        hideEmpty();

        return Ajax.call([{
            methodname: 'local_annualtrainingforecast_get_forecast_data',
            args: {
                startdate: Math.floor(range.start.getTime() / 1000),
                enddate:   Math.floor(range.end.getTime()   / 1000),
                status:    state.filters.status,
                category:  state.filters.category,
                managerid: state.filters.managerid,
                search:    state.filters.search,
            },
        }])[0].then(data => {
            state.trainings = data.trainings;
            state.events    = data.events;
            renderAll();
            showLoading(false);
            if (!state.trainings.length && !state.events.length) showEmpty();
        }).catch(err => {
            showLoading(false);
            Notification.exception(err);
        });
    }

    // =========================================================================
    // Render
    // =========================================================================

    function renderAll() {
        updatePeriodLabel();
        renderNames();
        renderTimeline();
    }

    function updatePeriodLabel() {
        const label = document.getElementById('gantt-period-label');
        if (!label) return;
        const range = visibleRange();
        const opts  = { month: 'short', year: 'numeric' };
        const fmt   = d => d.toLocaleDateString(undefined, opts);
        label.textContent = fmt(range.start) + ' – ' + fmt(new Date(range.end.getTime() - 86400000));
    }

    // ── Names column ──────────────────────────────────────────────────────────

    function renderNames() {
        const container = document.getElementById('gantt-names');
        if (!container) return;
        container.querySelectorAll('.gantt-name-row').forEach(r => r.remove());

        state.trainings.forEach(t => {
            const row = document.createElement('div');
            row.className  = 'gantt-name-row';
            row.setAttribute('role', 'listitem');
            row.dataset.id = t.id;

            const text = document.createElement('span');
            text.className   = 'gantt-name-row__text';
            text.textContent = t.name;
            text.title       = [t.name, t.category].filter(Boolean).join(' · ');
            row.appendChild(text);

            const badge = document.createElement('span');
            badge.className   = 'gantt-name-row__badge gantt-bar--' + t.status;
            badge.textContent = statusLabel(t.status);
            row.appendChild(badge);

            if (state.canManage) {
                const actions = document.createElement('div');
                actions.className = 'gantt-name-row__actions';
                actions.appendChild(makeIconBtn('✎', 'Edit', () => openTrainingModal(t)));
                row.appendChild(actions);
            }

            container.appendChild(row);
        });
    }

    function makeIconBtn(icon, title, onClick) {
        const btn = document.createElement('button');
        btn.className   = 'gantt-icon-btn';
        btn.textContent = icon;
        btn.title       = title;
        btn.addEventListener('click', e => { e.stopPropagation(); onClick(); });
        return btn;
    }

    // ── Timeline (percentage-based, with min-width for month view) ───────────

    function renderTimeline() {
        const range     = visibleRange();
        const totalDays = daysBetween(range.start, range.end);
        const totalH    = Math.max(state.trainings.length * ROW_H + 10, 200);
        const cols      = buildColumns(range);

        // Apply day-level horizontal scroll whenever column resolution is 'days'
        // (which includes Month view and any other view zoomed in to day columns).
        const isDayRes  = (state.colResolution === 'days' || state.viewMode === VIEW.MONTH);
        const minBodyPx = isDayRes ? totalDays * DAY_MIN_PX : 0;

        const app = document.getElementById('gantt-forecast-app');
        if (app) {
            app.classList.toggle('gantt--month-view', isDayRes);
        }

        // Header and body MUST share the same width, otherwise day-% positions
        // on bars drift away from the date labels (visible in month / day zoom).
        const header = document.getElementById('gantt-timeline-header');
        const body = document.getElementById('gantt-timeline-body');
        const widthCss = minBodyPx ? minBodyPx + 'px' : '';
        if (header) {
            header.style.minWidth = widthCss;
            header.style.width = widthCss || '100%';
        }
        if (body) {
            body.style.minHeight = totalH + 'px';
            body.style.minWidth = widthCss;
            body.style.width = widthCss || '100%';
        }

        renderHeader(cols, totalDays);
        renderGrid(range, cols, totalDays, totalH);
        renderRows(totalH);
        renderBars(range, totalDays);
        renderTodayMarker(range, totalDays, totalH);
        renderMilestones(range, totalDays, totalH);
    }

    function renderHeader(cols, totalDays) {
        const header = document.getElementById('gantt-timeline-header');
        if (!header) return;
        header.innerHTML = '';
        const today = new Date(); today.setHours(0, 0, 0, 0);

        cols.forEach(col => {
            const div = document.createElement('div');
            div.className   = 'gantt-header-col';
            div.style.width = pct(col.days, totalDays);
            div.title       = col.fullLabel || col.label;
            if (col.containsToday && col.containsToday(today)) div.classList.add('gantt-header-col--today');
            if (col.isWeekend)                                  div.classList.add('gantt-header-col--weekend');

            if (col.sublabel) {
                const wd  = document.createElement('span');
                wd.className   = 'gantt-header-col__wd';
                wd.textContent = col.sublabel;
                const num = document.createElement('span');
                num.className   = 'gantt-header-col__num';
                num.textContent = col.label;
                div.appendChild(wd);
                div.appendChild(num);
            } else {
                div.textContent = col.label;
            }

            header.appendChild(div);
        });
    }

    function renderGrid(range, cols, totalDays, totalH) {
        const grid = document.getElementById('gantt-grid');
        if (!grid) return;
        grid.innerHTML    = '';
        grid.style.height = totalH + 'px';

        if (state.viewMode === VIEW.MONTH) {
            let d = new Date(range.start), dayIdx = 0;
            while (d < range.end) {
                const dow = d.getDay();
                if (dow === 0 || dow === 6) {
                    const bg = document.createElement('div');
                    bg.className    = 'gantt-grid-weekend';
                    bg.style.left   = pct(dayIdx, totalDays);
                    bg.style.width  = pct(1, totalDays);
                    bg.style.height = totalH + 'px';
                    grid.appendChild(bg);
                }
                dayIdx++;
                d.setDate(d.getDate() + 1);
            }
        }

        let accDays = 0;
        cols.forEach((col, i) => {
            if (i > 0) {
                const line = document.createElement('div');
                line.className    = 'gantt-grid-line' + (col.isMajor ? ' gantt-grid-line--major' : '');
                line.style.left   = pct(accDays, totalDays);
                line.style.height = totalH + 'px';
                grid.appendChild(line);
            }
            accDays += col.days;
        });

        const today = new Date(); today.setHours(0, 0, 0, 0);
        if (today >= range.start && today < range.end) {
            const todayBg = document.createElement('div');
            todayBg.className    = 'gantt-grid-today-col';
            todayBg.style.left   = pct(daysBetween(range.start, today), totalDays);
            todayBg.style.width  = pct(1, totalDays);
            todayBg.style.height = totalH + 'px';
            grid.appendChild(todayBg);
        }
    }

    function renderRows(totalH) {
        const rows = document.getElementById('gantt-rows');
        if (!rows) return;
        rows.innerHTML = '';
        state.trainings.forEach(() => {
            const stripe = document.createElement('div');
            stripe.className = 'gantt-row-stripe';
            rows.appendChild(stripe);
        });
    }

    function renderBars(range, totalDays) {
        const rows = document.getElementById('gantt-rows');
        if (!rows) {
            return;
        }
        const stripes = rows.querySelectorAll('.gantt-row-stripe');

        state.trainings.forEach((t, idx) => {
            const stripe = stripes[idx];
            if (!stripe) {
                return;
            }

            const tStart = new Date(t.startdate * 1000);
            tStart.setHours(0, 0, 0, 0);
            const tEnd = new Date(t.enddate * 1000);
            tEnd.setHours(0, 0, 0, 0);

            // End date is inclusive — use the next day as the exclusive boundary
            // so a course 15/07–30/07 covers all 16 day columns.
            const tEndExclusive = new Date(tEnd);
            tEndExclusive.setDate(tEndExclusive.getDate() + 1);

            const dispStart = tStart < range.start ? range.start : tStart;
            const dispEnd = tEndExclusive > range.end ? range.end : tEndExclusive;

            if (dispStart >= range.end || dispEnd <= range.start) {
                return;
            }

            const leftDays = daysBetween(range.start, dispStart);
            const widthDays = daysBetween(dispStart, dispEnd);
            if (widthDays <= 0) {
                return;
            }

            const bar = document.createElement('div');
            bar.className = 'gantt-bar gantt-bar--' + t.status;
            bar.style.left = pct(leftDays, totalDays);
            bar.style.width = pct(widthDays, totalDays);
            bar.tabIndex = 0;
            bar.setAttribute('role', 'button');
            bar.setAttribute('aria-label', t.name + ' — ' + t.status);
            bar.dataset.id = t.id;

            const lbl = document.createElement('span');
            lbl.className = 'gantt-bar__label';
            lbl.textContent = t.name;
            bar.appendChild(lbl);

            if (state.canManage) {
                const actions = document.createElement('div');
                actions.className = 'gantt-bar__actions';
                actions.appendChild(makeIconBtn('✎', 'Edit', () => openTrainingModal(t)));
                bar.appendChild(actions);
                bar.addEventListener('dblclick', () => openTrainingModal(t));
            }

            bar.addEventListener('mouseenter', e => showTooltip(e, trainingTooltipHtml(t)));
            bar.addEventListener('mouseleave', hideTooltip);
            bar.addEventListener('focus', e => showTooltip(e, trainingTooltipHtml(t)));
            bar.addEventListener('blur', hideTooltip);

            stripe.appendChild(bar);
        });
    }

    function renderTodayMarker(range, totalDays, totalH) {
        const marker = document.getElementById('gantt-today-marker');
        if (!marker) return;
        const today = new Date(); today.setHours(0, 0, 0, 0);

        if (today < range.start || today >= range.end) {
            marker.style.display = 'none';
            return;
        }
        marker.style.display = 'block';
        marker.style.left    = pct(daysBetween(range.start, today), totalDays);
        marker.style.height  = totalH + 'px';
    }

    function renderMilestones(range, totalDays, totalH) {
        const layer = document.getElementById('gantt-events-layer');
        if (!layer) return;
        layer.innerHTML    = '';
        layer.style.height = totalH + 'px';

        state.events.forEach(ev => {
            const evDate = new Date(ev.eventdate * 1000); evDate.setHours(0, 0, 0, 0);
            if (evDate < range.start || evDate >= range.end) return;

            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:absolute;left:' + pct(daysBetween(range.start, evDate), totalDays) +
                                  ';top:0;width:0;height:100%;pointer-events:none;';

            const diamond = document.createElement('div');
            diamond.className = 'gantt-milestone';
            diamond.style.top = '14px';
            diamond.tabIndex  = 0;
            diamond.setAttribute('role', 'button');
            diamond.setAttribute('aria-label', ev.title);

            diamond.addEventListener('mouseenter', e => showTooltip(e, eventTooltipHtml(ev)));
            diamond.addEventListener('mouseleave', hideTooltip);
            diamond.addEventListener('focus',      e => showTooltip(e, eventTooltipHtml(ev)));
            diamond.addEventListener('blur',       hideTooltip);

            if (state.canAddEvent) {
                const editBtn = document.createElement('div');
                editBtn.className   = 'gantt-milestone__edit';
                editBtn.textContent = '✎';
                editBtn.title       = 'Edit event';
                editBtn.addEventListener('click', e => { e.stopPropagation(); openEventModal(ev); });
                diamond.appendChild(editBtn);
                diamond.addEventListener('dblclick', () => openEventModal(ev));
            }

            wrap.appendChild(diamond);
            layer.appendChild(wrap);
        });
    }

    // =========================================================================
    // Column builders
    // =========================================================================

    function buildColumns(range) {
        // Month view is always day-level; otherwise use the zoomed resolution.
        if (state.viewMode === VIEW.MONTH || state.colResolution === 'days')  return dayColumns(range);
        if (state.colResolution === 'weeks')                                   return weekColumns(range);
        return monthColumns(range);
    }

    function monthColumns(range) {
        const cols = [];
        let d = new Date(range.start.getFullYear(), range.start.getMonth(), 1);
        while (d < range.end) {
            const mStart = new Date(d.getFullYear(), d.getMonth(), 1);
            const mEnd   = new Date(d.getFullYear(), d.getMonth() + 1, 1);
            const cStart = mStart < range.start ? range.start : mStart;
            const cEnd   = mEnd   > range.end   ? range.end   : mEnd;
            cols.push({
                label:         d.toLocaleString(undefined, { month: 'short' }) + ' ' + d.getFullYear(),
                fullLabel:     d.toLocaleString(undefined, { month: 'long', year: 'numeric' }),
                days:          daysBetween(cStart, cEnd),
                isMajor:       true,
                containsToday: t => t >= mStart && t < mEnd,
            });
            d = new Date(d.getFullYear(), d.getMonth() + 1, 1);
        }
        return cols;
    }

    function weekColumns(range) {
        const cols = [];
        let d = mondayOf(range.start);
        const dd = dt => String(dt.getDate()).padStart(2, '0') + '/' + String(dt.getMonth() + 1).padStart(2, '0');
        while (d < range.end) {
            const wEnd   = new Date(d); wEnd.setDate(d.getDate() + 7);
            const cStart = d    < range.start ? range.start : d;
            const cEnd   = wEnd > range.end   ? range.end   : wEnd;
            const wLast  = new Date(wEnd.getTime() - 86400000);
            cols.push({
                label:         dd(cStart) + ' – ' + dd(wLast),
                fullLabel:     cStart.toLocaleDateString() + ' – ' + wLast.toLocaleDateString(),
                days:          daysBetween(cStart, cEnd),
                isMajor:       cStart.getDate() <= 7 || cStart >= range.start && cStart.getDate() === range.start.getDate(),
                containsToday: t => t >= d && t < wEnd,
            });
            d = new Date(wEnd);
        }
        return cols;
    }

    function dayColumns(range) {
        const WD = getWD();
        const cols = [];
        let d = new Date(range.start);
        while (d < range.end) {
            const day = new Date(d);
            const dow = d.getDay();
            cols.push({
                label:         String(d.getDate()),
                sublabel:      WD[dow],
                fullLabel:     d.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' }),
                days:          1,
                isMajor:       dow === 1,
                isWeekend:     dow === 0 || dow === 6,
                containsToday: t => isSameDay(t, day),
            });
            d.setDate(d.getDate() + 1);
        }
        return cols;
    }

    // =========================================================================
    // Training navigation (managed via manage.php)
    // =========================================================================

    function openTrainingModal(training) {
        const base = state.manageUrl || (Cfg.wwwroot + '/local/annualtrainingforecast/manage.php');
        if (training && training.id) {
            window.location.href = base + '?action=edit&type=iteration&id=' + training.id;
            return;
        }
        window.location.href = base + '?action=add&type=iteration';
    }

    function confirmDeleteTraining() {
        // Deletion is handled on the manage page.
    }

    // =========================================================================
    // Event modal
    // =========================================================================

    function openEventModal(ev) {
        const modal = document.getElementById('gantt-modal-event');
        const title = document.getElementById('gantt-modal-event-title');
        if (!modal) return;
        const isEdit = !!ev;
        if (title) title.textContent = isEdit ? S('editEvent', 'Edit Event') : S('addEvent', 'Add Event');

        setVal('event-id',          isEdit ? ev.id                     : 0);
        setVal('event-title',       isEdit ? ev.title                   : '');
        setVal('event-startdate',   isEdit ? tsToDateStr(ev.eventdate)   : todayStr());
        setVal('event-enddate',     isEdit ? tsToDateStr(ev.enddate || ev.eventdate) : todayStr());
        setVal('event-description', isEdit ? (ev.description || '')     : '');

        const delBtn = document.getElementById('event-delete-btn');
        if (delBtn) delBtn.style.display = isEdit ? 'inline-flex' : 'none';

        hideError('gantt-event-error');
        modal.hidden = false;
        document.getElementById('event-title')?.focus();
    }

    function closeEventModal() {
        const m = document.getElementById('gantt-modal-event');
        if (m) m.hidden = true;
    }

    function saveEvent() {
        const id        = parseInt(getVal('event-id'), 10) || 0;
        const title     = getVal('event-title').trim();
        const startStr  = getVal('event-startdate');
        const endStr    = getVal('event-enddate') || startStr;
        const desc      = getVal('event-description').trim();

        if (!title)    return showError('gantt-event-error', S('errTitle', 'Event title is required.'));
        if (!startStr) return showError('gantt-event-error', S('errEventDate', 'Event date is required.'));

        const startdate = dateStrToTs(startStr);
        const enddate   = dateStrToTs(endStr);
        if (enddate < startdate) {
            return showError('gantt-event-error', S('errDates', 'End date must be on or after start date.'));
        }

        const start = startdate;
        const end = enddate + 86399;

        setBtnLoading('event-save-btn', true);
        hideError('gantt-event-error');

        const method = id ?
            'local_annualtrainingforecast_update_generalevent' :
            'local_annualtrainingforecast_create_generalevent';
        const args = id ?
            { id, name: title, description: desc, start, end } :
            { name: title, description: desc, start, end };

        Ajax.call([{ methodname: method, args }])[0].then((result) => {
            if (result && result.success === false) {
                throw new Error(result.message || S('errGeneric', 'An error occurred. Please try again.'));
            }
            closeEventModal();
            const range = visibleRange();
            const evDate = new Date(start * 1000);
            if (evDate < range.start || evDate >= range.end) {
                state.periodStart = periodStartFor(state.viewMode, evDate);
            }
            return loadData();
        }).catch(err => {
            showError('gantt-event-error', err.message || S('errGeneric', 'An error occurred. Please try again.'));
        }).finally(() => {
            setBtnLoading('event-save-btn', false);
        });
    }

    function deleteEventFromModal() {
        const id = parseInt(getVal('event-id'), 10);
        if (!id || !confirm(S('delEvent', 'Delete this event? This cannot be undone.'))) return;
        Ajax.call([{ methodname: 'local_annualtrainingforecast_delete_generalevent', args: { id } }])[0]
            .then(() => { closeEventModal(); loadData(); })
            .catch(Notification.exception);
    }

    // =========================================================================
    // Date helpers
    // =========================================================================

    function tsToDateStr(ts) {
        const d = new Date(ts * 1000);
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }

    function dateStrToTs(str) {
        const parts = str.split('-').map(Number);
        return Math.floor(new Date(parts[0], parts[1] - 1, parts[2]).getTime() / 1000);
    }

    function todayStr() {
        return tsToDateStr(Math.floor(Date.now() / 1000));
    }

    function visibleRange() {
        const start = new Date(state.periodStart); start.setHours(0, 0, 0, 0);
        const end   = new Date(start);
        switch (state.viewMode) {
            case VIEW.MONTH:    end.setMonth(end.getMonth() + 1);       break;
            case VIEW.QUARTER:  end.setMonth(end.getMonth() + 3);       break;
            case VIEW.HALFYEAR: end.setMonth(end.getMonth() + 6);       break;
            case VIEW.YEAR:     end.setFullYear(end.getFullYear() + 1); break;
        }
        return { start, end };
    }

    function periodStartFor(view, date) {
        const d = new Date(date);
        switch (view) {
            case VIEW.MONTH:    return new Date(d.getFullYear(), d.getMonth(), 1);
            case VIEW.QUARTER:  return new Date(d.getFullYear(), Math.floor(d.getMonth() / 3) * 3, 1);
            case VIEW.HALFYEAR: return new Date(d.getFullYear(), d.getMonth() < 6 ? 0 : 6, 1);
            default:            return new Date(d.getFullYear(), 0, 1);
        }
    }

    function centerDate() {
        const r = visibleRange();
        return new Date((r.start.getTime() + r.end.getTime()) / 2);
    }

    function daysBetween(a, b) {
        return Math.round((b.getTime() - a.getTime()) / 86400000);
    }

    function isSameDay(a, b) {
        return a.getFullYear() === b.getFullYear() &&
               a.getMonth()    === b.getMonth()    &&
               a.getDate()     === b.getDate();
    }

    function mondayOf(date) {
        const d = new Date(date);
        d.setDate(d.getDate() + (d.getDay() === 0 ? -6 : 1 - d.getDay()));
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function statusLabel(status) {
        const ss = state.strings.statuses;
        if (ss && ss[status]) return ss[status];
        const map = {
            upcoming:   'Upcoming',
            inprogress: 'In Progress',
            completed:  'Completed',
            cancelled:  'Cancelled',
            general:    'Event',
        };
        return map[status] || status;
    }

    // =========================================================================
    // Tooltip
    // =========================================================================

    const tip = () => document.getElementById('gantt-tooltip');

    function showTooltip(e, html) {
        const el = tip();
        el.innerHTML = html;
        el.classList.add('is-visible');
        el.setAttribute('aria-hidden', 'false');
        positionTooltip(e);
        e.target.addEventListener('mousemove', positionTooltip);
    }

    function hideTooltip(e) {
        const el = tip();
        el.classList.remove('is-visible');
        el.setAttribute('aria-hidden', 'true');
        if (e) e.target.removeEventListener('mousemove', positionTooltip);
    }

    function positionTooltip(e) {
        const el = tip(), gap = 14;
        let x = e.clientX + gap, y = e.clientY + gap;
        if (x + 270 > window.innerWidth)  x = e.clientX - 270 - gap;
        if (y + 180 > window.innerHeight) y = e.clientY - 180 - gap;
        el.style.left = x + 'px';
        el.style.top  = y + 'px';
    }

    function trainingTooltipHtml(t) {
        const T = state.strings.tip || {};
        const D = state.strings.dur || ['day', 'days'];
        const fmt = ts => new Date(ts * 1000).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
        const row = (l, v) =>
            '<div class="gantt-tooltip__row"><span class="gantt-tooltip__label">' + l +
            '</span><span>' + esc(v) + '</span></div>';
        const dur = Math.round((t.enddate - t.startdate) / 86400) + 1;
        return '<div class="gantt-tooltip__title">' + esc(t.name) + '</div>' +
            (t.category    ? row(T.category || 'Category', t.category)    : '') +
            (t.managername ? row(T.manager  || 'Manager',  t.managername) : '') +
            row(T.start    || 'Start',    fmt(t.startdate)) +
            row(T.end      || 'End',      fmt(t.enddate)) +
            row(T.duration || 'Duration', dur + ' ' + (dur !== 1 ? (D[1] || 'days') : (D[0] || 'day'))) +
            row(T.status   || 'Status',   statusLabel(t.status));
    }

    function eventTooltipHtml(ev) {
        const T = state.strings.tip || {};
        const fmt = ts => new Date(ts * 1000).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });
        return '<div class="gantt-tooltip__title">' + esc(ev.title) + '</div>' +
            '<div class="gantt-tooltip__row"><span class="gantt-tooltip__label">' + (T.date || 'Date') + '</span><span>' + fmt(ev.eventdate) + '</span></div>' +
            '<div class="gantt-tooltip__row"><span class="gantt-tooltip__label">' + (T.type || 'Type') + '</span><span>' + esc(ev.eventtype) + '</span></div>' +
            (ev.description ? '<div class="gantt-tooltip__row"><span class="gantt-tooltip__label">' + (T.note || 'Note') + '</span><span>' + esc(ev.description) + '</span></div>' : '');
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // =========================================================================
    // Export
    // =========================================================================

    function updateExportLinks() {
        const range = visibleRange();
        const viewMap = {
            month: 'quarter',
            quarter: 'quarter',
            halfyear: 'halfyear',
            year: 'year',
        };
        const view = viewMap[state.viewMode] || 'year';
        const year = range.start.getFullYear();
        const qs = new URLSearchParams({
            format: 'excel',
            view: view,
            year: year,
        }).toString();
        const pdfqs = new URLSearchParams({
            format: 'pdf',
            view: view,
            year: year,
        }).toString();
        const excel = document.getElementById('gantt-export-excel');
        const pdf = document.getElementById('gantt-export-pdf');
        if (excel) {
            excel.href = excel.href.split('?')[0] + '?' + qs;
        }
        if (pdf) {
            pdf.href = pdf.href.split('?')[0] + '?' + pdfqs;
        }
    }

    // =========================================================================
    // UI helpers
    // =========================================================================

    function showLoading(show) {
        const el = document.getElementById('gantt-loading');
        if (el) el.style.display = show ? 'flex' : 'none';
    }

    function showEmpty() {
        const wrapper = document.getElementById('gantt-timeline-wrapper');
        if (!wrapper) return;
        let empty = document.getElementById('gantt-empty-state');
        if (!empty) {
            empty = document.createElement('div');
            empty.id        = 'gantt-empty-state';
            empty.className = 'gantt-empty gantt-empty--overlay';
            empty.innerHTML =
                '<div class="gantt-empty__icon">&#128197;</div>' +
                '<div>' + esc(S('noTrainings', 'No trainings found for this period.')) + '</div>' +
                '<div style="font-size:.78rem;color:#94a3b8;margin-top:4px">' +
                esc(S('navHint', 'Navigate with ❮ ❯, or click + Add Training.')) + '</div>';
            wrapper.appendChild(empty);
        }
        empty.style.display = 'flex';
    }

    function hideEmpty() {
        const el = document.getElementById('gantt-empty-state');
        if (el) el.style.display = 'none';
    }

    function getVal(id)    { const el = document.getElementById(id); return el ? el.value : ''; }
    function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v; }

    function showError(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.textContent = msg; el.hidden = false; }
    }
    function hideError(id) {
        const el = document.getElementById(id);
        if (el) { el.textContent = ''; el.hidden = true; }
    }
    function setBtnLoading(id, loading) {
        const el = document.getElementById(id);
        if (!el) return;
        if (loading) {
            el._origText = el.textContent;
            el.textContent = S('saving', 'Saving…');
            el.disabled = true;
        } else {
            el.textContent = el._origText || S('save', 'Save');
            el.disabled = false;
        }
    }

    return { init };
});
