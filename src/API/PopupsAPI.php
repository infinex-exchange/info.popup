<?php

use Infinex\Exceptions\Error;
use function Infinex\Validation\validateId;
use React\Promise;

class PopupsAPI {
    private $log;
    private $pdo;
    
    function __construct($log, $pdo) {
        $this -> log = $log;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized popups API');
    }
    
    public function initRoutes($rc) {
        $rc -> get('/', [$this, 'getPopups']);
    }
    
    public function getPopups($path, $query, $body, $auth) {
        if(isset($query['localId']) && !validateId($query['localId']))
            throw new Error('VALIDATION_ERROR', 'localId', 400);
        
        $task = [];
        
        $sql = 'SELECT popupid,
                       title,
                       body
                FROM popups
                WHERE enabled = TRUE';
        
        if(isset($query['localId'])) {
            $task[':popupid'] = $query['localId'];
            $sql .= ' AND popupid > :popupid';
        }
        
        $sql .= ' ORDER BY popupid DESC
                  LIMIT 10';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        
        $popups = [];
        while($row = $q -> fetch()) {
            $popups[] = [
                'popupid' => $row['popupid'],
                'title' => $row['title'],
                'body' => $row['body']
            ];
        }
        
        return [
            'popups' => $popups
        ];
    }
}

?>