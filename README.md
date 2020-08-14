# Beebots/Taxify
This module gets realtime tax rates from Taxify.
That's all it does at this point. It does not push commited orders to Taxify.


## Installation
Add `beebots/magento2-taxify` to your `composer.json` file. (We're not currently published to
packagist.)

```json
{
    "require": {
        "beebots/magento2-taxify": "^0.1.0"
    },
    "repositories": {
        "beebots-taxify": {
            "type": "vcs",
            "url": "git@github.com:beebots/magento2-taxify.git",
            "no-api": true
        },
        "rk-taxify": {
            "type": "vcs",
            "url": "git@github.com:rk/taxify.git",
            "no-api": true
        }
    }
}
```