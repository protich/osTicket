<?php

class StaffManager extends Module {
    var $prologue = 'CLI department manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import staff from CSV file',
                'export' => 'Export staff from the system to CSV',
                'list' => 'List staff based on search criteria',
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

          //parse out data for specific tables
          foreach ($data as $D)
          {
            //role id
            $role = Role::getIdByName($D['role_id']);

            //department id
            $department = Dept::getIdByName($D['dept_id']);

            $D['role_id'] = $role;
            $D['dept_id'] = $department;
          }

          //create staff
          $errors = array();
          foreach ($data as $o) {
              if ('Staff::__create' && is_callable('Staff::__create'))
                  @call_user_func_array('Staff::__create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

            break;

        case 'export':
            if ($options['yaml'])
            {
              //get the agents
              $staff = $this->getQuerySet($options);

              //format the array nicely
              foreach ($staff as $S)
              {
                $clean[] = array('dept_id' => $S->getDept(), 'role_id' => $S->getRole(), 'username' => $S->getUserName(), 'firstname' => $S->getFirstName(), 'lastname' => $S->getLastName(),'passwd' => $S->getPasswd(), 'email' => $S->getEmail(), 'permissions' => $S->permissions);

              }

              //export yaml file
              echo (Spyc::YAMLDump($clean));
            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Username', 'First Name', 'Last Name', 'Email', 'Permissions'));
              foreach (Staff::objects() as $staff)
                  fputcsv($this->stream,
                          array((string) $staff->getUserName(), $staff->getFirstName(), $staff->getLastName(), $staff->getEmail(), $staff->permissions));
            }

            break;

        case 'list':
            $staff = $this->getQuerySet($options);

            foreach ($staff as $S) {
                $this->stdout->write(sprintf(
                    "%d %s <%s>%s\n",
                    $S->getUserName(), $S->getFirstName(), $S->getLastName(), $S->getEmail(), $S->permissions
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $staff = Staff::objects();

        return $staff;
    }
}
Module::register('staff', 'StaffManager');
?>
