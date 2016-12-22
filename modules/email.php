<?php

class EmailManager extends Module {
    var $prologue = 'CLI email manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import to emails table from yaml file',
                'export' => 'Export from emails table to CSV or yaml',
                'list' => 'List email addresses based on search criteria',
            ),
        ),
    );


    var $options = array(
        'file' => array('-f', '--file', 'metavar'=>'path',
            'help' => 'File or stream to process'),
        'verbose' => array('-v', '--verbose', 'default'=>false,
            'action'=>'store_true', 'help' => 'Be more verbose'),
        'csv' => array('-csv', '--csv', 'default'=>false,
            'action'=>'store_true', 'help'=>'Export in csv format'),
        'yaml' => array('-yaml', '--yaml', 'default'=>false,
            'action'=>'store_true', 'help'=>'Export in yaml format'),
        );

    var $stream;

    function run($args, $options) {

      if (!function_exists('boolval')) {
        function boolval($val) {
          return (bool) $val;
        }
      }

        Bootstrap::connect();

        switch ($args['action']) {
        case 'import':
          // Properly detect Macintosh style line endings
          ini_set('auto_detect_line_endings', true);

          //check command line option
          if (!$options['file'] || $options['file'] == '-')
          $options['file'] = 'php://stdin';

          //make sure the file can be opened
          if (!($this->stream = fopen($options['file'], 'rb')))
          $this->fail("Unable to open input file [{$options['file']}]");

          //place file into array
          $data = YamlDataParser::load($options['file']);

          //create emails with a unique name as a new record
          $errors = array();
          foreach ($data as $o) {
              if ('self::__create' && is_callable('self::__create'))
                  @call_user_func_array('self::__create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }
        break;

        case 'export':
            if ($options['yaml'])
            {
              //get the departments
              $emails = $this->getQuerySet($options);

              //format the array nicely
              foreach ($emails as $E)
              {
                $clean[] = array('priority_id' => $E->getPriorityId(), 'dept_id' => $E->getDeptId(), 'email' => $E->getEmail(), 'name' => $E->getName());
              }

              //export yaml file
              echo (Spyc::YAMLDump($clean));
            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Email ID','Priority ID', 'Department', 'Email', 'Name'));
              foreach (Email::objects() as $email)
                  fputcsv($this->stream,
                          array((string) $email->getId(), $email->getPriorityId(), $email->getDept(), $email->getEmail(), $email->getName()));
            }

            break;

        case 'list':
            $emails = $this->getQuerySet($options);

            foreach ($emails as $E) {
                $this->stdout->write(sprintf(
                    "%d %s <%s>%s\n",
                    $E->getId(), $E->getPriorityId(), $E->getDept(), $E->getEmail(), $E->getName()
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $emails = Email::objects();

        return $emails;
    }

    static function __create($vars, &$error=false, $fetch=false) {
        //see if staff exists
        if ($fetch && ($emailId=Email::getIdByEmail($vars['email'])))
        {
          var_dump('match');
          return Email::lookup($emailId);
        }
        else
        {
          var_dump('new');
          $email = Email::create($vars);
          $email->save();
          return $email->email_id;
        }


    }
}
Module::register('email', 'EmailManager');
?>
