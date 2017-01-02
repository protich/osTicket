<?php

class ThreadManager extends Module {
    var $prologue = 'CLI thread manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import threads from yaml file',
                'export' => 'Export threads from the system to CSV or yaml',
                'list' => 'List threads based on search criteria',
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

          //create threads with a unique name as a new record
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
              //get the statuses
              $threads = self::getQuerySet($options);

              //format the array nicely
              foreach ($threads as $thread)
              {
                $clean[] = array('object_id' => $thread->getObjectId(), 'object_type' => $thread->getObjectType(),
                'extra' => $thread->get('extra'), 'lastresponse' => $thread->get('lastresponse'));

              }


              //export yaml file
              echo Spyc::YAMLDump(array_values($clean), true, false, true);

              // if(!file_exists('thread.yaml'))
              // {
              //   $fh = fopen('thread.yaml', 'w');
              //   fwrite($fh, (Spyc::YAMLDump($clean)));
              //   fclose($fh);
              // }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('object_id', 'object_type', 'extra', 'lastresponse'));
              foreach (Thread::objects() as $thread)
                  fputcsv($this->stream,
                          array((string) $thread->getObjectId(), $thread->getObjectType(), $thread->get('extra'), $thread->get('lastresponse')));
            }

            break;

        case 'list':
            $threads = $this->getQuerySet($options);

            foreach ($threads as $T) {
                $this->stdout->write(sprintf(
                    "%d %s <%s> %s %s\n",
                    $T->getObjectId(), $T->getObjectType(), $T->get('extra'), $T->get('lastresponse')
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $threads = Thread::objects();

        return $threads;
    }

    private function getIdByCombo($object_id, $object_type)
    {
      $row = DynamicFormEntry::objects()
          ->filter(array(
            'object_id'=>$object_id,
            'object_type'=>$object_type))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }

    static function __create($vars, &$error=false, $fetch=false) {
        //see if thread exists
        if ($fetch && ($threadId=self::getIdByCombo($vars['object_id'], $vars['object_type'])))
        {
          var_dump('match');
          return Thread::lookup($threadId);
        }
        else
        {
          var_dump('new');
          $thread = Thread::create($vars);
          $thread->save();
          return $thread->id;
        }


    }


}
Module::register('thread', 'ThreadManager');
?>
