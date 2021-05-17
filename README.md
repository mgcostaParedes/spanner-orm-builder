## Spanner ORM Builder For PHP

[![License](https://poser.pugx.org/mgcosta/spanner-orm-builder/license)](//packagist.org/packages/mgcosta/spanner-orm-builder)
[![Actions Status](https://github.com/mgcostaParedes/spanner-orm-builder/workflows/CI/badge.svg)](https://github.com/mgcostaParedes/spanner-orm-builder/actions)
[![codecov](https://codecov.io/gh/mgcostaParedes/spanner-orm-builder/branch/main/graph/badge.svg?token=OEUY7ZDTOP)](https://codecov.io/gh/mgcostaParedes/spanner-orm-builder)
[![Total Downloads](https://poser.pugx.org/mgcosta/spanner-orm-builder/downloads)](//packagist.org/packages/mgcosta/spanner-orm-builder)


The Spanner ORM Builder is a database toolkit to PHP, providing an expressive query builder, ActiveRecord style ORM, it can serve as a database layer for your PHP app if you intend to work with **Google Cloud Spanner**.

## Install

Via Composer

``` bash
$ composer require mgcosta/spanner-orm-builder
```

### Usage Instructions

First, we should create a new "Manager" instance. Manager aims to make configuring the library for every framework as easy as possible.

```PHP

use MgCosta\Spanner\Manager;
use Google\Cloud\Spanner\Database;

// $database = your database instance for google cloud spanner;
// instance of Google\Cloud\Spanner\Database;

$manager = new Manager($database);
$manager->boot();

```

That's it, you're ready to use the library, just be sure to instantiate the manager as soon as possible on your APP, usually on your bootstrap or config file.

Once the Manager instance has been registered, we may use it like:

**Using The Query Builder**

```PHP
use MgCosta\Spanner\Model\Model;

class User extends Model {}

$users = User::where('age', '>', 30)->get();

$id = 1;
$user = User::find($id);

```

**Updating/Deleting using the Query Builder**

```PHP
use MgCosta\Spanner\Model\Model;

class User extends Model {}

// deleting
User::where('id', 1)->delete();

// updating
$status = User::where('id', 5)->update(['name' => 'Richard', 'age' => 30]);

```

**Saving a model**

```PHP
use MgCosta\Spanner\Model\Model;

class User extends Model {

    protected $primaryKey = 'userId';
    
    // available strategies [uuid4, increment] 
    // increment is not recommend by cloud spanner
    protected $keyStrategy = 'uuid4';
    
    // we must define the properties which corresponds to the columns of the table as public
    public $userId;
    public $name;
    public $age;
    public $email;
}

$user = new User();
$user->name = 'Miguel';
$user->age = 28;
$user->email = 'email@gmail.com';
$user->save();

```

**Using the query builder without Model Class**
```PHP
use MgCosta\Spanner\Facade\SpannerDB;

(new SpannerDB())->table('users')->whereIn('id', [1, 2, 3])->get();

// you can also provide a custom spanner Database Instance
// $database = instance of Google\Cloud\Spanner\Database;
(new SpannerDB($database))->table('users')->where('id', 1)->first();
```


The implementation of the query builder is inspired on Laravel Query Builder, to get more documentation follow the [link](https://laravel.com/docs/master/queries).

## Roadmap

You can get more details of the plans for this early version on the following [link](https://github.com/mgcostaParedes/spanner-orm-builder/projects/1).

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


## Credits

- [Miguel Costa][https://github.com/mgcostaParedes]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
