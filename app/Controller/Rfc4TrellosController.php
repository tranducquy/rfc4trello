<?php

App::uses('AppController', 'Controller');
App::uses('Client', 'Model');
App::uses('Board', 'Model');
App::uses('Member', 'Model');
App::uses('Label', 'Model');
App::uses('TrelloComponent', 'Component');
App::uses('UtilComponent', 'Component');
App::uses('PhpReader', 'Configure');
/**
 * RFC For Trello Controller class
 */
class Rfc4TrellosController extends AppController {

    public $uses = array("Client", "Board", 'Member', 'Label');

    const TOKEN_EXPIRED = 'expired token';
    const API_REQUEST_FAILED = 'API Request failed';
    const ROUGH_DOING = 'Rough(Doing)';
    const ROUGH_REVIEW = 'Rough(Review)';
    const FILL_DOING = 'Fill(Doing)';
    const FILL_REVIEW = 'Fill(Review)';
    const CLOSING = 'Closing';
    const DONE = 'Done';

    const EXCEL_DUE_COL_INDEX = 'C';
    const EXCEL_ROUGH_DAYS_COL_INDEX = 'F';
    const EXCEL_COEFICIENT_COL_INDEX = 'M';
    const WARNING_LEVEL = 4;
   
    const TASK_COLOR = 'green';
    const DEFAULT_COEF_NO = 1;

    private $_rfcData = array();
    private $_boardId = ''; 
    private $_board_short_id = '';
        
/**
 * Displays a view
 *
 * @return void
 * @throws NotFoundException When the view file could not be found
 *	or MissingViewException in debug mode.
 */
 public $components = array('Trello', 'PhpExcel.PhpExcel', 'Session', 'Util', 'RequestHandler');

    // in any of your methods
    public function trello() {
        $key = Configure::read('TrelloApi.key');
        $secret = Configure::read('TrelloApi.secret');
        
        $trello = new TrelloComponent($key, $secret);
        $userOptions = array(
            'expiration' => 'never',
            'scope' => array(
                'read' => true,
                'write' => true,
            ),
            'name' => 'RFC For Trello',
            'redirect_uri' => Configure::read('TrelloApi.callBackUri')
        );
        $trello->authorize($userOptions);
        //debug($trello->authorized());
        //debug( $trello->token());
        if ($trello->authorized()) { 
            // アクセスTokenを保存する 
            $this->Session->write('oauth_token', $trello->token());
            
            //update token if expired
            $this->_updateToken($trello->token());
            
            if (! empty($this->Session->read('redirect_url'))) {
                $redirect_url = $this->Session->read('redirect_url');
                $this->Session->delete('redirect_url');
                $this->redirect($redirect_url);                
                return;
            }            
            $this->redirect("/board/" . $this->_boardId);
        }

        if (! empty($this->Session->read('redirect_url'))) {
            $redirect_url = $this->Session->read('redirect_url');
            $this->Session->delete('redirect_url');            
            $this->redirect($redirect_url); 
        }        
    }
    
    /**
     * Update Oauth token
     * @param type $token
     */
    private function _updateToken($token) {
        if (! empty($this->Session->read('boardId'))) {
           
            $this->_boardId = $this->Session->read('boardId');
            $key = Configure::read('TrelloApi.key');
            $secret = Configure::read('TrelloApi.secret');    
           
            $file_name = APP . 'Config' . DS  . 'rfc_files' . DS . $this->_boardId . '.php';
            if (! file_exists($file_name)) {
                debug($file_name);
                exit('現在、APIで作成したRFCモデルしか対応していません。');
                $myfile = fopen($file_name, "w") or die("Unable to open file!");
                fclose($myfile);
                
                $client = new Client($key, $token, $secret);
                $board = new Board($client);  
                $board->setId($this->_boardId);
                $boardData = $board->get();
                //debug($boardData);
                
                Configure::write($this->_boardId . 'rfc_board_id', $boardData->id);
                Configure::write($this->_boardId . 'rfc_board_short_url', $boardData->shortUrl);
                Configure::write($this->_boardId . 'rfc_board_url', $boardData->url);
                
                $lists = $board->getLists();
                $list_info = array();
                foreach ($lists as $list) {
                    $list_info[$list->id] = $list->name;                   
                }
                Configure::write($this->_boardId . 'lists', $list_info);
                
                $labels = $board->getLabels();                
                foreach ($labels as $label) {
                    if (self::TASK_COLOR == $label->color) {
                         Configure::write($this->_boardId . 'rfc_board_task_id', $label->id);   
                    } 
                }               
            } else {
                Configure::load('rfc_files' . DS . $this->_boardId); 
            }
             Configure::write($this->_boardId . 'oauth_token', $token);
             Configure::dump('rfc_files' . DS . $this->_boardId . '.php', 'default',
                array(
                    $this->_boardId . 'oauth_token', 
                    $this->_boardId . 'rfc_board_id',
                    $this->_boardId . 'rfc_board_short_url',
                    $this->_boardId . 'rfc_board_url', 
                    $this->_boardId . 'rfc_board_task_id',
                    $this->_boardId . 'lists'
                )
            );            
        }
    }
    
