<?php
//require_once('role.php');
include('role.php');
include('topic.php');
include('status.php');
include('sla.php');
include('organization.php');
include('faq_category.php');
include('faq.php');
include('department.php');
include('agent.php');
include('user.php');
include('ticket.php');

class RunExports extends Module
{


  var $prologue = 'CLI role manager';

  var $arguments = array(
      'action' => array(
          'help' => 'Action to be performed',
          'options' => array(
              'import' => 'Import roles from yaml file',
              'export' => 'Export roles from the system to CSV or yaml',
              'list' => 'List roles based on search criteria',
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

      case 'export':
          if ($options['yaml'])
          {
            $action = array('action' => 'export');
            $option = array('yaml');

            //run exports
             $role = RoleManager::run($action, 'yaml');
            // $topic = TopicManager::run($action, 'yaml');
            // $status = StatusManager::run($action, 'yaml');
            // $sla = SLAManager::run($action, 'yaml');
            // $organization = OrganizationManager::run($action, 'yaml');
            // $faq_category = FAQCategoryManager::run($action, 'yaml');
            // $faq = FAQManager::run($action, 'yaml');
            // $department = DepartmentManager::run($action, 'yaml');
            // $agent = AgentManager::run($action, 'yaml');
            // $user = UserManager::run($action, 'yaml');
            // $ticket = TicketManager::run($action, 'yaml');




          }

          break;


      default:
          $this->stderr->write('Unknown action!');
      }
      @fclose($this->stream);
  }


}
Module::register('run_export', 'RunExports');
?>
