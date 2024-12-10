# SmartArray Changelog

## [2.0.1] - 2024-12-09

### Changed
- ZenDB support: Removed references to ->mysqli('error') and ->mysqli('errno') as try/catch is now used for error handling
- Code and text formatting updates

## [2.0.0] - 2024-11-26

### Changed
* BREAKING: Values now stay as raw values by default (previously auto-converted to SmartStrings)
* Added SmartArray::new() for raw value handling and SmartArray::newSS() for SmartString conversion
* Improved performance through optimized value handling and lazy conversion

### Added
* New methods for array manipulation:
    * where() - Filter rows by matching conditions
    * pluckNth() - Extract values by position from nested arrays
    * merge() - Combine multiple arrays
* New debugging and introspection tools:
    * debug() - Enhanced troubleshooting information
    * Added SmartArray::rawValue() helper for consistent value conversion
* New database integration and advanced features:
    * load() - Load related records from a database column 
    * mysqli() - Access database result metadata (affected_rows, insert_id, error, etc.)
    * root() - Access root SmartArray from nested children (useful for eager caching and other advanced use cases)
    * setLoadHandler() for configuring related record loading

### Deprecated
- Added support for legacy ZenDB methods, legacy methods still work, but log deprecation errors and will be removed in future
- Renamed join() to implode() to better match PHP's implode() function (old method still works, but log deprecation error)

## [1.2.0] - 2024-10-31
* Added sort() and sortBy($column) methods
* Updated readme.md with examples
* Misc code and other minor improvements

## [1.1.0] - 2024-10-28
* Added unique() method
* Misc code and other minor improvements

## [1.0.0] - 2024-10-28
* Initial release
