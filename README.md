SQLI eZ Toolbox Bundle
========================================

[SQLI](http://www.sqli.com) eZToolbox is a bundle used in SQLI projects gathering some bundles like "SQLI Entities Manager", "SQLI ContentType Installer", "SQLI Command Toolbox", some helpers and some Twig operators
Compatible with eZPlatform 2.x

Installation
------------

### Install with composer
```
composer require sqli/eztoolbox:dev-master
```

### Register the bundle

Activate the bundle in `app/AppKernel.php` AFTER all eZSystem/Ibexa bundles

```php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = [
        // ...
        new SQLI\EzToolboxBundle\SQLIEzToolboxBundle(),
    ];
}
```

### Add routes

In `app/config/routing.yml` :

```yml
# SQLI Admin routes
_sqli_eztoolbox:
    resource: "@SQLIEzToolboxBundle/Resources/config/routing.yml"
    prefix: /
```

### Clear cache

```bash
php bin/console cache:clear
```

### Parameters

##### Full example

```yaml
sqli_ez_toolbox:
    entities:
        - { directory: 'AcmeBundle/Entity/Doctrine' }
    contenttype_installer:
        installation_directory: app/content_types
        is_absolute_path: false
    admin_logger:
        enabled: true
    storage_filename_cleaner:
        enabled: true
```

### How to use

*(Optional) Change label tabname*

You can change label of the default tab using this translation key for domain `sqli_admin` : **sqli_admin__menu_entities_tab__default**

[Entities Manager](README_entities_manager.md)

[ContentTypes Installer](README_contenttype_installer.md)

[Toolbox](README_toolbox.md)
