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
                if (!$cfg->isEmailPollingEnabled())
                    $this->fail('Email polling is not enabled');

                $MAXERRORS = 5; //Max errors before we start delayed fetch attempts
                $TIMEOUT = 10; //Timeout in minutes after max errors is reached.

                $emails = Email::objects()
                    ->filter(array(
                                'mail_active' => 1,
                                'mail_protocol' => 'IMAP',
                                Q::any(array(
                                        'mail_errors__lte' => $MAXERRORS,
                                        'mail_lasterror__gt' =>
                                            SqlFunction::NOW()->minus(SqlInterval::MINUTE($TIMEOUT))
                                            )
                                    )
                                )
                            );

                if (isset($options['id']))
                    $emails->filter(array('email_id' => $options['id']));

                if (!count($emails))
                    $this->fail('Unable to find email acconts to monitor');

                $read = $write = $except = null;
                $map = array();
                foreach ($emails as $email) {
                    $this->stderr->write(sprintf("Monitoring ID#%d\n",
                                $email->getId()));
                    $imap = IMAP::open($email->getMailAccountInfo());
                    $imap->setDebug(true);
                    $res=$imap->idle();
                    $read[$email->getId()] = $imap->_socket->fp;
                    $map[$email->getId()] = $imap;
                }
                var_dump($read);
                // Idle waiting for mail to arrive
                while (stream_select($read, $write, $except, 300, 0)
                        !== false) {
                    foreach ($read as $k => $r) {
                        if (($o = $map[$k]))
                            $o->wakeup();

                        $this->stderr->write("New mail for  $k");
                    }
                    $this->stderr->write("Listen again!");

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
