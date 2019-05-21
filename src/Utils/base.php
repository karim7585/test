<?php

namespace Utils;

// PHPMailer (NB: to comment if !USE_PHPMailer ..)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//class BaseException extends CustomException {}

/*
 * Class Base
 *
 * @author      l.lelion
 * @description class Base
 * @package     Base
 * @since       1.0
 * @version     $Revision: 1 $
 */

class Base
{
    protected $logs = array();
    protected $sqls = array();

    protected $debug = DEV;	// mode debug


    //-------------------------------------------------------------------------
    //                            Debug
    //-------------------------------------------------------------------------

    /**
     * Màj le mode debug
     * @return	void
     */
    public function setDebugMode($debug)
    {
        $this->debug = $debug;	// TODO : vérifier IP client ...
    }

    /**
     * Renvoie le mode debug en cours
     * @return	boolean
     */
    public function isDebugMode()
    {
        return $this->debug;
    }
    

    //-------------------------------------------------------------------------
    //                            Logs
    //-------------------------------------------------------------------------

    /**
     * Initialise les logs
     * @return	void
     */
    protected function initLogs()
    {
        $this->logs = array();
    }
    
    /**
     * Ajoute une ligne aux logs
     * @param	string	$log
     * @return	void
     */
    protected function addToLog($log)
    {
        $this->logs[] = "[".date('d/m/Y H:i:s')."] ".$log;
    }

    /**
     * Renvoie les logs
     * @return	array
     */
    public function getLogs()
    {
        return $this->logs;
    }

    //-------------------------------------------------------------------------
    //                            SQL Debug
    //-------------------------------------------------------------------------

    /**
     * Stocke les requêtes
     * @param 	string 	$sql
     * @return 	void
     */
    protected function logQuery($sql)
    {
        $this->sqls[] = $sql;
    }

    /**
     * Renvoie les requêtes SQL
     * @return 	array
     */
    public function getQueries()
    {
        $this->cleanSqlRequests();
        return $this->sqls;
    }

    /**
     * Formatte la liste des requêtes SQL
     * @param 	string 	$sql
     * @return	string | void
     */
    protected function cleanSqlRequests($sql = null)
    {
        $charsToClean = array("\n", "\t", "\r");
        if (!empty($sql)) {
            return str_replace($charsToClean, " ", $sql);
        } else {
            foreach ($this->sqls as $key => $sql) {
                $this->sqls[$key] = str_replace($charsToClean, " ", $sql);
            }
        }
    }

    //-------------------------------------------------------------------------
    //                            Mail, CURL ...
    //-------------------------------------------------------------------------

    /**
     * Envoie un mail via SQL_SERVER
     * @param   $MAILParams (array)
     * @return  boolean
     */
    public function sendMail($MAILParams)
    {
        if (USE_PHPMailer) {
            return self::sendMailByPHPMailer($MAILParams);
        }

        $subject = isset($MAILParams['subject']) ? $MAILParams['subject']:"";
        $message = isset($MAILParams['message']) ? $MAILParams['message']:"";
        $recipients = isset($MAILParams['recipients']) ? $MAILParams['recipients']:null;
        $copy_recipients = isset($MAILParams['copy_recipients']) ? $MAILParams['copy_recipients']:null;
        $profile_name =	isset($MAILParams['profile_name']) ? $MAILParams['profile_name']:'ENVOI_COMMANDE';
        $file_attachments =	isset($MAILParams['file_attachments']) ? $MAILParams['file_attachments']:null;
        $body_format =	isset($MAILParams['body_format']) ? $MAILParams['body_format']:'HTML';

        if (DEV) {
            $subject = "[DEV] ". $subject;
            $message = "[DEV] ". $message;
        }

        $query = "EXEC msdb.dbo.sp_send_dbmail
	               @profile_name = '".$profile_name."',
	               @recipients = '".$recipients."',
	               @copy_recipients='".$copy_recipients."',                   
	               @file_attachments='".$file_attachments."',
	               @body = :BODY,    
	               @body_format= '".$body_format."',
	               @subject = :SUBJECT";

        /*$query = "EXEC msdb.dbo.sp_send_dbmail
                   @profile_name = '".$profile_name."',
                   @recipients = '".$recipients."',
                   @copy_recipients='".$copy_recipients."',
                   @file_attachments='".$file_attachments."',
                   @body = '".encodeToMailFormat($message)."',
                   @body_format= '".$body_format."',
                   @subject = '".utf8_decode($subject)."'";
           */

        $this->logQuery($query);

        $connection = new Connection();
        $db = $connection->getConnection();

        $exec = $db->prepare($query);
        $exec->bindValue(':BODY', encodeToMailFormat($message), PDO::PARAM_STR);
        $exec->bindValue(':SUBJECT', utf8_decode($subject), PDO::PARAM_STR);

        //$exec = $db->exec($query);

        if (!$exec->execute()) {	// KO (pb encodage ...)
            //if (false === $exec->execute()) {
            throw new Exception('['.__FUNCTION__.'] ATTENTION Il y a eu un problème lors de l\'envoi du mail ['.$subject.'] au(x) destinataire(s): '.$recipients.' MSSQL Query failed: ' . implode(" - ", $db->errorInfo()) . "[$query]");
            //throw new Exception('['.__FUNCTION__.'] MSSQL Query failed: ' . implode(" - ", $exec->errorInfo()) . "[$query]");
        }

        $message = 'Le mail ['.$subject.'] a été envoyé avec succès au(x) destinataire(s): '.$recipients;
        $this->addToLog($message);

        return $message;
    }

