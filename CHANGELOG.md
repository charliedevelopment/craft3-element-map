## [Unreleased]

### Changed
- `resources` folder renamed to `assets` to follow Craft conventions.

## [1.0.2] 2018-01-06

### Fixed
- Further revised quoting and how queries are built to fully address the issue partially addressed by 1.0.1.
- Updated conditions used to traverse matrix blocks for inner related elements, meaning element -> block -> element relationships should now show up properly in the map.

## [1.0.1] 2018-01-04

### Changed
- Minor grammatical updates to README.md

### Fixed
- Quoted column names in joins, which were causing errors when the map was rendered with certain DB configurations.

## [1.0.0] 2017-12-13

The initial release of the Element Map plugin.

### Added
- Element map in full-page editor sidebars, shows elements with relations to the currently edited element