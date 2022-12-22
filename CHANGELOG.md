# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- This changelog
- Initial support for E7 tariffs
- `price` table has column `rate_type` as `ENUM('day','night','standard')`
- `price_h` also has `rate_type` added
- Added [upgrade_db.php](db%2Fupgrade_db.php) to migrate database schemas
- E7 Config keys that are only relevant if you have been on E7 and the times weren't 0:30 - 7:30:
  - `octopus.e7.start` defaults to `00:30`
  - `octopus.e7.end` defaults to `07:30`

### Changed

- Split `schema.sql` into `schema.sql` and `views.sql`
- Power hour is now off by default in `octopus.power_hour`

## [0.0.1] - 2022-12-21

Initial Release