<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
class TableIO
{
    public $affected_rows;
    public $error;
    public $insert_id;
    public $num_rows;

    private $errorMode;
    private $inTransaction;
    private $mysqli;
    private $result;

    function __construct($host, $user, $password, $database)
    {
        $this->errorMode = 'html';
        $this->inTransaction = false;
        try
        {
            $this->mysqli = new mysqli($host, $user, $password, $database);
        }
        catch (mysqli_sql_exception $e)
        {
            $this->exitWithError($e, 'Cannot connect to database');
        }
        try
        {
            $this->mysqli->set_charset('utf8mb4');
        }
        catch (mysqli_sql_exception $e)
        {
            $this->exitWithError($e);
        }
    }

    function beginTransaction()
    {
        $this->mysqli->begin_transaction();
        $this->inTransaction = true;
    }

    function commit()
    {
        $this->mysqli->commit();
        $this->inTransaction = false;
    }

    function escapeString($string)
    {
        return $this->mysqli->real_escape_string($string);
    }

    function getAllRecords()
    {
        return $this->result->fetch_all(MYSQLI_ASSOC);
    }

    function getNextRecord()
    {
        return $this->result->fetch_assoc();
    }

    function insertId()
    {
        return $this->mysqli->insert_id;
    }

    function inTransaction()
    {
        return $this->inTransaction;
    }

    function prepare($q)
    {
        return $this->mysqli->prepare($q);
    }

    function query($q, $showError = true)
    {
        $this->affected_rows = 0;
        $this->num_rows = 0;
        $q = trim($q);
        try
        {
            $this->result = $this->mysqli->query($q);
        }
        catch (mysqli_sql_exception $e)
        {
            if ($showError)
                $this->exitWithError($e);
            return false;
        }
        $this->affected_rows = $this->mysqli->affected_rows;
        if (is_a($this->result, 'mysqli_result'))
            $this->num_rows = $this->result->num_rows;
        $this->insert_id = $this->mysqli->insert_id;
        return true;
    }

    function rollBack()
    {
        $this->mysqli->rollback();
        $this->inTransaction = false;
    }

    function setErrorMode($mode = 'html')
    {
        if (($mode != 'html') && ($mode != 'json')) return;
        $this->errorMode = $mode;
    }

    private function exitWithError($e, $altMessage = '')
    {
        $code = $e->getCode();
        $message = $altMessage == '' ? $e->getMessage() : $altMessage;
        if ($this->errorMode == 'json')
        {
            http_response_code(400);
            echo json_encode(array(
                'code' => $code,
                'message' => $message
            ));
            exit();
        }
        // errorMode is 'html'
        if (isset($_SERVER['HTTP_HOST']))
        {
            $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <body>
            <h1>Database Error</h1>
            <div class="error-code">$code</div>
            <div class="error-message">$message</div>
            </body>
            </html>
            HTML;
            echo $html;
        }
        else
        {
            echo "Database Error\n$code\n$message\n";
        }
        exit();
    }
}
?>
