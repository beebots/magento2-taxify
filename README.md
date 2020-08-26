# Beebots/Taxify
This module gets realtime tax rates from Taxify.
That's all it does at this point. It does not push commited orders to Taxify.


## Installation
We're not currently published to
packagist. so add the following to the repositories section of your root `composer.json` file:

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

Then require in the module like so

```
composer require "beebots/magento2-taxify:~0.1.1"
```

## Thanks
Thanks to [rk/taxify](https://github.com/rk/taxify) for providing the API layer this module uses.
