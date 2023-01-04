Octopus DB
==========

SETUP
-----

- Create a database
- Copy `config.php.sample` to `config.php`
- Fill in configuration settings
- Run `composer install`
- Run `php octo db/schema`
- If you'd like to have the reporting views, run `php octo db/views`
- Run `php octo octopus/import` to import the data

REQUIRES
--------

- PHP 8.2
- MySQL (Other databses not tested)

KOWN ISSUES
-----------

- Gas usage is still work in progress
- Bright will import gas in kWh,
  Octopus will import in kWh for SMETS1 and m³ for SMETS2. 
  Curently the records will be mixed in the database.
- Data for evey tariff you've ever been on will be imported in perpetuity