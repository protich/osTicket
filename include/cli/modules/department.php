<?php

class DepartmentManager extends Module {
    var $prologue = 'CLI department manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import departments from yaml file',
                'export' => 'Export departments from the system to CSV or yaml',
                'list' => 'List departments based on search criteria',
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

          //create departments with a unique name as a new record
          $errors = array();
          foreach ($data as $o) {
              if ('Dept::__create' && is_callable('Dept::__create'))
                  @call_user_func_array('Dept::__create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

          break;

        case 'export':
            if ($options['yaml'])
            {
              //get the departments
              $departments = self::getQuerySet($options);

              //format the array nicely
              foreach ($departments as $department)
              {
                $clean[] = array('name' => $department->getName(), 'signature' => $department->getSignature(),
                  'ispublic' => $department->ispublic,
                  'group_membership' => $department->group_membership);

              }

              //export yaml file
              echo Spyc::YAMLDump(array_values($clean), true, false, true);

              if(!file_exists('department.yaml'))
              {
                $fh = fopen('department.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Name', 'Signature', 'ispublic', 'group_membership'));
              foreach (Dept::objects() as $department)
                  fputcsv($this->stream,
                          array((string) $department->getName(), $department->getSignature(), boolval($department->ispublic), boolval($department->group_membership)));

            }

            break;

        case 'list':
            $departments = $this->getQuerySet($options);

            foreach ($departments as $D) {
                $this->stdout->write(sprintf(
                    "%d %s <%s>%s\n",
                    $D->id, $D->getName(), $D->getSignature(), boolval($D->ispublic), boolval($D->group_membership)
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $departments = Dept::objects();

        return $departments;
    }

}
Module::register('department', 'DepartmentManager');
?>
