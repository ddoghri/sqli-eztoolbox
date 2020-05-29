SQLI ContentType Installer
========================

### Commands

```shell script
php bin/console sqli:contentTypesInstaller:create_or_update <filename.yml>
php bin/console sqli:contentTypesInstaller:extract <filename.yml> <content_type_identifier>
```


### Parameters

Default values :
```yml
sqli_ez_toolbox:
    contenttype_installer:
        installation_directory: app/content_types
        is_absolute_path: false
```