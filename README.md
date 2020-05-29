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

Activate the bundle in `app/AppKernel.php`

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

##### Entity Manager

Configure directories (and namespaces if not according to PSR-0 rules) entities to lookup :

```yml
sqli_ez_toolbox:
    entities:
        - { directory: 'Acme/AcmeBundle/Entity/Doctrine' }
        - { directory: 'Acme/AcmeBundle2/Entity/Doctrine', namespace: 'Acme\AcmeBundle2NoPSR0\ORM\Doctrine' }
    contenttype_installer:
        installation_directory: app/content_types
        is_absolute_path: false
```
Use "~" if the namespace of your classes observe PSR-0 rules or specify directory which contains them.

##### ContentType Installer

Default values :
```yml
sqli_ez_toolbox:
    contenttype_installer:
        installation_directory: app/content_types
        is_absolute_path: false
```

*(Optional) Change label tabname*

You can change label of the default tab using this translation key for domain `sqli_admin` : **sqli_admin__menu_entities_tab__default**

### How to use

[Entities Manager](README_entities_manager.md)

[ContentTypes Installer](README_contenttype_installer.md)

[Toolbox](README_toolbox.md)
