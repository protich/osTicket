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
            $form_entry_values = $D['form_entry_values'];
            $ticket_id = Ticket::getIdByNumber($D['ticket_number']);

            foreach ($form_entry_values as $fev)
            {
              $form_id = self::getFormIdByName($fev['form_name']);
              $entry_id = self::getFormEntryByCombo($form_id, $ticket_id);
              $field_id = self::getFieldIdByCombo($form_id, $fev['field_label'], $fev['field_name']);

              $form_entry_val_import[] = array('entry_id' => $entry_id,
                'field_id' => $field_id,
                'value' => $fev['value']);
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
                  $ticket_id = self::getTicketByFormEntry($form_entry_val->entry_id);
                  $ticket_number = self::getNumberById($ticket_id);
                  $form_id = self::getFormIdById($form_entry_val->field_id);

                  //form entry id
                  $form_entry_vals_clean[] = array('- ticket_number' =>  $ticket_number,'  form_entry_values' => '');

                  //form entry values for ticket
                  array_push($form_entry_vals_clean, array(
                  '    - field_id' => $form_entry_val->field_id, '      field_label' => self::getFieldLabelById($form_entry_val->field_id),
                  '      field_name' => self::getFieldNameById($form_entry_val->field_id), '      form_name' => self::getFormNameById($form_id),
                  '      value' => $form_entry_val->value
                  )
                  );
                }
              }
              unset($form_entry_vals);

              //export yaml file
              echo (Spyc::YAMLDump($form_entry_vals_clean, false, 0));

              // if(!file_exists('form_entry_value.yaml'))
              // {
              //   $fh = fopen('form_entry_value.yaml', 'w');
              //   fwrite($fh, (Spyc::YAMLDump($form_entry_vals_clean, false, 0)));
              //   fclose($fh);
              // }
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
        $FeVal->value_id = TicketPriority::getPriorityByName($vars['value']);
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
          // var_dump('match');
          return DynamicFormEntryAnswer::lookup($FevVal);
        }
        else
        {
          // var_dump('new ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
          $Fev = self::create_form_entry_val($vars);
          $Fev->save();
          return $Fev->entry_id;
        }

    }

    //form entry value (value field)
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

    //object_type
    static function getTypeById($id) {
        $row = DynamicFormEntry::objects()
            ->filter(array('id'=>$id))
            ->values_flat('object_type')
            ->first();

        return $row ? $row[0] : 0;
    }

    //ticket id
    static function getTicketByFormEntry($id) {
        $row = DynamicFormEntry::objects()
            ->filter(array('id'=>$id))
            ->values_flat('object_id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Entry Id
    static function getFormEntryByCombo($form_id, $object_id) {
        $row = DynamicFormEntry::objects()
            ->filter(array('form_id'=>$form_id, 'object_id'=>$object_id))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //ticket Number
    static function getNumberById($id) {
        $row = Ticket::objects()
            ->filter(array('ticket_id'=>$id))
            ->values_flat('number')
            ->first();

        return $row ? $row[0] : 0;
    }

    //field Label
    static function getFieldLabelById($id) {
        $row = DynamicFormField::objects()
            ->filter(array('id'=>$id))
            ->values_flat('label')
            ->first();

        return $row ? $row[0] : 0;
    }

    //field Name
    static function getFieldNameById($id) {
        $row = DynamicFormField::objects()
            ->filter(array('id'=>$id))
            ->values_flat('name')
            ->first();

        return $row ? $row[0] : 0;
    }

    //field Id
    static function getFieldIdByCombo($form_id, $label, $name) {
        $row = DynamicFormField::objects()
            ->filter(array('form_id'=>$form_id, 'label'=>$label, 'name'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Id By Form Entry
    static function getFormIdById($id) {
        $row = DynamicFormField::objects()
            ->filter(array('id'=>$id))
            ->values_flat('form_id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Name
    static function getFormNameById($id) {
        $row = DynamicForm::objects()
            ->filter(array('id'=>$id))
            ->values_flat('title')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Id By Name
    static function getFormIdByName($name) {
        $row = DynamicForm::objects()
            ->filter(array('title'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }
}
Module::register('form_entry_val', 'FormEntryValManager');
?>
