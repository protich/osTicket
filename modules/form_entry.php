<?php

class FormEntryManager extends Module {
    var $prologue = 'CLI Form Entry manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import form entries from YAM: file',
                'export' => 'Export form entries from the system to CSV or YAML',
                'list' => 'List form entries based on search criteria',
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

          if (!$options['file'] || $options['file'] == '-')
              $options['file'] = 'php://stdin';
          if (!($this->stream = fopen($options['file'], 'rb')))
              $this->fail("Unable to open input file [{$options['file']}]");

          //place file into array
          $data = YamlDataParser::load($options['file']);

          //create emails with a unique name as a new record
          $errors = array();
          foreach ($data as $o) {
              if ('self::create' && is_callable('self::create'))
                  @call_user_func_array('self::create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }
          break;

        case 'export':
            if ($options['yaml'])
            {
              //get the form entries
              $form_entry = $this->getQuerySet($options);

              $clean = array();

              //format the array nicely
              foreach ($form_entry as $F)
              {
                //form_entry table
                $clean[] = array('id' => $F->getId(), 'form_id' => $F->form_id,
                'object_id' => $F->object_id, 'object_type' => $F->object_type,
                'sort' => $F->sort, 'extra' => $F->extra,

                //form_entry_values table
                'form_title' => $F->getTitle()
              );
              }

              //export yaml file
              echo (Spyc::YAMLDump($clean));

              //var_dump($clean);
            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('ID', 'Form ID', 'ObjectID', 'Object Type', 'Sort', 'Extra'));
              foreach (DynamicFormEntry::objects() as $F)
                  fputcsv($this->stream,
                          array((string) $F->getId(), $F->form_id, $F->object_id, $F->object_type, $F->sort, $F->extra));
            }


            break;

        case 'list':
            $form_entry = $this->getQuerySet($options);

            foreach ($form_entry as $F) {
                $this->stdout->write(sprintf(
                    "%d %s \n",
                    $F->getId(), $F->form_id
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $form_entry = DynamicFormEntry::objects();

        return $form_entry;
    }

    static function create($vars, &$error=false, $fetch=false) {
        //see if form entry exists
        if ($fetch && ($FeId=self::getIdByCombo($vars['form_id'], $vars['object_id'])))
        {
          var_dump('match');
          return DynamicFormEntry::lookup($FeId);
        }
        else
        {
          var_dump('new');
          $Fe = DynamicFormEntry::create($vars);
          $Fe->save();
          return $Fe;
        }

        // var_dump('youre passing in ' . $vars['form_id'] . ' ' .  $vars['object_id']);
        // var_dump('entry id is ' . self::getIdByCombo($vars['form_id'], $vars['object_id']));

    }

    private function getIdByCombo($form_id, $object_id)
    {
      $row = DynamicFormEntry::objects()
          ->filter(array(
            'form_id'=>$form_id,
            'object_id'=>$object_id))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }
}
Module::register('form_entry', 'FormEntryManager');
?>
