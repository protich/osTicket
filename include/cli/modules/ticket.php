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
            $userId = self::getIdByEmail($D['user_email']);
            $D['user_id'] = $userId;

            //status
            $statusId = self::getIdByName($D['status_name']);
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

          //processing for threads
          foreach ($data as $D)
          {
            $thread = $D['thread'];

            foreach ($thread as $T)
            {
              $extra = $T['extra'];
              $lastresponse = $T['lastresponse'];
              $lastmessage = $T['lastmessage'];
            }

            //object_id
            $object_id = Ticket::getIdByNumber($D['number']);

            //form_entry table
            $thread_import[] = array('object_id' => $object_id, 'object_type' => 'T',
                'extra' => $extra, 'lastresponse' => 'T', 'lastmessage' => $lastmessage);

          }

          //import threads
          $errors = array();
          foreach ($thread_import as $o) {
              if ('self::thread_create' && is_callable('self::thread_create'))
                  @call_user_func_array('self::thread_create', array($o, &$errors, true));
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


            break;

        case 'export':
            if ($options['yaml'])
            {
              //get the tickets
              $tickets = self::getQuerySet($options);

              //get form entries
              $form_entries = self::getQuerySet_formentry($options);

              //get form entry values
              $form_entry_vals = self::getQuerySet_formentry_vals($options);

              //get the threads
              $threads = self::getQuerySet_thread($options);

              //get the thread entries
              $thread_entries = self::getQuerySet_threadentry($options);

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
                $topicName = self::getNameById($topicId);

                //agent email
                $agentId = $ticket->getStaffId();
                $agentEmail = self::getEmailById($agentId);

                if($agentId == null)
                {
                  $agentId = 0;
                }

                //array to store the export
                $clean[] = array(
                //ticket specific fields
                'number' => $ticket->getNumber(), 'user_id' => $ticket->getUserId(),
                'status_id' => $ticket->getStatusId(),
                'dept_id'=> $ticket->getDeptId(), 'sla_id'=> $ticket->getSLAId(), 'topic_id'=> $ticket->getTopicID(),
                'staff_id'=> $agentId,
                'lock_id'=> $ticket->get('lock_id'), 'flags' => $ticket->flags, 'ip_address' => $ticket->getIP(),
                'source' => $ticket->getSource(), 'source_extra' => $ticket->source_extra, 'duedate' => $ticket->getDueDate(),
                'isoverdue' => boolval($ticket->isoverdue), 'isanswered' => boolval($ticket->isanswered),
                'est_duedate' => $ticket->getEstDueDate(), 'reopened' => $ticket->getReopenDate(), 'closed' => $ticket->getCloseDate(),
                'lastupdate' => $ticket->getEffectiveDate(),

                //related object fields
                'status_name' => $ticket->getStatus(), 'priority' => $ticket->getPriority(), 'department_name' => $ticket->getDeptName(),
                'user_name' => $ticket->getName(), 'user_email' => $userEmail, 'organization' => $orgname, 'sla_name' => $sla_prefix,
                'topic_name' => $topicName, 'agent_email' =>  $agentEmail, 'grace_period' => 75, 'subject' => $ticket->getSubject(),

                'form_entry' => array('- form_entry_id' => self::getFormEntryId($ticket->ticket_id), '  form_id' => self::getFormId(self::getFormEntryId($ticket->ticket_id)),
                              ),

                 'thread' => array('- object_id' => $ticket->getId(),
                                   '  object_type' => $ticket->thread->object_type, '  extra' => $ticket->thread->extra,
                                    '  lastresponse' => $ticket->getLastResponseDate(), '  lastmessage' => $ticket->getLastMessageDate())
                );

                //array for thread import
                foreach ($threads as $thread)
                {
                  if($thread->getObjectId() == $ticket->ticket_id && $thread->object_type == 'T')
                  {
                    $thread_clean[] = array('ticket_num' => $ticket->getNumber(), 'id' => $thread->id, 'object_id' => $thread->getObjectId(), 'object_type' => $thread->getObjectType(),
                    'extra' => $thread->get('extra'), 'lastresponse' => $thread->get('lastresponse'),
                    'lastmessage' => $thread->get('lastmessage'));
                  }

                }

                //array for form import
                foreach ($form_entries as $form_entry)
                {
                  if($form_entry->object_id == $ticket->ticket_id && $form_entry->object_type == 'T')
                  {
                    $form_entry_clean[] = array('form_entry_id' => $form_entry->id, 'form_id' => $form_entry->form_id,
                      'object_type' => 'T', 'sort' => 0, 'extra' => '{"disable":[]}'
                    );
                  }
                }

            } //end data foreach

            //prepare thread entry yaml array
            for ($i=0; $i <count($thread_clean); $i++)
            {
              //ticket number
              $thread_entries_clean[] = array('- ticket' => $thread_clean[$i]['ticket_num'], '  thread_entry' => '');
              foreach ($thread_entries as $thread_entry)
              {

                //thread entries for ticket
                if($thread_clean[$i]['object_type'] == 'T' && $thread_entry->thread_id == $thread_clean[$i]['id'])
                {
                  array_push($thread_entries_clean, array(
                  '    - thread_id' => $thread_entry->getThreadId(), '      object_id' => $thread->object_id,  '      pid' => $thread_entry->getPid(),
                  '      staff_id' => $thread_entry->getStaffId(),'      user_id' => $thread_entry->getUserId(),
                  '      type' => $thread_entry->getType(), '      flags' => $thread_entry->get('flags'),
                  '      poster' => $thread_entry->getPoster(), '      editor' => $thread_entry->getEditor(),
                  '      editor_type' => $thread_entry->get('editor_type'), '      source' => $thread_entry->getSource(),
                  '      title' => $thread_entry->getTitle(), '      body' => $thread_entry->getBody(),
                  '      format' => $thread_entry->get('format'), '      ip_address' => $thread_entry->get('ip_address'),
                  '      created' => $thread_entry->get('created')
                  )
                  );
                }
              }
            }

            //prepare form entry vals for yaml file
            for ($i=0; $i <count($form_entry_clean); $i++)
            {
              //form entry id
              $form_entry_vals_clean[] = array('- entry_id' => $form_entry_clean[$i]['form_entry_id'], '  form_entry_values' => '');
              foreach ($form_entry_vals as $form_entry_val)
              {
                //var_dump('object type ' . $form_entry_clean[$i]['object_type'] . ' id ' . $form_entry_clean[$i]['form_entry_id']);
                //form entry values for ticket
                if($form_entry_clean[$i]['object_type'] == 'T' && $form_entry_val->entry_id == $form_entry_clean[$i]['form_entry_id'])
                {
                  array_push($form_entry_vals_clean, array(
                  '    - field_id' => $form_entry_val->field_id, '      value' => $form_entry_val->value
                  )
                  );
                }
              }
            }

              //export yaml files
              //ticket, form entry, and thread
              // echo Spyc::YAMLDump($clean, true, false, true);
              // $separator = '----------thread entries-----------';
              // print ($separator);
              //
              // //thread entries
              // echo Spyc::YAMLDump($thread_entries_clean, true, false, true);

              // $separator = '----------form entry values-----------';
              //form entry values
              echo Spyc::YAMLDump($form_entry_vals_clean, true, false, true);

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
                            $ticket->getSLAId(), $ticket->getTopicID(), $ticket->get('lock_id')
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

    //return tickets
    function getQuerySet($options, $requireOne=false) {
        $tickets = Ticket::objects();

        return $tickets;
    }

    //return threads
    function getQuerySet_thread($options, $requireOne=false) {
        $threads = Thread::objects();

        return $threads;
    }

    //return thread entries
    function getQuerySet_threadentry($options, $requireOne=false) {
        $thread_entries = ThreadEntry::objects();

        return $thread_entries;
    }

    //return form entries
    function getQuerySet_formentry($options, $requireOne=false) {
        $form_entries = DynamicFormEntry::objects();

        return $form_entries;
    }

    //return form entries
    function getQuerySet_formentry_vals($options, $requireOne=false) {
        $form_entry_val = DynamicFormEntryAnswer::objects();

        return $form_entry_val;
    }

    //adriane
    static function create_ticket($vars=array())
    {
      $ticket = new Ticket($vars);

      $ticket->created = new SqlFunction('NOW');

      //return the ticket
      return $ticket;

    }

    static function form_entry_create($vars, &$error=false, $fetch=false) {
        //var_dump('form');
        //see if form entry exists
        if ($fetch && ($FeId=self::getIdByCombo($vars['form_id'], $vars['object_id'])) || $vars['form_id']  == null)
        {
          //var_dump('match');
          return DynamicFormEntry::lookup($FeId);
        }
        else
        {
          //var_dump('new + ticket id is ' . $vars['object_id']);
          $Fe = DynamicFormEntry::create($vars, '', true);
          $Fe->sort = 0;
          $Fe->extra = '{"disable":[]}';
          $Fe->save();
          return $Fe->id;
        }

        // var_dump('youre passing in ' . $vars['form_id'] . ' and ' .  $vars['object_id']);
        // var_dump('entry id is ' . self::getIdByCombo($vars['form_id'], $vars['object_id']));

      }


    //adriane
    static function ticket_create($vars, &$errors=array(), $fetch=false) {
      //var_dump('ticket');
        //see if ticket exists
        if ($fetch && ($ticketId=Ticket::getIdByNumber($vars['number'])))
        {
          var_dump('found ticket match');
          return Ticket::lookup($ticketId);
        }
        //otherwise create new ticket
        else
        {
          var_dump('new ticket');
          $ticket = self::create_ticket($vars);
          $ticket->loadDynamicData(true);
          $ticket->save();
          return $ticket->ticket_id;
        }

        // $arrayin = $vars['number'];
        //
        // var_dump('youre passing in ' . $vars['number']);
        // var_dump('ticket id is ' . Ticket::getIdByNumber($vars['number']));


    }

    static function thread_create($vars, &$error=false, $fetch=false) {
        //see if thread exists
        if ($fetch && ($threadId=self::getThreadIdByCombo($vars['object_id'], $vars['object_type'])))
        {
          var_dump('thread match');
          return Thread::lookup($threadId);
        }
        else
        {
          var_dump('new thread');
          $thread = Thread::create($vars);
          $thread->save();
          return $thread->id;
        }


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

    private function getThreadIdByCombo($object_id, $object_type)
    {
      $row = Thread::objects()
          ->filter(array(
            'object_id'=>$object_id,
            'object_type'=>$object_type))
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

      if(count($row) != 0)
      {
        for ($i=0; $i<count($row); $i++)
        {
          $form_ids .= implode(',', $row[$i]) . ',';
        }

      }
      return rtrim($form_ids, ',');
    }

    private function getFormId($form_entry_id)
    {
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

    //methods for related object
    //staff
    private function getEmailById($id) {
        $list = Staff::objects()->filter(array(
            'staff_id'=>$id,
        ))->values_flat('email')->first();

        if ($list)
            return $list[0];
    }

    //topic
    private function getNameById($id) {
        $list = Topic::objects()->filter(array(
            'topic_id'=>$id,
        ))->values_flat('topic')->first();

        if ($list)
            return $list[0];
    }

    //user
    static function getIdByEmail($email) {
        $row = User::objects()
            ->filter(array('emails__address'=>$email))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //ticket status
    static function getIdByName($name) {
        $row = TicketStatus::objects()
            ->filter(array('name'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

}


Module::register('ticket', 'TicketManager');
?>
