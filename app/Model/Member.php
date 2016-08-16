<?php

App::uses('TrelloObject', 'Model');
App::uses('Card', 'Model');
class Member extends TrelloObject {

    protected $_model = 'members';

    public function getBoards()
    {
        $data = $this->getPath('boards');

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Board($this->getClient(), $item));
        }

        return $tmp;

    }

    public function getOrganizations()
    {
        $data = $this->getPath('organizations');

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Organization($this->getClient(), $item));
        }

        return $tmp;

    }

    public function getCards(array $params = array())
    {
        $data = $this->getPath('cards', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Card($this->getClient(), $item));
        }

        return $tmp;
    }

}