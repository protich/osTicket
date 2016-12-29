<?php

class TicketManager extends Module {
    var $prologue = 'CLI ticket manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import tickets from YAML file',
                'export' => 'Export tickets from the system to CSV or YAML',
                'list' => 'List tickets based on search criteria',
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

          //processing for tickets
          foreach ($data as $D)
          {
            //remap export values to match the database

            //user id
            $useremail = $D['user_email'];
            $userId = User::getIdByEmail($D['user_email']);
            $D['user_id'] = $userId;

            //status
            $statusId = TicketStatus::getIdByName($D['status_name']);
            $D['status_id'] = $statusId;

            //department
            $deptId = Dept::getIdByName($D['department_name']);
            $D['dept_id'] = $deptId;

            //sla
            $SLAId = SLA::getIdByName($D['sla_name']);
            $D['sla_id'] = $SLAId;

            //topic
            $topicId = Topic::getIdByName($D['topic_name']);
            $D['topic_id'] = $topicId;

            //staff
            $staffId = Staff::getIdByEmail($D['agent_email']);
            $D['staff_id'] = $staffId;

            //priority
            $priorityId = TicketPriority::getPriorityByName($D['priority']);
            $D['priority'] = $priorityId;

            //ticket table
            //for any related id's, look them up from imported data
            $ticket_import[] = array('number' => $D['number'], 'user_id' => $D['user_id'],
            'status_id' => $D['status_id'],
            'dept_id'=> $D['dept_id'], 'sla_id'=> $D['sla_id'], 'topic_id'=> $D['topic_id'],
            'staff_id'=> $D['staff_id'],
            'flags' => $D['flags'], 'ip_address' => $D['ip_address'],
            'source' => $D['source'], 'source_extra' => $D['source_extra'], 'duedate' => $D['duedate'],
            'isoverdue' => $D['isoverdue'], 'isanswered' => $D['isanswered'],
            'est_duedate' => $D['est_duedate'], 'reopened' => $D['reopened'], 'closed' => $D['closed'],
            'lastupdate' => $D['lastupdate']
            );
          }

