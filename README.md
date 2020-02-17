google-drive-filesystem
==========

A Google Drive Filesystem module for Codeception.

## Installation
To install simply require the package in the `composer.json` file like

```
composer require --dev polevaultweb/google-drive-filesystem
```

### GoogleDriveFilesystem configuration

GoogleDriveFilesystem extends `Filesystem` module hence any parameter required and available to that module is required and available in `GoogleDriveFilesystem` as well.  

In the suite `.yml` configuration file add the module among the loaded ones with the `authorizationToken`. 

```yml
  modules:
      enabled:
          - GoogleDriveFilesystem
      config:
          GoogleDriveFilesystem:
              authorizationToken: xxxxxxxxxxxx
``` 

### Supports

* doesDriveFileExist
* deleteDriveFile

And assertions

* seeDriveFile

### Usage

```php
$I = new AcceptanceTester( $scenario );

$I->seeDriveFile( 'path/to/file.jpg' );
```

