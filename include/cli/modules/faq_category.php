<?php

class FAQCategoryManager extends Module {
    var $prologue = 'CLI faq manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import faqs from YAML file',
                'export' => 'Export faqs from the system to CSV or YAML',
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

          //create emails with a unique name as a new record
          $errors = array();
          foreach ($data as $o) {
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
              //get the faq categories
              $faq_category = self::getQuerySet($options);

              $clean = array();

              //format the array nicely
              foreach ($faq_category as $C)
              {
                $clean[] = array('ispublic' => $C->ispublic,
                'name' => $C->getName(), 'description' => $C->getDescription(),
                'notes' => $C->getNotes());
              }

              //export yaml file
              echo (Spyc::YAMLDump($clean));

              if(!file_exists('faq_category.yaml'))
              {
                $fh = fopen('faq_category.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }
            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Category ID', 'isPublic', 'Name', 'Description', 'Notes'));
              foreach (Category::objects() as $faq_category)
                  fputcsv($this->stream,
                          array((string) $faq_category->getId(), boolval($faq_category->ispublic), $faq_category->getName(), $faq_category->getDescription(), $faq_category->getNotes()));
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
        $faq_category = Category::objects();

        return $faq_category;
    }

    static function create($vars, &$error=false, $fetch=false) {
        //see if staff exists
        if ($fetch && ($catId=Category::findIdByName($vars['name'])))
        {
          var_dump('match');
          return Category::lookup($catId);
        }
        else
        {
          var_dump('new');
          $cat = Category::create($vars);
          $cat->save();
          return $cat;
        }

        // $arrayin = $vars['name'];
        //
        // var_dump('youre passing in ' . $arrayin);
        // var_dump('sla id is ' . Category::findIdByName($arrayin));

    }
}
Module::register('faq_category', 'FAQCategoryManager');
?>
