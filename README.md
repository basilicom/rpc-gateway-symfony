RPC Gateway
================================================

Developer info: [basilicom](http://basilicom.de/)

## Synopsis

* 

### Code Example / Method of Operation

* if your service namespace ist not "\App\Rpc\Service", please don't forget to set your custom namespace.
* For Example, your Service Class is \Website\Rpc\Custom\User.php:

```php
    $rpc = new \RpcGateway\Gateway();
    $rpc->setServiceClassNamespace('\Website\Rpc\Custom\\')
```

### Installation

* just add '"basilicom/rpc-gateway-symfony": "dev-master"' to your composer '"require": {}'

### API Reference

* n/a

### Tests

* none

### Contributors

* Conrad Guelzow <conrad.guelzow@basilicom.de>
* Marco Senkpiel <marco.senkpiel@basilicom.de>

### License

* BSD-3-Clause

### Pimcore Controller Example
```php
<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class RpcController extends AbstractController
{

    public function defaultAction(Request $request)
    {
        $gateway = new \RpcGateway\Gateway($request);
        $gateway->setServiceClassNamespace('\AppBundle\Rpc\\');
        return $gateway->dispatch();
    }
}

```
