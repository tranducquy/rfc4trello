<?php

App::uses('TrelloObject', 'Model');
App::uses('Board', 'Model');
class Organization extends TrelloObject {

    protected $_model = 'organizations';

    public function getBoards(array $params = array()){

        $data = $this->getPath('boards', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Board($this->getClient(), $item));
        }

        return $tmp;

    }

}