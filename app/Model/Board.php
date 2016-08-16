<?php

App::uses('TrelloObject', 'Model');
App::uses('Card', 'Model');
App::uses('Action', 'Model');
App::uses('Lane', 'Model');
class Board extends TrelloObject {

    protected $_model = 'boards';

    public function getCards(array $params = array()){

        $data = $this->getPath('cards', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Card($this->getClient(), $item));
        }

        return $tmp;

    }

    public function getCard($card_id, array $params = array()){

        $data = $this->getPath("cards/{$card_id}", $params);

        return new Card($this->getClient(), $data);

    }

    public function getActions(array $params = array()){

        $data = $this->getPath('actions', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Action($this->getClient(), $item));
        }

        return $tmp;

    }
        
    public function getLists(array $params = array()){

        $data = $this->getPath('lists', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Lane($this->getClient(), $item));
        }

        return $tmp;

    }
    
    public function getMembers(array $params = array()){

        $data = $this->getPath('members', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Member($this->getClient(), $item));
        }

        return $tmp;

    }

    public function getLabels(array $params = array()){

        $data = $this->getPath('labels', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Label($this->getClient(), $item));
        }

        return $tmp;

    }
    
    public function copy($new_name = null, array $copy_fields = array()){

        if ($this->getId()){

            $tmp = new self($this->getClient());
            if (!$new_name){
                $tmp->name = $this->name . ' Copy';
            }else{
                $tmp->name = $new_name;
            }
            $tmp->idBoardSource = $this->getId();

            if (!empty($copy_fields)){
                $tmp->keepFromSource = implode(',', $copy_fields);
            }

            return $tmp->save();

        }

        return false;

    }
    
}