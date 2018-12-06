<?php

class Choice2ListManager extends Module {
    var $prologue = 'CLI choice2list manager';
    var $arguments = array(
        'action' => array(
            'help' => 'Choice field ID to be converted to list',
        ),
    );


    var $options = array(
        'fid' => array('-fid', '--field', 'metavar'=>'id',
            'help' => 'Field ID'),
        'lid' => array('-lid', '--list', 'metavar'=>'id',
            'help' => 'List ID'),
        );

    var $stream;

    function run($args, $options) {

        Bootstrap::connect();
        $field=$list = null;
        if (!$args[0])
            $this->fail('Field ID required as the first  uargumrnt');
        elseif (!($field=DynamicFormField::lookup((int) $args[0])))
            $this->fail('Unknown field ID ('.$args[0].')');
        elseif (strcmp($field->type, 'choices'))
            $this->fail('Field must be a choice field');

        $field = $field->getField()->getImpl();
        if (!($choices=$field->getChoices()) || count($choices)  < 3)
            $this->fail('Field must have 3 or more choices to make a list');

        $lid = $options['lid'] ?: $options['list'];
        if ($lid
            && !($list = DynamicList::lookup((int) $lid)))
            $this->fail('Unable to load list');


        if (!$list) {
            $items = $errors = array();
            foreach ($choices as $choice)
                $items[] = array('value' => $choice);
            $ht = array(
                    'name'  => $field->getLabel(),
                    'items' => $items,
                    );
            $list = DynamicList::__create($ht, $errors);
        }

        if (!$list)
            $this->fail('Unable to create list');

        $sql = "UPDATE `ost_form_entry_values` v
            INNER JOIN `ost_list_items` i ON v.`value` = CONCAT('{\"', REPLACE(i.value, '/', '\\/'), '\":\"', REPLACE(i.value, '/', '\\/'), '\"}')
            SET v.value=i.value, v.value_id=i.id
            WHERE v.field_id={$field->getId()} AND i.list_id={$list->getId()}";

        db_query($sql);
        $sql = "UPDATE ost_form_entry_values v SET `value`=NULL WHERE `value` = '{\"\":\"\"}' AND field_id={$field->getId()}";
        db_query($sql);
        $sql = "UPDATE ost_form_field SET `configuration`=NULL, type='list-{$list->getId()}' WHERE id={$field->getId()}";
        db_query($sql);
    }
}
Module::register('choice2list', 'Choice2ListManager');
?>