    /**
     * RFCモデルのTrelloボードを作成する
     */
    public function createRFCBoard() {
        $key = Configure::read('TrelloApi.key');
        $secret = Configure::read('TrelloApi.secret');             
        $oauth_token = $this->Session->read('oauth_token');
       
        $this->render("trello");  
        try {           
            $client = new Client($key, $oauth_token, $secret);
            $board = new Board($client);    
            $board->name = 'RFC For Trello';
            $board->save();
            $result = json_decode($board->getClient()->getRawResponse());
            
            if (!empty($result->id)) {
                          
                $shortData = split('/', $result->shortUrl);
                $this->_board_short_id = $shortData[4];
                
                $this->_boardId = $result->id;
                
                
                
                $board->setId($result->id);
                Configure::write($this->_board_short_id . 'oauth_token', $oauth_token);
                Configure::write($this->_board_short_id . 'rfc_board_id', $result->id);
                Configure::write($this->_board_short_id . 'rfc_board_short_url', $result->shortUrl);
                Configure::write($this->_board_short_id . 'rfc_board_url', $result->url);
                
                //TrelloのDefaultリストを削除する
                $default_lists = $board->getLists();                
                if (!empty($default_lists)) {
                    foreach ($default_lists as $list_index => $default_list) { 
                        var_dump($list_index);
                        $default_list->closed();
                    }
                }
               
                //Labelを登録する
                $labels = array(
                    "green" => "タスク",
                    "yellow" => "障害",
                    "orange" => "ストーリー",
                    "red" => "エピック",
                    "purple" =>"要検討",
                    "blue" =>"To do"
                );
                
                $board_labels = $board->getLabels();
                                
                foreach ($labels as $color => $lblName) {
                    foreach ($board_labels as $board_label) {
                        if ($color == $board_label->color) {
                            $board_label->name = $lblName;
                            $board_label->save();                            
                            if (self::TASK_COLOR == $color) {
                                 Configure::write($this->_board_short_id . 'rfc_board_task_id', $board_label->id);   
                            }
                            break;
                        }                       
                    }                                        
                }
                
                //リストを登録
                $lists = array(
                    1 => 'Next_Sprint_Backlog',
                    2 => 'This_Sprint_Backlog:' . date('Y/m/d'),
                    3 => 'To do',
                    4 => 'Rough(Doing)',
                    5 => 'Rough(Review)',
                    6 => 'Fill(doing)',
                    7 => 'Fill(Review)',
                    8 => 'Closing',
                    9 => 'Done',
                );
                $list_info = array();
                foreach ($lists as $position => $list_title) {
                    $list_data = array(
                        'pos' => $position,
                        'name' => $list_title,
                        'idBoard' => $this->_boardId,    
                    );
                    $list = new Lane($client, $list_data);
                    $list->save();
                    $list_result = json_decode($list->getClient()->getRawResponse());
                    $list_info[$list_result->id] = $list_title;
                }
                Configure::write($this->_board_short_id . 'lists', $list_info);
                //設定ファイルに保存する
                //URLにBoardのショートIDを使うため、設定ファイルもショートIDで保存する
                $file_name = APP . 'Config' . DS  . 'rfc_files' . DS . $this->_board_short_id . '.php';
                if (! file_exists($file_name)) {
                    $myfile = fopen($file_name, "w") or die("Unable to open file!");
                    fclose($myfile);
                }
                Configure::dump('rfc_files/' . $this->_board_short_id . '.php', 'default',
                    array(
                        $this->_board_short_id . 'oauth_token', 
                        $this->_board_short_id . 'rfc_board_id',
                        $this->_board_short_id . 'rfc_board_short_url',
                        $this->_board_short_id . 'rfc_board_url', 
                        $this->_board_short_id . 'rfc_board_task_id',
                        $this->_board_short_id . 'lists'
                    )
                );   
                $this->redirect($result->url);
            }           
        } catch (Exception $e) {
            debug($e);exit;
            $err_msg = $e->getMessage();
            if (strpos($err_msg, 'unauthorized') !== false) {
                $this->Session->write('redirect_url', "/makeBoard");                
                $this->redirect("/api");
            }            
        }
    }
    
