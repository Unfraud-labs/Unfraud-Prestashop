Unfraud Prestashop
=======

This is the Unfraud plugin for Prestashop. The plugin supports the Prestashop edition from 1.4.9.0 to 1.6.2.0.

We commit all our new features directly into our GitHub repository.
But you can also request or suggest new features or code changes yourself!


Requirements
-------------------------
1. The service uses the Unfraud REST api for processing transactions.
2. The server needs to support cURL



Installation Instructions
-------------------------
### Via modman

- Install [modman](https://github.com/colinmollenhour/modman)
- Use the command from your Magento installation folder: `modman clone https://github.com/Unfraud/Unfraud-Prestashop/`

### Via composer
- Install [composer](http://getcomposer.org/download/)
- Install [Magento Composer](https://github.com/magento-hackathon/magento-composer-installer)
- Create a composer.json into your project like the following sample:

```json
{
    ...
    "require": {
        "unfraud/unfraud-prestashop":"*"
    },
    "repositories": [
	    {
            "type": "vcs",
            "url": "https://github.com/Unfraud/Unfraud-Prestashop"
        }
    ],
    "extra":{
        "magento-root-dir": "./"
    }
}
```

- Then from your `composer.json` folder: `php composer.phar install` or `composer install`

### Manually
- You can copy the files from the folders of this repository to the same folders of your installation


Configuration
-------------------------
a) Enter into admin tab "Modules > Module and Services"
 
b) Search Unfraud module from module's list

c) Click "Install" on Unfraud module configuration button
 
d) After it you can click "Configure" on Unfraud module configuration button

d) Fill the input data with your Unfraud credentials: "Email","Password" and "API_KEY" (you can find it into your Unfraud panel in https://www.unfraud.com/dashboard/).

e) After that you'll see your "Unfraud Dashboard" below the configuration form of the same page.



Operation of the module
-------------------------
The score of your transaction will be added to the Unfraud cloud service when the user first creates order. 
The source code is commented on how to delay the creation of a transaction score in Unfruad to when the order is completed.

