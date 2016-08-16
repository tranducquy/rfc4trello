<?php

App::uses('TrelloObject', 'Model');
class Label extends TrelloObject {

    protected $_model = 'labels';

    public function getLabels(array $params = array()){

        $data = $this->getPath('labels', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Label($this->getClient(), $item));
        }

        return $tmp;

    }
}