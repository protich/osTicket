<?php

class StatusManager extends Module {
    var $prologue = 'CLI department manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import statuses from yaml file',
                'export' => 'Export statuses from the system to CSV or yaml',
                'list' => 'List statuses based on search criteria',
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

          //create statuses with a unique name as a new record
          $errors = array();
          foreach ($data as $o) {
              if ('TicketStatus::__create' && is_callable('TicketStatus::__create'))
                  @call_user_func_array('TicketStatus::__create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }
          break;

        case 'export':
            if ($options['yaml'])
            {
              //get the statuses
              $statuses = self::getQuerySet($options);

              //format the array nicely
              foreach ($statuses as $status)
              {
                $clean[] = array('name' => $status->getName(), 'state' => $status->getState(), 'mode' => $status->get('mode'), 'sort' => $status->get('sort'), 'properties' => $status->get('properties'));
              }

              //export yaml file
              // echo Spyc::YAMLDump(array_values($clean), true, false, true);

              if(!file_exists('status.yaml'))
              {
                $fh = fopen('status.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('name', 'state', 'mode', 'sort', 'properties'));
              foreach (TicketStatus::objects() as $status)
                  fputcsv($this->stream,
                          array((string) $status->getName(), $status->getState(), $status->get('mode'), $status->get('sort'), $status->get('properties')));

            }

            break;

        case 'list':
            $statuses = $this->getQuerySet($options);

            foreach ($statuses as $S) {
                $this->stdout->write(sprintf(
                    "%d %s <%s> %s %s\n",
                    $S->getName(), $S->getState(), $S->get('mode'), $S->get('sort'), $S->get('properties')
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $statuses = TicketStatus::objects();

        return $statuses;
    }

    static function getIdByName($name) {
        $row = TicketStatus::objects()
            ->filter(array('name'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

}
Module::register('status', 'StatusManager');
?>
