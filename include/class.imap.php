<?php
/**
 * Peter Rotich <peter@osticket.com>
 * http://www.osticket.com
 *
 *
 * This classextends pear/Net_IMAP and provides the IMAP IDLE command (RFC 2177).
 *
 *
 * It's based on script developed by @noisan
 * https://github.com/noisan/imap-idle
 * @author Akihiro Yamanoi <akihiro.yamanoi@gmail.com>
 *
 */

require PEAR_DIR.'Net/IMAP.php';

class IMAP extends Net_IMAP {

    const RESPONSE_TIMEOUT = 'IDLE_ABORTED';
    const RESPONSE_DIFER = 'IDLE_DIFER';

    private $idling;
    private $maxIdleTime;



    // Trap the constructor to set stream context
    function __construct() {

        // SSL context options to turn of PHP 5.6+ strict checks
        $options = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    ),
                );

        $this->setStreamContextOptions($options);
        // Call the parent constructor and pass whatever was passed to us
        call_user_func_array(array('parent', '__construct'),
                func_get_args());
    }

    /**
     * Uses the IMAP IDLE command (RFC 2177).
     *
     * @see http://tools.ietf.org/html/rfc2177
     *
     * @param int|null $maxIdleTime Number of seconds for timeout.
     * @return boolean|\PEAR_Error TRUE if the selected mailbox is updated,
     *                             FALSE if $maxIdleTime expires.
     *                             PEAR_Error on error.
     */


    public function idle($maxIdleTime=null, $callback=null)
    {
        $this->_blocking = ($maxIdleTime); // Bocking if we have timeout
        $this->maxIdleTime = $maxIdleTime;
        $this->_socket->setBlocking(false);

        while (true) {
            $res = $this->_idle();
            if ($res instanceof PEAR_Error)
                return $res;

            if (!$callback || !call_user_func_array($callback, array($this, $res)))
                break;
        }

        return $res;
    }

    private function _idle()
    {
        $this->idling = true;
        $ret = $this->_genericCommand('IDLE');

        if ($ret instanceof PEAR_Error) {
            // this means that the socket is already disconnected
            return $ret;
        }

        if (strtoupper($ret['RESPONSE']['CODE']) != 'OK') {
            // unknown response type
            // probably we got disconnected before sending the DONE command
            return new PEAR_Error($ret['RESPONSE']['CODE'] .
                    ', ' . $ret['RESPONSE']['STR_CODE']);
        }

        if (isset($ret['PARSED'][0]['COMMAND']))
            return $ret['PARSED'][0]['COMMAND'];

        return true;
    }

    protected function done()
    {
        $this->idling = false;
        $ret = $this->_send('DONE' . "\r\n");
        if ($ret instanceof PEAR_Error) {
            $this->onError($ret);
        }
    }

    // override
    function _recvLn()
    {

        if (!$this->idling) {
            return parent::_recvLn();
        }

        // Idling with no timeout  --  deffer read.
        if (!$this->maxIdleTime)
             return new PEAR_Error('123', 'Timeout required to idle');

        if ($this->_socket->select(NET_SOCKET_READ, $this->maxIdleTime)) {
            return $this->wakeup();
        }

        $this->done();

        $this->lastline = $this->timeoutMessage();

        return $this->lastline;
    }

    function wakeup() {
        $lastline = parent::_recvLn();
        if ($lastline instanceof PEAR_Error) {
            return $this->onError($lastline);
        }

        if ($this->idling && $this->isMailboxUpdated($lastline)) {
            $this->done();
        }

        return $lastline;
    }

    public function isIdling() {
        return ($this->idling);
    }

    protected function isMailboxUpdated($lastline)
    {
        $keywords = array('EXISTS', 'RECENT', 'EXPUNGE');
        foreach ($keywords as $k) {
            if (stripos($lastline, $k) !== false)
                return true;
        }

        return false;
    }

    protected function createMessage($msg)
    {
        return '* ' . $msg . "\r\n";
    }

    protected function timeoutMessage() {
        return $this->createMessage(self::RESPONSE_TIMEOUT);
    }

    protected function deferMessage() {
        return $this->createMessage(self::RESPONSE_DIFER);
    }

    // override
    function _retrParsedResponse(&$str, $token, $previousToken = null)
    {
        if (strtoupper($token) == self::RESPONSE_TIMEOUT) {
            return array($token => rtrim(substr($this->_getToEOL($str, false), 1)));
        }
        return parent::_retrParsedResponse($str, $token, $previousToken);
    }

    protected function onError($error)
    {
        return $error;
        /*
        throw new UnexpectedValueException($error->getMessage());
         */
    }

    static function open($info) {

        $imap = new IMAP(
                $info['host'],
                $info['port'],
                $info['ssl'],
                'UTF-8');

        $res = $imap->login($info['username'], $info['password']);
        if ($res instanceof PEAR_Error)
            return $res;

        if (isset($info['mailbox']))
            $imap->selectMailbox($info['mailbox']);

        return $imap;
    }
}
?>
