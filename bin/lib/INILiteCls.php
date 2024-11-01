<?php
/**
 * Copyright (C) 2007 INICIS Inc.
 *
 * 해당 라이브러리는 절대 수정되어서는 안됩니다.
 * 임의로 수정된 코드에 대한 책임은 전적으로 수정자에게 있음을 알려드립니다.
 *
 * @version         2.0
 * @author          ddaemiri
 * @date            2007.02.23
 * @note            first release
 *
 * @version         2.1
 * @author          ddaemiri
 * @date            2009.01.16
 * @note            first release
 *
 * @version         3.0
 * @author          ddaemiri
 * @date            2009.04.17
 * @note            add INICIS ESCROW
 *
 * @version         3.1
 * @author          ddaemiri
 * @date            2009.09.17
 * @note            add 구매확인/거절 응답플래그 추가
 */

/*GLOBAL*/
define("PROGRAM", "INILite");
define("VERSION", "3.1");
define("BUILDDATE", "20090917");
define("TID_LEN", 40);
define("KEY_LEN", 24);
  
/*HTTP SERVER INFO*/
//----------------
//TEST
//----------------
//define("HTTP_SERVER", "ts.inicis.com");
//define("HTTP_PORT", 18080);
//define("HTTP_SERVER", "inilite3.inicis.com");
//define("HTTP_PORT", 80);
//define("HTTP_SSL_SERVER", "inilite3.inicis.com");
//define("HTTP_SSL_PORT", 443);
//----------------
//PRODUCTION
//----------------
define("HTTP_SERVER", "inilite.inicis.com");
define("HTTP_PORT", 80);
define("HTTP_SSL_SERVER", "inilite.inicis.com");
define("HTTP_SSL_PORT", 443);
//----------------
//ACTION URI
//----------------
define("HTTP_SECUREPAY_URI", "/inipayR");
define("HTTP_CANCEL_URI", "/inicancelR");
define("HTTP_ESCROW_DELIVERY_URI", "/iniescrowDeliveryR");
define("HTTP_ESCROW_CONFIRM_URI", "/iniescrowConfirmR");
define("HTTP_ESCROW_DENYCONFIRM_URI", "/iniescrowDenyConfirmR");
define("HTTP_REPAY_URI", "/inirepayR");
define("HTTP_RECEIPT_URI", "/inireceiptR");

/*TIMEOUT*/
define("TIMEOUT_CONNECT", 5);
define("TIMEOUT_READ", 25);

/*LOG LEVEL*/
define("CRITICAL", 1);
define("ERROR", 2);
define("NOTICE", 3);
define("INFO", 5);
define("DEBUG", 7);

/*HTTP CALL ERROR CODE*/
define("READ_TIMEOUT_ERR", 1000);

/*INILite ERROR CODE*/
define("ERR_MAKETID", 9401);
define("ERR_OPENLOG", 9406);

define("ERR_NULL_MID", 9202);
define("ERR_NULL_TID", 9204);
define("ERR_NULL_GOODNAME", 9226);
define("ERR_NULL_PRICE", 9226);
define("ERR_NULL_BUYERNAME", 9226);
define("ERR_NULL_BUYEREMAIL", 9226);
define("ERR_NULL_BUYERETEL", 9226);

define("ERR_MAKEENCRYPT", 9999);
define("ERR_SSLCONN", 9999);
define("ERR_CONN", 9999);
define("ERR_RESPONSE", 9999);
define("ERR_XML", 9999);

/*-----------------------------------------------------*/
/* Global Function                                     */
/*-----------------------------------------------------*/
function Base64Encode( $str )
{   
  return substr(chunk_split(base64_encode( $str ),64,"\n"),0,-1)."\n";
}   
function GetMicroTime()
{
    list($usec, $sec) = explode(" ", microtime(true));
    return (float)$usec + (float)$sec;
}
function SetTimestamp()
{
    $m = explode(' ',microtime());
    list($totalSeconds, $extraMilliseconds) = array($m[1], (int)round($m[0]*1000,3));
    return date("Y-m-d H:i:s", $totalSeconds) . ":$extraMilliseconds";
}

/*-----------------------------------------------------*/
/* LOG Class			                               */
/*-----------------------------------------------------*/
class INILog 
{
	var $handle;
	var $type;
	var $log;
	var $debug_mode;
	var	$array_key;
	var $debug_msg;
	var $starttime;

