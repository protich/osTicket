<?php

class OrganizationManager extends Module {
    var $prologue = 'CLI organization manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import organizations from yaml file',
                'export' => 'Export organizations from the system to CSV or yaml',
                'list' => 'List organizations based on search criteria',
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

          if($options['yaml'])
          {
            //place file into array
            $data = YamlDataParser::load($options['file']);

            //create organizations with a unique name as a new record
            $errors = array();
            foreach ($data as $o) {
                if ('Organization::__create' && is_callable('Organization::__create'))
                    @call_user_func_array('Organization::__create', array($o, &$errors, true));
                // TODO: Add a warning to the success page for errors
                //       found here
                $errors = array();
            }

          }
          elseif($options['csv'])
          {
            if (!$options['file'])
                $this->fail('Import CSV file required!');
            elseif (!($this->stream = fopen($options['file'], 'rb')))
                $this->fail("Unable to open input file [{$options['file']}]");

            //Read the header (if any)
            if (($data = fgetcsv($this->stream, 1000, ","))) {
                if (strcasecmp($data[0], 'name'))
                    fseek($this->stream, 0); // We don't have an header!
                else;
                // TODO: process the header here to figure out the columns
                // for now we're assuming one column of Name
            }

            while (($data = fgetcsv($this->stream, 1000, ",")) !== FALSE) {
                if (!$data[0])
                    $this->stderr->write('Invalid data format: Name
                            required');
                elseif (!Organization::fromVars(array('name' => $data[0], 'email')))
                    $this->stderr->write('Unable to import record: '.print_r($data, true));
            }
          }
          else
          {
            echo 'Please choose import type of --yaml or --csv' . "\n" ;
          }

          break;

        case 'export':
            if ($options['yaml'])
            {
              //get the statuses
              $organizations = self::getQuerySet($options);

              //format the array nicely
              foreach ($organizations as $organization)
              {
                $clean[] = array('name' => $organization->getName(), 'manager' => $organization->getAccountManager(),
                'status' => $organization->get('status'), 'domain' => $organization->get('domain'),
                'extra' => $organization->get('extra'));

              }


              //export yaml file
              // echo Spyc::YAMLDump(array_values($clean), true, false, true);

              if(!file_exists('organization.yaml'))
              {
                $fh = fopen('organization.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('name', 'manager', 'status', 'domain', 'extra'));
              foreach (Organization::objects() as $organization)
                  fputcsv($this->stream,
                          array((string) $organization->getName(), $organization->getAccountManager(), $organization->get('status'), $organization->get('domain'), $organization->get('extra')));
            }

            break;

        case 'list':
            $organizations = $this->getQuerySet($options);

            foreach ($organizations as $O) {
                $this->stdout->write(sprintf(
                    "%d %s <%s> %s %s\n",
                    $O->getName(), $O->getAccountManager(), $O->get('status'), $O->get('domain'), $O->get('extra')
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $organizations = Organization::objects();

        return $organizations;
    }

    static function getIdByName($name) {
        $row = Organization::objects()
            ->filter(array('name'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

}
Module::register('organization', 'OrganizationManager');
?>
