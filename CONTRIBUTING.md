# Contributing

Contributions to PeachySQL are welcome and encouraged.
If you'd like to help out, you've come to the right place!

## Dev environment setup

1. Clone the repository: `git clone git@github.com:DevTheorem/PeachySQL.git`
2. Install dependencies: `composer install`

## Tests

From a console in the working directory, execute `composer test` to run all unit tests.

### Database setup

By default, database tests for MySQL will attempt to run on a database named
`PeachySQL` as the root user with a blank password. To override connection settings,
create a `LocalConfig.php` class in the `test/src` directory which extends `Config`
and overrides the desired methods.

## Static analysis

Run `composer analyze` to detect type-related errors before runtime.
