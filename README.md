WordPress GitHub Installer
==========================

Install and update GitHub hosted WordPress plugins.

Features
--------
 - Install plugins from GtHub repository URL
 - Checks for updates
 - Works with public and private repositories (Access token required)
 - Translation ready (translators welcome!)
 - Works in multisite environment

Plugin Developers
-----------------
Installation and auto update should work with any github hosted wordpress plugin, as long as
 - The main plugin file is located in the root directory 
 - The Plugin URI points to the github repository.

ToDo:
-----
- be sensitive with local repositories (identified by presence of .git directory)
- Option: manual Plugin update check.
- install and update themes
