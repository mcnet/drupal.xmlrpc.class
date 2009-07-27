<?

define('__DEBUG', 0);

define('_DEFAULT_HOST', 'example.com');
define('_DEFAULT_PORT', 80);
define('_DEFAULT_PATH', '/services/xmlrpc');

require_once("xmlrpc/xmlrpc.inc");

class drupalxmlrpc {

	static $session;
	
    function __construct( $conf = array() ) {
        $this->path = isset($conf['path']) ? $conf['path'] : _DEFAULT_PATH;
        $this->serv = isset($conf['serv']) ? $conf['serv'] : _DEFAULT_HOST;
        $this->port = isset($conf['port']) ? $conf['port'] : _DEFAULT_PORT;
        $this->send_message('system.connect');
    }
	
	function _pack_params($params = array()) {
		$message = array();
		
		for($i = 0, $sz = sizeof($params); $i < $sz; $i++) {
			$message[] = php_xmlrpc_encode( $params[$i] );
		}
		
		return $message;
	}

    function send_message($method, $message = array()) {
        $XMLRPC = $this->connect();
		
        $msg = new xmlrpcmsg($method, $message);
        $ret = $XMLRPC->send($msg);

        if(!$ret->faultCode()) {
            $answer = $ret->value();
			if (isset($answer['sessid']) && !isset(self::$session)) {
            	self::$session = $answer['sessid'];
			}
			return  $ret->value();
        } else return $ret->faultString();
    }

    function connect() {
        $conn = new xmlrpc_client($this->path, $this->serv, $this->port);
        $conn->setDebug(__DEBUG);
        $conn->return_type = "phpvals";
        return $conn;
    }

    function user_login($login, $passwd) {
		$params = array(
					self::$session,
					$login,
					$passwd
				);
		$connected = $this->send_message('user.login', $this->_pack_params($params));
		self::$session = $connected['sessid'];
        return $connected;
    }

    function node_save($user, $message) {
    	if ( mb_strlen($message, 'utf-8') == 0 ) 
			return "\n".'message is too short';
		/**
		 * Try to add user;
		 * if user exists, return uid, 
		 * else add user and return uid
		 * TODO: make less actions
		 */		
    	$user = $this->user_load( $this->user_add($user) );
		
		$node = array (
			'title' => mb_substr($message, 0, 15, 'utf-8') . '…',
			'body' => $message,
			'type' => 'blog',
			'promote' => 1,
			'uid'  => is_array($user) ? $user['uid']  : 0 ,
			'name' => is_array($user) ? $user['name'] : 'Anonymouse' ,
		);
		$msg = $this->_pack_params(array(self::$session, $node));
        return $this->send_message('node.save', $msg);
    }
	
	function user_load($uid) {
		$msg = $this->_pack_params(array(self::$session, $uid));
        return $this->send_message('user.get', $msg);
	}

	function user_add($jid) {
		$user = new stdClass();
		$user->name = $this->_get_username($jid);
		$user->pass = md5(time());
		$user->mail = $this->_get_usermail($jid);
		$user->roles = array( 2 => 'authenticated user' );
		$user->status = 1;
		
		$msg = $this->_pack_params(array(self::$session, $user));
        return $this->send_message('user.save', $msg);
	}
	
	function _get_username($jid) {
		return substr($jid, 0, strpos($jid, '@'));
	}
	
	function _get_usermail($jid) {
		$pos = strpos($jid, '/');
		return ($pos !== FALSE) ? substr($jid, 0, $pos) : $jid ;
	}

}