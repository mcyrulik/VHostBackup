## Introduction

We had a problem.. Ok not really a problem, but.. well.. nevermind. 

We had web servers that were hositng sites. and needed backups. We had backups, just not ones that made sense for how we worked. We wanted each user directory zipped with the date, and each database exported and zipped - also with the date.

Enter this little repo. 

The goal here is for the user to enter a couple of values in the ServerBackupSettings.php file and run the server_backup.php file via cron. 

The first version was not very object oriented in the class structuring. This is better (Not perfect..). This is something that I can build on and run with.

Asssuming that you didn't make any mistakes in entering things, after some amount of time your files should be uploaded to the FTP of your choice.


## Installation

Download all the files in this repository to a folder on your server..

It is not recommended that this is placed in a location on your server where it can be accessed from a public URL. Security is important.

## Usage

This can be run by setting up a cron job. something like "php -q /path/to/server_backup.php" is pretty sufficient. Currently, the script only allows for daily runs (the date parameter doesn't have time in it... yet.)


## DISCLAIMER
**Use at your own risk. This script intentionally does not write to any of the directories, or attempt in any way to alter the databases that we are dumping, but odd things happen. Please be careful. Also: this script does absolutely, positively nothing to verify that the zip archives are usable. This should be part of your workflow for disaster recovery anyway, right?**

## Issues and suggestions
If you find issues with the way script works, or have a suggestion as to how you think it could be improved in later release or future development, please [submit an issue](https://github.com/mcyrulik/VHostBackup/issues)

## Things that I'm still planning
* Work on exception, and some better messaging when things aren't quite right.
* SSL for the FTP option
* Add in S3 as a storage option
* Add Box.com as a storage option.
* Better abstraction for the upload methods - once S3 and Box are added.
* (?) add dropbox as an option for storage.
* Add in an actual logger interface (Monolog?)


## Versions
* **v0.24** - Added ability to archive sub-directory.
* **v0.23** - Initial beta-ish release. Things should work, but there are some cleanup things to do.