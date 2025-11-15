# Annual Training Forecast - Moodle Plugin

A Moodle local plugin for managing and visualizing annual training forecasts with Gantt chart views.

## Features

- Create and manage parent courses
- Create course iterations with scheduling
- Visual Gantt chart display (year/half-year/quarterly views)
- Export to Excel and PDF formats
- Status tracking and completion monitoring
- Reports and analytics

## Installation

1. Clone or download this plugin to your Moodle's `local/annualtrainingforecast` directory
2. Install Composer dependencies:
   \`\`\`bash
   cd local/annualtrainingforecast
   composer install
   \`\`\`
3. Visit Site Administration > Notifications to complete the installation

## Requirements

- Moodle 3.11 or higher
- PHP 7.4 or higher
- Composer (for mPDF dependency)

## Usage

1. Navigate to Site Administration > Plugins > Local plugins > Annual Training Forecast
2. Add parent courses
3. Create iterations for each parent course
4. View the Gantt chart and export data as needed

## License

GPL v3 or later
\`\`\`

```plaintext file=".gitignore"
/vendor/
composer.lock
