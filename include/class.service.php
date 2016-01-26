<?php
/*********************************************************************
    class.service.php

    Register and start osTicket service.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

include_once INCLUDE_DIR.'class.cli.php';


abstract class CLIProcess {

	protected $pid = 0;
	protected $stdOut = null;
	protected $stdErr = null;
	protected $stdIn  = null;

	// Executors
	abstract function run($cmd);
	abstract function terminate();

	// Getters
    function getPid() {
        return $this->pid;
    }

	function getStdOut() {
		return $this->stdOut;
	}

	function getStdErr() {
		return $this->stdErr;
	}

	function getStdIn() {
		return $this->stdIn;
	}

	abstract function getStatus();
	abstract function getExitCode();

	// Helpers
	function isRunning() {
		return false;
	}
}


// Simple Process wrapper for WScript.Shell
class WshShell extends CLIProcess {

	private $cmd = 'cmd /C php.exe';
	protected $wshShell;
	protected $oExec;

	function __construct() {
		$this->wshShell = new COM('WScript.Shell');
	}

	// Exec
	private function exec($script) {
        $cmd = sprintf('%s %s 2>&1', $this->cmd, $script);
        $this->oExec = $this->wshShell->exec($cmd);

		if (!$this->oExec || !$this->oExec->ProcessId)
			return false;

		$this->stdIn = $this->oExec->stdIn;
		$this->stdOut = $this->oExec->stdOut;
		$this->pid = $this->oExec->ProcessId;

		return $this->pid;
	}

	function run($script) {
		return $this->exec($script);
	}

	function terminate() {
		if ($this->isRunning())
			$this->oExec->Terminate();
	}

	function isRunning() {
		return ($this->oExec && $this->getStatus() == 0);
	}

	public function getStatus() {
		return $this->oExec->Status;
	}

	public function getExitCode() {
		// Exit code of null means process is new.
		if (!$this->oExec)
			return null;

		return $this->oExec->ExitCode;
	}
}

// Template for Base Service (Daemon assumed)
abstract class SimpleService {

	private $manager;
    private $exec;

    // Max idle timeout in seconds
    protected $idleTimeout = 20;

    // Idle timestamp
    private $idleTime = 0;


    // Module
    protected $module = '';
	// Default process
	protected $options = array(
		'timeout' => 0,
	);

	function __construct($manager, $options=array()) {

		if (!$manager instanceof BaseServiceManager)
			throw new Exception('Invalid CLI hadle');

		$this->manager = $manager;
		$this->options = array_merge($this->options, $options);
        $this->exec = sprintf('%s %s %s',
                realpath(ROOT_DIR.'manage.php'),
                $this->module,
                $this->getArgsStr());

	}

    abstract protected function getArgsStr();

	function getName() {
		return $this->name;
	}

	function getDescription(){
		return $this->getDescription;
	}

	// If the process is running then we're busy
	function isRunning() {
		return ($this->process && $this->process->isRunning());
	}

    function isIdlieng() {
        return ($this->idleTime);
    }

    // Routine for service checkup
    function howdy() {

        // Process is running
        if ($this->isRunning())
            return true;

        // Idle check
        if ($this->isIdlieng()) {
            if ((time()- $this->idleTime) < $this->idleTimeout)
                return true; // Leave me alone.
        }

        $this->idleTime = time(); // Idleing for now.
        // TODO: if we need to go idle differently based on prior exit codes.
        $code = $this->process ? $this->process->getExitCode() : null;
        $this->log(sprintf('Process Status:  %s (%s)', $this->process->getStatus(), $code));
        // Restart the process
        $this->restart();

        return true;
    }

	// Start the process
	function start() {

		if ($this->isRunning())
            return true;

		try {
			$this->process = call_user_func(array($this->manager, 'cliProcess'));
			if (!$this->process || !$this->process->run($this->exec))
                throw new Exception('failed to start');

		} catch(Exception $ex) {
            $this->log(sprintf('%s failed to start (%s)',
                        $this->getName(), $ex->getMessage()));

			return false;
		}

		$this->log(sprintf('%s started with pid #%d',
                    $this->getName(), $this->process->getPid()));

		return true;
	}

    protected function restart() {

        if ($this->process)
            $this->process->terminate();

        $this->log(sprintf('%s restarting...', $this->getName()));

        return $this->start();

    }

	function stop() {
		return $this->process->terminate();
	}

	function getStream() {
		return $this->process->getStdOut();
	}

    function log($msg) {
        return $this->manager->log(sprintf('%s - %s ', $this->getName(), $msg));
    }
}

class EmailService extends SimpleService {
	protected $name = 'Email Service';
	protected $desc = 'Email Accounts Service';

	protected $module = 'email';

    private $args = null;

    protected function getArgsStr() {

        if (isset($this->args))
            return $this->args;

        // Email action..
        $args = 'fetch';
        if (isset($this->options['action']))
            $args = $this->options['action'];

        if ($this->options['id'] && is_numeric($this->options['id']))
            $args .= ' --id='.$this->options['id'];

        return $this->args = $args;
    }

}


class CronService extends SimpleService {

    protected $name = 'Cron Service';
    protected $desc = 'osTikcet Cron Service';

    protected $module = 'cron';

    // Call interval in seconds
    protected $idleTimeout = 60;

    //arguments
    private $args = null;

    protected function getArgsStr() {

        if (isset($this->args))
            return $this->args;

        //Target cron call
        $type = 'run';
        if (isset($this->options['type']))
            $type = $this->options['type'];

        return $this->args = sprintf('%s --interval=%d', $type, 0);
    }

}

abstract class BaseServiceManager {
	// Service name and description
	protected $name = 'osTicket Service';
    protected $description = 'osTicket Service Manager';

    //options
    private $options = array(
            'debug' => false,
            );

	// CLI Process class
	static protected $cli = 'CLIProcess';

	// Services to manage
	protected $services = null;

	// Streams to listen to for outputs from services
	protected $streams = array();

	// Idle timeout in seconds (defalt to 10 seconds)
	protected $idleTimeout = 10;

	// Default services supported -- more can be added via ::register();
	static private $_registry = array();

	function __construct($options=array()) {

        // Options
        $this->options = array_merge($this->options, $options);

        // output streams
        $this->stdout = new OutputStream('php://output');
        $this->stderr = new OutputStream('php://stderr');

        // root dir
        $this->rootdir = ROOT_DIR;

        // Adding a hash to the name based on secrest key for this installation
        // Windows Service relies on unique name as indentifier.
        $this->name = sprintf('%s (%s)',
            $this->getName(), substr(md5(SECRET_KEY), -8));

    }

    static function registry() {
        return self::$_registry;
    }

	function register($service, $options=array()) {
		if (!is_string($service) || !class_exists($service))
			throw new Exception('Invalid service registration');

		self::$_registry[$service] = $options;

        return true;
	}

	// Command line process for the manager.
	static function cliProcess(){
		$class = static::$cli; // Process class
		return new $class();
	}

	public function getName() {
        return $this->name;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getNumServices() {
        return isset($this->services) ? count($this->services) : 0;
    }

    protected function getServices() {
        return $this->services;
    }

	private function initServices() {
		// Init registered services
		if (isset($this->services))
            $this->stop();

        $this->services = array();
        foreach (static::registry() as $service => $opts)
            $this->services[] = new $service($this, $opts);

        return $this->getNumServices();
    }

    private function startServices() {

		// start registered services
		$this->log('Starting Services');
        $i = 0;
		foreach ($this->services as $k => &$service) {
			if (!$service || !$service->start()) continue;

            if (($stream=$service->getStream()))
				$this->streams[$k] = $stream;

            $i++;
		}

        return $i;
	}

    private function checkServices() {

		foreach ($this->services as $service)
			$service->howdy();
    }

    private function stopServices() {

		$this->log('Stopping Services');
		if ($this->service) {
			foreach ($this->services as $k => $service) {
				if ($service && $service->stop())
					unset($this->streams[$k], $this->services[$k]);
			}
		}

		$this->log('Local Services stopped');

        return true;
    }

	protected function listen($timeout=0) {

        $read = array();
        foreach ($this->services as $k => $service) {
            if ($service && ($stream = $service->getStream()))
                $read[$k] = $stream;
        }

        // nothing to read.
        if (!count($read))
            return false;

        $timeout = $timeout ?: $this->idleTimeout;
        $num = stream_select($read, $w = null, $e = null, $timeout);
        if ($num === false) // stream_select error
            return false;

        // timed out -- report success.
        if ($num === 0)
            return true;

        foreach ($read as $k => $stream) {
            $service = $this->services[$k];
            $stdout = stream_get_contents($stream);
            if (!$stdout) continue;
            $this->log(sprintf('%s - %s', $service->getName(), $stdout));
        }

        return true;
	}

    function init() {
        return $this->initServices();
    }

    function start() {
        return $this->startServices();
    }

    function stop() {
        return $this->stopServices();
    }

    protected function monitor() {
        return $this->checkServices();
    }

	protected function sleep($timeout=0) {
		$timeout = $timeout ?: $this->idleTimeout;
        $this->log("Going to sleep for $timeout seconds");
		sleep($timeout);
	}

    protected function fail($text='') {
        $this->log($text, true);
        $this->stop();
        die();
    }

	public function log($text, $err = false) {

        // Errors override debug setting
        if (!$err && !$this->options['debug'])
            return;

        $text = sprintf("%s : %s\n", $this->getName(), $text);
        if ($err) {
            // XXX: Logging error_log and stdout
            error_log($text);
            $this->stderr->write($text);
        } else {
            $this->stdout->write($text);
        }
    }


}


class GenericServiceManager extends BaseServiceManager {
    static $instance;

    protected function run($opts=array()) {

        // Init services.
        if (!($this->init()))
            $this->fail('Unable to initialize local services');


        // Start local services
        if (!$this->start())
            $this->log('Unable to start ANY local services');

        $this->log(sprintf('Service manager running with %d local services',
                        $this->getNumServices()));

        while (true) {

            $this->monitor();
            $this->sleep();
        }

        $this->stop();
    }


    static function instance($options=array()) {

        if (isset(static::$instance))
            return static::$instance;

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN'))
            throw new Exception('Service only available in Windows platform');

        static::$instance =  WindowsServiceManager::instance($options);


        return static::$instance;

    }

}

class WindowsServiceManager extends GenericServiceManager {
    static $instance;
    // Windows specific CLI Process.
    static protected $cli = 'WshShell';


    static function instance($options=array()) {

        if (isset(static::$instance))
            return static::$instance;

        // Make sure we're on Windows paltform
        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN'))
            throw new Exception('Service module only available on Windows platform');

        // Make sure COM extension is loaded
        if (!extension_loaded('com_dotnet')
                && !@dl('php_com_dotnet.dll'))
            throw new Exception('Windows Service Manager: Cannot load
                    php_com_dotnet.dll extension');

        static::$instance = new static($options);

        return static::$instance;
    }

}


class Win32ServiceManager extends WindowsServiceManager {

	// win32service instance
	static $instance;

    // Win32 Service statuses
    static protected $statuses = array(
            WIN32_SERVICE_STOPPED => 'Service Stopped',
            WIN32_SERVICE_START_PENDING => 'Service Start Pending',
            WIN32_SERVICE_RUNNING => 'Service Running',
            WIN32_SERVICE_STOP_PENDING => 'Service Stop Pending',
            WIN32_SERVICE_CONTINUE_PENDING => 'Service Continue Pending',
            WIN32_SERVICE_PAUSE_PENDING => 'Service Pause Pending',
            WIN32_SERVICE_PAUSED => 'Service Paused',
            );

    public function getStatus() {
        return win32_query_service_status($this->getName());
    }

    public function getStatusCode() {
        $status = $this->getStatus();
        return $status['CurrentState'] ?: $status;
    }

    public function showStatus() {

        $code = $this->getStatusCode();
        if (isset(self::$statuses[$code]))
            $this->stdout->write(self::$statuses[$code]);
		elseif ($code === 1060)
            $this->stdout->write('Unknown Service');
        else
            $this->stdout->write('Unknown Status ('.$code.')');
    }

    private function exists() {
        return ($this->getStatus() !== 1060);
    }

    private function isRunning() {
        return ($this->getStatusCode() == WIN32_SERVICE_RUNNING);
    }

    private function create() {

        if ($this->exists())
            $this->fail(sprintf("WinService %s already created!", $this->getName()));

		$file = realpath($this->rootdir.'manage.php');
        $service = win32_create_service(array(
                    'service' => $this->getName(),
					'display' => $this->getName(),
                    'description' => $this->getDescription(),
                    'params' => sprintf('"%s" service run',
                        $file),
                    )
                );

        if ($service !== WIN32_NO_ERROR)
            $this->fail("Unable to create win32service ($service");

        return true;
    }

    private function delete() {
        return win32_delete_service($this->getName());
    }

    private function _run($create=true) {

        // Check if the service is installed - auto create on request.
        if (!$this->exists() && (!$create || !$this->create()))
            $this->fail('Win32Service unavailable');

        // Init services.
        if (!($this->init()))
            $this->fail('Unable to initialize local services');


        // Tell the dispatcher we're running.
        win32_start_service_ctrl_dispatcher($this->getName());
        win32_set_service_status(WIN32_SERVICE_RUNNING);

        // Start local services
        if (!$this->start())
            $this->log('Unable to start ANY local services');

        $this->log(sprintf('Win32Service running with %d local services',
                        $this->getNumServices()));

        while (true) {
			$x = win32_get_last_control_message();
            switch ($x){
            case WIN32_SERVICE_CONTROL_INTERROGATE:
                win32_set_service_status(WIN32_NO_ERROR);
                break;
            case WIN32_SERVICE_CONTROL_STOP:
                win32_set_service_status(WIN32_SERVICE_STOP_PENDING);
                $this->stop();
                win32_set_service_status(WIN32_SERVICE_STOPPED);
                exit;
			case WIN32_SERVICE_CONTROL_CONTINUE:
			case WIN32_NO_ERROR:
			    break;
            default:
				$this->log("Unknown control message" .$x);
            }

            // Check services and do restart if need be.
			$this->monitor();

			// Listen to streams if any or simply sleep
			$this->sleep();
	        //if ($this->listen() || $this->sleep());
        }

        // Set status of stopped.
        win32_set_service_status(WIN32_SERVICE_STOPPED);
        // Some how the daemon went away!
        $this->stopServices();
        exit();
    }


    public function run($action) {

        if (!$this->exists() && !in_array($action, array('create', 'run')))
            $this->fail('Unknown service - create first');

        switch ($action) {
        case 'run':
            // check if the service is already running
            if ($this->isRunning()) {
                $this->log('Win32Service already running');
                break;
            }
            // Run the service daemon
            $this->_run();
            // Return means failure
            $this->fail('Win32Service run failed');
            break;
        case 'start':
            // CLI user permission matter here greatly.
            $rv = win32_start_service($this->getName());
            if ($rv !== WIN32_NO_ERROR)
                $this->fail("Unable to start - ($rv)");

            $this->log('Win32Service started');
            break;
        case 'stop':
            win32_stop_service($this->getName());
            $this->log('Win32Service stopped');
            break;
		case 'create':
			if (!$this->create())
				$this->fail('Unable to create service');
			$this->log('Win32Service created successfully');
			break;
		case 'remove':
        case 'delete':
            win32_delete_service($this->getName());
            $this->log('Win32Service deleted');
            break;
        case 'status':
            $this->showStatus();
            break;
        default:
            $this->log('Unknown command');
        }
    }

    static function instance($options=array()) {

        if (isset(static::$instance))
            return static::$instance;

        // Make sure Win32Service extension is loaded
        if (!extension_loaded('win32service')
                && !@dl('php_win32service.dll'))
            throw new Exception('Windows Service Manager: Cannot load
                    php_win32service.dll extension');

        static::$instance = parent::instance($options);

        return static::$instance;
    }
}
?>
