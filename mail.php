<?php

/*

David Guest 2014
IT Services
University of Sussex

A simple mail client to read and send email messages using
the roundcube IMAP/SMTP libraries

*/



	//header('content-type: application/octet-stream');
	//header("Content-Disposition: attachment; filename=\"$filename\"");
	

	//header('Content-Type: text/plain; charset=UTF-8');

header("Access-Control-Allow-Origin: *");
error_reporting( error_reporting() & ~E_NOTICE & ~E_STRICT);
require_once('lib/email.php');


if((isset($_REQUEST["username"]) && isset($_REQUEST["password"]))) {

	
	$mail = new email($_REQUEST["username"], $_REQUEST["password"]);
	
	
	$action = "inbox";
	if(isset($_REQUEST["action"])) {
		switch($_REQUEST["action"]) {
			case "read": $action= "read"; break;
			case "send": $action= "send"; break;
			case "download": $action= "download"; break;
			case "token": $action= "token"; break;
			case "data": $action= "data"; break;
		}
	}
	
	if(!$mail) {
	
		header('Content-Type: text/plain; charset=UTF-8');
		echo json_encode(array("error"=>"please check your login details"));
	
	} elseif($action=="inbox") {
		
		// return a list of messages
		
		//parameters - page number, number of messages per page
		$params = array("p"=>1, "q"=>40);
		foreach(array_keys($params) as $param) {
			if(isset($_REQUEST[$param])) {
				$params[$param] = intval($_REQUEST[$param]);
			}
		}
		
		header('Content-Type: text/plain; charset=UTF-8');
		echo json_encode($mail->list_messages($params["q"], $params["p"]));

	} elseif($action=="read") {
		
		// return text of a particular message
		if(isset($_REQUEST["uid"])) {
		
			$uid = intval($_REQUEST["uid"]);
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode($mail->get_message($uid, true, true));
			
		} else {
		
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode(array("error"=>"Please specify the UID of the message"));
		
		}

	} elseif($action=="seen") {
	
		if(isset($_REQUEST["uid"])) {
		
			$getuids = explode(",", $_REQUEST["uid"]);
			$uids = array();
			foreach($getuids as $getuid) {
				$uids[] = intval($getuid);
			}
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode($mail->seen($uids));
			
		} else {
			
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode(array("error"=>"Please specify at least one UID of a message to mark as seen"));
		
		}
	
	} elseif($action=="send") {
	
		if(isset($_REQUEST["to"])) {
		
			$cc = $bcc = $subject = $body = '';
			$to = $_REQUEST["to"];
			$cc = @$_REQUEST["cc"];
			$bcc = @$_REQUEST["bcc"];
			$subject = @$_REQUEST["subject"];
			$body = @$_REQUEST["body"];
			$body = str_replace("----ampersand----", "&", $body);
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode($mail->send_message($to, $cc, $bcc, $subject, $body));
		
		} else {
		
			echo json_encode(array("error"=>"Please specify at least one recipient"));
		
		}
	} elseif($action=="download") {
		$uid = intval($_REQUEST["uid"]);
		$mime_id = intval($_REQUEST["mime_id"]);
		$filedetails = $mail->get_attachment_details($uid, $mime_id);
		if(count($filedetails)==0) {
		
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode(array("error"=>"Could not locate an attachment for those details"));
		
		} else {
			$filename = $filedetails["filename"];
			$size = $filedetails["size"];
			$mimetype = $filedetails["mimetype"];
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Type: ' . $mimetype);
			echo $mail->get_attachment($uid, $mime_id);
		}
	} elseif($action=="token") {
	
		header('Content-Type: text/plain; charset=UTF-8');
		echo json_encode($mail->login_token($_REQUEST["username"], $_REQUEST["password"]));
		
	} elseif($action=="data") {
	
		header('Content-Type: text/plain; charset=UTF-8');
		echo json_encode($mail->inbox_data());
	
	}

} else {

	header('Content-Type: text/plain; charset=UTF-8');
	echo json_encode(array("error"=>"Please specify login details"));

}


