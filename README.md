# Beebots/Taxify
This module gets realtime tax rates from Taxify.
That's all it does at this point. It does not push commited orders to Taxify.


## Installation
Add `beebots/magento2-taxify` to the require section of your `composer.json` file. 

```json
{
    "require": {
        "beebots/magento2-taxify": "^0.1.1"
    }
}


```

We're not currently published to
packagist. so add the following to your repositories section:

```json
{    
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

## Thanks
Thanks to [rk/taxify](https://github.com/rk/taxify) for providing the API layer this module uses.
