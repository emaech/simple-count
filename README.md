# Simple Count

![Simple Count](https://raw.githubusercontent.com/emaech/simple-count/main/simple-count/simple-count.png)

## Description
**Simple Count** is a WordPress plugin that tracks visitor origins by country and displays a pie chart on the WordPress dashboard. It uses a custom database table to store visitor data and provides an intuitive interface for monitoring visits over various date ranges.

## Example
![Example](https://github.com/user-attachments/assets/ad16b114-6514-41e8-a0ba-c3bca5860f9c)

## Features
- Tracks visitor origins using the Cloudflare geolocation header.
- Filters out bots and scrapers based on user-agent keywords.
- Displays a pie chart of visitor data on the WordPress dashboard.
- Allows filtering of visitor data by date range (e.g., Today, Last 7 Days, Last 30 Days).
- Includes a responsive dashboard widget with Chart.js visualization.

## Installation
1. Download the plugin file.
2. Upload the plugin folder to the wp-content/plugins directory of your WordPress site.
3. Activate the plugin from the WordPress Admin Dashboard.


## Usage
- The plugin automatically begins tracking visitors upon activation.
- Visit the WordPress Dashboard to view the "Visitors by Country" widget.
- Use the dropdown filter in the widget to adjust the date range for the data displayed.

## Requirements
- WordPress 5.6 or higher.
- PHP 7.4 or higher.
- Requires the Chart.js library (included from cdn.jsdelivr.net)

## Bot Filtering
The plugin uses a list of predefined bot keywords to prevent logging visits from bots and scrapers.

## Dashboard Widget
A pie chart, built with Chart.js, summarizes the top countries by visitor count. Additional countries are grouped into an "Other" category for better visualization.

## Uninstallation
Upon uninstallation, the plugin removes the custom database table to ensure a clean slate.

## Known Issues
- Visitor data accuracy depends on the availability of Cloudflare geolocation headers.
- Bot detection may not catch all bot traffic.

## Other Issues
If you encounter any issues or bugs, you're on your own. I wrote this for myself and am not interested in maintaining it beyond my own needs.

## Disclaimer
This software is provided "as is", without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose, and noninfringement. By using this plugin, you agree to use it at your own risk. The author is not responsible for any damages, data loss, or other issues caused by the use of this plugin.



