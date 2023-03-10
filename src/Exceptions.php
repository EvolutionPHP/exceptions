<?php
namespace EvolutionPHP\Exceptions;
use EvolutionPHP\Logger\Log;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

define('EvolutionPHPExceptions', 1);

class Exceptions {

	/**
	 * Nesting level of the output buffering mechanism
	 *
	 * @var	int
	 */
	public $ob_level;
	/**
	 * Use Logger
	 * @var bool
	 */
	private $use_logger = false;
	/**
	 * @var Log
	 */
	private $logger;

	private $debug;

	private $sent_header = false;
	/**
	 * List of available error levels
	 *
	 * @var	array
	 */
	public $levels = array(
		E_ERROR			=>	'Error',
		E_WARNING		=>	'Warning',
		E_PARSE			=>	'Parsing Error',
		E_NOTICE		=>	'Notice',
		E_CORE_ERROR		=>	'Core Error',
		E_CORE_WARNING		=>	'Core Warning',
		E_COMPILE_ERROR		=>	'Compile Error',
		E_COMPILE_WARNING	=>	'Compile Warning',
		E_USER_ERROR		=>	'User Error',
		E_USER_WARNING		=>	'User Warning',
		E_USER_NOTICE		=>	'User Notice',
		E_STRICT		=>	'Runtime Notice'
	);

