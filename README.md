# SilverStripe Data Pages

This is a module (package) as a helper to create linkable data objects with meta tags, url segments, links and more. 

## Usage

To create your own linkable object here is an example of simple product object

```php
    class Product extends DataPage {
        
    }
```

## Make object searchable

add this configuration to your `config.yml` to make `DataPage` fulltext searchable.

```yaml
DataPage:
  indexes:
    SearchFields:
      type: fulltext
      name: SearchFields
      value: '"Title", "Content", "MenuTitle", "MetaDescription", "MetaKeywords"'
  create_table_options:
    MySQLDatabase: 'ENGINE=MyISAM'
```

## Todo

 1. Improve documentation later.
 2. Add versioning of the objects
 3. ...