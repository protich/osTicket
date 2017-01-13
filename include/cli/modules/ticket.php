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
      // var_dump('made it in');
        $row = TicketPriority::objects()
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
          unset($ticket_import);

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
          unset($thread_import);

          //processing for form entries
          foreach ($data as $D)
          {
            $form_entry = $D['form_entry'];

            foreach ($form_entry as $T)
            {
              $form_id = $T['form_id'];
            }

            //parse form id
            $form_id_all = explode(",", $form_id);

            //object_id
            $object_id = Ticket::getIdByNumber($D['number']);

            foreach ($form_id_all as $fid)
            {
              //form_entry table
              $form_entry_import[] = array('form_id' => $fid,
                  'object_id' => $object_id, 'object_type' => 'T');
            }

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
                'isoverdue' => $ticket->isoverdue, 'isanswered' => $ticket->isanswered,
                'est_duedate' => $ticket->getEstDueDate(), 'reopened' => $ticket->getReopenDate(), 'closed' => $ticket->getCloseDate(),
                'lastupdate' => $ticket->getEffectiveDate(),

                //related object fields
                'status_name' => $ticket->getStatus(), 'priority' => $ticket->getPriority(), 'department_name' => $ticket->getDeptName(),
                'user_name' => $ticket->getName(), 'user_email' => $userEmail, 'organization' => $orgname, 'sla_name' => $sla_prefix,
                'topic_name' => $topicName, 'agent_email' =>  $agentEmail, 'grace_period' => 75, 'subject' => $ticket->getSubject(),

                'form_entry' => array('- form_entry_id' => self::getFormEntryId($ticket->ticket_id), '  form_id' => self::getFormId($ticket->ticket_id),
                                      '  extra' => self::getFormExtra($ticket->ticket_id), '  sort' => self::getFormSort($ticket->ticket_id)
                              ),

                 'thread' => array('- object_id' => $ticket->getId(),
                                   '  object_type' => $ticket->thread->object_type, '  extra' => $ticket->thread->extra,
                                    '  lastresponse' => $ticket->getLastResponseDate(), '  lastmessage' => $ticket->getLastMessageDate())
                );

            } //end ticket foreach

            unset($tickets);

              //export yaml file

              //ticket
              //echo Spyc::YAMLDump($clean, true, false, true);

              //export tickets directly to yaml file
              if(!file_exists('ticket.yaml'))
              {
                $fh = fopen('ticket.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }

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
          $Fe->save();
          return $Fe->id;
        }

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
          $fe_ids .= implode(',', $row[$i]) . ',';
        }

      }
      return rtrim($fe_ids, ',');
    }

    private function getFormExtra($ticket_id)
    {
      $row = DynamicFormEntry::objects()
          ->filter(array(
            'object_type'=>'T',
            'object_id'=>$ticket_id))
          ->values_flat('extra')
          ->first();

      return $row ? $row[0] : 0;
    }

    private function getFormSort($ticket_id)
    {
      $row = DynamicFormEntry::objects()
          ->filter(array(
            'object_type'=>'T',
            'object_id'=>$ticket_id))
          ->values_flat('sort')
          ->first();

      return $row ? $row[0] : 0;
    }

    private function getFormId($ticket_id)
    {
        $row = DynamicFormEntry::objects()
            ->filter(array(
              'object_type'=>'T',
              'object_id'=>$ticket_id))
            ->values_flat('form_id');

      if(count($row) != 0)
      {
        for ($i=0; $i<count($row); $i++)
        {
          $form_ids .= implode(',', $row[$i]) . ',';
        }
      }
      return rtrim($form_ids, ',');

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

    //ticket Number
    static function getNumberById($id) {
        $row = Ticket::objects()
            ->filter(array('ticket_id'=>$id))
            ->values_flat('number')
            ->first();

        return $row ? $row[0] : 0;
    }

    //thread object id
    static function getObjectByThread($thread_id) {
        $row = Thread::objects()
            ->filter(array('id'=>$thread_id))
            ->values_flat('object_id')
            ->first();

        return $row ? $row[0] : 0;
    }

}


Module::register('ticket', 'TicketManager');
?>
