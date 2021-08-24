# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2021-08-24
### Fixed
- Fix Drupal 9.1 Event dispatching deprecations (https://www.drupal.org/node/3159012 and https://www.drupal.org/node/3154407) 

## [1.7.5] - 2021-06-20
### Fixed
- Update error handler

## [1.7.4] - 2021-06-14
### Fixed
- Disable default exception, error & fatal error integrations to prevent duplicate events
- Allow events with empty or invalid IP addresses

## [1.7.3] - 2021-03-12
### Fixed
- Revert "Rename excluded `channel` tags to `logger`"
- Fix `context` & `type` not being added to event tags

## [1.7.2] - 2021-04-01
### Fixed
- Rename excluded `channel` tags to `logger`

## [1.7.1] - 2021-03-12
### Fixed
- Allow interfaces in the excluded_exceptions option

## [1.7.0] - 2021-03-05
### Added
- Add an HTTP endpoint for setting the Sentry release ID

## [1.6.2] - 2021-02-18
### Fixed
- Fix backwards incompatible changes after v3 update

## [1.6.1] - 2021-02-17
### Fixed
- Fix error during module install
- Fix Drush integration
- Replace recommended php-http/curl-client with nyholm/psr7

## [1.6.0] - 2021-02-17
### Added
- Add support for the `in_app_include` and `in_app_exclude` options
- Add all log messages as breadcrumbs
- Add support for Drush errors
- Add default excluded exceptions & tags
- Add PHP 8 support

### Fixed
- Replace recommended guzzlehttp/psr7 with php-http/guzzle6-adapter

## [1.5.1] - 2020-06-06
### Fixed
- Fix excluded exceptions option. Since we're not using ClientInterface::captureException to capture exceptions,
 we should do the check ourselves

## [1.5.0] - 2020-05-27
### Changed
- Increase minimum version of the SDK to 2.3.0

### Fixed
- Fix excluded exceptions option
- Add missing drupal:user dependency

## [1.4.1] - 2020-01-06
### Changed
- Remove maintainers section & update security email address in README

### Fixed
- Fix error when no context is passed to the error handler

## [1.4.0] - 2019-12-18
### Added
- Add coding standard fixers
- Add changelog
- Add issue & pull request templates

### Changed
- Normalize composer.json
- Update .gitignore
- Update README
- Apply automatic code style fixes

## [1.3.5] - 2019-11-22
### Changed
- Change Drupal composer repo url
- Increase drupal/core version constraint to support version 9

### Removed
- Remove HTTP library dependencies

## [1.3.4] - 2019-05-16
### Changed
- Add context array to `SentryScopeAlterEvent`

## [1.3.3] - 2019-05-02
### Fixed
- Handle 'user' being removed from logging context

## [1.3.2] - 2019-03-27
### Fixed
- Fix potential error when backtrace is empty

## [1.3.1] - 2019-03-27
### Fixed
- Fix removing logger classes from backtrace

## [1.3.0] - 2019-03-19
### Added
- Add an option to include function arguments in the stacktrace

## [1.2.4] - 2019-03-11
### Fixed
- Fix module file not always being included

## [1.2.3] - 2019-03-08
### Fixed
- Fix issue where Drupal shows Sentry as the function caller in errors

## [1.2.2] - 2019-03-01
### Changed
- Upgrade Sentry SDK to stable release

## [1.2.1] - 2019-02-21
### Fixed
- Fix log level checkboxes in settings form

## [1.2.0] - 2019-02-21
### Added
- Add menu link to settings form
- Add config option to choose which log levels should be captured

## [1.1.1] - 2019-02-13
### Fixed
- Fix TypeError

## [1.1.0] - 2019-02-12
### Added
- Add settings form
- Add `administer wmsentry settings` permission
- Add schema for `wmsentry.settings.yml`

## [1.0.0] - 2019-02-12
Initial release