  function INILog( $log, $mode, $type )
  {
		$this->debug_msg = array( "", "CRITICAL", "ERROR", "NOTICE", "4", "INFO", "6", "DEBUG", "8"  );
		$this->debug_mode = $mode;
		$this->type = $type;
		$this->log = $log;
		$this->starttime=GetMicroTime();
	}
  function StartLog($dir, $mid) 
	{
		if( $this->log == "false" ) return true;

		$logfile = $dir. "/log/".PROGRAM."_".$this->type."_".$mid."_".date("ymd").".log";
		$this->handle = fopen( $logfile, "a+" );
		if( !$this->handle )
		{
			return false;
		}
		$this->WriteLog( INFO, "START ".PROGRAM." ".$this->type." (V".VERSION."B".BUILDDATE."(OS:".php_uname('s').php_uname('r').",PHP:".phpversion()."))" );
		return true;
	}
  function WriteLog($debug, $data) 
	{
		if( !$this->handle || $this->log == "false" ) return;
		if( !$this->debug_mode && $debug >= DEBUG ) return;

    $pfx = $this->debug_msg[$debug]." [" . date("Y-m-d H:i:s") . "] <" . getmypid() . "> ";
    if( is_array( $data ) )
    {
        foreach ($data as $key => $val)
        {
						if( $key == "key" )
            	fwrite( $this->handle, $pfx . $key . ":[" . substr_replace($val, '******', 2, 6) . "]\r\n");
						else
            	fwrite( $this->handle, $pfx . $key . ":[" . $val . "]\r\n");
        }
    }
    else
    {
        fwrite( $this->handle, $pfx . $data . "\r\n" );
    }
    fflush( $this->handle );
	}
  function CloseLog($msg)
  {
		if( $this->log == "false" ) return;

		$laptime=GetMicroTime()-$this->starttime;
		$this->WriteLog( INFO, "END ".$this->type." ".$msg ." Laptime:[".round($laptime,3)."sec]" );
		$this->WriteLog( INFO, "===============================================================" );
    fclose( $this->handle );
  }

}

/*-----------------------------------------------------*/
/* Util Class			                               */
/* -TID Generate Function	                           */
/* -Data Encrypt Function	                           */
/* -Check Field Function	                           */
/*-----------------------------------------------------*/
class INIUtil 
{
  function CheckField( $data, &$err_code, &$err_field )
	{
		if( !$data["goodname"] )
		{
			$err_code = ERR_NULL_GOODNAME;
			$err_field = "goodname";
			return false;
		}
		if( !$data["buyername"] )
		{
			$err_code = ERR_NULL_BUYERNAME;
			$err_field = "buyername";
			return false;
		}
		if( !$data["buyeremail"] )
		{
			$err_code = ERR_NULL_BUYEREMAIL;
			$err_field = "buyeremail";
			return false;
		}
		if( !$data["buyertel"] )
		{
			$err_code = ERR_NULL_BUYERTEL;
			$err_field = "buyertel";
			return false;
		}
		return true;
	}

  function MakeTID( $pgId, $mid, &$tid )
  {
    list($usec, $sec) = explode(" ", microtime());
    $datestr = date("YmdHis", $sec).substr($usec,2,3); //YYYYMMDDHHMMSSSSS
    $tid = $pgId . $mid . $datestr . rand(100,999);
    if( strlen( $tid ) == TID_LEN )
		{
			return true;
		}
    return false;
  }

  function MakeEncrypt($pt, $key, &$ct)
  {
    $key = base64_decode($key);
		if( strlen($key) != KEY_LEN )
		{
			return false;
		}
    if (get_magic_quotes_gpc())
    {
      $key = stripslashes($key);
      $pt = stripslashes($pt);
    }
    $ct = chunk_split(base64_encode( $this->Encrypt ( $key, $pt )), 64, "\n");
    $ct = substr($ct, 0, -1); // Eliminate unnecessary \n
    return true;
  }

