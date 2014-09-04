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

## Version 0.2

Added feature to restart multiple apps.

Enabled Logging.

New config vars format needed for this to work. See below:

`` TARGET_APP=app1,app2 `` - The apps that will be restarted separated by comma.

`` SCHEDULER_LAST_DYNO_RESTART={"app1":"{time last updated}","app2":"{time last updated }","last-restarted-app":"{name of app last restarted}"} `` - Must be in proper JSON format

`` TIME_INTERVAL={"app1":"{time interval in hours for app1}","app2":"{time interval in hours for app2}"} `` - Must be in proper JSON format

`` SHOW_DEBUG_LOGS=TRUE `` Set to FALSE if you don't enable execution logs.

### How the execution works

On scheduler run, it will first get the last restarted app,then will select a new app to restart. If the time elapsed since last app restart is greater than or equal to the time interval set, then it will restart the target app. It will set the target app as the last restarted app so during next scheduler run it will check the next app instead.


##Contributors
[@ramalveyra](https://github.com/ramalveyra)

##Credits
[@jonmountjoy(https://github.com/jonmountjoy)] - php-getting-started(https://github.com/heroku/php-getting-started)