CSOB Transaction authorizator
=============================

![Integrity check](https://github.com/baraja-core/csob-payment-authorizator/workflows/Integrity%20check/badge.svg)

Find transactions in mail box, parse and call authorization logic.

Install
-------

By Composer:

```shell
composer require baraja-core/csob-payment-authorizator
```

And create service by Neon:

```yaml
services:
    - Baraja\CsobPaymentChecker\CsobPaymentAuthorizator(%tempDir%, %csob.imapPath%, %csob.login%, %csob.password%)

parameters:
    csob:
        imapPath: xxx
        login: xxx
        password: xxx
```

Usage
-----

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
