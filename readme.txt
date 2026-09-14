=== ACF Event Schedule ===
Contributors: joelmcdwebworks
Tags: acf, events, schedule, sessions, speakers
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.16
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create sessions, speakers, and event schedules with Advanced Custom Fields.

== Description ==

This is not a standalone events plugin. You attach a schedule to existing public post types chosen in settings. Those events get Dates, Time Blocks, and Spaces. The plugin then adds Session and Speaker post types and renders a time-by-space grid on the front end.

Requires Advanced Custom Fields Pro.

== Installation ==

1. Install and activate Advanced Custom Fields Pro.
2. Upload the plugin to `wp-content/plugins/ACF-Event-Schedule`.
3. Activate ACF Event Schedule from Plugins.

After the first GitHub Release that includes a plugin zip, WordPress will offer later versions on the Plugins screen.

== Changelog ==

= 1.1.16 =
* Override theme `!important` paragraph margins so the schedule grid keeps plugin spacing.

= 1.1.15 =
* Reset theme paragraph margins in the schedule grid so session cards and time slots keep plugin spacing.
* Fix a migrator key reference.

= 1.1.14 =
* First GitHub release: sessions, speakers, and a time-by-space schedule grid for existing event post types.
* Settings allowlist, CSV import/export, shortcode and block display, and in-dashboard updates from GitHub Releases.
