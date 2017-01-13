<?php

//adriane
class TicketPriority extends VerySimpleModel {
    static $meta = array(
        'table' => TICKET_PRIORITY_TABLE,
        'pk' => array('priority_id'),
        'joins' => array(
            'cdata' => array(
                'constraint' => array('priority_id' => 'TicketCData.priority'),
            ),
        ),
    );


    //adriane
    function getPriorityByName($name) {
      var_dump('made it in');
        $row = static::objects()
            ->filter(array('priority'=>$name))
            ->values_flat('priority_id')
            ->first();

        return $row ? $row[0] : 0;
    }
}

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

          //processing for form entry values
          foreach ($data as $D)
          {
            $entry_id = $D['entry_id'];
            $form_entry_values = $D['form_entry_values'];

            foreach ($form_entry_values as $form_entry_value)
            {
              $form_entry_val_import[] = array('entry_id' => $entry_id, 'field_id' => $form_entry_value['field_id'], 'value' => $form_entry_value['value']);
            }

          }

          //import form entry values
          $errors = array();
          foreach ($form_entry_val_import as $o) {
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
              //get the form entry values
              $form_entry_vals = $this->getQuerySet($options);

              //prepare form entry vals for yaml file
              foreach ($form_entry_vals as $form_entry_val)
              {
                $object_type = self::getTypeById($form_entry_val->entry_id);
                if($object_type == 'T')
                {
                  //form entry id
                  $form_entry_vals_clean[] = array('- entry_id' => $form_entry_val->entry_id, '  form_entry_values' => '');

                  //form entry values for ticket
                  array_push($form_entry_vals_clean, array(
                  '    - field_id' => $form_entry_val->field_id, '      value' => $form_entry_val->value
                  )
                  );
                }
              }
              unset($form_entry_vals);

              //export yaml file
              // echo (Spyc::YAMLDump($form_entry_vals_clean));

              if(!file_exists('form_entry_value.yaml'))
              {
                $fh = fopen('form_entry_value.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($form_entry_vals_clean)));
                fclose($fh);
              }
              unset($form_entry_vals_clean);
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

    static function create_form_entry_val($vars=array())
    {
      $FeVal = new DynamicFormEntryAnswer($vars);

      //if the entry value is for priority, set value_id
      if ($vars['field_id'] == 22)
      {
        var_dump('passing in ' . $vars['value']);
        $FeVal->value_id = TicketPriority::getPriorityByName($vars['value']);
        var_dump('val id ' . $FeVal->value_id);
      }

      //return the form entry value
      return $FeVal;

    }

    static function create($vars, &$error=false, $fetch=false)
    {

        $FevVal = self::getIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value']);
        //see if form entry val exists
        if ($fetch && ($FevVal != '0'))
        {
          var_dump('match');
          return DynamicFormEntryAnswer::lookup($FevVal);
        }
        else
        {
          var_dump('new ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
          $Fev = self::create_form_entry_val($vars);
          $Fev->save();
          return $Fev->entry_id;
        }

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

    //ticket Number
    static function getTypeById($id) {
        $row = DynamicFormEntry::objects()
            ->filter(array('id'=>$id))
            ->values_flat('object_type')
            ->first();

        return $row ? $row[0] : 0;
    }
}
Module::register('form_entry_val', 'FormEntryValManager');
?>
