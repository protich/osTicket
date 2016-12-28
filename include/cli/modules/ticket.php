<?php

class TicketManager extends Module {
    var $prologue = 'CLI ticket manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import tickets from CSV file',
                'export' => 'Export tickets from the system to CSV',
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

          //parse out data for specific tables
          foreach ($data as $D)
          {
            //lookup related fields

            //user id
            $useremail = $D['user_email'];
            $userId = User::getIdByEmail($D['user_email']);
            //var_dump('user id is ' . $userId);
            $D['user_id'] = $userId;
            //var_dump('user id is ' . $D['user_id']);

            //status
            $statusId = TicketStatus::getIdByName($D['status_name']);
            //var_dump('status id is ' . $statusId);
            $D['status_id'] = $statusId;
            //var_dump('status id is ' . $D['status_id']);

            //department
            $deptId = Dept::getIdByName($D['department_name']);
            //var_dump('dept id is ' . $deptId);
            $D['dept_id'] = $deptId;
            //var_dump('dept id is ' . $D['dept_id']);

            //sla
            $SLAId = SLA::getIdByName($D['sla_name']);
            //var_dump('sla id is ' . $SLAId);
            $D['sla_id'] = $SLAId;
            //var_dump('sla id is ' . $D['sla_id']);

            //topic
            $topicId = Topic::getIdByName($D['topic_name']);
            //var_dump('topic id is ' . $topicId);
            $D['topic_id'] = $topicId;
            //var_dump('topic id is ' . $D['topic_id']);

            //staff
            $staffId = Staff::getIdByEmail($D['agent_email']);
            //var_dump('staff is ' . $staffId);
            $D['staff_id'] = $staffId;
            //var_dump('staff id is ' . $D['staff_id']);

            //priority
            $priorityId = TicketPriority::getPriorityByName($D['priority']);
            //var_dump('priority id is ' . $priorityId);
            $D['priority'] = $priorityId;
            //var_dump('priority id is ' . $D['priority']);

            //object_id
            $object_id = Ticket::getIdByNumber($D['number']);

            //pull out individual form ids
            $form_entry = $D['form_entry'];
            foreach ($form_entry as $T)
            {
              $form_id = $T['form_id'];
              $form_entry_vals = $T['form_entry_values'];
            }

            //pull out individual form entry values
            foreach ($form_entry_vals as $V)
            {
              //var_dump('field id is ' . $V['field_id'] . ' val is ' . $V['value']);
              $field_id = $V['field_id'];
              $value = $V['value'];

            }

            //form entry id
            $form_entry_id = self::getIdByCombo($form_id, $object_id);

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

            //form_entry table
            $form_entry_import[] = array('form_id' => $form_id,
            'object_id' => $object_id, 'object_type' => 'T'
            );

            //form_entry_values
            $form_entry_values_import[] = array('entry_id' => $form_entry_id,
            'field_id' => $field_id, 'value' => $value
            );


            //ticket table
            //for any related id's, look them up from imported data.
            // $ticket_import[] = array('number' => $D['number'], 'userId' => $D['user_id'],
            // 'statusId' => $D['status_id'],'deptId'=> $D['dept_id'],
            // 'slaId'=> $D['sla_id'], 'topicId'=> $D['topic_id'],
            // 'staffId'=> $D['staff_id'], 'ip' => $D['ip_address'], 'source' => $D['source'],
            // 'time' => $D['est_duedate'], 'priorityId' => $D['priority']);

          }

          //import tickets
          // $errors = array();
          // //create Tickets
          // foreach ($ticket_import as $o) {
          //     if ('self::ticket_create' && is_callable('self::ticket_create'))
          //         @call_user_func_array('self::ticket_create', array($o, &$errors, true));
          //     // TODO: Add a warning to the success page for errors
          //     //       found here
          //     $errors = array();
          // }

          //import form_entries
          // $errors = array();
          // foreach ($form_entry_import as $o) {
          //     if ('self::form_entry_create' && is_callable('self::form_entry_create'))
          //         @call_user_func_array('self::form_entry_create', array($o, &$errors, true));
          //     // TODO: Add a warning to the success page for errors
          //     //       found here
          //     $errors = array();
          // }

          //import form_entry_values
          $errors = array();
          foreach ($form_entry_values_import as $o) {
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

                $entries = array('10, 9');

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
                //'entries' => array('id' => self::getFormEntryId($ticket->ticket_id), 'form_id' => self::getFormId(self::getFormEntryId($ticket->ticket_id)))
                'entries' => self::getFormEntryId($ticket->ticket_id)

                //it's doing this:
                //'entries' => "array('id' => 10,'form_id' => 7),array('id' => 9,'form_id' => 2)"

                //it should be doing this
                // 'entries' => array('id' => 23,'form_id' => 2),
                //              array('id' => 9, 'form_id' => 7)





                // 'form_entry_id' => self::getFormEntryId($ticket->ticket_id),
                // 'form_id' => self::getFormId($ticket->ticket_id),
                // 'field_id' => self::getFieldId($ticket->ticket_id),
                // 'value' => self::getFieldValue($ticket->ticket_id)

              );

