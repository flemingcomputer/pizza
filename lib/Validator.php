<?php
// Copyright 2025 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Validator
{
    public $error;
    public $method;
    public $submittedIndex;

    private $input;

    function __construct($method = 'post')
    {
        $this->error = false;
        $this->submittedIndex = false;
        $this->method = $method;
        $this->setMethod($method);
    }

    function submitted($name)
    {
        if (!array_key_exists($name, $this->input)) return false;
        $this->submittedIndex = false;
        if (is_array($this->input[$name]))
        {
            // The name of the submitted element has an index; e.g. myButton[42].
            // As far as I know, this array is always of length one, and the
            // one and only key is the index of interest.
            $keys = array_keys($this->input[$name]);
            $this->submittedIndex = $keys[0];
        }
        return true;
    }

    function checkCaptcha($field)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        // Treat challenge as case-insensitive and up to 20 characters.
        $v = strtolower(substr($v, 0, 20));
        $e = '';
        if ($v == '') $e = 'please provide';
        else if (strtolower($v) != strtolower(sge('captcha-code', ''))) $e = 'incorrect code';
        return $this->result('', $e);
    }

    function checkCheckbox($field)
    {
        $v = $this->get($field);
        return $this->result($v !== false, '');
    }

    function checkDate($field)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        $e = '';
        $t = strtotime($v);
        if (($t === false) || ($t == -1)) $e = 'invalid date';
        return $this->result($v, $e);
    }

    function checkDateTime($format, $field)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        $e = '';
        $dateTime = DateTime::createFromFormat($format, $v);
        $r = $dateTime && $dateTime->format($format) === $v;
        if ($r === false) $e = 'invalid date';
        return $this->result($v, $e);
    }

    function checkEmail($field)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        $e = '';
        if ($v == '') $e = 'please provide';
        else if (!filter_var($v, FILTER_VALIDATE_EMAIL))
        {
            $e = 'invalid email';
            $v = substr($v, 0, 255);
        }
        return $this->result($v, $e);
    }

    function checkInteger($field, $min = 'nan', $max = 'nan')
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        $e = '';
        if ($v == '') $e = 'please provide';
        else if (
            (($min !== 'nan') && ($max !== 'nan')) &&
            (!is_numeric($v) || !(intval($v) == $v) || ($v < $min) || ($v > $max))
        )
            $e = "between $min and $max please";
        else if (
            (($min === 'nan') && ($max !== 'nan')) &&
            (!is_numeric($v) || !(intval($v) == $v) || ($v > $max))
        )
            $e = "$max or less please";
        else if (
            (($min !== 'nan') && ($max === 'nan')) &&
            (!is_numeric($v) || !(intval($v) == $v) || ($v < $min))
        )
            $e = "$min or greater please";
        else if (!is_numeric($v) || !(intval($v) == $v))
            $e = 'must be an integer';
        if ($e != '') $v = substr($v, 0, strlen($max) + 1);
        return $this->result($v, $e);
    }

    function checkLength($field, $min, $max)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        if ((strlen($v) < $min) || (strlen($v) > $max))
        {
            if ($v == '') $e = 'please provide';
            else if ($min == $max)
            {
                $e = '1 character please';
                if ($min > 1) $e = "$min characters please";
            }
            else $e = "$min to $max characters please";
            $v = substr($v, 0, $max + 1);
            return $this->result($v, $e);
        }
        if (func_num_args() > 3)
        {
            $modifierFunctions = array_slice(func_get_args(), 3);
            foreach ($modifierFunctions as $mf)
            {
                if ($mf == 'normalize') $v = preg_replace('/\s\s+/', ' ', $v);
                else $v = $mf($v);
            }
        }
        return $this->result($v, '');
    }

    function checkMoney($field, $mustBePositive = true, $required = true)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        if (substr($v, 0, 1) == '$') $v = trim(substr($v, 1));
        $e = '';
        if (($v == '') && ($required === false))
            return $this->result($v, $e);
        if ($v == '') $e = 'please provide';
        else if (strlen($v) > 14)
        {
            $v = substr($v, 0, 14);
            $e = '1 to 14 characters please';
        }
        else if (!is_numeric($v)) $e = 'not a number';
        else if (round($v, 2) != $v) $e = 'dollars and cents please';
        else if (($mustBePositive !== false) && ($v < 0)) $e = 'must be positive';
        return $this->result($v, $e);
    }

    function checkNewPassword($field1)
    {
        $password = $this->get($field1, false);
        if ($password === false) return $this->result('', 'not defined');
        // MUST be 8-50 characters and have at least one digit,
        // uppercase and lowercase letter, and CAN contain !@#$%&*
        $p = '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[a-zA-Z0-9!@#$%&*]{8,50}$/';
        $v = ''; $e = '';
        if (!preg_match($p, $password))
            $e = '8-50 characters with at least one digit, lowercase, and uppercase letter';
        if ($e == '') $v = $password;
        return $this->result($v, $e);
    }

    function checkPhone($field)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        $e = '';
        if ($v == '') $e = 'please provide';
        else
        {
            if (strlen($v) > 30) $e = 'less than 30 characters';
            else  // it is 1 to 30 characters
            {
                // Determine if a phone extension has been supplied.
                // This is because we are looking for (at least)
                // 10 digits, but not beyond the 'ex'.
                $extPosition = strpos(strtolower($v), 'ex');
                if ($extPosition === false) $limit = strlen($v);
                else $limit = $extPosition;
                // Build up a string of just the digits.
                $phoneNumber = '';
                for ($i = 0; $i < $limit; $i++)
                {
                    $c = $v[$i];
                    if ((ord($c) >= 48) && (ord($c) <= 57)) $phoneNumber .= $c;
                }
                if (strlen($phoneNumber) < 10) $e = 'at least 10 digits with area code';
            }
        }
        if ($e != '') $v = substr($v, 0, 31);
        return $this->result($v, $e);
    }

    function checkRadio($field)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        return $this->result($v, '');
    }

    function checkUploadedFile($field, $i)
    {
        $v = $_FILES[$field];
        $fileName = $v['name'][$i];
        $fileType = $v['type'][$i];
        $fileSize = $v['size'][$i];
        $tmpName = $v['tmp_name'][$i];
        $error = $v['error'][$i];
        $e = '';
        if ($error == UPLOAD_ERR_NO_FILE) $e = 'no file given';
        else if (($error == UPLOAD_ERR_INI_SIZE) || ($error == UPLOAD_ERR_FORM_SIZE))
            $e = 'file is too large (' . ini_get('upload_max_filesize') . ' max)';
        else if ($error == UPLOAD_ERR_PARTIAL) $e = 'partial file upload error';
        else if ($error != UPLOAD_ERR_OK) $e = 'file upload error';
        else if (!is_uploaded_file($tmpName)) $e = 'failed to upload file';
        else if ($fileSize == 0) $e = 'file does not exist or is empty';
        return $this->result($v, $e);
    }

    function checkUrl($field, $min, $max)
    {
        $v = $this->get($field);
        if ($v === false) return $this->result('', 'not defined');
        if (($min == 0) && ($v == '')) return $this->result($v, '');
        if (
            ($v != '')
            && (substr($v, 0, 6) != 'ftp://')
            && (substr($v, 0, 7) != 'http://')
            && (substr($v, 0, 8) != 'https://')
        )
            $v = "http://$v";
        if ((strlen($v) < $min) || (strlen($v) > $max))
        {
            if ($v == '') $e = 'please provide';
            else if ($min == $max)
            {
                $e = '1 character please';
                if ($min > 1) $e = "$min characters please";
            }
            else $e = "$min to $max characters please";
            $v = substr($v, 0, $max + 1);
            return $this->result($v, $e);
        }
        $n = preg_match('/^(ftp|http|https):\/\/[a-z0-9\-]+(\.[a-z0-9\-]+)+(\/.*)?$/i', $v);
        if ($n == 0)
        {
            $v = substr($v, 0, $max + 1);
            return $this->result($v, 'invalid URL');
        }
        return $this->result($v, '');
    }

    function reset()
    {
        $this->error = false;
    }

    function setMethod($method)
    {
        if ($method == 'get') $this->input = $_GET;
        else if ($method == 'post') $this->input = $_POST;
        else if ($method == 'none') $this->input = false;
        else throw new Exception("Invalid input method: must be 'get', 'post', or 'none'.");
    }

    private function get($field, $trim = true)
    {
        if ($this->input === false)
        {
            if ($trim === true) return trim($field);
            return $field;
        }
        if (!isset($this->input[$field])) return false;
        if ($trim === true) return trim($this->input[$field]);
        return $this->input[$field];
    }

    private function result($value, $error)
    {
        if ($error != '') $this->error = true;
        return array('value' => $value, 'error' => $error);
    }
}
?>
