<?php

class TopicManager extends Module {
    var $prologue = 'CLI help topic manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import help topics from yaml file',
                'export' => 'Export help topics from the system to CSV or yaml',
                'list' => 'List help topics based on search criteria',
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

          //create topics with a unique name as a new record
          $errors = array();
          foreach ($data as $o) {
              if ('Topic::__create' && is_callable('Topic::__create'))
                  @call_user_func_array('Topic::__create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

          break;

        case 'export':
            if ($options['yaml'])
            {
              //get the topics
              $topics = self::getQuerySet($options);

              //format the array nicely
              foreach ($topics as $topic)
              {
                $clean[] = array('isactive' => $topic->isactive,
                'ispublic' => $topic->ispublic, 'dept_id' => $topic->getDeptId(), 'priority_id' => $topic->getPriorityId(),
                'topic' => $topic->topic, 'notes' => $topic->notes);

              }

              //export yaml file
              echo Spyc::YAMLDump(array_values($clean), true, false, true);

              if(!file_exists('topic.yaml'))
              {
                $fh = fopen('topic.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Topici Id', 'isactive', 'ispublic', 'Priority Id', 'Department Id', 'Topic', 'Notes'));
              foreach (Topic::objects() as $topic)
                  fputcsv($this->stream,
                          array((string) $topic->getId(), boolval($topic->isactive), boolval($topic->ispublic),
                          $topic->getDeptId(), $topic->getPriorityId(), $topic->topic, $topic->notes));
            }

            break;

        case 'list':
            $topics = $this->getQuerySet($options);

            foreach ($topics as $topic)
            {
                $this->stdout->write(sprintf(
                    "%d %s <%s> %s %s %s %s\n",
                    $topic->getId(), boolval($topic->isactive), boolval($topic->ispublic),
                    $topic->getDeptId(), $topic->getPriorityId(), $topic->topic, $topic->notes)
                );
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $departments = Topic::objects();

        return $departments;
    }


}
Module::register('topic', 'TopicManager');
?>
