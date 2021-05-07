## Spanner ORM Builder

[![Build Status](https://travis-ci.com/mgcostaParedes/spanner-orm-builder.svg?branch=main)](https://travis-ci.com/mgcostaParedes/spanner-orm-builder)
[![codecov](https://codecov.io/gh/mgcostaParedes/spanner-orm-builder/branch/main/graph/badge.svg?token=OEUY7ZDTOP)](https://codecov.io/gh/mgcostaParedes/spanner-orm-builder)

The Spanner ORM Builder is a database toolkit to PHP, providing an expressive query builder, ActiveRecord style ORM, it can serve as a database layer for your PHP app if you intend to work with **Google Cloud Spanner**.

### Usage Instructions

First, we should create a new "Manager" instance. Manager aims to make configuring the library for every framework as easy as possible.

```PHP

use MgCosta\Spanner\Manager;
use Google\Cloud\Spanner\Database;

// $database = your database instance for google cloud spanner;

$manager = new Manager($database);
$manager->boot();

```

That's it, you're ready to use the library, just be sure to instantiate the manager as soon as possible on your APP, usually on your bootstrap or config file.

Once the Manager instance has been registered, we may use it like:

**Using The Query Builder**

```PHP

class User extends \MgCosta\Spanner\Model\Model {}

$users = User::where('age', '>', 30)->get();

```

The implementation of the query builder is inspired on Laravel Query Builder, to get more documentation follow the [link](https://laravel.com/docs/master/queries).