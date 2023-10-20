<?php

use Infinex\Exceptions\Error;
use Infinex\Pagination;
use Infinex\Database\Search;
use function Infinex\Validation\validateId;
use React\Promise;

class Popups {
    private $log;
    private $amqp;
    private $pdo;
    
    function __construct($log, $amqp, $pdo) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized popups manager');
    }
    
    public function start() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> method(
            'getPopups',
            [$this, 'getPopups']
        );
        
        $promises[] = $this -> amqp -> method(
            'getPopup',
            [$this, 'getPopup']
        );
        
        $promises[] = $this -> amqp -> method(
            'deletePopup',
            [$this, 'deletePopup']
        );
        
        $promises[] = $this -> amqp -> method(
            'editPopup',
            [$this, 'editPopup']
        );
        
        $promises[] = $this -> amqp -> method(
            'createPopup',
            [$this, 'createPopup']
        );
        
        return Promise\all($promises) -> then(
            function() use($th) {
                $th -> log -> info('Started popups manager');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to start popups manager: '.((string) $e));
                throw $e;
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> unreg('getPopups');
        $promises[] = $this -> amqp -> unreg('getPopup');
        $promises[] = $this -> amqp -> unreg('deletePopup');
        $promises[] = $this -> amqp -> unreg('editPopup');
        $promises[] = $this -> amqp -> unreg('createPopup');
        
        return Promise\all($promises) -> then(
            function() use ($th) {
                $th -> log -> info('Stopped popups manager');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to stop popups manager: '.((string) $e));
            }
        );
    }
    
    public function getPopups($body) {
        if(isset($body['enabled']) && !is_bool($body['enabled']))
            throw new Error('VALIDATION_ERROR', 'enabled');
            
        $pag = new Pagination\Offset(50, 500, $body);
        $search = new Search(
            [
                'title',
                'body'
            ],
            $body
        );
            
        $task = [];
        $search -> updateTask($task);
        
        $sql = 'SELECT popupid,
                       title,
                       body,
                       enabled
                FROM popups
                WHERE 1=1';
        
        if(isset($body['enabled'])) {
            $task[':enabled'] = $body['enabled'] ? 1 : 0;
            $sql .= ' AND enabled = :enabled';
        }
            
        $sql .= $search -> sql()
             .' ORDER BY popupid DESC'
             . $pag -> sql();
            
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
            
        $popups = [];
        
        while($row = $q -> fetch()) {
            if($pag -> iter()) break;
            $popups[] = $this -> rtrPopup($row);
        }
            
        return [
            'popups' => $popups,
            'more' => $pag -> more
        ];
    }
    
    public function getPopup($body) {
        if(!isset($body['popupid']))
            throw new Error('MISSING_DATA', 'popupid');
        
        if(!validateId($body['popupid']))
            throw new Error('VALIDATION_ERROR', 'popupid');
        
        $task = [
            ':popupid' => $body['popupid']
        ];
        
        $sql = 'SELECT popupid,
                       title,
                       body,
                       enabled
                FROM popups
                WHERE popupid = :popupid';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        if(!$row)
            throw new Error('NOT_FOUND', 'Popup '.$body['popupid'].' not found');
            
        return $this -> rtrPopup($row);
    }
    
    public function deletePopup($body) {
        if(!isset($body['popupid']))
            throw new Error('MISSING_DATA', 'popupid');
        
        if(!validateId($body['popupid']))
            throw new Error('VALIDATION_ERROR', 'popupid');
        
        $task = [
            ':popupid' => $body['popupid']
        ];
        
        $sql = 'DELETE FROM popups
                WHERE popupid = :popupid
                RETURNING 1';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        if(!$row)
            throw new Error('NOT_FOUND', 'Popup '.$body['popupid'].' not found');
    }
    
    public function editPopup($body) {
        if(!isset($body['popupid']))
            throw new Error('MISSING_DATA', 'popupid');
        
        if(!validateId($body['popupid']))
            throw new Error('VALIDATION_ERROR', 'popupid');
        
        if(!isset($body['title']) && !isset($body['body']) && !isset($body['enabled']))
            throw new Error('MISSING_DATA', 'Nothing to change');
        if(isset($body['title']) && !is_string($body['title']))
            throw new Error('VALIDATION_ERROR', 'title');
        if(isset($body['body']) && !is_string($body['body']))
            throw new Error('VALIDATION_ERROR', 'body');
        if(isset($body['enabled']) && !is_bool($body['enabled']))
            throw new Error('VALIDATION_ERROR', 'enabled');
        
        $task = [
            ':popupid' => $body['popupid']
        ];
        
        $sql = 'UPDATE popups
                SET popupid = popupid';
        
        if(isset($body['title'])) {
            $task[':title'] = $body['title'];
            $sql .= ', title = :title';
        }
        
        if(isset($body['body'])) {
            $task[':body'] = $body['body'];
            $sql .= ', body = :body';
        }
        
        if(isset($body['enabled'])) {
            $task[':enabled'] = $body['enabled'] ? 1 : 0;
            $sql .= ', enabled = :enabled';
        }
        
        $sql .= ' WHERE popupid = :popupid
                  RETURNING 1';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        if(!$row)
            throw new Error('NOT_FOUND', 'Popup '.$body['popupid'].' not found');
    }
    
    public function createPopup($body) {
        if(!isset($body['title']))
            throw new Error('MISSING_DATA', 'title');
        if(!isset($body['body']))
            throw new Error('MISSING_DATA', 'body');
        
        if(!is_string($body['title']))
            throw new Error('VALIDATION_ERROR', 'title');
        if(!is_string($body['body']))
            throw new Error('VALIDATION_ERROR', 'body');
            
        if(isset($body['enabled']) && !is_bool($body['enabled']))
            throw new Error('VALIDATION_ERROR', 'enabled');
        
        $task = array(
            ':title' => $body['title'],
            ':body' => $body['body'],
            ':enabled' => @$body['enabled'] ? 1 : 0,
        );
        
        $sql = 'INSERT INTO popups(
                    title,
                    body,
                    enabled
                ) VALUES (
                    :title,
                    :body,
                    :enabled
                )
                RETURNING popupid';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        return [
            'popupid' => $row['popupid']
        ];
    }
    
    private function rtrPopup($row) {
        return [
            'popupid' => $row['popupid'],
            'title' => $row['title'],
            'body' => $row['body'],
            'enabled' => $row['enabled']
        ];
    }
}

?>