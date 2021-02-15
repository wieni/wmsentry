# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
