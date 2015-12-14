<?php
/*********************************************************************
    service.php

    Module for osTicket CLI service.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2016 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

@set_time_limit(0);
@ob_implicit_flush(true);

require_once INCLUDE_DIR.'class.service.php';

class ServiceManager extends Module {
    var $prologue = 'CLI service manager';
    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'start' => 'Start Service',
                'stop' => 'Stop Service',
                'status' => 'Service Status',
                'run'  => 'Run Service (System Only)',
                'create' => 'Create Windows Service',
                'remove' => 'Remove Windows Service',
            ),
        ),
    );


    var $options = array();

    var $service = null;

    function __construct() {
        parent::__construct();
        $options = &$this->arguments['action']['options'];
        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN'))
            unset($options['create'], $options['remove']);
    }

    function run($args, $options) {

        // Make sure we're on Windows paltform
        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN'))
            $this->fail('Service module only available on Windows platform');

        // Init the service
        try {
            $this->service = Win32ServiceManager::instance();
        } catch( Exception $ex) {
            $this->fail($ex->getMessage());
        }

        // Register services
        // Email account minitoring service
        $this->service->register('EmailService',
                array('action' => 'monitor'));
        // Cron services
        $this->service->register('CronService',
                array('interval' => 60));
        // Run the service manager
        $this->service->run($args['action']);
    }

}
Module::register('service', 'ServiceManager');
?>