    /**
     * Envoie un mail via PHPMailer
     * @param   $MAILParams (array)
     * @param   $senderSettings (array)
     * @return  boolean
     */
    public function sendMailByPHPMailer($MAILParams, $senderSettings = SENDER_SETTINGS)
    {
        $subject = isset($MAILParams['subject']) ? $MAILParams['subject']:null;
        $message = isset($MAILParams['message']) ? $MAILParams['message']:null;
        $recipients = isset($MAILParams['recipients']) ? explode(";", $MAILParams['recipients']):array();
        $copy_recipients = isset($MAILParams['copy_recipients']) ? explode(";", $MAILParams['copy_recipients']):array();
        $blind_copy_recipients = isset($MAILParams['blind_copy_recipients']) ? explode(";", $MAILParams['blind_copy_recipients']):array();
        //$profile_name =	isset($MAILParams['profile_name']) ? $MAILParams['profile_name']:'ENVOI_COMMANDE';
        $file_attachments =	isset($MAILParams['file_attachments']) ? explode(";", $MAILParams['file_attachments']):array();
        $body_format =	isset($MAILParams['body_format']) ? $MAILParams['body_format']:'HTML';

        if (DEV) {
            $subject = "[DEV] ". $subject;
            $message = "[DEV] ". $message;
        }

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);	// DEV

        // UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // -- Server settings
        $mail->SMTPDebug = SMTP_DEBUG;
        $mail->isSMTP();        // Set mailer to use SMTP
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // -- Recipients
        $mail->setFrom($senderSettings['SET_FROM'], $senderSettings['SET_FROM_NAME']);	// TODO : créer adresse générique envoi

        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
        }
        foreach ($copy_recipients as $recipient) {
            $mail->addCC($recipient);
        }
        foreach ($blind_copy_recipients as $recipient) {
            $mail->addBCC($recipient);
        }
        $mail->addReplyTo($senderSettings['REPLY_TO'], $senderSettings['REPLY_TO_NAME']);

        // -- Attachments
        if (sizeof($file_attachments) > 0) {
            foreach ($file_attachments as $file) {
                $mail->addAttachment($file);
            }
        }

        // -- Content
        $mail->isHTML(true);	 // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;
        //$mail->AltBody = strip_tags($message);

        if ($mail->send()) {
            $message = 'Le mail ['.$subject.'] a été envoyé avec succès au(x) destinataire(s): '.implode(", ", $recipients);
            $this->addToLog($message);

            // -- On écrit une ligne dans vocalcom avec le statut Y
            if (LOG_EMAILS) {
                $RECORD_INDEX = isset($MAILParams['INDICE']) ? $MAILParams['INDICE']:null;
                $APPLICATION = 'PHPMailer';
                if (isset($MAILParams['APPLICATION'])) {
                    $APPLICATION .= "|".$MAILParams['APPLICATION'];
                }

                $logEMail = self::logEMail($subject, implode(";", $recipients), $message, $RECORD_INDEX, $APPLICATION);
                if ($logEMail) {
                    return $message;
                }
            }
            return $message;
        } else {
            throw new Exception('['.__FUNCTION__.'] ATTENTION Il y a eu un problème lors de l\'envoi du mail ['.$subject.'] au(x) destinataire(s): '.implode(", ", $recipients).' Mailer Error: '.$mail->ErrorInfo);
        }
    }

    /**
     * Enregistre le mail précédemment envoyé par PHPmailer dans la table FULLFILMENT (Vocalcom)
     * @param   $subject (string)
     * @param   $SEND_TO (string)
     * @param   $body (string)
     * @param   $RECORD_INDEX (int)
     * @param   $APPLICATION (string)
     * @param   $senderSettings (array)
     * @return boolean
     */
    public static function logEMail($subject, $SEND_TO, $body, $RECORD_INDEX, $APPLICATION = 'PHPMailer', $senderSettings = SENDER_SETTINGS)
    {
        //var_dump(func_get_args());

        $db = Connection::getDbConnection();

        $TYPE = "EMAIL";
        $IN_OUT = "O";
        $REQUEST_DATE = date('Ymd');
        $REQUEST_TIME = date("Hi");

        $DOCUMENT = "";
        $PARAM  = $subject."¤".$body."<br>";
        $LSTSQL = "";
        $SENDER = $senderSettings['SET_FROM'];

        $SEND_DATE = date("Ymd");
        $SEND_TIME = date("Hi");
        $SENT = 'Y';
        //$APPLICATION = 'PHPMailer';

        $exec = $db->prepare('INSERT INTO [vocalcom].[dbo].[FULLFILMENT] 
                  (TYPE, IN_OUT, REQUEST_DATE, REQUEST_TIME, RECORD_INDEX, DOCUMENT, SEND_TO, PARAM, LSTSQL, SENDER, SEND_DATE, SEND_TIME, SENT, APPLICATION )
                   VALUES (:type, :in_out, :req_date, :req_time, :record, :document, :send_to, :param, :lstsql, :sender, :SEND_DATE, :SEND_TIME, :SENT, :APPLICATION)');

        return $exec->execute(array( 'type' => $TYPE, 'in_out' => $IN_OUT, 'req_date' => $REQUEST_DATE, 'req_time' => $REQUEST_TIME, 'record' => $RECORD_INDEX, 'document' => $DOCUMENT, 'send_to' => $SEND_TO, 'param' => $PARAM, 'lstsql' => $LSTSQL, 'sender' => $SENDER, 'SEND_DATE' => $SEND_DATE, 'SEND_TIME' => $SEND_TIME, 'SENT' => $SENT,  'APPLICATION' => $APPLICATION));
    }

    /**
     * Dépose un fichier par ftp
     * @param   $FTP_CLIENT_PARAMS (array)
     * @param   $fileName (string) (chemin vers le fichier distant)
     * @param   $filePath (string) (chemin vers le fichier local)
     * @param   $customTargetDir (string)
     * @return  boolean
     */
    public function uploadFileByFTP($FTP_CLIENT_PARAMS, $fileName, $filePath, $customTargetDir = false)
    {
        $logs = array();

        //pr($FTP_CLIENT_PARAMS);

        $this->addToLog('['.__FUNCTION__.'] HOST: '.$FTP_CLIENT_PARAMS['HOST'].' CLIENT ['.$FTP_CLIENT_PARAMS['CLIENT'].']');
        $this->addToLog('['.__FUNCTION__.'] fileName: '.$fileName .' customTargetDir: '.$customTargetDir);

        // -- host (TODO : demander autorisation au client ?)
        $ftp = ftp_connect($FTP_CLIENT_PARAMS['HOST'], $FTP_CLIENT_PARAMS['PORT']);
        
        if (false === $ftp) {
            throw new Exception('['.get_class($this).'->'.__FUNCTION__.'] ATTENTION Erreur lors de la connexion sur '.$FTP_CLIENT_PARAMS['HOST'].' CLIENT ['.$FTP_CLIENT_PARAMS['CLIENT'].']');
        }

        // -- login + pwd
        if (false === ftp_login($ftp, $FTP_CLIENT_PARAMS['LOGIN'], $FTP_CLIENT_PARAMS['PASSWORD'])) {
            throw new Exception('['.__FUNCTION__.'] ATTENTION Erreur lors de l`authentification sur '.$FTP_CLIENT_PARAMS['HOST'].' CLIENT ['.$FTP_CLIENT_PARAMS['CLIENT'].']');
        }

        // -- dir
        if ($customTargetDir) {	// si rep spécifique renseigné
            $FTP_CLIENT_PARAMS['DIR'] = $customTargetDir;
        }

        if (false === ftp_chdir($ftp, $FTP_CLIENT_PARAMS['DIR'])) {
            throw new Exception('['.__FUNCTION__.'] ATTENTION Erreur lors du changement de répertoire '.$FTP_CLIENT_PARAMS['DIR'].' sur '.$FTP_CLIENT_PARAMS['HOST'].' CLIENT ['.$FTP_CLIENT_PARAMS['CLIENT'].']');
        }

        // -- dépôt du fichier
        if (false === ftp_put($ftp, $fileName, $filePath, FTP_BINARY)) {
            throw new Exception('['.__FUNCTION__.'] ATTENTION Erreur lors du dépôt du fichier '.$fileName.' dans le répertoire '.$FTP_CLIENT_PARAMS['DIR'].' sur '.$FTP_CLIENT_PARAMS['HOST'].' CLIENT ['.$FTP_CLIENT_PARAMS['CLIENT'].']');
        }
        ftp_close($ftp);
                           
        $this->addToLog('Le fichier '.$fileName.' a été chargé avec succès vers le '.$FTP_CLIENT_PARAMS['HOST'].' CLIENT ['.$FTP_CLIENT_PARAMS['CLIENT'].']');
        
        return true;
    }
}
