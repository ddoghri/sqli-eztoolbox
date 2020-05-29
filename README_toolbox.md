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