              // $test = self::getFormEntryId($ticket->ticket_id);
              // var_dump($test);
              // str_replace('"', "", $test);
              // var_dump($test);
              //
              // //parse form entry id
              // $entries = explode(",", $test);
              //
              // var_dump('entries count ' . count($entries));



               }

              //export yaml file
              echo Spyc::YAMLDump(array_values($clean), true, false, true);

            //   if(!file_exists('ticket.yaml'))
            //   {
            //     $fh = fopen('ticket.yaml', 'w');
            //     fwrite($fh, (Spyc::YAMLDump($clean)));
            //     fclose($fh);
            //   }
            //
            }
            // else
            // {
            //   $stream = $options['file'] ?: 'php://stdout';
            //   if (!($this->stream = fopen($stream, 'c')))
            //       $this->fail("Unable to open output file [{$options['file']}]");
            //
            //   fputcsv($this->stream, array('Name', 'Signature', 'ispublic', 'group_membership'));
            //   foreach (Ticket::objects() as $ticket)
            //       fputcsv($this->stream,
            //               array((string) $ticket->getName(), $ticket->getSignature(), boolval($ticket->ispublic), boolval($ticket->group_membership)));
            //
            // }

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
    // static function create2($vars=array())
    // {
    //   $ticket = new static($vars);
    //   //$ticket->created = SqlFunction::NOW();
    //   $ticket->created = new SqlFunction('NOW');
    //   //return the ticket id
    //   //adriane
    //   return $ticket;
    //
    // }

    static function form_entry_create($vars, &$error=false, $fetch=false) {
        //see if form entry exists
        if ($fetch && ($FeId=self::getIdByCombo($vars['form_id'], $vars['object_id'])) || $vars['form_id']  == null)
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

        // var_dump('youre passing in ' . $vars['form_id'] . ' and ' .  $vars['object_id']);
        // var_dump('entry id is ' . self::getIdByCombo($vars['form_id'], $vars['object_id']));

      }

      static function form_entry_val_create($vars, &$error=false, $fetch=false) {
        $FevVal = self::getValIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value']);
        var_dump('fevval is ' . $FevVal . ' and ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
        if($FevVal == '')
        {
          var_dump('its null');
        }
        elseif($FevVal == 0)
        {
          var_dump('its 0');
        }
        else {
          var_dump('its full');
        }
          //see if form entry val exists
          // if ($fetch && ($FevVal = self::getValIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value'])) || $FevVal == '' || $vars['field_id']  == null)
          // {
          //   var_dump('match');
          //   return DynamicFormEntryAnswer::lookup($FevVal);
          // }
          // else
          // {
          //   var_dump('new ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
          //   // $Fev = DynamicFormEntryAnswer::create($vars);
          //   // $Fev->save();
          //   return $Fev;
          // }

          //var_dump('youre passing in ' . $vars['entry_id'] . ', ' . $vars['field_id'] . ', and ' .  $vars['value']);
          //var_dump('entry id is ' . self::getIdByCombo($vars['entry_id'], $vars['field_id']));

      }

    //adriane
    static function ticket_create($vars, &$errors=array(), $fetch=false) {
        //see if ticket exists
        if ($fetch && ($ticketId=Ticket::getIdByNumber($vars['number'])))
        {
          var_dump('found match');
          return Ticket::lookup($ticketId);
        }
        //otherwise create new ticket
        else
        {
          var_dump('new ');

          $ticket = new Ticket();
          $ticket = Ticket::create2($vars);

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

        //parse form entry id
        $entries = explode(",", $form_ids);

        //var_dump('entry count ' . count($entries));

        // if(count($entries) > 1)
        // {
        //   var_dump('greater');
        // }
        foreach ($entries as $F)
        {
          //var_dump('id is ' . $F);
          if($F)
          {
            //var_dump('id is ' . $F);
            //var_dump('form id is ' . self::getFormId($F));
            //'entries' => array('id' => 23,'form_id' => 2)

            //$form_ids = $F;
            //$clean_entry .= ($F . ',');
            //$clean_entry .= ('array(\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F) . '),');
            $clean_entry .= (array('\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F) . '),'));

            //$form_ids = array('\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F));
            //$form_ids = ('array(\'id\' => ' . $F . ',\'form_id\' => ' . self::getFormId($F) . '),');

            //var_dump('form ids count is ' . count($form_ids));
            //var_dump('form ids is ' . $form_ids);


            // foreach ($form_ids as $A)
            // {
            //   var_dump($A);
            // }
          }

        }

       }

       return rtrim($clean_entry, ',');
       //return $form_ids;

    }

    private function getFormId($form_entry_id)
    {
      //$form_entry_id = self::getFormEntryId($ticket_id);

      //parse form entry id
      //$entries = explode(",", $form_entry_id);

      //pass entries in to get field id
      // foreach ($entries as $E)
      // {
        $row = DynamicFormEntry::objects()
            ->filter(array(
              'id'=>$form_entry_id))
            ->values_flat('form_id')
            ->first();
        //}

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
