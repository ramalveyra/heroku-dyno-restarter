# Dyno Restarter

Version 0.1

A simple php script that will restart an app dynos using heroku api

To use:

Add the following to the app config variable key pairs:

``SCHEDULER_LAST_DYNO_RESTART``

Date of when to start the check. Suggested time is date of first execution time is 00:00:00
 
`` RESTARTER_API:{YOUR HEROKU API KEY}``

`` RESTARTER_APP:{The app name of the where the script will run} ``

`` TARGET_APP:{The app name where dynos will be restarted}``

`` TIME_INTERVAL:{The time in hours when dynos will be restarted}``

Add a scheduler using Heroku Scheduler using ``php web/restartDyno.php``.

##Contributors
[@ramalveyra](https://github.com/ramalveyra)

##Credits
[@jonmountjoy(https://github.com/jonmountjoy)] - php-getting-started(https://github.com/heroku/php-getting-started)
