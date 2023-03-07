# Simple PHP error handler

Simple PHP error handler and logger.

## Installation

Use [Composer](http://getcomposer.org) to install Logger into your project:
```bash
composer require evolutionphp/exceptions
```


## Initialize

1. Initialize the Exception class
```php
$exceptions = new \EvolutionPHP\Exceptions\Exceptions();
```
2. Register handler. If it's true, script will display errors in window (good for debugging) otherwise errors will be hidden.
```php
$exceptions->register(true);
```

## Logger

This is optional, you can save logs of errors. For params of logger go to [SimpleLogger](https://github.com/EvolutionPHP/logger)
```php
$exceptions->add_logger([
	'path' => __DIR__.'/logs/',
	'level' => 1
]);
```

## Call instance

If you already initialize the Exception class, then you can call an instance
```php
$instance = \EvolutionPHP\Exceptions\Exceptions::instance();
//Set status header
$instance->set_status_header(500);

//Write log message
$instance->write_log('error','This is error message');

//Check if script is working under command line
if($instance->is_cli()){
	echo "You are in command line.\n";
}else{
	echo 'Welcome to our site.';
}
```


## Authors

This library was primarily developed by [CodeIgniter 3](https://codeigniter.com/) and modified by [Andres M](https://twitter.com/EvolutionPHP) for standalone use.
