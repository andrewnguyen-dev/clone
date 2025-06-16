# SolarQuotes Crons

### Environment Variables

Copy .env.example to .env and fill all the .env variables with the correct values

These variables will used by config.php. No values in config.php should be changed when setting up a new server, the variables that are not in the .env.example files are variables that should be shared between all environments.


### Execution

Generally speaking, the php files in this project get executed via crontab.  The schedule changes between files, but the name of the file generally gives a hint.  Do not try and execute any files within this project from the browser, it is not geared to work like that and the code will fail.

### Location

The location of the project should be /var/www/private_html_2024/php.  So for example the .env would sit /var/www/private_html_2024/php/.env