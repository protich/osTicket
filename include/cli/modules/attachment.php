<?php

class AttachmentManager extends Module {
    var $prologue = 'CLI thread entry manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import attachments from yaml file',
                'export' => 'Export attachments from the system to CSV or yaml',
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
            $ticket_id = Ticket::getIdByNumber($D['ticket_number']);

            $attachment_import[] = array('ticket_number' => $D['ticket_number'], 'ticket_id' => $ticket_id,
              'created' => $D['file_created'],
              'object_id' => $D['att_obj_id'], 'id' => $D['att_file_id'],
              'inline' => $D['att_inline'], 'name' => $D['file_name'], 'size' => $D['file_size'],
              'signature' => $D['file_signature'], 'key' => $D['file_key'],
            );
          }

          foreach ($attachment_import as $attachment)
          {
            $thread_id = self::getThreadIdByCombo($attachment['ticket_id'], 'T');
            $thread_entry_id = self::getIdByCombo($thread_id, $attachment['created']);

            $attachment['object_id'] = $thread_entry_id;
            $file_id = self::getFileIdBySignature($attachment['signature']);
            if($file_id != 0 && $thread_entry_id != 0)
            {
              self::createAttachment($attachment);
            }
          }
          unset($attachment_import);

          break;

        case 'export':
            if ($options['yaml'])
            {
              //get the attachments
              $attachments = self::getQuerySet($options);

              //if thread entry has attachment, add attachment details to yaml file
              foreach ($attachments as $att)
              {
                if($att->type == 'H')
                {
                  $attachment_obj_id = $att->object_id;
                  $attachment_file_id = $att->file_id;
                  $attachment_inline = $att->inline;

                  $attachment_file = $att->getFile();

                  $thread_id = self::getThreadId($attachment_obj_id);
                  $ticket_id = self::getTicketId($thread_id);
                  $ticket_number = self::getNumberById($ticket_id);

                  $attachments_clean[] = array(
                      '- attachment' => '', '  ticket_number' => $ticket_number,
                      '  att_obj_id' => $attachment_obj_id, '  att_file_id' => $attachment_file_id,
                      '  att_inline' => $attachment_inline,
                      '  file_type' => $attachment_file->getType(), '  file_size' => $attachment_file->getSize(),
                      '  file_name' => $attachment_file->getName(), '  file_created' => $attachment_file->created,
                      '  file_key' => $attachment_file->getKey(), '  file_signature' => $attachment_file->getSignature()
                  );
                }
              }

              //export yaml file
              // echo Spyc::YAMLDump($attachments_clean, true, false, true);

              if(!file_exists('attachment.yaml'))
              {
                $fh = fopen('attachment.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($attachments_clean)));
                fclose($fh);
              }
              unset($attachments_clean);
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

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $attachments = Attachment::objects();

        return $attachments;
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

    private function getThreadId($object_id)
    {
      $row = ThreadEntry::objects()
          ->filter(array(
            'id'=>$object_id))
          ->values_flat('thread_id')
          ->first();
      return $row ? $row[0] : 0;
    }

    private function getTicketId($thread_id)
    {
      $row = Thread::objects()
          ->filter(array(
            'id'=>$thread_id))
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

}
Module::register('attachment', 'AttachmentManager');
?>