          //import tickets
          $errors = array();
          //create Tickets
          foreach ($ticket_import as $o)
          {
              if ('self::ticket_create' && is_callable('self::ticket_create'))
                  @call_user_func_array('self::ticket_create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

          //processing for form entries
          foreach ($data as $D)
          {
            $form_entry = $D['form_entry'];

            foreach ($form_entry as $T)
            {
              $form_id = $T['form_id'];
            }

            //object_id
            $object_id = Ticket::getIdByNumber($D['number']);

            //form_entry table
            $form_entry_import[] = array('form_id' => $form_id,
                'object_id' => $object_id, 'object_type' => 'T');

          }

          //import form_entries
          $errors = array();
          foreach ($form_entry_import as $o)
          {
              if ('self::form_entry_create' && is_callable('self::form_entry_create'))
                  @call_user_func_array('self::form_entry_create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

          // //processing for form entry values
          foreach ($data as $D)
          {
             // //pull out individual form entries
             $form_entry = $D['form_entry'];
             foreach ($form_entry as $T)
             {
               if($T['form_id'] != null)
               {
                 $form_id = $T['form_id'];
                 //used to get entry_id
                 $object_id = Ticket::getIdByNumber($D['number']);
               }

               $form_entry_vals = $T['form_entry_values'];
             }

             //pull out individual form entry values
             foreach ($form_entry_vals as $V)
             {
               if($V['field_id'] != null)
               {
                 //form_entry_values
                 $form_entry_values_import[] = array('entry_id' => self::getIdByCombo($form_id, $object_id),
                    'field_id' => $V['field_id'], 'value' => $V['value']);
               }

             }

          }

          //import form_entry_values
          $errors = array();
          foreach ($form_entry_values_import as $o)
          {
              if ('self::form_entry_val_create' && is_callable('self::form_entry_val_create'))
                  @call_user_func_array('self::form_entry_val_create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

            break;

        case 'export':
            if ($options['yaml'])
            {
              //get the tickets
              $tickets = self::getQuerySet($options);

              //format the array nicely
              foreach ($tickets as $ticket)
              {

                //vars for related objects
                $user = $ticket->getUser();
                $userEmail = $user->getDefaultEmail();

                $org = $user->getOrgId();
                $orgname = Organization::lookup($org);

                $slaId = $ticket->getSLAId();
                $sla_name = SLA::getSLAName($slaId);
                $sla_prefix = trim(substr($sla_name, 0, strpos($sla_name, '(')));
                $grace_period = strstr($sla_name, '(');

                //topic name
                $topicId = $ticket->getTopicId();
                $topicName = Topic::getNameById($topicId);

                //agent email
                $agentId = $ticket->getStaffId();
                $agentEmail = Staff::getEmailById($agentId);

                if($agentId == null)
                {
                  $agentId = 0;
                }

                //get count of form entry vals per form entry
                $entry_id = self::getFVEntryId($ticket->ticket_id);
                $entries_split = explode(",", $entry_id);

                //store form entry vals into one line
                for ($i=0; $i < count($entries_split); $i++)
                {
                  $field_id_clean = '';
                  //field ids
                  $field_id = self::getFieldId($ticket->ticket_id);
                  //parse field ids
                  $split_field_id = explode(",", $field_id);
                  //set text for yaml
                  foreach ($split_field_id as $E)
                  {
                    //var_dump('\'field_id\' => ' .  $E);
                    $field_id_clean .= '\'field_id\' => ' .  $E . ', ';
                    //var_dump($field_id_clean);
                  }

                  //values
                  $value_clean = '';
                  $value = self::getFieldValue($ticket->ticket_id);
                  //parse values
                  $split_value = explode(",", $value);
                  //var_dump('count split vals ' . count($split_value));
                  //set text for yaml
                  foreach ($split_value as $S)
                  {
                    if($S == null || $S == '')
                    {
                      //var_dump('its null');
                      $S = 'null';
                    }
                    else
                    {
                      //var_dump($S);
                    }
                    //var_dump('\'value\' => ' .  $S);
                    $value_clean .= '\'value\' => ' .  $S . ', ';
                  }
                }



                //array to store the export
                $clean[] = array(
                //ticket specific fields
                'number' => $ticket->getNumber(), 'user_id' => $ticket->getUserId(),
                'status_id' => $ticket->getStatusId(),
                'dept_id'=> $ticket->getDeptId(), 'sla_id'=> $ticket->getSLAId(), 'topic_id'=> $ticket->getTopicID(),
                'staff_id'=> $agentId,
                'lock_id'=> $ticket->getLockId(), 'flags' => $ticket->flags, 'ip_address' => $ticket->getIP(),
                'source' => $ticket->getSource(), 'source_extra' => $ticket->source_extra, 'duedate' => $ticket->getDueDate(),
                'isoverdue' => boolval($ticket->isoverdue), 'isanswered' => boolval($ticket->isanswered),
                'est_duedate' => $ticket->getEstDueDate(), 'reopened' => $ticket->getReopenDate(), 'closed' => $ticket->getCloseDate(),
                'lastupdate' => $ticket->getEffectiveDate(),


                //related object fields
                'status_name' => $ticket->getStatus(), 'priority' => $ticket->getPriority(), 'department_name' => $ticket->getDeptName(),
                'user_name' => $ticket->getName(), 'user_email' => $userEmail, 'organization' => $orgname, 'sla_name' => $sla_prefix,
                'topic_name' => $topicName, 'agent_email' =>  $agentEmail, 'grace_period' => 75, 'subject' => $ticket->getSubject(),

                // 'form_entry' => array('form_entry_id' => self::getFormEntryId($ticket->ticket_id), 'form_id' => self::getFormId(self::getFormEntryId($ticket->ticket_id)),
                //                 'form_entry_values' => array('field_id' => self::getFieldId($ticket->ticket_id),
                //                                              'value' => self::getFieldValue($ticket->ticket_id))

                'form_entry' => array('- form_entry_id' => self::getFormEntryId($ticket->ticket_id), '  form_id' => self::getFormId(self::getFormEntryId($ticket->ticket_id)),
                                '  form_entry_values' => array(rtrim($field_id_clean, ', '), rtrim($value_clean, ', '))
                )
                //'entries' => self::getFormEntryId($ticket->ticket_id)

                //it's doing this:
                //'entries' => "array('id' => 10,'form_id' => 7),array('id' => 9,'form_id' => 2)"

                //it should be doing this
                // 'form_entry' => array('id' => 23,'form_id' => 2),
                //                 array('id' => 9, 'form_id' => 7)



                // 'form_entry_id' => self::getFormEntryId($ticket->ticket_id),
                //'form_id' => self::getFormId($ticket->ticket_id),
                // 'field_id' => self::getFieldId($ticket->ticket_id),
                // 'value' => self::getFieldValue($ticket->ticket_id)
              );

               }

              //export yaml file
              echo Spyc::YAMLDump(array_values($clean), true, false, true);

            //export directly to yaml file
            //   if(!file_exists('ticket.yaml'))
            //   {
            //     $fh = fopen('ticket.yaml', 'w');
            //     fwrite($fh, (Spyc::YAMLDump($clean)));
            //     fclose($fh);
            //   }
            
            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('number', 'user_id', 'status_id', 'dept_id', 'sla_id', 'topic_id', 'lock_id'));
              foreach (Ticket::objects() as $ticket)
                  fputcsv($this->stream,
                          array((string) $ticket->getNumber(), $ticket->getUserId(), $ticket->getStatusId(), $ticket->getDeptId(),
                            $ticket->getSLAId(), $ticket->getTopicID(), $ticket->getLockId()
                         ));
            }

            break;

        case 'list':
            $tickets = $this->getQuerySet($options);

            foreach ($tickets as $T) {
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
        $tickets = Ticket::objects();

        return $tickets;
    }

    //adriane
    static function create_ticket($vars=array())
    {
      $ticket = new Ticket($vars);

      $ticket->created = new SqlFunction('NOW');

      //return the ticket
      return $ticket;

    }

    static function create_form_entry_val($vars=array())
    {
      $FeVal = new DynamicFormEntryAnswer($vars);

      //return the form entry value
      return $FeVal;

    }

    static function form_entry_create($vars, &$error=false, $fetch=false) {
        var_dump('form');
        //see if form entry exists
        if ($fetch && ($FeId=self::getIdByCombo($vars['form_id'], $vars['object_id'])) || $vars['form_id']  == null)
        {
          //var_dump('match');
          return DynamicFormEntry::lookup($FeId);
        }
        else
        {
          var_dump('new + ticket id is ' . $vars['object_id']);
          $Fe = DynamicFormEntry::create($vars, '', true);
          $Fe->save();
          return $Fe->id;
        }

        // var_dump('youre passing in ' . $vars['form_id'] . ' and ' .  $vars['object_id']);
        // var_dump('entry id is ' . self::getIdByCombo($vars['form_id'], $vars['object_id']));

      }

      static function form_entry_val_create($vars, &$error=false, $fetch=false)
      {

          $FevVal = self::getValIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value']);
          //see if form entry val exists
          if ($fetch && ($FevVal != '0'))
          {
            //var_dump('match');
            return DynamicFormEntryAnswer::lookup($FevVal);
          }
          else
          {
            var_dump('new ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
            $Fev = self::create_form_entry_val($vars);
            $Fev->save();
            return $Fev->entry_id;
          }



          //var_dump('youre passing in ' . $vars['entry_id'] . ', ' . $vars['field_id'] . ', and ' .  $vars['value']);
          //var_dump('val is ' . self::getValIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value']));
          // $FevVal = self::getValIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value']);
          // if($FevVal == '0')
          // {
          //   var_dump('its 0');
          //
          // }
          // else
          // {
          //   var_dump('its valid');
          // }
      }

    //adriane
    static function ticket_create($vars, &$errors=array(), $fetch=false) {
      var_dump('ticket');
        //see if ticket exists
        if ($fetch && ($ticketId=Ticket::getIdByNumber($vars['number'])))
        {
          //var_dump('found match');
          return Ticket::lookup($ticketId);
        }
        //otherwise create new ticket
        else
        {
          var_dump('new ');

          $ticket = self::create_ticket($vars);

          var_dump('error count 2 is ' . count($errors));

          // Add dynamic data (if any)
          if ($vars['fields'])
          {
              $ticket->save(true);
              $ticket->addDynamicData($vars['fields']);
          }
          //otherwise create without dynamic fields
          else
          {
            $ticket->save();
          }

          return $ticket->ticket_id;
        }

        // $arrayin = $vars['number'];
        //
        // var_dump('youre passing in ' . $vars['number']);
        // var_dump('ticket id is ' . Ticket::getIdByNumber($vars['number']));


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

    private function getValIdByCombo($entry_id, $field_id,$value)
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


    private function getFormEntryId($ticket_id)
    {
      $row = DynamicFormEntry::objects()
          ->filter(array(
            'object_type'=>'T',
            'object_id'=>$ticket_id))
          ->values_flat('id');

      //var_dump('counts is ' . count($row));

      if(count($row) != 0)
      {
        for ($i=0; $i<count($row); $i++)
        {
          $form_ids .= implode(',', $row[$i]) . ',';
          //$form_ids = $row[$i];
          //array_push($form_ids, $row[$i]);
          //var_dump('all rows are ' . $form_ids);
        }

      //   //parse form entry id
      //   $entries = explode(",", $form_ids);
       //
      //   //var_dump('entry count ' . count($entries));
       //
      //   // if(count($entries) > 1)
      //   // {
      //   //   var_dump('greater');
      //   // }
      //   foreach ($entries as $F)
      //   {
      //     //var_dump('id is ' . $F);
      //     if($F)
      //     {
      //       //var_dump('id is ' . $F);
      //       //var_dump('form id is ' . self::getFormId($F));
      //       //'entries' => array('id' => 23,'form_id' => 2)
       //
      //       //$form_ids = $F;
      //       //$clean_entry .= ($F . ',');
      //       //$clean_entry .= ('array(\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F) . '),');
      //       $clean_entry .= (array('\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F) . '),'));
       //
      //       //$form_ids = array('\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F));
      //       //$form_ids = ('array(\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F) . '),');
       //
      //       //var_dump('form ids count is ' . count($form_ids));
      //       //var_dump('form ids is ' . $form_ids);
       //
       //
      //       // foreach ($form_ids as $A)
      //       // {
      //       //   var_dump($A);
      //       // }
      //     }
       //
      //   }
       //
        }
       //
      //  return rtrim($clean_entry, ',');
      //  return rtrim('. ' . $form_ids, ',');
      return rtrim($form_ids, ',');

    }

    private function getFormId($form_entry_id)
    {
      //$form_entry_id = self::getFormEntryId($ticket_id);

      //parse form entry id
      $entries = explode(",", $form_entry_id);

      //pass entries in to get field id
      foreach ($entries as $E)
      {
        $row = DynamicFormEntry::objects()
            ->filter(array(
              'id'=>$form_entry_id))
            ->values_flat('form_id')
            ->first();
      }

       return $row ? $row[0] : 0;

    }

    private function getFieldId($ticket_id)
    {
      $form_entry_id = self::getFormEntryId($ticket_id);

      //parse form entry id
      $entries = explode(",", $form_entry_id);


      //pass entries in to get field id
      foreach ($entries as $E)
      {
        //var_dump('passing in ' . $E);
        $prev .= $E;
        $row = DynamicFormEntryAnswer::objects()
            ->filter(array(
              'entry_id'=>$E))
            ->values_flat('field_id');

        //store field ids in a string
        foreach ($row as $R)
        {
          $field_ids .= implode(',', $R) . ',';
        }
       }

       //return the field ids
       return rtrim($field_ids, ',');

    }

    private function getFVEntryId($ticket_id)
    {
      $form_entry_id = self::getFormEntryId($ticket_id);

      //parse form entry id
      $entries = explode(",", $form_entry_id);


      //pass entries in to get field id
      foreach ($entries as $E)
      {
        //var_dump('passing in ' . $E);
        $prev .= $E;
        $row = DynamicFormEntryAnswer::objects()
            ->filter(array(
              'entry_id'=>$E))
            ->values_flat('entry_id');

        //store field ids in a string
        foreach ($row as $R)
        {
          $field_ids .= implode(',', $R) . ',';
        }
       }

       //return the field ids
       return rtrim($field_ids, ',');

    }

    private function getUniqueFieldId($ticket_id)
    {
      $form_entry_id = self::getFormEntryId($ticket_id);

      //parse form entry id
      $entries = explode(",", $form_entry_id);


      //pass entries in to get field id
      foreach ($entries as $E)
      {
        $prev .= $E;
        $row = DynamicFormEntryAnswer::objects()
            ->filter(array(
              'entry_id'=>$E))
            ->values_flat('field_id');


        //store unique field ids in a string
        foreach ($row as $R)
        {
          if($prev != $E)
          {
            $field_ids .= implode(',', $R) . ',';
          }

        }
       }

       //return the field ids
       return rtrim($field_ids, ',');

    }

    private function getFieldValue($ticket_id)
    {
      $form_entry_id = self::getFormEntryId($ticket_id);

      //parse form entry id
      $entries = explode(",", $form_entry_id);

      //pass entries in to get field value
      foreach ($entries as $E)
      {
        //var_dump('passing in ' . $E);
        $row = DynamicFormEntryAnswer::objects()
            ->filter(array(
              'entry_id'=>$E))
            ->values_flat('value');
      }


      //store field vals in a string
      if(count($row) != 0)
      {
        for ($i=0; $i<count($row); $i++)
        {
            $field_vals .= implode(',', $row[$i]) . ',';
        }
       }

       //return the field vals
       return rtrim($field_vals, ',');

    }

}
Module::register('ticket', 'TicketManager');
?>
