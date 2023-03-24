<?php

namespace UnzerPayments\Services;

use Exception;

class LogService{
    private $logDir;

    public function __construct(){
        if(defined('WC_LOG_DIR')){
            $this->logDir = WC_LOG_DIR;
        }else{
            $this->logDir = ABSPATH.'wc-content/uploads/';
        }
    }

    private function getLogFile($level){
        return $this->logDir . 'unzer-'.$level.'-'.date('Y-m-d').'.log';
    }

    public function log($message, $level = 'debug', $data = null){
        try{
            $serializedData = serialize($data);
        }catch (Exception $e){
            if(is_array($data)){
                foreach($data as $key => $value){
                    try{
                        $serializedData[$key] = serialize($value);
                    }catch (Exception $e){
                        $serializedData[$key] = gettype($value);
                    }
                }
            }
            $serializedData = serialize($serializedData);
        }
        file_put_contents($this->getLogFile($level), '['.date('Y-m-d H:i:s').'] '.$message.' | '.$serializedData."\n", 8);
    }

    public function debug($message, $data = null){
        $this->log($message, 'debug', $data);
    }

    public function warning($message, $data = null){
        $this->log('⚠ '.$message, 'debug', $data);
        $this->log($message, 'warning', $data);
    }

    public function error($message, $data = null){
        $this->log('💣 '.$message, 'debug', $data);
        $this->log($message, 'error', $data);
    }

}
