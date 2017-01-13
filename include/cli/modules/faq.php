<?php

class FAQManager extends Module {
    var $prologue = 'CLI faq manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import faqs from CSV file',
                'export' => 'Export faqs from the system to CSV',
                'list' => 'List faqs based on search criteria',
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

            foreach ($data as $D)
            {
              $faq_import[] = array('category_id' => self::getIdByName($D['category_name']),
                'ispublished' => $D['ispublished'], 'question' => $D['question'],
                'answer' => $D['answer']);
            }

            //create emails with a unique name as a new record
            $errors = array();
            foreach ($faq_import as $o) {
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
              //get the departments
              $faq = self::getQuerySet($options);

              $clean = array();

              //format the array nicely
              foreach ($faq as $F)
              {
                $clean[] = array('category_name' => self::getNameById($F->category_id),
                'ispublished' => $F->ispublished, 'question' => $F->getQuestion(),
                'answer' => $F->getAnswer());
              }

              //export yaml file
              echo (Spyc::YAMLDump($clean));

              if(!file_exists('faq.yaml'))
              {
                $fh = fopen('faq.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Category ID', 'isPublished', 'Question', 'Answer'));
              foreach (FAQ::objects() as $faq)
                  fputcsv($this->stream,
                          array((string) $faq->getCategoryId(), boolval($faq->ispublished), $faq->getQuestion(), $faq->getAnswer()));
            }

            break;

        case 'list':
            $faq = $this->getQuerySet($options);

            foreach ($faq as $F) {
                $this->stdout->write(sprintf(
                    "%d %s <%s>%s\n",
                    $F->getCategoryId(), boolval($F->ispublished), $F->getQuestion(), $F->getAnswer()
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $faq = FAQ::objects();

        return $faq;
    }

    static function getIdByQuestion($question) {
         $qs = FAQ::objects()->filter(Q::any(array(
                         'question'  => $question
                         )))
             ->values_flat('faq_id');

         $row = $qs->first();
         return $row ? $row[0] : false;
     }

     static function getNameById($id) {
          $row = Category::objects()
              ->filter(array('category_id'=>$id))
              ->values_flat('name')
              ->first();

          return $row ? $row[0] : null;
      }

      static function getIdByName($name) {
           $row = Category::objects()
                ->filter(array('name'=>$name))
                ->values_flat('category_id')
                ->first();

           return $row ? $row[0] : null;
       }

    //adriane
    private function create($vars, &$error=false, $fetch=false) {
        //see if staff exists
        if ($fetch && ($faqId=self::getIdByQuestion($vars['question'])))
        {
          var_dump('match');
          return FAQ::lookup($faqId);
        }
        else
        {
          var_dump('new');
          $faq = FAQ::create($vars);
          $faq->save();
          return $faq->faq_id;
        }

    }
}
Module::register('faq', 'FAQManager');
?>
