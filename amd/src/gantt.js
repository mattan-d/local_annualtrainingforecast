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
 * Gantt chart JavaScript
 *
 * @module     local_annualtrainingforecast/gantt
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const define = require('define'); // Declare the define variable

define(["jquery", "core/ajax", "core/notification", "core/str", "core/templates"], (
    $,
    Ajax,
    Notification,
    Str,
    Templates,
) => {
  /**
   * Module initialization
   */
  var init = () => {
    var container = $("#gantt-chart")
    if (container.length === 0) {
      return
    }

    var viewType = container.data("view")
    var year = container.data("year")
    loadGanttData(viewType, year)
  }

  /**
   * Load Gantt chart data
   *
   * @param {string} viewType - The view type (year, halfyear, quarter)
   * @param {int} year - The year to display
   */
  var loadGanttData = (viewType, year) => {
    $("#gantt-loading").show()

    var promises = Ajax.call([
      {
        methodname: "local_annualtrainingforecast_get_gantt_data",
        args: {
          viewtype: viewType,
          year: year,
        },
      },
    ])

    promises[0]
    .done((response) => {
      renderGanttChart(response)
      $("#gantt-loading").hide()
    })
    .fail((error) => {
      $("#gantt-loading").hide()
      Notification.exception(error)
    })
  }

  /**
   * Render the Gantt chart
   *
   * @param {Object} data - The Gantt chart data
   */
  var renderGanttChart = (data) => {
    var container = $("#gantt-chart")

    console.log("[v0] Gantt data received:", data)
    console.log("[v0] Start date:", new Date(data.timerange.start * 1000))
    console.log("[v0] End date:", new Date(data.timerange.end * 1000))

    // Prepare template data
    var templateData = {
      startdate: data.timerange.start,
      enddate: data.timerange.end,
      totaldays: Math.ceil((data.timerange.end - data.timerange.start) / (24 * 60 * 60)),
      items: data.items,
    }

    // Render template
    Templates.render("local_annualtrainingforecast/gantt_chart", templateData)
    .done((html) => {
      container.html(html)

      // Now render the Gantt chart using the data from the template
      renderGanttHeader(data.timerange.start, data.timerange.end)
      renderGanttItems()

      // Set up event handlers
      setupEventHandlers()
    })
    .fail((error) => {
      Notification.exception(error)
    })
  }

  /**
   * Render the Gantt chart header (months and days)
   *
   * @param {int} startTimestamp - The start timestamp (optional, from data attribute if not provided)
   * @param {int} endTimestamp - The end timestamp (optional, from data attribute if not provided)
   */
  var renderGanttHeader = (startTimestamp, endTimestamp) => {
    var timerangeEl = $("#gantt-timerange")

    // If timestamps not provided, get from data attributes
    if (!startTimestamp) {
      startTimestamp = Number.parseInt(timerangeEl.data("start"))
    }
    if (!endTimestamp) {
      endTimestamp = Number.parseInt(timerangeEl.data("end"))
    }

    var totalDays = Number.parseInt(timerangeEl.data("totaldays"))

    var startDate = new Date(startTimestamp * 1000)
    var endDate = new Date(endTimestamp * 1000)

    var monthsHtml = '<div class="gantt-months">'
    var daysHtml = '<div class="gantt-days">'

    var currentDate = new Date(startDate)

    // Generate months and days
    while (currentDate <= endDate) {
      var monthStart = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1)
      var monthEnd = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0)

      if (monthEnd > endDate) {
        monthEnd = new Date(endDate)
      }

      var daysInMonth = Math.round((monthEnd - monthStart) / (24 * 60 * 60 * 1000)) + 1
      var monthWidth = (daysInMonth / totalDays) * 100

      // Add month
      monthsHtml +=
          '<div class="gantt-month" style="width: ' +
          monthWidth +
          '%;">' +
          currentDate.toLocaleDateString("default", { month: "long", year: "numeric" }) +
          "</div>"

      // Add days for this month
      var monthDay = new Date(monthStart)
      while (monthDay <= monthEnd) {
        var isWeekend = monthDay.getDay() === 0 || monthDay.getDay() === 6
        var isToday = new Date().toDateString() === monthDay.toDateString()

        var dayClass = "gantt-day"
        if (isWeekend) dayClass += " weekend"
        if (isToday) dayClass += " today"

        daysHtml += '<div class="' + dayClass + '">' + monthDay.getDate() + "</div>"

        monthDay.setDate(monthDay.getDate() + 1)
      }

      currentDate.setMonth(currentDate.getMonth() + 1)
    }

    monthsHtml += "</div>"
    daysHtml += "</div>"

    // Add months and days to header
    $(".gantt-header").html(monthsHtml + daysHtml)
  }

  /**
   * Render the Gantt chart items
   */
  var renderGanttItems = () => {
    var timerangeEl = $("#gantt-timerange")
    var startTimestamp = Number.parseInt(timerangeEl.data("start"))
    var endTimestamp = Number.parseInt(timerangeEl.data("end"))
    var totalDays = Number.parseInt(timerangeEl.data("totaldays"))

    var startDate = new Date(startTimestamp * 1000)
    var endDate = new Date(endTimestamp * 1000)

    // Process items
    var itemsHtml = ""

    $(".gantt-item-data").each(function () {
      var item = $(this)
      var id = item.data("id")
      var name = item.data("name")
      var parentname = item.data("parentname")
      var itemStart = new Date(Number.parseInt(item.data("start")) * 1000)
      var itemEnd = new Date(Number.parseInt(item.data("end")) * 1000)
      var status = Number.parseInt(item.data("status"))
      var completed = Number.parseInt(item.data("completed"))
      var statusclass = item.data("statusclass")

      // Calculate position
      // First, ensure we're working with the start of the day for all dates to avoid time-of-day issues
      var startDateDay = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate())
      var endDateDay = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate())
      var itemStartDay = new Date(itemStart.getFullYear(), itemStart.getMonth(), itemStart.getDate())
      var itemEndDay = new Date(itemEnd.getFullYear(), itemEnd.getMonth(), itemEnd.getDate())

      // Calculate days between dates more precisely
      var daysBetweenStartAndItemStart = 0
      if (itemStartDay >= startDateDay) {
        daysBetweenStartAndItemStart = Math.round((itemStartDay - startDateDay) / (24 * 60 * 60 * 1000))
      }

      // Calculate item duration more precisely
      var itemDuration = Math.max(1, Math.round((itemEndDay - itemStartDay) / (24 * 60 * 60 * 1000)) + 1)

      // Adjust for items that start before the chart range
      if (itemStartDay < startDateDay) {
        itemStartDay = startDateDay
        daysBetweenStartAndItemStart = 0
        itemDuration = Math.max(1, Math.round((itemEndDay - itemStartDay) / (24 * 60 * 60 * 1000)) + 1)
      }

      // Adjust for items that end after the chart range
      if (itemEndDay > endDateDay) {
        itemEndDay = endDateDay
        itemDuration = Math.max(1, Math.round((itemEndDay - itemStartDay) / (24 * 60 * 60 * 1000)) + 1)
      }

      // Calculate total days in the chart more precisely
      var totalDaysExact = Math.round((endDateDay - startDateDay) / (24 * 60 * 60 * 1000)) + 1

      // Calculate position and width as percentages
      var left = (daysBetweenStartAndItemStart / totalDaysExact) * 100
      var width = (itemDuration / totalDaysExact) * 100

      // Ensure the bar is visible even if it's very short
      if (width < 0.5) width = 0.5

      // Ensure the bar doesn't extend beyond the chart
      if (left + width > 100) width = 100 - left

      // Status text
      var statusTexts = [
        "Upcoming", // These should be localized, but for simplicity we're hardcoding
        "In Progress",
        "Completed",
        "Cancelled",
      ]

      var statusText = statusTexts[status]

      // Create item HTML
      itemsHtml +=
          '<div class="gantt-item" data-id="' +
          id +
          '">' +
          '<div class="gantt-item-label" title="' +
          name +
          '">' +
          name +
          "</div>" +
          '<div class="gantt-item-bar-container">' +
          '<div class="gantt-item-bar status-' +
          statusclass +
          '" ' +
          'style="left: ' +
          left +
          "%; width: " +
          width +
          '%;" ' +
          'title="' +
          name +
          '">' +
          '<div class="gantt-item-tooltip">' +
          "<strong>" +
          name +
          "</strong><br>" +
          parentname +
          "<br>" +
          itemStart.toLocaleDateString() +
          " - " +
          itemEnd.toLocaleDateString() +
          "<br>" +
          statusText +
          '<div class="gantt-item-actions">' +
          '<select class="update-status" data-id="' +
          id +
          '">' +
          '<option value="0"' +
          (status === 0 ? ' selected' : '') +
          '>Upcoming</option>' +
          '<option value="1"' +
          (status === 1 ? ' selected' : '') +
          '>In Progress</option>' +
          '<option value="2"' +
          (status === 2 ? ' selected' : '') +
          '>Completed</option>' +
          '<option value="3"' +
          (status === 3 ? ' selected' : '') +
          '>Cancelled</option>' +
          '</select>' +
          '<div class="mt-2">' +
          '<label>' +
          '<input type="checkbox" class="update-completed" data-id="' +
          id +
          '"' +
          (completed ? ' checked' : '') +
          '>' +
          'Completed' +
          '</label>' +
          '</div>' +
          '</div>' +
          '</div>' +
          '</div>' +
          '</div>'
    })

    // Add items to container
    $(".gantt-items").html(itemsHtml)
  }

  /**
   * Set up event handlers for status updates
   */
  var setupEventHandlers = () => {
    // Initialize status update handlers
    $(document).on("change", ".update-status", function (e) {
      var iterationId = $(this).data("id")
      var status = $(this).val()
      var completed = $(this).closest(".gantt-item-actions").find(".update-completed").prop("checked") ? 1 : 0

      updateIterationStatus(iterationId, status, completed)
    })

    $(document).on("change", ".update-completed", function (e) {
      var iterationId = $(this).data("id")
      var completed = $(this).prop("checked") ? 1 : 0
      var status = $(this).closest(".gantt-item-actions").find(".update-status").val()

      updateIterationStatus(iterationId, status, completed)
    })
  }

  /**
   * Update iteration status
   *
   * @param {int} iterationId - The iteration ID
   * @param {int} status - The new status
   * @param {int} completed - The completion status
   */
  var updateIterationStatus = (iterationId, status, completed) => {
    var promises = Ajax.call([
      {
        methodname: "local_annualtrainingforecast_update_iteration_status",
        args: {
          id: iterationId,
          status: status,
          completed: completed,
        },
      },
    ])

    promises[0]
    .done((response) => {
      if (response.success) {
        // Reload the page to reflect changes
        window.location.reload()
      } else {
        console.error(response.message)
      }
    })
    .fail((error) => {
      console.error(error)
    })
  }

  return {
    init: init,
  }
})
