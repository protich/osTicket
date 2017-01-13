<?php

class ThreadEntryManager extends Module {
    var $prologue = 'CLI thread entry manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import thread entries from yaml file',
                'export' => 'Export thread entries from the system to CSV or yaml',
                'list' => 'List thread entries based on search criteria',
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

          //place file into array
          $data = YamlDataParser::load($options['file']);

          //processing for thread entries
          foreach ($data as $D)
          {
            $ticket = $D['ticket'];
            $thread_entry = $D['thread_entry'];
            foreach ($thread_entry as $te)
            {
              $ticket_id = Ticket::getIdByNumber($ticket);
              $thread_id = self::getThreadIdByCombo($ticket_id, 'T');

              $thread_entry_import[] = array('pid' => $te['pid'], 'thread_id' => $thread_id,
                'staff_id' => $te['staff_id'], 'user_id' => $te['user_id'], 'type' => $te['type'],
                'flags' => $te['flags'], 'poster' => $te['poster'], 'editor' => $te['editor'],
                'editor_type' => $te['editor_type'], 'source' => $te['source'], 'title' => $te['title'],
                'body' => $te['body'], 'format' => $te['format'], 'ip_address' => $te['ip_address'],
                'created' => $te['created']);

            }

          }

          //create threads with a unique name as a new record
          $errors = array();
          foreach ($thread_entry_import as $o) {
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
              //get the thread entries
              $thread_entries = self::getQuerySet($options);

              foreach ($thread_entries as $thread_entry)
              {
                $thread_id = $thread_entry->getThreadId();
                $ticket_id = self::getObjectByThread($thread_entry->getThreadId());
                $ticket_num = self::getNumberById($ticket_id);

                $thread_entries_clean[] = array('- ticket' => $ticket_num, '  thread_entry' => '');

                array_push($thread_entries_clean, array(
                '    - thread_id' => $thread_id, '      object_id' => $ticket_id,
                '      pid' => $thread_entry->getPid(),
                '      staff_id' => $thread_entry->getStaffId(),'      user_id' => $thread_entry->getUserId(),
                '      type' => $thread_entry->getType(), '      flags' => $thread_entry->get('flags'),
                '      poster' => $thread_entry->getPoster(), '      editor' => $thread_entry->getEditor(),
                '      editor_type' => $thread_entry->get('editor_type'), '      source' => $thread_entry->getSource(),
                '      title' => $thread_entry->getTitle(), '      body' => $thread_entry->getBody(),
                '      format' => $thread_entry->get('format'), '      ip_address' => $thread_entry->get('ip_address'),
                '      created' => $thread_entry->get('created'), '      num_attachments' => $thread_entry->getNumAttachments(),

                )
                );

              }
              unset($thread_entries);

              //export yaml file
              // echo Spyc::YAMLDump($thread_entries_clean, true, false, true);

              if(!file_exists('thread_entry.yaml'))
              {
                $fh = fopen('thread_entry.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($thread_entries_clean)));
                fclose($fh);
              }
              unset($thread_entries_clean);

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('thread_id', 'pid', 'title', 'body'));
              foreach (ThreadEntry::objects() as $thread_entry)
                  fputcsv($this->stream,
                          array((string) $thread_entry->getThreadId(), $thread_entry->getPid(), $thread_entry->getTitle(), $thread_entry->getBody()));
            }

            break;

        case 'list':
            $thread_entries = $this->getQuerySet($options);

            foreach ($thread_entries as $T) {
                $this->stdout->write(sprintf(
                    "%d %s <%s> %s\n",
                    $T->getThreadId(), $T->getPid(), $T->getTitle(), $T->getBody()
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $thread_entries = ThreadEntry::objects();

        return $thread_entries;
    }

    private function getIdByCombo($thread_id, $created)
    {
      $row = ThreadEntry::objects()
          ->filter(array(
            'thread_id'=>$thread_id,
            'created'=>$created))
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

    private function getFileIdBySignature($signature)
    {
      $row = AttachmentFile::objects()
          ->filter(array(
            'signature'=>$signature))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }

    function createAttachment($file, $name=false) {
        $att = new Attachment(array(
            'type' => 'H',
            'object_id' => $file['object_id'],
            'file_id' => $file['id'],
            'inline' => $file['inline'] ? 1 : 0,
        ));


        // Record varying file names in the attachment record
        if (is_array($file) && isset($file['name'])) {
            $filename = $file['name'];
        }
        elseif (is_string($name)) {
            $filename = $name;
        }
        if ($filename) {
            // This should be a noop since the ORM caches on PK
            $F = @$file['file'] ?: AttachmentFile::lookup($file['id']);
            // XXX: This is not Unicode safe
            if ($F && 0 !== strcasecmp($F->name, $filename))
                $att->name = $filename;
        }

        if (!$att->save())
            return false;
        return $att;
    }

    static function create_thread_entry($vars=array())
    {
      $thread_entry = new ThreadEntry($vars);

      //return the thread entry
      return $thread_entry;

    }

    static function __create($vars, &$error=false, $fetch=false) {
        //see if thread entry exists
        if ($fetch && ($threadId=self::getIdByCombo($vars['thread_id'], $vars['created'])))
        {
          // var_dump('match');
          return ThreadEntry::lookup($threadId);
        }
        else
        {
          // var_dump('new');
          $thread_entry = self::create_thread_entry($vars);
          $thread_entry->save();

          return $thread_entry->id;
        }

    }

    //thread object id
    static function getObjectByThread($thread_id) {
        $row = Thread::objects()
            ->filter(array('id'=>$thread_id))
            ->values_flat('object_id')
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

static function write_block($what) {
    fwrite($this->stream, Spyc::YAMLDump($thread_entries_clean, true, false, true));
    fwrite($this->stream, "\n");
}


}
Module::register('thread_entry', 'ThreadEntryManager');
?>
