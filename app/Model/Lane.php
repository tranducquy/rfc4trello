<?php

App::uses('TrelloObject', 'Model');
App::uses('Card', 'Model');
class Lane extends TrelloObject {

    protected $_model = 'lists';

    public function save(){

        if (empty($this->name)){
            throw new \InvalidArgumentException('Missing required field "name"');
        }

        if (empty($this->idBoard)){
            throw new \InvalidArgumentException('Missing required filed "idBoard" - id of the board that the list should be added to');
        }

        if (empty($this->pos)){
            $this->pos = 'bottom';
        }else{
            if ($this->pos !== 'top' && $this->pos !== 'bototm' && $this->pos <= 0){
                throw new \InvalidArgumentException("Invalid pos value {$this->pos}. Valid Values: A position. top, bottom, or a positive number");
            }
        }

        return parent::save();

    }

    public function closed(){

        if (!$this->getId()){
            throw new \InvalidArgumentException('There is no ID set for this object - Please call setId before calling update');
        }

       
        $response = $this->getClient()->put($this->getModel() . '/' . $this->getId() . '/closed', ["value" => "true"], ['Content-Type' => ' application/json; charset=utf-8']);

        $child = get_class($this);

        return new $child($this->getClient(), $response);

    }
    
    public function getCards(array $params = array()){

        $data = $this->getPath('cards', $params);

        $tmp = array();
        foreach ($data as $item){
            array_push($tmp, new Card($this->getClient(), $item));
        }

        return $tmp;

    }
    
    public function archive() {
        return parent::update();
    }

}