<?php
include_once INCLUDE_DIR.'class.imap.php';

class EmailManager extends Module {
    var $prologue = 'CLI email manager';
    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'monitor' => 'Monitor IMAP4 accounts',
                'fetch' => 'Export list items from the system',
            ),
        ),
    );


    var $options = array(
        'id' => array('-ID', '--id', 'metavar'=>'id',
            'help' => 'List ID'),
        );

    function run($args, $options) {
        global $ost, $cfg;

        Bootstrap::connect();

        if (!($ost=osTicket::start()) || !($cfg = $ost->getConfig()))
            $this->fail('Unable to load config info!');

        switch ($args['action']) {
            case 'monitor':
                if (!$cfg->isEmailPollingEnabled() && sleep(10))
                    $this->fail('Email polling is not enabled');

                try {
                    Email::monitor($options);
                } catch (Exception $ex) {
                     $this->fail(sprintf("\nEmail monitoring error - %s",
                                 $ex->getMessage()));
                }
                break;
            case 'fetch':
                MailFetcher::run($options);
                break;
            default:
                $this->stderr->write('Unknown action!');
        }
    }
}
Module::register('email', 'EmailManager');
?>