    /**
     * RFCモデルのデータを取得する
     * 画面に表示する
     */
    public function getBoards($board_id) {
             
        $this->Session->delete('oauth_token');
        $this->set('board_id', $board_id);
        if (!empty($board_id) && $board_id != 'OAuthAuthorizeToken' && $board_id != 'undefined') {
            $this->_boardId = $board_id;
            $this->Session->write('boardId', $board_id);
            //RFCモデルのデータを取得する
            $this->_getRfcBoardData();
        } else {
            $this->Session->setFlash("RFCモデルのボードIDを指定してください。");
        }      
        
        //Viewにデータを設定する
        $this->set('data', $this->_rfcData);
        $this->render("trello");  
          
    }
    
    /**
     * RFCモデルのデータを取得する
     * Json形式
     */
    public function getJson($boadId) {
        
        $this->layout = false;
        $this->autoRender = false;
        $this->response->type('json');
        $this->Session->delete('oauth_token');
        if (! empty($boadId) && $boadId != 'OAuthAuthorizeToken' && $boadId != 'undefined') { 
            $this->_boardId = $boadId;
            $this->Session->write('boardId', $boadId);
            $this->_rfcData['boardId'] = $boadId;
            //RFCモデルのデータを取得する
            $this->_getRfcBoardData();
        } else { 
            $this->_rfcData['error'] = "RFCモデルのボードIDを正しく指定してください。";
            return;
        }
        
        //Viewにデータを設定する
        $json =  json_encode($this->_rfcData);
        $this->response->body($json);
    }
             
    /**
     * RFCモデル用のExcelファイルを作成する
     */
    public function exportRfcDataToExcel($board_id) {
        //レスポンスを設定する
        $this->_setExcelResponse();
                
        //RFC用のExcelを初期化
        $this->PhpExcel->createWorksheet()
            ->setDefaultFont('Calibri', 10);

        //テーブルのヘッダーを作成する
        $this->_setExcelTableHeader();        
        
        if (! empty($board_id)) {
            $this->_boardId = $board_id;
            //RFCデータを取得する
            $this->_getRfcBoardData();
        }        
         
        if (empty($this->_rfcData['rfc_card'])) {
            // close table and output
            $this->PhpExcel->addTableFooter()
                ->output();
             return;
        }
        
        // add data
        foreach ($this->_rfcData['rfc_card'] as $data) {
            $this->PhpExcel->addTableRow(array(
                $data['Card']['id'],
                $data['Card']['title'],
                $data['RoughDoing']['due'],
                $data['RoughDoing']['startDate'],
                $data['RoughDoing']['passDate'],
                $data['RoughDoing']['days'],
                $data['RoughReview']['date'],
                $data['FillDoing']['date'],
                $data['FillReview']['date'],
                $data['Closing']['date'],
                $data['Done']['date'],
                $data['CycleTime']['days'],
                $data['Coefficient']['number'],
            ));           
        }
        //背景色を設定する
        $this->_setWarningColor();
        
        //平均係数を設定する
        $this->_setAverageCoefficient();
        
        // close table and output
        $this->PhpExcel->addTableFooter()
            ->output();
    }
    
    /**
     * 係数を平均を計算する
     */
    private function _setAverageCoefficient() {
        $styleArray = array(
            'fill'  => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,                
                'color' => array('rgb' => 'FF0000'),
            )
        ); 
        
