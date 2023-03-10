<?php
include __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
\Symfony\Component\ErrorHandler\ErrorHandler::register();
//Initialize class
$exceptions = new \EvolutionPHP\Exceptions\Exceptions();

//Register, if debug is enable then script will display erros, otherwise it will be hidden.
$exceptions->register(true);

// Optional: you can save logs of errors. For params of logger go to https://github.com/EvolutionPHP/logger
$exceptions->add_logger([
	'path' => __DIR__.'/logs/',
	'level' => 1
]);

//You can use instance if you already call the class
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



//Testing exceptions
function sum(int $a, int $b){
	return $a+$b;
}
var_dump(str_contains("foobar", null));
echo sum(4,'ab');
