<?php

namespace EvolutionPHP\Exceptions;

class System
{
	public function error_handler(callable $handler)
	{
		set_error_handler($handler);
	}

	public function exception_handler(callable $handler)
	{
		set_exception_handler($handler);
	}

	public function shutdown_handler(callable $handler)
	{
		register_shutdown_function($handler);
	}
}