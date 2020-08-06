CSOB Transaction authorizator
=============================

![Integrity check](https://github.com/baraja-core/csob-payment-authorizator/workflows/Integrity%20check/badge.svg)

Find transactions in mail box, parse and call authorization logic.

📦 Installation & Basic Usage
-----------------------------

This package can be installed using [Package Manager](https://github.com/baraja-core/package-manager) which is also part of the Baraja [Sandbox](https://github.com/baraja-core/sandbox). If you are not using it, you have to install the package manually following this guide.

A model configuration can be found in the `common.neon` file inside the root of the package.

To manually install the package call Composer and execute the following command:

```shell
$ composer require baraja-core/csob-payment-authorizator
```

In the projects `common.neon` you have to define the database credentials. A fully working example of configuration can be found in the `common.neon` file inside this package.

You can define the configuration simply using parameters (stored in the super-global array `parameters`).

For example:

```yaml
services:
    - Baraja\CsobPaymentChecker\CsobPaymentAuthorizator(%tempDir%, %csob.imapPath%, %csob.login%, %csob.password%)

parameters:
    csob:
        imapPath: xxx
        login: xxx
        password: xxx
```

⚙️ Usage
--------

In presenter use it very simply:

```php
/** @var CsobPaymentAuthorizator $csob **/
$csob = $this->context->getByType(CsobPaymentAuthorizator::class);

// Or simply:

$csob = new Baraja\CsobPaymentChecker\CsobPaymentAuthorizator(...);

// Check account and authorize new orders

$unauthorizedVariables = [];

$csob->authOrders(
    $unauthorizedVariables,
    function (Transaction $transaction): void {
        // Do something...
    }
);
```

📄 License
-----------

`baraja-core/csob-payment-authorizator` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/doctrine/blob/master/LICENSE) file for more details.
