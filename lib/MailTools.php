<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('PHPMailer/Exception.php');
require_once('PHPMailer/PHPMailer.php');
require_once('PHPMailer/SMTP.php');

class MailTools
{
    private $mail;
    private $body;
    private $from;
    private $htmlBody;
    private $replyTo;
    private $subject;
    private $to;

    function __construct()
    {
        $this->mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->SMTPAuth = $GLOBALS['pizza']['config']['smtpAuth'] ?? false;
        if ($this->mail->SMTPAuth)
        {
            $this->mail->Username = $GLOBALS['pizza']['config']['smtpUsername'];
            $this->mail->Password = $GLOBALS['pizza']['config']['smtpPassword'];
        }
        $this->mail->Host = $GLOBALS['pizza']['config']['smtpHost'];
        $this->mail->Port = $GLOBALS['pizza']['config']['smtpPort'];
        $this->body = '';
        $this->from = '';
        $this->htmlBody = '';
        $this->replyTo = '';
        $this->subject = '';
        $this->to = '';
    }

    function addAttachment($path, $name = '', $encoding = self::ENCODING_BASE64,
        $type = '', $disposition = 'attachment')
    {
        $this->mail->addAttachment($path, $name, $encoding, $type, $disposition);
    }

    function send()
    {
        $this->mail->Subject = $this->subject;
        if ($this->replyTo !== '')
            $this->mail->addReplyTo($this->replyTo['email'], $this->replyTo['name']);
        $this->mail->setFrom($this->from['email'], $this->from['name']);
        $this->mail->clearAllRecipients();
        $this->mail->addAddress($this->to['email'], $this->to['name']);
        if ($this->htmlBody != '')
        {
            $this->mail->isHTML(true);
            $this->mail->Body = $this->htmlBody;
            $this->mail->AltBody = $this->body;
        }
        else
        {
            $this->mail->isHTML(false);
            $this->mail->Body = $this->body;
        }
        try {
            $this->mail->clearCustomHeader('References');
            $this->mail->addCustomHeader('References', '<uid' . mt_rand(1000000, 9999999) . '@' . strtolower($GLOBALS['pizza']['config']['siteName']) . '>');
            $this->mail->send();
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }

    function setBody($body)
    {
        $this->body = str_replace("\r\n", "\n", $body);
    }

    function setFrom($from)
    {
        $this->from = $this->parseAddress($from);
    }

    function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    function setHtmlBody($htmlBody)
    {
        $this->htmlBody = str_replace("\r\n", "\n", $htmlBody);
    }

    function setReplyTo($replyTo)
    {
        $this->replyTo = $this->parseAddress($replyTo);
    }

    function setSubject($subject)
    {
        $this->subject = $subject;
    }

    function setTo($to)
    {
        $this->to = $this->parseAddress($to);
    }

    private function parseAddress($address)
    {
        $name = '';
        $email = $address;
        if (preg_match('/(^[^<]+)<([^>]+)>$/', $address, $matches))
        {
            $name = trim($matches[1]);
            $email = trim($matches[2]);
        }
        // No need to double-quote, as PHPMailer handles this.
        // // Double-quote the name if it has special characters.
        // if (preg_match('/[^a-z0-9\s_]/i', $name))
        //     $name = "\"$name\"";
        // echo $name, '<br>';
        // echo $email, '<br>';
        // exit();
        return array(
            'name' => $name,
            'email' => $email
        );
    }
}
?>
