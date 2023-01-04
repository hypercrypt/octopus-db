# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.3] - 2023-01-04

### Added

- The `octo` runner - check the README.md
- Support for importing Bright data
  - `octo bright/import` to import data since the latest Octopus data
  - `octo bright/current [json|csv|table]` to get current (live for electricity) consumption data
    - `table` is the default type
    - Only electricity is supported
  - `octo bright/list` lists the UUIDs and names to help with config
- DB Schema import and upgrade via `octo`
  - Added `octo db/upgrade` to migrate database schemas
    - This does not delete data 
    - This does not update views, use `octo db/views` to do that
  - Use `octo db/schema` to import the tables
    - **NOTE**: This commands will delete the tables before re-creating
  - Use `octo db/views` to import the views
    - **NOTE**: This commands will delete the views before re-creating

### Removed

- `upgrade_db.php` has been removed, use `octo db/upgrade` instead

### Deprecated

- `octopus.php` has been replaced with `octo octopus/import`. 
  - Will be removed in 0.1.0

## [0.0.2] - 2022-12-22

## [Unreleased]

### Added

- This changelog
- Initial support for E7 tariffs
- `price` table has column `rate_type` as `ENUM('day','night','standard')`
- `price_h` also has `rate_type` added
- Added upgrade_db.php to migrate database schemas (**REMOVED in 0.0.3***)
- E7 Config keys that are only relevant if you have been on E7 and the times weren't 0:30 - 7:30:
  - `octopus.e7.start` defaults to `00:30`
  - `octopus.e7.end` defaults to `07:30`

### Changed

- Split `schema.sql` into `schema.sql` and `views.sql`
- Power hour is now off by default in `octopus.power_hour`
## [0.0.1] - 2022-12-21

Initial Release