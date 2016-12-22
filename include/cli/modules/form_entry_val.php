<?php

class FormEntryValManager extends Module {
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
                //form_entry_values table
                $clean[] = array('entry_id' => $F->entry_id, 'field_id' => $F->field_id,
                'value' => $F->value, 'value_id' => $F->value_id

                //form_entry table


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

              fputcsv($this->stream, array('EntryID', 'FieldID', 'Value', 'ValueId'));
              foreach (DynamicFormEntryAnswer::objects() as $F)
                  fputcsv($this->stream,
                          array((string) $F->entry_id, $F->field_id, $F->value, $F->value_id));
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
        $form_entry = DynamicFormEntryAnswer::objects();

        return $form_entry;
    }

    static function create($vars, &$error=false, $fetch=false) {
        //see if staff exists
        if ($fetch && ($FevVal=self::getIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value'])))
        {
          var_dump('match');
          return DynamicFormEntryAnswer::lookup($FevVal);
        }
        else
        {
          var_dump('new ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
          // $Fev = DynamicFormEntryAnswer::create($vars);
          // $Fev->save();
          // return $Fev;
        }

        // var_dump('youre passing in ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
        // var_dump('entry id is ' . self::getIdByCombo($vars['entry_id'], $vars['field_id']));

    }

    private function getIdByCombo($entry_id, $field_id,$value)
    {
      $row = DynamicFormEntryAnswer::objects()
          ->filter(array(
            'entry_id'=>$entry_id,
            'field_id'=>$field_id,
            'value'=>$value))
          ->values_flat('value')
          ->first();

      return $row ? $row[0] : 0;
    }
}
Module::register('form_entry_val', 'FormEntryValManager');
?>