	protected static $instance;

	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		self::$instance = $this;
		$this->ob_level = ob_get_level();
		// Note: Do not log messages from this constructor.
	}

	/*
	 * Instance constructor
	 */
	static function instance()
	{
		if(!self::$instance)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	/*
	 * Add Logger
	 */
	public function add_logger($config)
	{
		try{
			$this->logger = new Log($config);
			$this->use_logger = true;
		}catch (\Exception $exception){
			throw new \Exception($exception->getMessage());
		}
	}

	public function write_log($level, $message)
	{
		if($this->use_logger){
			$this->logger->write_log($level, $message);
		}
	}

	public function register(bool $debug=true)
	{
		$this->debug = $debug;
		if($this->debug === true){
			error_reporting(-1);
			ini_set('display_errors', 1);
		}else{
			ini_set('display_errors', 0);
			error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
		}
		$system = new System();
		$system->error_handler([$this, 'error_handler']);
		$system->exception_handler([$this,'exception_handler']);
		$system->shutdown_handler([$this,'shutdown_handler']);
	}

	public function is_cli()
	{
		return (PHP_SAPI === 'cli' OR defined('STDIN'));
	}

	// --------------------------------------------------------------------

	/**
	 * Exception Logger
	 *
	 * Logs PHP generated error messages
	 *
	 * @param	int	$severity	Log level
	 * @param	string	$message	Error message
	 * @param	string	$filepath	File path
	 * @param	int	$line		Line number
	 * @return	void
	 */
	public function log_exception($severity, $message, $filepath, $line)
	{
		$severity = isset($this->levels[$severity]) ? $this->levels[$severity] : $severity;
		$this->write_log('error', 'Severity: '.$severity.' --> '.$message.' '.$filepath.' '.$line);
	}


	// --------------------------------------------------------------------

	/**
	 * 404 Error Handler
	 *
	 * @uses	Exceptions::show_error()
	 *
	 * @param	string	$page		Page URI
	 * @param 	bool	$log_error	Whether to log the error
	 * @return	void
	 */
	public function show_404($page = '', $log_error = TRUE)
	{
		if ($this->is_cli())
		{
			$heading = 'Not Found';
			$message = 'The controller/method pair you requested was not found.';
		}
		else
		{
			$heading = '404 Page Not Found';
			$message = 'The page you requested was not found.';
		}

		// By default we log this, but allow a dev to skip it
		if ($log_error)
		{
			$this->write_log('error', $heading . ': ' . $page);
		}

		echo $this->show_error($heading, $message, 'error_404', 404);
		exit(4); // EXIT_UNKNOWN_FILE
	}

	// --------------------------------------------------------------------

	/**
	 * General Error Page
	 *
	 * Takes an error message as input (either as a string or an array)
	 * and displays it using the specified template.
	 *
	 * @param	string		$heading	Page heading
	 * @param	string|string[]	$message	Error message
	 * @param	string		$template	Template name
	 * @param 	int		$status_code	(default: 500)
	 *
	 * @return	string	Error page output
	 */
	public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
	{
		$templates_path = __DIR__.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR;

		if ($this->is_cli())
		{
			$message = "\t".(is_array($message) ? implode("\n\t", $message) : $message);
			$template = 'cli'.DIRECTORY_SEPARATOR.$template;
		}
		else
		{
			$this->set_status_header($status_code);
			$message = '<p>'.(is_array($message) ? implode('</p><p>', $message) : $message).'</p>';
			$template = 'html'.DIRECTORY_SEPARATOR.$template;
		}

		if (ob_get_level() > $this->ob_level + 1)
		{
			ob_end_flush();
		}
		ob_start();
		include($templates_path.$template.'.php');
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}

	// --------------------------------------------------------------------

	public function show_exception($exception)
	{
		if($this->is_cli()){
			$message = $exception->getMessage();
			$templates_path = __DIR__.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR;
			$template = 'cli'.DIRECTORY_SEPARATOR.'error_php';
			if (ob_get_level() > $this->ob_level + 1)
			{
				ob_end_flush();
			}
			ob_start();
			include($templates_path.$template.'.php');
			$buffer = ob_get_contents();
			ob_end_clean();
			echo $buffer;
		}else{
			$message = new HtmlErrorRenderer(true);
			$render = $message->render($exception);
			echo $render->getAsString();
		}

	}

	// --------------------------------------------------------------------

	/**
	 * Native PHP error handler
	 *
	 * @param	int	$severity	Error level
	 * @param	string	$message	Error message
	 * @param	string	$filepath	File path
	 * @param	int	$line		Line number
	 * @return	void
	 */
	public function show_php_error($severity, $message, $filepath, $line)
	{
		if($this->is_cli()){
			$templates_path = __DIR__.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR;
			$template = 'cli'.DIRECTORY_SEPARATOR.'error_php';
			if (ob_get_level() > $this->ob_level + 1)
			{
				ob_end_flush();
			}
			ob_start();
			include($templates_path.$template.'.php');
			$buffer = ob_get_contents();
			ob_end_clean();
			echo $buffer;
		}else{
			$exception = new \ErrorException($message, 0 , $severity, $filepath, $line);
			$message = new HtmlErrorRenderer(true);
			$render = $message->render($exception);
			$content = $render->getAsString();
			echo $content;
		}


	}

	public function set_status_header($code = 200, $text = '')
	{
		if ($this->is_cli())
		{
			return;
		}
		if($this->sent_header){
			return;
		}
		$this->sent_header = true;
		if (empty($code) OR ! is_numeric($code))
		{
			$message = 'Status codes must be numeric';
			if($this->debug){
				echo $this->show_error('Status codes must be numeric', 500);
				exit(1);
			}else{
				$this->write_log('error', $message);
			}
		}

		if (empty($text))
		{
			is_int($code) OR $code = (int) $code;
			$stati = array(
				100	=> 'Continue',
				101	=> 'Switching Protocols',

				200	=> 'OK',
				201	=> 'Created',
				202	=> 'Accepted',
				203	=> 'Non-Authoritative Information',
				204	=> 'No Content',
				205	=> 'Reset Content',
				206	=> 'Partial Content',

				300	=> 'Multiple Choices',
				301	=> 'Moved Permanently',
				302	=> 'Found',
				303	=> 'See Other',
				304	=> 'Not Modified',
				305	=> 'Use Proxy',
				307	=> 'Temporary Redirect',

				400	=> 'Bad Request',
				401	=> 'Unauthorized',
				402	=> 'Payment Required',
				403	=> 'Forbidden',
				404	=> 'Not Found',
				405	=> 'Method Not Allowed',
				406	=> 'Not Acceptable',
				407	=> 'Proxy Authentication Required',
				408	=> 'Request Timeout',
				409	=> 'Conflict',
				410	=> 'Gone',
				411	=> 'Length Required',
				412	=> 'Precondition Failed',
				413	=> 'Request Entity Too Large',
				414	=> 'Request-URI Too Long',
				415	=> 'Unsupported Media Type',
				416	=> 'Requested Range Not Satisfiable',
				417	=> 'Expectation Failed',
				422	=> 'Unprocessable Entity',
				426	=> 'Upgrade Required',
				428	=> 'Precondition Required',
				429	=> 'Too Many Requests',
				431	=> 'Request Header Fields Too Large',

				500	=> 'Internal Server Error',
				501	=> 'Not Implemented',
				502	=> 'Bad Gateway',
				503	=> 'Service Unavailable',
				504	=> 'Gateway Timeout',
				505	=> 'HTTP Version Not Supported',
				511	=> 'Network Authentication Required',
			);

			if (isset($stati[$code]))
			{
				$text = $stati[$code];
			}
			else
			{
				$message = 'No status text available. Please check your status code number or supply your own message text.';
				if($this->debug){
					echo $this->show_error($message, 500);
					exit(1);
				}else{
					$this->write_log('error', $message);
				}
			}
		}

		if (strpos(PHP_SAPI, 'cgi') === 0)
		{
			header('Status: '.$code.' '.$text, TRUE);
			return;
		}

		$server_protocol = (isset($_SERVER['SERVER_PROTOCOL']) && in_array($_SERVER['SERVER_PROTOCOL'], array('HTTP/1.0', 'HTTP/1.1', 'HTTP/2'), TRUE))
			? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
		header($server_protocol.' '.$code.' '.$text, TRUE, $code);
	}


	/*
	 * Handlers
	 */
	public function error_handler($severity, $message, $filepath, $line)
	{
		$is_error = (((E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR) & $severity) === $severity);

		// When an error occurred, set the status header to '500 Internal Server Error'
		// to indicate to the client something went wrong.
		// This can't be done within the $_error->show_php_error method because
		// it is only called when the display_errors flag is set (which isn't usually
		// the case in a production environment) or when errors are ignored because
		// they are above the error_reporting threshold.


		// Should we ignore the error? We'll get the current error_reporting
		// level and add its bits with the severity bits to find out.
		if (($severity & error_reporting()) !== $severity)
		{
			return;
		}

		$this->log_exception($severity, $message, $filepath, $line);

		// Should we display the error?
		if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors')))
		{
			$this->show_php_error($severity, $message, $filepath, $line);
		}

		// If the error is fatal, the execution of the script should be stopped because
		// errors can't be recovered from. Halting the script conforms with PHP's
		// default error handling. See http://www.php.net/manual/en/errorfunc.constants.php
		if ($is_error)
		{
			exit(1); // EXIT_ERROR
		}
	}

	public function exception_handler($exception)
	{
		$this->log_exception('error', 'Exception: '.$exception->getMessage(), $exception->getFile(), $exception->getLine());
		// Should we display the error?
		if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors')))
		{
			$this->show_exception($exception);
		}

		exit(1); // EXIT_ERROR
	}

	public function shutdown_handler()
	{
		$last_error = error_get_last();
		if (isset($last_error) &&
			($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING)))
		{
			$this->error_handler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
		}
	}
}