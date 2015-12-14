<?php
require_once INCLUDE_DIR.'class.cron.php';

class CronManager extends Module {
    var $prologue = 'CLI cron manager for osTicket';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'run' => 'Run Cron Services',
                'fetch' => 'Fetch email',
                'search' => 'Build search index'
            ),
        ),
    );

    var $options = array(
        'interval' => array('-i', '--interval', 'metavar'=>'seconds',
            'help' => 'Number of seconds to wait before running the service again'),
        );

    function run($args, $options) {
        global $ost, $cfg;

        Bootstrap::connect();

        if (!($ost= osTicket::start()) || !($cfg=$ost->getConfig()))
            $this->fail('Unable to load config');

        switch (strtolower($args[0])) {
        case 'run':
            $start = time();
            Cron::run();
            $seconds = time() - $start;
            $this->stdout->write("\nCronService ($seconds)\n");
            break;
        case 'fetch':
            Cron::MailFetcher();
            break;
        case 'search':
            $ost->searcher->backend->IndexOldStuff();
            break;
        }
    }
}

Module::register('cron', 'CronManager');
?>
