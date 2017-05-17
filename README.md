# SilverStripe Data Pages

This is a module (package) as a helper to create linkable data objects with meta tags, url segments, links and more. 

## Usage

To create your own linkable object here is an example of simple product object

```php
// Product.php
class Product extends DataPage {
    private static $db = []; // your own options
}

// ProductListingPage.php
class ProductListingPage extends Page {

    // list of the products
    public function getProducts() {
        return Product::get();
    }

    // override MetaTags to get from Product
    public function MetaTags($includeTitle = true) {
        $segment = Controller::curr()->getRequest()->param('URLSegment');

        if ($product = Product::getByUrlSegment($segment)) {
            return $product->MetaTags($includeTitle);
        }

        return parent::MetaTags($includeTitle);
    }
    
}

// ProductListingPage.php
class ProductListingPage_Controller extends Page_Controller {
    private static $allowed_actions = ['product'];
    private static $url_handlers = [
        ''             => 'index',
        '$URLSegment!' => 'product'
    ];
    
    public function product(SS_HTTPRequest $request) {
        $segment = $request->param('URLSegment');
        
        if (($product = Product::getByUrlSegment($segment)) && $product instanceof Product && $product->canView()) {
            $this->Title = $product->Title;

            return $this->renderWith(['ProductListingPage_product', 'Page'], [
                'Title'    => $product->Title,
                'Content'  => DBField::create_field('HTMLText', $product->Content),
                'Pictures' => $product->Pictures(),
            ]);
        }
        
        $this->httpError(404);
    }
}
```

```html
<!-- ProductListingPage_product.ss -->
<div class="container">
    <article>
        <% if $Title %><h1>$Title</h1><% end_if %>
        <div class="content">
            $Content
        </div>
    </article>
</div>
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