    function Encrypt ($pwd, $data)
    {
        $key[] = '';
        $box[] = '';
        $cipher = '';

        $pwd_length = strlen($pwd);
        $data_length = strlen($data);

        for ($i = 0; $i < 256; $i++)
        {
            $key[$i] = ord($pwd[$i % $pwd_length]);
            $box[$i] = $i;
        }
        for ($j = $i = 0; $i < 256; $i++)
        {
            $j = ($j + $box[$i] + $key[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $data_length; $i++)
        {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $k = $box[(($box[$a] + $box[$j]) % 256)];
            $cipher .= chr(ord($data[$i]) ^ $k);
        }
        return $cipher;
    }
}

/*-----------------------------------------------------*/
/* Http Proxy Class		                               */
/* HTTP												   */ 
/* HTTPS( PHP5.1.4 & OpenSSL 필요)               	   */
/*-----------------------------------------------------*/
class HttpClient 
{
    var $sock=0;
    var $host;
    var $port;
    var $ssl;
    var $status;
    var $headers="";
    var $body="";
    var $reqeust;
	var $errorcode;
	var $errormsg;

    function HttpClient($ssl) 
	{
		if( $ssl == "true" )
		{	
            $this->host = HTTP_SSL_SERVER;
            $this->port = HTTP_SSL_PORT;
            $this->ssl = "ssl://";
		}
        else
        {
            $this->host = HTTP_SERVER;
            $this->port = HTTP_PORT;
        }
    }

	function HttpConnect($INILog)
	{
        if (!$this->sock = @fsockopen( $this->ssl.$this->host, $this->port, $errno, $errstr, TIMEOUT_CONNECT)) 
		{
			$this->errorcode = $errno;
            switch($errno) 
			{
                case -3:
                    $this->errormsg = 'Socket creation failed (-3)';
                case -4:
                    $this->errormsg = 'DNS lookup failure (-4)';
                case -5:
                    $this->errormsg = 'Connection refused or timed out (-5)';
                default:
                    $this->errormsg = 'Connection failed ('.$errno.')';
                $this->errormsg .= ' '.$errstr;
            }
			return false;
        }
		$INILog->WriteLog( INFO, $this->ssl.$this->host.":".$this->port." Server Connect OK" );
		return true;
	}
	function HttpRequest($uri, $data, $INILog)
	{
    	$this->headers="";
    	$this->body="";

		$postdata = $this->buildQueryString($data);

		/*Write*/
		$request  = "POST ".$uri." HTTP/1.0\r\n";
		$request .= "Connection: close\r\n";
		$request .= "Host: ".$this->host."\r\n";
		$request .= "Content-type: application/x-www-form-urlencoded\r\n";
		$request .= "Content-length: ".strlen($postdata)."\r\n";
		$request .= "Accept: */*\r\n";
		$request .= "\r\n";
		$request .= $postdata."\r\n";
		$request .= "\r\n";
		fwrite($this->sock, $request);

		$INILog->WriteLog( DEBUG, "MSG_TO_SVR::[".$request."]" );

		/*Read*/
		stream_set_blocking($this->sock, FALSE ); 

		$atStart = true;
		$IsHeader = true;
		$timeout = false;
		$start_time= time();
		while ( !feof($this->sock) && !$timeout )
		{
			$line = fgets($this->sock, 4096);
			$diff=time()-$start_time;
			if( $diff >= TIMEOUT_READ )
			{
				$timeout = true;
			}
			if( $IsHeader )
			{
				if( $line == "" ) //for stream_set_blocking
				{
					continue;
				}
				if( substr( $line, 0, 2 ) == "\r\n" )  //end of header
				{
					$IsHeader = false;
					continue;
				}
  				$this->headers .= $line;
            	if ($atStart) 
				{
                	$atStart = false;
                	if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) 
					{
                    	$this->errormsg = "Status code line invalid: ".htmlentities($line);
						fclose( $this->sock );
                    	return false;
                	}
                	$http_version = $m[1];
                	$this->status = $m[2];
                	$status_string = $m[3];
                	continue;
				}
            }
			else
			{
  				$this->body .= $line;
			}
		}
		fclose( $this->sock );

		if( $timeout )
		{
			$this->errorcode = READ_TIMEOUT_ERR;
            $this->errormsg = "Socket Timeout(".$diff."SEC)";
			$INILog->WriteLog( ERROR, $this->errormsg );
			return false;
		}

		$INILog->WriteLog( DEBUG, "MSG_FROM_SVR(Header)[".$this->headers."]" );
		$INILog->WriteLog( DEBUG, "MSG_FROM_SVR(Body)[".$this->body."]" );
		return true;
	}

    function buildQueryString($data) 
	{
        $querystring = '';
        if (is_array($data)) 
		{
            foreach ($data as $key => $val) 
			{
                if (is_array($val)) 
				{
                    foreach ($val as $val2) 
					{
												if( $key != "key" )
                        	$querystring .= urlencode($key).'='.urlencode($val2).'&';
                    }
                } 
				else 
				{
										if( $key != "key" )
                    	$querystring .= urlencode($key).'='.urlencode($val).'&';
                }
            }
            $querystring = substr($querystring, 0, -1);
        } 
		else 
		{
            $querystring = $data;
        }
        return $querystring;
    }
	function NetCancel()
	{
		return true;
	}
    function getStatus() 
	{
        return $this->status;
    }
    function getBody() 
	{
        return $this->body;
    }
    function getHeaders() 
	{
        return $this->headers;
    }
    function getErrorMsg() 
	{
        return $this->errormsg;
    }
    function getErrorCode() 
	{
        return $this->errorcode;
    }
}

/*-----------------------------------------------------*/
/* XML Parser Class 	                               */
/* Script 버전( NOT USE libxml )                       */
/*-----------------------------------------------------*/
class XMLParser 
{
	var $node0;
	var $node1;
	var $node2;
	var $xmldata;
    function XMLParser()
	{
		$node0="";
		$node1="";
		$node2="";
	}
    function setNData($node0, $node1, $node2)
	{
		$this->node0 = "";
		$this->node1 = "";
		$this->node2 = "";
		$this->node0 = $node0;
		$this->node1 = $node1;
		$this->node2 = $node2;
	}
    function getXMLData( $name )
	{
		if( $this->node0 )
			$xmldata = $this->xmldata["$this->node0"][0]["body"]["$name"][0]["body"];
		if( $this->node1 )
			$xmldata = $this->xmldata["$this->node0"][0]["body"]["$this->node1"][0]["body"]["$name"][0]["body"];
		if( $this->node2 )
			$xmldata = $this->xmldata["$this->node0"][0]["body"]["$this->node1"][0]["body"]["$this->node2"][0]["body"]["$name"][0]["body"];
		return $xmldata;
	}
    function existNData( $node0, $node1="", $node2="" )
    {
		$this->node0 = "";
		$this->node1 = "";
		$this->node2 = "";
		$this->node0 = $node0;
		if( $node1 != "" && $node2 != "")
		{
			$this->node1 = $node1;
			$this->node2 = $node2;
        	return is_array( $this->xmldata["$node0"][0]["body"]["$node1"][0]["body"]["$node2"][0]["body"]  );
		}
		else if( $node1 != "" && $node2 == "")
		{
			$this->node1 = $node1;
        	return is_array( $this->xmldata["$node0"][0]["body"]["$node1"][0]["body"] );
		}
		else if( $node1 == "")
		{
        	return is_array( $this->xmldata["$node0"][0]["body"] );
		}
    }
    function Xml2Array($string, $parser_str = '\n')
    {
        $match_ele_exp = '/<(\S+)([^>]*)>(.*?)<\/\\1>/s';
        $match_att_exp = "/(\S+)\=$parser_str([^$parser_str]*)$parser_str/s";
        preg_match_all($match_ele_exp, $string, $match);
        for($i=0,$count=count($match[1]);$i<$count;$i++)
        {
            $key = $i;
            $val = $match[1][$i];
            $tmparray=array();
            preg_match_all($match_att_exp, $match[2][$i], $matchatt);
            for($s=0,$scount=count($matchatt[1]);$s<$scount;$s++)
                $tmparray[$matchatt[1][$s]] = $matchatt[2][$s];

            $match[3][$key] = trim($match[3][$key]);
            if(eregi("^<!\[CDATA\[(.*)\]\]>$",$match[3][$key],$cdatatmp))
            {
                $match[3][$key] = $cdatatmp[1];
                if($tmparray) $arr[$val][] = array("att"=>$tmparray, "body" => $match[3][$key]);
                else $arr[$val][] = array("body" => $match[3][$key]);
            }
            else if (preg_match($match_ele_exp, $match[3][$key]) )
            {
                if($tmparray) $arr[$val][] = array("att"=>$tmparray, "body" => $this->Xml2Array($match[3][$key],$parser_str));
                else $arr[$val][] = array("body" => $this->Xml2Array($match[3][$key],$parser_str));
            }
            else
            {
                if($tmparray) $arr[$val][] = array("att"=>$tmparray, "body" => $match[3][$key]);
                else $arr[$val][] = array("body" => $match[3][$key]);
            }
        }
		return $arr;
    }

}

?>

