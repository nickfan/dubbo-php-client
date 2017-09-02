<?php
/**
 * Created by IntelliJ IDEA.
 * User: user
 * Date: 2017/3/8
 * Time: 17:48
 */

namespace DubboPhp\Client\Protocols;

use DubboPhp\Client\DubboPhpException;
use DubboPhp\Client\Invoker;
use Psr\Log\LoggerInterface;

class Jsonrpc extends Invoker{

    public function __construct($url=null, $debug=false,LoggerInterface $logger=null)
    {
        parent::__construct($url,$debug,$logger);
    }

    public function __call($name, $arguments)
    {
        if (!is_scalar($name)) {
            throw new DubboPhpException('Method name has no scalar value');
        }

        // check
        if (is_array($arguments)) {
            // no keys
            $params = array_values($arguments);
        } else {
            throw new DubboPhpException('Params must be given as array');
        }

        // sets notification or request task
        if ($this->notification) {
            $currentId = NULL;
        } else {
            $currentId = $this->id;
            $this->id++;
        }

        // prepares the request
        $requestParam = array(
            'method' => $name,
            'params' => $params,
            'id' => $currentId
        );
        // curl -i -H 'content-type: application/json' -X POST -d '{"jsonrpc": "2.0", "method": "hello", "params": [ "World"],"id": 1 , "version":"1.0.0"}' 'http://127.0.0.1:8080/com.dubbo.demo.HelloService'
//        echo 'curl -i -H \'content-type: application/json\' -X POST -d \''.json_encode($request).'\' \''.$this->url.'\''.PHP_EOL;
        $request = json_encode($requestParam);

        $this->debug==true && !is_null($this->logger) && $this->logger->debug('DubboRpc Call Parameters',[
            'request'=>$requestParam,
        ]);
        $curlcmd = 'curl -i -H \'content-type: application/json\' -X POST -d \''.json_encode($request).'\' \''.$this->url.'\''.PHP_EOL;
        $this->debug==true && !is_null($this->logger) && $this->logger->debug('DubboRpc Call CURL CMD: '.$curlcmd);

        //@file_put_contents('/tmp/dev.log',date('Y-m-d H:i:s').' [CURL] '.__METHOD__.'#'.__LINE__.' '.PHP_EOL.$curlcmd.PHP_EOL,FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $responseContent = curl_exec($ch);
        $curlErrorCode = curl_errno($ch);
        $curlErrorMessage = curl_error($ch);
        curl_close($ch);
        $this->debug==true && !is_null($this->logger) && $this->logger->debug('DubboRpc Call Response',[
            'error_code'=>$curlErrorCode,
            'error_msg'=>$curlErrorMessage,
            'content'=>$responseContent,
        ]);
//        echo '$curlErrorCode:'.$curlErrorCode.PHP_EOL;
//        echo '$curlErrorMessage:'.$curlErrorMessage.PHP_EOL;
//        echo '$responseContent:'.PHP_EOL.$responseContent.PHP_EOL;

        //@file_put_contents('/tmp/dev.log',date('Y-m-d H:i:s').' [CONTENT] '.__METHOD__.'#'.__LINE__.' '.PHP_EOL.$responseContent.PHP_EOL,FILE_APPEND);

        if ($responseContent === FALSE)  {
            throw new DubboPhpException('Unable to connect to '.$this->url.' :'.$curlErrorMessage,$curlErrorCode);
        }

        $response = json_decode($responseContent,true);
        $jsonDecodeErrorCode = json_last_error();
        if($jsonDecodeErrorCode!==JSON_ERROR_NONE){
            $jsonDecodeErrorMessage = json_last_error_msg();
            throw new DubboPhpException('Unable to decode response content: '.$jsonDecodeErrorMessage.' :'.$responseContent,$jsonDecodeErrorCode);
        }


        // debug output
        if ($this->debug) {
            //echo nl2br($debug);
            //@file_put_contents('/tmp/dev.log',date('Y-m-d H:i:s').' [RESP] '.__METHOD__.'#'.__LINE__.' '.PHP_EOL.var_export($response,true).PHP_EOL,FILE_APPEND);
        }

        // final checks and return
        if (!$this->notification) {
            // check
            if ($response['id'] != $currentId) {
                throw new DubboPhpException('Incorrect response id (request id: '.$currentId.', response id: '.$response['id'].')');
            }
            if (isset($response['error'])) {
                //var_dump($response);
                //@file_put_contents('/tmp/dev.log',date('Y-m-d H:i:s').' [RESP_ERR] '.__METHOD__.'#'.__LINE__.' '.PHP_EOL.var_export($response,true).PHP_EOL,FILE_APPEND);
                $responseErrorCode = isset($response['error']['code'])?$response['error']['code']:0;
                $responseErrorMessage = isset($response['error']['message'])?$response['error']['message']:'';
                throw new DubboPhpException('Response error: '.$responseErrorMessage,$responseErrorCode);
            }
            return $response['result'];

        } else {
            return true;
        }
    }

}
