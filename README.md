## Installation

If you have the package emico/tweakwise installed, uninstall this first. This package replaces that one

Install package using composer
```sh
composer require tweakwise/magento2-tweakwise-export
```

Enable module

Run installers
```sh
php bin/magento module:enable Tweakwise_Magento2TweakwiseExport
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

## Usage
All export settings can be found under Stores -> Configuration -> Catalog -> Tweakwise -> Export.

Generating feeds can be done using the command line.
```sh
php bin/magento tweakwise:export
php bin/magento tweakwise:export -t stock //stock export
php bin/magento tweakwise:export -t price //price export
php bin/magento tweakwise:export -s storecode //store level export, only works is store level export is enabled.
```

If 'Store Level Export' enabled single store feed  can be generated using the command line.
```sh
php bin/magento tweakwise:export --store '<storecode>'
```

## Debugging
Debugging is done using the default debugging functionality of Magento / PHP. You can enable indentation of the feed by setting deploy mode to developer.
```sh
php bin/magento deploy:mode:set developer

Usage:
 tweakwise:export [-c|--validate] [file]

Arguments:
 file                  Export to specific file (default: "var/feeds/tweakwise.xml")
 store                 Export a specific storeId, only possible of Store Level Export is enabled.

Options:
 --validate (-c)       Validate feed and rollback if fails.
 --help (-h)           Display this help message
 --quiet (-q)          Do not output any message
 --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
 --version (-V)        Display this application version
 --ansi                Force ANSI output
 --no-ansi             Disable ANSI output
 --no-interaction (-n) Do not ask any interactive question
```

## Feed structure
The feed contains some header information followed by categories and then products. Tweakwise does not natively support multiple stores, in order to circumvent this all categories and products are prefixed with 1000{store_id}.
If a product (with id 1178) is active and visible in multiple stores (say 1, 5 and 8) then it will appear three times in the feed (Or if Store Level Export is enabled the products is exported in 3 diffrent feeds) with ids: 100011178, 100051178 and 100081178.
The data on that product depends on the attribute values of the specific store. In short an entity is available in the feed as ``1000{store_id}{entity_id}``

The feed only contains products that are visible under your catalog configuration. If a product has children (say it is configurable) then the feed will also contain all the data from those children.
Child data is aggregated onto the "parent" product. 
The reason for this is that when a user searches for a t-shirt with size M then the configurable must show up in the results, therefor the configurable should be exported with all sizes available among its children. 

The feed contains only attributes which have bearing on search or navigation, check ``src/Model/ProductAttributes.php:45`` to see the criteria an attribute must meet in order to be exported.

The feed prices are exported in the default configured currency of the store (from v5.1.0 forward). If an exchange rate is available the prices for that currency are calculated. If no exchange rate is available, the original prices are used.

## Grouped export
If you have groupcode enabled all products are exported with their groupcode. All variants of a product (configurable, grouped, bundle) are linked together using the groupcode. If this is enabled all variants are separated products in Tweakwise. Use this if you want to filter out products based on variant data.
If you switch from to normal export to groupcode export, you will need to do the following:
1. Enable groupcode export in the configuration.
2. Run the tweakwise:export command to generate the feed.
3. Make sure the image urls are correct in Tweakwise.
4. Import the feed into Tweakwise and publish the catalog.
5. Enable Stores->configuration->catalog->tweakwise->general->Grouped products
6. Clear the cache

During the switch the catalog may be empty. If the image url are not correct no product images may be shown in magento.
If you use recommendations, add an attribute with the name "groupcode" in tweakwise and use it as an api attribute. Without this recommendations will not work.
Please contact Tweakwise support if you have any issues with the groupcode export.

## A note on the feed implementation
Magento's native interfaces and handlers for data retrieval were deemed to slow for a large catalog.
Since performance is essential we decided on our own queries for data retrieval. The consequence is that we need to keep track of the inner workings of magento and are subject to its changes.
If you find an issue with data retrieval please create an issue on github.

## Feed urls
https://yoursite.com/tweakwise/feed/export/key/{{feed_key}}
https://yoursite.com/tweakwise/feed/export/key/{{feed_key}}/type/stock //stock export
https://yoursite.com/tweakwise/feed/export/key/{{feed_key}}/type/price //price export
https://yoursite.com/tweakwise/feed/export/key/{{feed_key}}/store/storecode //store level export, only available if store level export is enabled

## Export Settings

All export settings can be found under Stores -> Configuration -> Catalog -> Tweakwise -> Export.

- **Enabled**: If products of that store should be exported to Tweakwise. If disabled, navigation and search should also be disabled for that store.
- **Store Level Export**: Enables generating separate feeds for each store. If enabled, feeds are generated per store.
- **Use groupcode for export**: Use groupcode for product export.
- **Key**: Used to validate feed and cache flush requests. The feed will only be served if the request contains the correct key.
- **Allow cache flush**: Allows automated cache flush via a specific URL. Used for post-publish cache clearing.
- **Validate**: Validates export on product, category, and product-category link count.
- **Archive**: Number of feeds to keep in archive.
- **Export in Real Time**: Always export the feed in real time when requested. Not recommended for production.
- **Tweakwise Import API Url**: API trigger URL to start import in Tweakwise after feed generation.
- **Export out of stock combined product children**: Export out-of-stock child attributes in parent products.
- **Exclude child attributes**: Attributes that should not be combined in parent products.
- **Skip children for composite type(s)**: Composite product types for which child attributes should be excluded.
- **Date field**: Select how the created and updated date is exported.
- **Price field**: Select which field is used as "price" in Tweakwise. The first nonzero value is exported.
- **Batch size categories**: Set the batch size for categories during export. Lower for less memory, higher for more speed.
- **Batch size products**: Set the batch size for products during export.
- **Batch size products children**: Set the batch size for product children during export.
- **Schedule full export**: Cron schedule for generating the feed. Leave empty to disable export by cron.
- **State**: Shows the current export state.
- **Schedule exports**: Start exports on next cron run.

### Export Stock Settings

- **Schedule stock export**: Cron schedule for stock feed export.
- **State (stock)**: Shows the current stock export state.
- **Schedule stock export (start)**: Start stock exports on next cron run.
- **Tweakwise Import API Url for stock feed**: API trigger URL for stock feed import.

### Export Price Settings

- **Schedule price export**: Cron schedule for price feed export.
- **State (price)**: Shows the current price export state.
- **Schedule price export (start)**: Start price exports on next cron run.
- **Tweakwise Import API Url for price feed**: API trigger URL for price feed import.

### Visibility settings
Magento has multiple visibility settings, tweakwise only knows visible products meaning that if a product is in the feed then it will be visible while navigating and searching.
The magento visibility setting is exported in the feed so you can add a hidden filter to your tweakwise template to artificially use the correct settings.
If you do this then exclude the visibility attribute from child products (see "Export Settings"). If you use groupcode export then you need to use the parent_visibility as an hidden filter instead of visibility.

## Contributors 
If you want to create a pull request as a contributor, use the guidelines of semantic-release. semantic-release automates the whole package release workflow including: determining the next version number, generating the release notes, and publishing the package.
By adhering to the commit message format, a release is automatically created with the commit messages as release notes. Follow the guidelines as described in: https://github.com/semantic-release/semantic-release?tab=readme-ov-file#commit-message-format.
