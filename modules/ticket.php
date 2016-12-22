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

            //user email
             //$uemailId = User::getEmailIdByUser($userId);
            //var_dump('email id is ' . $uemailId);



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


            //ticket table
            //for any related id's, look them up from imported data
            // $ticket_import[] = array('number' => $D['number'], 'userId' => $D['user_id'],
            // 'statusId' => $D['status_id'],'deptId'=> $D['dept_id'],
            // 'slaId'=> $D['sla_id'], 'topicId'=> $D['topic_id'],
            // 'staffId'=> $D['staff_id'], 'ip' => $D['ip_address'], 'source' => $D['source'],
            // 'time' => $D['est_duedate'], 'priorityId' => $D['priority']);

          }

          //import tickets
          $errors = array();
          //create Tickets
          foreach ($ticket_import as $o) {
              if ('self::__create' && is_callable('self::__create'))
                  @call_user_func_array('self::__create', array($o, &$errors, true));
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
                'topic_name' => $topicName, 'agent_email' =>  $agentEmail, 'grace_period' => 75, 'subject' => $ticket->getSubject()

              );
               }

              //export yaml file
              echo Spyc::YAMLDump(array_values($clean), true, false, true);

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

              fputcsv($this->stream, array('Name', 'Signature', 'ispublic', 'group_membership'));
              foreach (Ticket::objects() as $ticket)
                  fputcsv($this->stream,
                          array((string) $ticket->getName(), $ticket->getSignature(), boolval($ticket->ispublic), boolval($ticket->group_membership)));

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

    //adriane
    static function __create($vars, &$errors=array(), $fetch=false) {
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
}
Module::register('ticket', 'TicketManager');
?>
