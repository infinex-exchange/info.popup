<?php

use Infinex\Exceptions\Error;
use Infinex\Pagination;
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
        $pag = new Pagination\Cursor('popupid', false, 50, 500, $query);
        
        $sql = 'SELECT popupid,
                       title,
                       body
                FROM popups
                WHERE enabled = TRUE'
             . $pag -> sql();
        
        $q = $this -> pdo -> query($sql);
        
        $popups = [];
        while($row = $q -> fetch()) {
            if($pag -> iter($row)) break;
            $popups[] = [
                'popupid' => $row['popupid'],
                'title' => $row['title'],
                'body' => $row['body']
            ];
        }
        
        return [
            'popups' => $popups,
            'cursor' => $pag -> cursor
        ];
    }
}

?>