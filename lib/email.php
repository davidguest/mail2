<?php

/*


		JSON gateway for Sussex Mail
		
		David Guest
		University of Sussex 2014/15


*/

// use roundcube classes to interact with servers
define('RCMAIL_CHARSET', 'UTF-8');
define('DEFAULT_MAIL_CHARSET', 'ISO-8859-1');
define('RCMAIL_PREFER_HTML', false);
require_once('lib/rcube_charset.php');
require_once('lib/rcube_imap_generic.php');
require_once('lib/rcmail.php');
require_once('lib/rcube_imap.php');
require_once('lib/rcube_message.php');
require_once('lib/rcube_html2text.php');


class email {

//configurations
private $inbound='imap.my.co.uk'; //imap server address
private $outbound='smtp.my.co.uk'; //smtp address
private $ad_server='ldaps://ad.my.co.uk'; //AD server to look up user details
private $ldap_look_up='dc=ad,dc=my,dc=co,dc=uk'; //LDAP query string
private $ad_domain = 'ad_myco'; //AD domain
private $default_mail_address = '@my.co.uk'; //appended to user name 
private $sentbox='Sent Items'; //folder for saving sent items

//other variables used dynamically
private $user;
private $pass;
private $identity;
private $imap;
private $smtp;

// mails are sent with SMTP using Zend::mail if Zend is installed
// if set to false, the fallback is to send with PHP mail
private $zend=false;


// constructor (call with username and password)
function __construct($user, $pass) { 

	$this->user = $user;
	$this->pass = $pass;
	$this->identity = $this->get_person_details();
	
	$this->imap = new rcube_imap();
	$this->imap->connect($this->inbound, $this->user, $this->pass, 993, true);

}


// destructor 
function __destruct() {

	$this->imap->close();
	
}

// parse strings
function scrape($body,$start,$end) {

	$base = explode($start, $body);
	$core = explode($end, $base[1]);
	return $core[0];
	
}

// parse strings and return all instances
function scrape_all($body, $start, $end) {

	$values = array();
	if(stristr($body, $start)) {
		$base = explode($start, $body);
		for($i=1;$i<count($base);$i++) {
			if(stristr($base[$i], $end)) {
				$core = explode($end, $base[$i]);
				$values[] = $core[0];
			}
		}
	} 
	return $values;
}

// get display name for the logged in user
protected function get_person_details() {

	//login to AD to get the person details	
    $ldap = ldap_connect($this->ad_server);
    $ldap_dn = $this->ad_domain . "\\" . $this->user;

    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    $binding = @ldap_bind($ldap, $ldap_dn, $this->pass);

	//set default value
	$identity = "";
	
	//default name
	$displayname = $this->user;
	
	//default email address
	$mailaddress = $this->user . $this->default_mail_address;
	
    if ($binding) {
    
        $filter="(sAMAccountName=$this->user)";
        $result = ldap_search($ldap,$this->ldap_look_up,$filter);
        ldap_sort($ldap,$result,"sn");
        $info = ldap_get_entries($ldap, $result);
        
        //get user details
		$displayname = @$info[0]["displayname"][0];
		$mailaddress = @$info[0]["mail"][0];
        
        @ldap_close($ldap);
        
    } 
    
    $identity = $displayname . " <" . $mailaddress . ">";
    
    return $identity;
    
}


// get an individual message
function get_message($uid, $fulltext=true, $seen=true) {

	$message = array();

	// parsed & analyzed logical message
	$rcm = new rcube_message($this->imap, $uid);
	
	// message IDs
	$message["id"] = $rcm->headers->id;
	$uid = $rcm->headers->uid;
	$message["uid"] = $uid;
	
	//get adjacent message
	$message["nextup"] = $this->get_adjacent_message($uid, "up");
	$message["nextdown"] = $this->get_adjacent_message($uid, "down");

	$subject = $rcm->headers->subject;
	if(preg_match("/=\?/", $subject)) {
		//$subject = mb_decode_mimeheader($subject);
		$subject = iconv_mime_decode($subject,0,"UTF-8");
	}
	
	//subject
	$message["subject"] = $subject;
	
	//date
	$fulldate = $rcm->headers->date;
	$message["fulldate"] = $fulldate;
	$timestmp = strtotime($fulldate);
	$message["date"] = date("j M H:i", $timestmp);

	//sender
	$sender = $rcm->sender;
	$message["from"]["name"] = $sender["name"];
	$message["from"]["address"] = $sender["mailto"];

	//recipient
	$receiver = $rcm->receiver;
	$message["to"]["name"] = $receiver["name"];
	$message["to"]["address"] = $receiver["mailto"];
	
	//to and cc
	$gobackto .= ", " . $sender["string"];
	$recipientString = $receiver["string"];
	$gobackto = str_replace($recipientString, "", $gobackto);
	$gobackto_mails = $this->parse_emails($gobackto);
	$gobackto = implode(', ', $gobackto_mails);
	
	$message["recipients"]["to"] = $rcm->headers->to;
	$message["recipients"]["gobackto"] = $gobackto;
	$message_cc = $this->parse_emails($rcm->headers->cc);
	$message["recipients"]["cc"] = implode(', ', $message_cc);

	//full text if required
	if($fulltext==true) {
		$rawbody = $rcm->first_text_part();
		
		//try to cut down on white space
		$rawbody = str_replace("\r\n\r\n\r\n","\r\n\r\n",$rawbody);
		$rawbody = str_replace("\r\n\r\n\r\n","\r\n\r\n",$rawbody);
		$message["bodytext"] = $rawbody;
		
		//parse links in text
		$links = array();
		$rawbody .= " ";
		$rawbody = str_replace("&lt;", "<", $rawbody);
		$rawbody = str_replace("&gt;", ">", $rawbody);
		$formatted_links = $this->scrape_all($rawbody,"<http",">");
		foreach($formatted_links as $formatted_link) {
			$candidatelink = "http" . $formatted_link;
			if(!in_array($candidatelink, $links)) {
				$links[] = $candidatelink;
			}
		}
		$replace_these = array("-\r\n", "?\r\n", "\r","\n",">",")","]");
		foreach($replace_these as $replace_this) {
			$rawbody = str_replace($replace_this, " ", $rawbody);
		}
		$http_links = $this->scrape_all($rawbody, "http://"," ");
		foreach($http_links as $http_link) {
			$candidatelink = "http://" . rtrim($http_link, ']>.)"');
			if(!in_array($candidatelink, $links)) {
				$links[] = $candidatelink;
			}
		}
		$https_links = $this->scrape_all($rawbody, "https://"," ");
		foreach($https_links as $https_link) {
			$candidatelink = "https://" . rtrim($https_link, ']>.)"');
			if(!in_array($candidatelink, $links)) {
				$links[] = $candidatelink;
			}
		}
		//sort the links so longest are first
		usort($links, function($a, $b) {
    		return strlen($b) - strlen($a);
		});
		$message["links"] = $links;
		
		//add in emails
		$message["emails"] = $this->parse_emails($rawbody);
		
		//add uid of most recent message
		$message["mailboxtotal"] = $this->mailbox_total();
	}

	//details of any attachments
	$attachments = array();
	foreach($rcm->attachments as $attachment) {
			if($attachment->disposition == "attachment") {
					 $mime_id = $attachment->mime_id;
					 $filename = $attachment->filename;
					 $bytesize = intval($attachment->size);
				 if($bytesize>1000) {
						   $filesize = round($bytesize/1000, 0) . "kb";
					 } else {
						   $filesize = $bytesize . "b";
					 } 
					 $attachments[] = $filename . " (" . $filesize . ")";
					 $message["attachments"][] = array("name"=>$filename, "size"=>$filesize, "mime_id"=>$mime_id);
			}
	}
	
	// flags
	$flags = array("SEEN"=>false, "DELETED"=>false, "ANSWERED"=>false, "FORWARDED"=>false);
	$rc_flags = $rcm->headers->flags;
	foreach(array_keys($flags) as $flag) {
		if(isset($rc_flags[$flag])) {
			$flags[$flag] = $rc_flags[$flag];
		} 
	}
	$message["flags"] = $flags;
	if($flags["SEEN"]==false && $seen==true) {
		$this->seen(array($uid));
		$message["markedAsSeen"] = true;
	}
	
	return $message;

}

// get an attachment
function get_attachment($uid, $mime_id) {

	// parsed & analyzed logical message
	$rcm = new rcube_message($this->imap, $uid);
	return $rcm->get_part_content($mime_id);

}

// get the filename for an attachment
function get_attachment_details($uid, $mime_id) {

	$filedetails = array();

	$rcm = new rcube_message($this->imap, $uid);
	foreach($rcm->attachments as $attachment) {
			if($attachment->disposition == "attachment" && $attachment->mime_id == $mime_id) {
					 
				$filedetails = array(
							"filename"=>$attachment->filename,
							"mimetype"=>$attachment->mimetype,
							"size"=>$attachment->size
						);
				
			}
	}
	
	return $filedetails;

}



// get list of messages from the inbox
public function list_messages($pagesize, $page, $fulltext=false) {

	// number of items to fetch (per page)
	$this->imap->set_pagesize($pagesize);
	$this->imap->set_page($page);
	

	$messages = array();

	foreach($this->imap->list_headers() as $header) {

		$messages[] = $this->get_message($header->uid, $fulltext, false);
		
	}

	return $messages;

}

// get size of mailbox
private function mailbox_total() {

	// number of items to fetch (per page)
	$this->imap->set_pagesize(1);
	$this->imap->set_page(1);
	
	foreach($this->imap->list_headers() as $header) {

		$uid = $header->uid;
		
	}

	return $uid;

}

// calculate adjacent message numbers
function get_adjacent_message($uid, $direction, $count=0) {

	if($direction=="up") {
		if($uid >= $this->mailbox_total()) {
			return false;
		} else {
			$next = $uid+1;
		}
	} else {
		if($uid <= 1) {
			return false;
		} else {
			$next = $uid-1;
		}
	}
	$mailbox = $this->imap->get_mailbox_name();
	$headers = $this->imap->get_headers($next, $mailbox);
	if($headers->uid==null) {
		if($count > 25) {
			return false;
		} else {
			return $this->get_adjacent_message($next, $direction, $count+1);
		}
	} else {
		return $headers->uid;
	}
	
}

// mark messages as seen 
public function seen($uids) {

	$this->imap->set_flag($uids, 'SEEN');
	return array("seen"=>$uids);

}

// mark messages as unseen 
public function unseen($uids) {

	$this->imap->set_flag($uids, 'SEEN');
	return array("seen"=>$uids);

}

// send a message
public function send_message($to, $cc='', $bcc='', $subject='', $body='') {

	//prepare headers
	$from_addr = $this->identity;
	$from_mail = $this->parse_emails($from_addr);
	$mailheaders = "From: " . $from_addr . "\r\n";
	$to_addr = $this->parse_emails($to);
	$to_string = implode(',', $to_addr);
	if($cc != '') {
		$cc_addr = $this->parse_emails($cc);
		$mailheaders .= "Cc: " . implode(',',$cc_addr) . "\r\n";
	}
	if($bcc != '') {
		$bcc_addr = $this->parse_emails($bcc);
		$mailheaders .= "Bcc: " . implode(',', $bcc_addr) . "\r\n";
	}
	$mailheaders .= "Subject: " . $subject . "\r\n";
	$mailheaders .= "Message-Id: " . time() . "-" . $from_mail[0] . "\r\n";
	$textversion = $mailheaders . "\r\n" . $body . "\r\n";
	
	if($this->zend) {
	
		// use Zend mail to send the message with SMTP
		// if the Zend framework is installed
		Zend_Mail::setDefaultTransport(new Zend_Mail_Transport_Smtp($this->outbound));
		$mail = new Zend_Mail('UTF-8');
	
		$mail->setBodyText($body);
		$mail->setSubject($subject);
		$mail->setFrom($from_mail[0], @$from_mail[1]);
		$mail->setReplyTo($from_mail[0], @$from_mail[1]);
		foreach($to_addr as $to_per) {
			$mail->addTo($to_per);
		}
		foreach($cc_addr as $cc_per) {
			$mail->addCc($cc_per);
		}
		foreach($bcc_addr as $bcc_per) {
			$mail->addBcc($bcc_per);
		}
		$mail->send();
	
	} else {
	
		//use PHP mail if Zend framework not installed
		$random_factor = md5(time());
		$mime_boundary = "------boundary_{$random_factor}";
		$mailheaders .= "MIME-Version: 1.0\r\n" . "Content-Type: multipart/mixed;" . "boundary=\"{$mime_boundary}\"";
	
		//prepare body
		$mailbody = "\r\nThis is a multi-part message in MIME format.\r\n\r\n";
		$mailbody .= "--" . $mime_boundary . "\n";
		$mailbody .= "Content-Type: text/plain; charset=UTF-8; format=flowed\nContent-Transfer-Encoding: base64\n\n";
		$data = base64_encode($body);
		$mailbody .= $data . "\r\n\r\n";
		$mailbody .= "--" . $mime_boundary . "--\r\n";
	
		//use PHP mail to send the message
		$sendit = mail($to_string, $subject, $mailbody, $mailheaders);
	
	}
	
	
	
	
	// save a copy in the sent folder
	$messagedata = "To: " . implode(',', $to_addr) . "\r\n" . $textversion;
	$this->imap->save_message($this->sentbox, $messagedata);
	
	
	$outcome = "message sent and a copy saved in " . $this->sentbox;
	return array("result"=>$outcome);

}

//parse strings for email addresses
private function parse_emails($str) {

	$str = str_replace('"', " ", $str);
	$str = str_replace("<", " ", $str); $str = str_replace(">", " ", $str);
	$str = str_replace("&lt;", " ", $str); $str = str_replace("&gt;", " ", $str);
	$str = str_replace("[", " ", $str); $str = str_replace("]", " ", $str);
	$str = str_replace("(", " ", $str); $str = str_replace(")", " ", $str);
	
	$pattern ="/(?:[a-zA-Z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-zA-Z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";

	preg_match_all($pattern, $str, $matches);
	return $matches[0];

}

// get inbox data
public function inbox_data() {

	$data = array();
	$data["all"] = number_format($this->imap->messagecount('', 'ALL'));
	$data["unseen"] = number_format($this->imap->messagecount('', 'UNSEEN'));
	$data["recent"] = number_format($this->imap->messagecount('', 'RECENT'));
	return $data;

}

}
