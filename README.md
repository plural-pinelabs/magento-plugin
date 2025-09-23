# Pinelabs Magento Plugin for V3 api's

# How to install

 ### Manual Install

```sh
    # goto root folder of your magento installation
    cd app/code
    wget {{ extension url }}
    tar -xf {{ Extension file }}

    # cd back into root folder
    php bin/magento module:enable Pinelabs_PinePGGateway
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy -f
```

# Supported Magento Version

 - 2.3.4 , 2.4.2 ,2.4.7

# Features

- Click link to check all api use in this plugin  [read more](https://developer.pinelabsonline.com/reference/orders-create) .

 - Pinelabs Online [read more](https://developer.pinelabsonline.com/reference/orders-create) integration to magento 2.
 
# How To Configure

 1. Login Into Admin Panel.
 2. Go to Stores Configuration.
 3. Go to payment Methods Tab inside Sales Section.
 4. Scroll down and locate Pinelabs Online.
 5. Fill your details Merchant Id, Merchant Secret, Merchant Access Code.

# Support / Help
