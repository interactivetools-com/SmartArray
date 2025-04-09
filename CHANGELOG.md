# SmartArray Changelog

## [2.1.3] - 2025-04-08

### Added
- Added optional copy mode to `withSmartStrings()` and `noSmartStrings()` - pass `true` to get a new SmartArray instead of modifying the existing one
- Added `usingSmartStrings()` method to check if an array is using SmartString conversion

### Changed
- Moved `help()` method documentation to external `/src/help.txt` file for easier maintenance
- Improved test coverage for better reliability

## [2.1.2] - 2025-03-11

### Changed
- Documentation updates and minor code optimizations

## [2.1.1] - 2025-02-26

### Fixed
- Updated return type on __callStatic to reflect mixed return values

## [2.1.0] - 2025-02-14

### Added
- `smartMap($callback)`: Applies a callback to each element as a SmartString or nested SmartArray.
   Useful for transforming values while preserving Smart objects.
- `each($callback)`: Executes a callback on each element as a SmartString or nested SmartArray. Used for side effects, doesn't modify array.
- Constructor: Added shorthand boolean option (true/false) to enable/disable SmartStrings

### Changed
- Misc code optimizations and other minor improvements

## [2.0.6] - 2025-02-11

### Changed
- `or404()` now returns a traditional 404 error page instead of a plain text message

##[2.0.5] - 2025-01-31

### Added
- contains($value): Check if the array contains a specific value.
- withSmartStrings() & noSmartStrings(): Dynamically toggle SmartString wrapping on/off for the current array.

### Changed
- Renamed: rawValue() → getRawValue().  Previous method name now logs a deprecation warning and calls getRawValue().
- Misc code optimizations and other minor improvements

## [2.0.4] - 2025-01-15

### Changed
- Misc code optimizations and other minor improvements

## [2.0.3] - 2025-01-13

### Changed
- Removed warnings for undefined properties when using offsetGet(), ->property, or array syntax []
- Updated SmartNull to extend stdClass to prevent dynamic property warnings
- Changed RuntimeExceptions to base Exception class

## [2.0.2] - 2024-12-27

### Added
- `orDie()`: Terminates execution with message if value is blank (empty string, null or false)
- `or404()`: Terminates execution with 404 header and message if value is blank (empty string, null or false)
- `orThrow()`: Throws an exception with message if value is blank (empty string, null or false)
- SmartNull objects now support mysqli() method so you can reference the original query that created them

### Changed
- Internal code organization and optimization improvements

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
