<?php

namespace foo\bar\biz;

class User extends \ActiveRecord\Model {
	static $hasMany = array(
		array('user_newsletters', 'className' => '\foo\bar\biz\UserNewsletter'),
		array('newsletters', 'className' => '\foo\bar\biz\Newsletter',
		      'through' => 'user_newsletters')
	);

}

class Newsletter extends \ActiveRecord\Model {
	static $hasMany = array(
		array('user_newsletters', 'className' => '\foo\bar\biz\UserNewsletter'),
		array('users', 'className' => '\foo\bar\biz\User',
		      'through' => 'user_newsletters')
	);
}

class UserNewsletter extends \ActiveRecord\Model {
	static $belongTo = array(
		array('user', 'className' => '\foo\bar\biz\User'),
		array('newsletter', 'className' => '\foo\bar\biz\Newsletter'),
	);
}

# vim: ts=4 noet nobinary
?>