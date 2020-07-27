SQLI ContentType Installer
========================

### Services

```shell script
FieldHelper
FetchHelper
DataFormattedHelper
```


### Twig Operators

Default values :
```twig
ez_parameter( 'parameter_name', 'namespace' ) {# ConfigResolver #}
content_name( Content ) {# ContentName even if we don't have access to this content #}
is_anonymous_user() {# Check if connected user is an anonymous user #}
empty_field( Content, 'field_identifier', ?'languagecode' ) {# Check if field exists and empty #}
fetch_location( locationId ) {# Fetch a Location object #}
fetch_content( contentId ) {# Fetch a Content object #}
fetch_ancestor( locationId|Location, 'contentTypeIdentifier' ) {# Search first ancestor of specified content type identifier #}
fetch_children( locationId|Location, ?'contentTypeIdentifier' ) {# Retrieve all children filtered with specified content type identifier #}
render_children( locationId|Location, 'viewType', 'contentTypeIdentifier', ?'params to template )
bundle_exists( 'bundle classname' ) {# Check if specified bundle is activated #}
```

### Parameter Handler

Your parameter handler must implement `SQLI\EzToolboxBundle\Services\Parameter\ParameterHandlerInterface` and your service's declaration must be tagged with `sqli.parameter_handler`

Example with "maintenance" handler:
```yaml
    SQLI\EzToolboxBundle\Services\Parameter\ParameterHandlerMaintenance:
        tags:
            - { name: sqli.parameter_handler }
```

All handlers can be accessible through a repository : `SQLI\EzToolboxBundle\Services\Parameter\ParameterHandlerRepository`