        $value = 0.00;
        if (!empty($this->_rfcData['average_coef'])) {
            $value = $this->_rfcData['average_coef'];
        }
        $row = count($this->_rfcData['rfc_card']) + 3;
        $this->PhpExcel->getActiveSheet()->getCell(self::EXCEL_COEFICIENT_COL_INDEX . $row)->setValue($value);
        $this->PhpExcel->getActiveSheet()
            ->getStyle(self::EXCEL_COEFICIENT_COL_INDEX . $row)
            ->applyFromArray($styleArray);
    }
    /**
     * レスポンスを設定する
     */
    private function _setExcelResponse() {
        $this->layout = false;
        $this->autoRender = false;
        // create new empty worksheet and set default font
        $this->response->header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
        $this->response->header("Content-Disposition: attachment; filename=rfc4trello.xls");
        $this->response->header("Expires: 0");
        $this->response->header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        $this->response->header("Cache-Control: private");
    }
    
    /**
     * RFCモデル用のExcelヘッダーを作成する
     */
    private function _setExcelTableHeader() {
        $styleArray = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'CCFFF5'),
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            ),
            'alignment' => array(
                'wrap' => false,
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
            ),
        );
        // define table cells
        $table = array(           
            array('label' => __('Story'), 'colspan' => 2),
            array('label' => __('')),
            array('label' => __('Rough(doing)')),
            array('label' => __('')),
            array('label' => __('')),
            array('label' => __('')),
            array('label' => __('Rough(Review)')),
            array('label' => __('Fill(doing)')),
            array('label' => __('Fill(Review)')),
            array('label' => __('Closing')),
            array('label' => __('Done')),
            array('label' => __('サイクルタイム'), 'width' => 15),
            array('label' => __('係数'), 'width' => 10)           
        );
        
        // add heading with different font and bold text
        $this->PhpExcel->addTableHeader($table, array('name' => 'Cambria', 'bold' => true));
        $headerRow2 = array(           
            array('label' => __('ID')),
            array('label' => __('Title'), 'width' => 120, 'wrap' => true),
            array('label' => __('Due')),
            array('label' => __('Start date')),
            array('label' => __('Pass date')),
            array('label' => __('Days')),
            array('label' => __('')),
            array('label' => __('')),
            array('label' => __('')),
            array('label' => __('')),
            array('label' => __('')),
            array('label' => __(''), 'width' => 15),
            array('label' => __(''), 'width' => 10)           
        );
        
        $this->PhpExcel->addTableHeader($headerRow2, array('name' => 'Cambria', 'bold' => true));
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('A1:B1');
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('C1:F1');        
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('G1:G2');
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('H1:H2');
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('I1:I2');
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('J1:J2');
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('K1:K2');
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('L1:L2');
        $this->PhpExcel->setActiveSheetIndex(0)->mergeCells('M1:M2');    
        //カラーを設定する
        $this->PhpExcel->getActiveSheet()
            ->getStyle('A1:M2')
            ->applyFromArray($styleArray);
    }
    
    /**
     * Boardのデータを取得する
     * @return type
     */
    private function _getRfcBoardData() {
        $key = Configure::read('TrelloApi.key');
        $secret = Configure::read('TrelloApi.secret');
        $oauth_token = '';
        try {
            Configure::load('rfc_files' . DS . $this->_boardId, 'default');
            $oauth_token = Configure::read($this->_boardId . 'oauth_token');
        } catch (Exception $exc) {
            //$this->Session->write('boardId', $this->_boardId);            
            $url = $this->_getRedirectUrl();        
            $this->Session->write('redirect_url', $url);
            $this->redirect('/api');
        }
        
        try {           
            //debug($oauth_token);// exit;
            $client = new Client($key, $oauth_token, $secret);
            $board = new Board($client);
            $board->setId($this->_boardId);
            $cards = $board->getCards();
            if ($cards == null && sizeof($cards) == 0) {
                return;
            }
            //debug($cards);
            $total_coef = 0;
            $task_count = 0;
            $this->_rfcData['rfc_card'] = array();
            $boardUpdateActions = $this->_getBoardActions($client);
            $task_label_id = Configure::read($this->_boardId . 'rfc_board_task_id');
            //カードを処理する
            foreach ($cards as $card) {               
                //Labelが「タスク」以外だと、無視                
                if (empty($card['idLabels'][0]) || $card['idLabels'][0] != $task_label_id) {
                    continue;
                }
                
                $rfc_record = array();                
                $card_url   = $card['url'];
                $ids        = split('/', $card_url);                
                if (sizeof($ids) > 0) {
                    $rfc_record['Card']['id'] = $ids[sizeof($ids) - 1];
                } else {
                    $rfc_record['Card']['id'] = $card['id'];
                }
                $rfc_record['Card']['idShort'] = $card['idShort'];
                $rfc_record['Card']['title'] = $card['name'];                
                $rfc_record['RoughDoing']['due'] = '';
                $rfc_record['RoughDoing']['dueDate'] = $card['due'];
                $rfc_record['RoughDoing']['startDate'] = '';
                $rfc_record['RoughDoing']['passDate'] = '';
                $rfc_record['RoughReview']['date'] = '';
                $rfc_record['FillDoing']['date'] = '';
                $rfc_record['FillReview']['date'] = '';
                $rfc_record['Closing']['date'] = '';
                $rfc_record['Done']['date'] = '';
                if (! empty($boardUpdateActions[$card['id']])) {
                    $updateActions = $boardUpdateActions[$card['id']];
                    foreach ($updateActions as $action_id => $action) {
                          
                        $moveToLane = $action['data']['listAfter']['name'];
                        $movedDateTime = $this->_convertTime($action['date']);
                        
                        if ($moveToLane == self::ROUGH_DOING) {
                            $rfc_record['RoughDoing']['startDate'] = $movedDateTime;                            
                            continue;
                        }
                        if ($moveToLane == self::ROUGH_REVIEW) {
                            $rfc_record['RoughDoing']['passDate'] = $movedDateTime;
                            $rfc_record['RoughReview']['date'] = $movedDateTime;
                            continue;
                        }
                        if ($moveToLane == self::FILL_DOING) {
                            $rfc_record['FillDoing']['date'] = $movedDateTime;
                            continue;
                        }
                        if ($moveToLane == self::FILL_REVIEW) {
                            $rfc_record['FillReview']['date'] = $movedDateTime;
                            continue;
                        }
                        if ($moveToLane == self::CLOSING) {
                            $rfc_record['Closing']['date'] = $movedDateTime;
                            continue;
                        }
                        if (strpos($moveToLane, self::DONE) !== false) {
                            $rfc_record['Done']['date'] = $movedDateTime;
                            continue;
                        }                        
                    }
                }
                $rfc_record['RoughDoing']['due'] = $this->_calWorkingDays(
                        $rfc_record['RoughDoing']['startDate'], 
                        $this->_convertTime($card['due']));
                $rfc_record['RoughDoing']['days'] = $this->_calWorkingDays(
                        $rfc_record['RoughDoing']['startDate'], 
                        $rfc_record['RoughDoing']['passDate']);
                $rfc_record['CycleTime']['days'] = $this->_calWorkingDays(
                        $rfc_record['RoughDoing']['startDate'], 
                        $rfc_record['Done']['date']);
                $rfc_record['Coefficient']['number'] = $this->_calCoefficient(
                        $rfc_record['RoughDoing']['due'],
                        $rfc_record['CycleTime']['days']);
                $total_coef += $rfc_record['Coefficient']['number'];               
                $this->_rfcData['rfc_card'][] = $rfc_record;
                if (! empty($rfc_record['Coefficient']['number'])) {
                    $task_count++;
                }
            }
            if ($task_count > 0 && !empty($total_coef)) {                
                $this->_rfcData['average_coef'] = number_format($total_coef / $task_count, 2);
            } else {
                $this->_rfcData['average_coef'] = self::DEFAULT_COEF_NO;
            }
            
        } catch (Exception $exc) {             
            $url = $this->_getRedirectUrl();
            $this->Session->delete('oauth_token');
            //debug($exc); exit;
            //Tokenの期限切れなので、再度発行する
            $message = $exc->getMessage();
            if (strpos($message, self::TOKEN_EXPIRED) !==false 
                    || strpos($message, self::API_REQUEST_FAILED) !== false) {
                $this->Session->write('redirect_url', $url);                
                $this->redirect("/api");                
            }
        }       
    }
    
    /**
     * 
     * @return string
     */
    private function _getRedirectUrl() {
        $action = $this->action;
        switch ($action) {
            case 'getJson':
                return '/getJson/' . $this->_boardId;
            case 'exportRfcDataToExcel':
                return '/export/' . $this->_boardId;
            case 'getBoards':
                return '/board/' . $this->_boardId;
            case 'createRFCBoard':
                return '/makeBoard';
            default:
                break;
        }
    }
    
    /**
     * RFCモデルのTrello Boardに全部Actionを取得する
     * @param type $client
     * @return array
     */
    private function _getBoardActions($client) { 
        
        $board = new Board($client);
        $board->setId($this->_boardId);
        $actions = $board->getActions(array('filter' => 'updateCard:idList', 'limit' => 1000));
        $updateActions = array();
        if (empty($actions)) {
            return $updateActions;
        }
        //debug($actions);
        foreach ($actions as $action) {
            if ($action->type == 'updateCard') {
                $updateAction = array(
                    'data' => $action->data,
                    'date' => $action->date,
                    'idMemberCreator' => $action->idMemberCreator
                );
                $updateActions[$action->data['card']['id']][] = $updateAction;
            }
        }
        return $updateActions;
    }
    
    /**
     * 日付フォーマットに変換する
     * @param type $dateTime
     * @return type
     */
    private function _convertTime($dateTime) {
        if (empty($dateTime)) {
            return '';
        }
        $time = strtotime($dateTime);
        return date("Y-m-d", $time);
    }
    
    /**
     * 営業日を計算する
     * @param type $startDate
     * @param type $passDate
     * @return string
     */
    private function _calWorkingDays($startDate, $passDate) {
        if (empty($startDate) || empty($passDate)) {
            return '';
        }
        //営業日を計算する
        return $this->Util->getWorkingDays($startDate, $passDate);
    }
    
    /**
     * 係数を計算する
     * @param type $due
     * @param type $fact
     * @return string
     */
    private function _calCoefficient($due, $fact) {
        if (empty($due) || empty($fact)) {
            return '';
        }
        $coeficient = $fact/$due;
        return number_format($coeficient, 2);
    }
    
    /**
     * Excelに警告色を設定する
     * @param type $due_days
     * @param type $rough_days
     * @param type $coeficient
     * @param type $row
     * @return type
     */
    private function _setWarningColor() {
       
        $total_rows = count($this->_rfcData['rfc_card']);
        if ($total_rows == 0) {
            return;
        }
        //Warningセルのスタイルを設定する
        $styleArray = array(
            'fill'  => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,                
                'color' => array('rgb' => 'FFFF00'),
            )
        );
        //セルのスタイルを定義する
        $cellArray = array(
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_DOTTED
                )
            )
        );
        
        //Cell borderを設定する
        $this->PhpExcel->getActiveSheet()
            ->getStyle('A3:M' . ($total_rows + 3))
            ->applyFromArray($cellArray);
        
        for ($row = 3; $row < $total_rows + 3; $row++) {
            $due_days = $this->PhpExcel->getActiveSheet()->getCell(self::EXCEL_DUE_COL_INDEX . $row)->getValue();
            $rough_days = $this->PhpExcel->getActiveSheet()->getCell(self::EXCEL_ROUGH_DAYS_COL_INDEX . $row)->getValue();
            $coeficient = $this->PhpExcel->getActiveSheet()->getCell(self::EXCEL_COEFICIENT_COL_INDEX . $row)->getValue();
           // debug("row:$row due_days: $due_days rough_days:$rough_days coeficient:$coeficient");
            
            //期限はない場合は、計算しない
            if (empty($due_days)) {
                continue;
            }

            //Roughフェースにて見積もりの係数を計算する              
            $roughEstimate = $rough_days / $due_days;
            if ($roughEstimate >= self::WARNING_LEVEL) {

                $this->PhpExcel->getActiveSheet()
                        ->getStyle(self::EXCEL_ROUGH_DAYS_COL_INDEX . $row)
                        ->applyFromArray($styleArray);
            }
            //全フェースにて見積もりの係数     
            if ($coeficient >= self::WARNING_LEVEL) {
                $this->PhpExcel->getActiveSheet()
                        ->getStyle(self::EXCEL_COEFICIENT_COL_INDEX . $row)
                        ->applyFromArray($styleArray);
            }           
        }
    }
    
    public function index() {
        
        
        
        
    }
}
