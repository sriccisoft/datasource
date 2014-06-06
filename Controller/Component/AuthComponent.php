<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Error;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Network\Session;
use Cake\Routing\Router;
use Cake\Utility\Debugger;
use Cake\Utility\Hash;
use Cake\Utility\Security;

/**
 * Authentication control component class
 *
 * Binds access control with user authentication and session management.
 *
 * @link http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html
 */
class AuthComponent extends Component {

/**
 * Constant for 'all'
 *
 * @var string
 */
	const ALL = 'all';

/**
 * Default config
 *
 * - `authenticate` - An array of authentication objects to use for authenticating users.
 *   You can configure multiple adapters and they will be checked sequentially
 *   when users are identified.
 *
 *   {{{
 *   $this->Auth->config('authenticate', [
 *      'Form' => [
 *         'userModel' => 'Users.Users'
 *      ]
 *   ]);
 *   }}}
 *
 *   Using the class name without 'Authenticate' as the key, you can pass in an
 *   array of config for each authentication object. Additionally you can define
 *   config that should be set to all authentications objects using the 'all' key:
 *
 *   {{{
 *   $this->Auth->config('authenticate', [
 *       AuthComponent::ALL => [
 *          'userModel' => 'Users.Users',
 *          'scope' => ['Users.active' => 1]
 *      ],
 *     'Form',
 *     'Basic'
 *   ]);
 *   }}}
 *
 * - `authorize` - An array of authorization objects to use for authorizing users.
 *   You can configure multiple adapters and they will be checked sequentially
 *   when authorization checks are done.
 *
 *   {{{
 *   $this->Auth->config('authorize', [
 *      'Crud' => [
 *          'actionPath' => 'controllers/'
 *      ]
 *   ]);
 *   }}}
 *
 *   Using the class name without 'Authorize' as the key, you can pass in an array
 *   of config for each authorization object. Additionally you can define config
 *   that should be set to all authorization objects using the AuthComponent::ALL key:
 *
 *   {{{
 *   $this->Auth->config('authorize', [
 *      AuthComponent::ALL => [
 *          'actionPath' => 'controllers/'
 *      ],
 *      'Crud',
 *      'CustomAuth'
 *   ]);
 *   }}}
 *
 * - `ajaxLogin` - The name of an optional view element to render when an Ajax
 *   request is made with an invalid or expired session.
 *
 * - `flash` - Settings to use when Auth needs to do a flash message with
 *   Session::flash(). Available keys are:
 *
 *   - `key` - The message domain to use for flashes generated by this component, defaults to 'auth'.
 *   - `params` - The array of additional params to use, defaults to []
 *
 * - `loginAction` - A URL (defined as a string or array) to the controller action
 *   that handles logins. Defaults to `/users/login`.
 *
 * - `loginRedirect` - Normally, if a user is redirected to the `loginAction` page,
 *   the location they were redirected from will be stored in the session so that
 *   they can be redirected back after a successful login. If this session value
 *   is not set, redirectUrl() method will return the URL specified in `loginRedirect`.
 *
 * - `logoutRedirect` - The default action to redirect to after the user is logged out.
 *   While AuthComponent does not handle post-logout redirection, a redirect URL
 *   will be returned from `AuthComponent::logout()`. Defaults to `loginAction`.
 *
 * - `authError` - Error to display when user attempts to access an object or
 *   action to which they do not have access.
 *
 * - `unauthorizedRedirect` - Controls handling of unauthorized access.
 *
 *   - For default value `true` unauthorized user is redirected to the referrer URL
 *     or `$loginRedirect` or '/'.
 *   - If set to a string or array the value is used as a URL to redirect to.
 *   - If set to false a `ForbiddenException` exception is thrown instead of redirecting.
 *
 * @var array
 */
	protected $_defaultConfig = [
		'authenticate' => null,
		'authorize' => null,
		'ajaxLogin' => null,
		'flash' => null,
		'loginAction' => null,
		'loginRedirect' => null,
		'logoutRedirect' => null,
		'authError' => null,
		'unauthorizedRedirect' => true
	];

/**
 * Other components utilized by AuthComponent
 *
 * @var array
 */
	public $components = array('RequestHandler');

/**
 * Objects that will be used for authentication checks.
 *
 * @var array
 */
	protected $_authenticateObjects = array();

/**
 * Objects that will be used for authorization checks.
 *
 * @var array
 */
	protected $_authorizeObjects = array();

/**
 * The session key name where the record of the current user is stored. Default
 * key is "Auth.User". If you are using only stateless authenticators set this
 * to false to ensure session is not started.
 *
 * @var string
 */
	public $sessionKey = 'Auth.User';

/**
 * The current user, used for stateless authentication when
 * sessions are not available.
 *
 * @var array
 */
	protected $_user = array();

/**
 * Controller actions for which user validation is not required.
 *
 * @var array
 * @see AuthComponent::allow()
 */
	public $allowedActions = array();

/**
 * Request object
 *
 * @var \Cake\Network\Request
 */
	public $request;

/**
 * Response object
 *
 * @var \Cake\Network\Response
 */
	public $response;

/**
 * Instance of the Session object
 *
 * @return void
 */
	public $session;

/**
 * Method list for bound controller.
 *
 * @var array
 */
	protected $_methods = array();

/**
 * The instance of the Authenticate provider that was used for
 * successfully logging in the current user after calling `login()`
 * in the same request
 *
 * @var Cake\Auth\BaseAuthenticate
 */
	protected $_authenticationProvider;

/**
 * The instance of the Authorize provider that was used to grant
 * access to the current user to the url they are requesting.
 *
 * @var Cake\Auth\BaseAuthorize
 */
	protected $_authorizationProvider;

/**
 * Initializes AuthComponent for use in the controller.
 *
 * @param Event $event The initialize event.
 * @return void
 */
	public function initialize(Event $event) {
		$controller = $event->subject();
		$this->request = $controller->request;
		$this->response = $controller->response;
		$this->_methods = $controller->methods;
		$this->session = $controller->request->session();

		if (Configure::read('debug')) {
			Debugger::checkSecurityKeys();
		}
	}

/**
 * Main execution method. Handles redirecting of invalid users, and processing
 * of login form data.
 *
 * @param Event $event The startup event.
 * @return void|\Cake\Network\Response
 */
	public function startup(Event $event) {
		$controller = $event->subject();
		$methods = array_flip(array_map('strtolower', $controller->methods));
		$action = strtolower($controller->request->params['action']);

		if (!isset($methods[$action])) {
			return;
		}

		$this->_setDefaults();

		if ($this->_isAllowed($controller)) {
			return;
		}

		if (!$this->_getUser()) {
			$result = $this->_unauthenticated($controller);
			if ($result instanceof Response) {
				$event->stopPropagation();
			}
			return $result;
		}

		if ($this->_isLoginAction($controller) ||
			empty($this->_config['authorize']) ||
			$this->isAuthorized($this->user())
		) {
			return;
		}

		$event->stopPropagation();
		return $this->_unauthorized($controller);
	}

/**
 * Events supported by this component.
 *
 * @return array
 */
	public function implementedEvents() {
		return [
			'Controller.initialize' => 'initialize',
			'Controller.startup' => 'startup',
		];
	}

/**
 * Checks whether current action is accessible without authentication.
 *
 * @param Controller $controller A reference to the instantiating controller object
 * @return bool True if action is accessible without authentication else false
 */
	protected function _isAllowed(Controller $controller) {
		$action = strtolower($controller->request->params['action']);
		if (in_array($action, array_map('strtolower', $this->allowedActions))) {
			return true;
		}
		return false;
	}

/**
 * Handles unauthenticated access attempt. First the `unathenticated()` method
 * of the last authenticator in the chain will be called. The authenticator can
 * handle sending response or redirection as appropriate and return `true` to
 * indicate no furthur action is necessary. If authenticator returns null this
 * method redirects user to login action. If it's an ajax request and config
 * `ajaxLogin` is specified that element is rendered else a 403 http status code
 * is returned.
 *
 * @param Controller $controller A reference to the controller object.
 * @return void|\Cake\Network\Response Null if current action is login action
 *   else response object returned by authenticate object or Controller::redirect().
 */
	protected function _unauthenticated(Controller $controller) {
		if (empty($this->_authenticateObjects)) {
			$this->constructAuthenticate();
		}
		$auth = $this->_authenticateObjects[count($this->_authenticateObjects) - 1];
		$result = $auth->unauthenticated($this->request, $this->response);
		if ($result !== null) {
			return $result;
		}

		if ($this->_isLoginAction($controller)) {
			if (empty($controller->request->data) &&
				!$this->session->check('Auth.redirect') &&
				$this->request->env('HTTP_REFERER')
			) {
				$this->session->write('Auth.redirect', $controller->referer(null, true));
			}
			return;
		}

		if (!$controller->request->is('ajax')) {
			$this->flash($this->_config['authError']);
			$this->session->write('Auth.redirect', $controller->request->here(false));
			return $controller->redirect($this->_config['loginAction']);
		}

		if (!empty($this->_config['ajaxLogin'])) {
			$controller->viewPath = 'Element';
			$response = $controller->render(
				$this->_config['ajaxLogin'],
				$this->RequestHandler->ajaxLayout
			);
			$response->statusCode(403);
			return $response;
		}
		return $controller->redirect(null, 403);
	}

/**
 * Normalizes config `loginAction` and checks if current request URL is same as login action.
 *
 * @param Controller $controller A reference to the controller object.
 * @return bool True if current action is login action else false.
 */
	protected function _isLoginAction(Controller $controller) {
		$url = '';
		if (isset($controller->request->url)) {
			$url = $controller->request->url;
		}
		$url = Router::normalize($url);
		$loginAction = Router::normalize($this->_config['loginAction']);

		return $loginAction === $url;
	}

/**
 * Handle unauthorized access attempt
 *
 * @param Controller $controller A reference to the controller object
 * @return \Cake\Network\Response
 * @throws \Cake\Error\ForbiddenException
 */
	protected function _unauthorized(Controller $controller) {
		if ($this->_config['unauthorizedRedirect'] === false) {
			throw new Error\ForbiddenException($this->_config['authError']);
		}

		$this->flash($this->_config['authError']);
		if ($this->_config['unauthorizedRedirect'] === true) {
			$default = '/';
			if (!empty($this->_config['loginRedirect'])) {
				$default = $this->_config['loginRedirect'];
			}
			$url = $controller->referer($default, true);
		} else {
			$url = $this->_config['unauthorizedRedirect'];
		}
		return $controller->redirect($url);
	}

/**
 * Sets defaults for configs.
 *
 * @return void
 */
	protected function _setDefaults() {
		$defaults = [
			'authenticate' => ['Form'],
			'flash' => [
				'element' => 'default',
				'key' => 'auth',
				'params' => []
			],
			'loginAction' => [
				'controller' => 'users',
				'action' => 'login',
				'plugin' => null
			],
			'logoutRedirect' => $this->_config['loginAction'],
			'authError' => __d('cake', 'You are not authorized to access that location.')
		];

		$config = $this->config();
		foreach ($config as $key => $value) {
			if ($value !== null) {
				unset($defaults[$key]);
			}
		}
		$this->config($defaults);
	}

/**
 * Check if the provided user is authorized for the request.
 *
 * Uses the configured Authorization adapters to check whether or not a user is authorized.
 * Each adapter will be checked in sequence, if any of them return true, then the user will
 * be authorized for the request.
 *
 * @param array $user The user to check the authorization of. If empty the user in the session will be used.
 * @param \Cake\Network\Request $request The request to authenticate for. If empty, the current request will be used.
 * @return bool True if $user is authorized, otherwise false
 */
	public function isAuthorized($user = null, Request $request = null) {
		if (empty($user) && !$this->user()) {
			return false;
		}
		if (empty($user)) {
			$user = $this->user();
		}
		if (empty($request)) {
			$request = $this->request;
		}
		if (empty($this->_authorizeObjects)) {
			$this->constructAuthorize();
		}
		foreach ($this->_authorizeObjects as $authorizer) {
			if ($authorizer->authorize($user, $request) === true) {
				$this->_authorizationProvider = $authorizer;
				return true;
			}
		}
		return false;
	}

/**
 * Loads the authorization objects configured.
 *
 * @return mixed Either null when authorize is empty, or the loaded authorization objects.
 * @throws \Cake\Error\Exception
 */
	public function constructAuthorize() {
		if (empty($this->_config['authorize'])) {
			return;
		}
		$this->_authorizeObjects = array();
		$authorize = Hash::normalize((array)$this->_config['authorize']);
		$global = array();
		if (isset($authorize[AuthComponent::ALL])) {
			$global = $authorize[AuthComponent::ALL];
			unset($authorize[AuthComponent::ALL]);
		}
		foreach ($authorize as $class => $config) {
			$className = App::className($class, 'Auth', 'Authorize');
			if (!class_exists($className)) {
				throw new Error\Exception(sprintf('Authorization adapter "%s" was not found.', $class));
			}
			if (!method_exists($className, 'authorize')) {
				throw new Error\Exception('Authorization objects must implement an authorize() method.');
			}
			$config = array_merge($global, (array)$config);
			$this->_authorizeObjects[] = new $className($this->_registry, $config);
		}
		return $this->_authorizeObjects;
	}

/**
 * Takes a list of actions in the current controller for which authentication is not required, or
 * no parameters to allow all actions.
 *
 * You can use allow with either an array or a simple string.
 *
 * `$this->Auth->allow('view');`
 * `$this->Auth->allow(['edit', 'add']);`
 * `$this->Auth->allow();` to allow all actions
 *
 * @param string|array $actions Controller action name or array of actions
 * @return void
 * @link http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#making-actions-public
 */
	public function allow($actions = null) {
		if ($actions === null) {
			$this->allowedActions = $this->_methods;
			return;
		}
		$this->allowedActions = array_merge($this->allowedActions, (array)$actions);
	}

/**
 * Removes items from the list of allowed/no authentication required actions.
 *
 * You can use deny with either an array or a simple string.
 *
 * `$this->Auth->deny('view');`
 * `$this->Auth->deny(['edit', 'add']);`
 * `$this->Auth->deny();` to remove all items from the allowed list
 *
 * @param string|array $actions Controller action name or array of actions
 * @return void
 * @see AuthComponent::allow()
 * @link http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#making-actions-require-authorization
 */
	public function deny($actions = null) {
		if ($actions === null) {
			$this->allowedActions = array();
			return;
		}
		foreach ((array)$actions as $action) {
			$i = array_search($action, $this->allowedActions);
			if (is_int($i)) {
				unset($this->allowedActions[$i]);
			}
		}
		$this->allowedActions = array_values($this->allowedActions);
	}

/**
 * Maps action names to CRUD operations.
 *
 * Used for controller-based authentication. Make sure
 * to configure the authorize property before calling this method. As it delegates $map to all the
 * attached authorize objects.
 *
 * @param array $map Actions to map
 * @return void
 * @see BaseAuthorize::mapActions()
 * @link http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#mapping-actions-when-using-crudauthorize
 */
	public function mapActions(array $map = array()) {
		if (empty($this->_authorizeObjects)) {
			$this->constructAuthorize();
		}
		foreach ($this->_authorizeObjects as $auth) {
			$auth->mapActions($map);
		}
	}

/**
 * Log a user in.
 *
 * If a $user is provided that data will be stored as the logged in user. If `$user` is empty or not
 * specified, the request will be used to identify a user. If the identification was successful,
 * the user record is written to the session key specified in AuthComponent::$sessionKey. Logging in
 * will also change the session id in order to help mitigate session replays.
 *
 * @param array $user Either an array of user data, or null to identify a user using the current request.
 * @return bool True on login success, false on failure
 * @link http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#identifying-users-and-logging-them-in
 */
	public function login($user = null) {
		$this->_setDefaults();

		if (empty($user)) {
			$user = $this->identify($this->request, $this->response);
		}
		if ($user) {
			$this->session->renew();
			$this->session->write($this->sessionKey, $user);
		}
		return (bool)$this->user();
	}

/**
 * Log a user out.
 *
 * Returns the logout action to redirect to. Triggers the logout() method of
 * all the authenticate objects, so they can perform custom logout logic.
 * AuthComponent will remove the session data, so there is no need to do that
 * in an authentication object. Logging out will also renew the session id.
 * This helps mitigate issues with session replays.
 *
 * @return string Normalized config `logoutRedirect`
 * @link http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#logging-users-out
 */
	public function logout() {
		$this->_setDefaults();
		if (empty($this->_authenticateObjects)) {
			$this->constructAuthenticate();
		}
		$user = (array)$this->user();
		foreach ($this->_authenticateObjects as $auth) {
			$auth->logout($user);
		}
		$this->session->delete($this->sessionKey);
		$this->session->delete('Auth.redirect');
		$this->session->renew();
		return Router::normalize($this->_config['logoutRedirect']);
	}

/**
 * Get the current user.
 *
 * Will prefer the user cache over sessions. The user cache is primarily used for
 * stateless authentication. For stateful authentication,
 * cookies + sessions will be used.
 *
 * @param string $key field to retrieve. Leave null to get entire User record
 * @return mixed User record. or null if no user is logged in.
 * @link http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#accessing-the-logged-in-user
 */
	public function user($key = null) {
		if (!empty($this->_user)) {
			$user = $this->_user;
		} elseif ($this->sessionKey && $this->session->check($this->sessionKey)) {
			$user = $this->session->read($this->sessionKey);
		} else {
			return null;
		}
		if ($key === null) {
			return $user;
		}
		return Hash::get($user, $key);
	}

/**
 * Similar to AuthComponent::user() except if the session user cannot be found, connected authentication
 * objects will have their getUser() methods called. This lets stateless authentication methods function correctly.
 *
 * @return bool true if a user can be found, false if one cannot.
 */
	protected function _getUser() {
		$user = $this->user();
		if ($user) {
			$this->session->delete('Auth.redirect');
			return true;
		}

		if (empty($this->_authenticateObjects)) {
			$this->constructAuthenticate();
		}
		foreach ($this->_authenticateObjects as $auth) {
			$result = $auth->getUser($this->request);
			if (!empty($result) && is_array($result)) {
				$this->_user = $result;
				return true;
			}
		}

		return false;
	}

/**
 * Get the URL a user should be redirected to upon login.
 *
 * Pass a URL in to set the destination a user should be redirected to upon
 * logging in.
 *
 * If no parameter is passed, gets the authentication redirect URL. The URL
 * returned is as per following rules:
 *
 *  - Returns the normalized URL from session Auth.redirect value if it is
 *    present and for the same domain the current app is running on.
 *  - If there is no session value and there is a config `loginRedirect`, the
 *    `loginRedirect` value is returned.
 *  - If there is no session and no `loginRedirect`, / is returned.
 *
 * @param string|array $url Optional URL to write as the login redirect URL.
 * @return string Redirect URL
 */
	public function redirectUrl($url = null) {
		if ($url !== null) {
			$redir = $url;
			$this->session->write('Auth.redirect', $redir);
		} elseif ($this->session->check('Auth.redirect')) {
			$redir = $this->session->read('Auth.redirect');
			$this->session->delete('Auth.redirect');

			if (Router::normalize($redir) === Router::normalize($this->_config['loginAction'])) {
				$redir = $this->_config['loginRedirect'];
			}
		} elseif ($this->_config['loginRedirect']) {
			$redir = $this->_config['loginRedirect'];
		} else {
			$redir = '/';
		}
		if (is_array($redir)) {
			return Router::url($redir + array('base' => false));
		}
		return $redir;
	}

/**
 * Use the configured authentication adapters, and attempt to identify the user
 * by credentials contained in $request.
 *
 * @param \Cake\Network\Request $request The request that contains authentication data.
 * @param \Cake\Network\Response $response The response
 * @return array User record data, or false, if the user could not be identified.
 */
	public function identify(Request $request, Response $response) {
		if (empty($this->_authenticateObjects)) {
			$this->constructAuthenticate();
		}
		foreach ($this->_authenticateObjects as $auth) {
			$result = $auth->authenticate($request, $response);
			if (!empty($result) && is_array($result)) {
				$this->_authenticationProvider = $auth;
				return $result;
			}
		}
		return false;
	}

/**
 * Loads the configured authentication objects.
 *
 * @return mixed either null on empty authenticate value, or an array of loaded objects.
 * @throws \Cake\Error\Exception
 */
	public function constructAuthenticate() {
		if (empty($this->_config['authenticate'])) {
			return;
		}
		$this->_authenticateObjects = array();
		$authenticate = Hash::normalize((array)$this->_config['authenticate']);
		$global = array();
		if (isset($authenticate[AuthComponent::ALL])) {
			$global = $authenticate[AuthComponent::ALL];
			unset($authenticate[AuthComponent::ALL]);
		}
		foreach ($authenticate as $class => $config) {
			if (!empty($config['className'])) {
				$class = $config['className'];
				unset($config['className']);
			}
			$className = App::className($class, 'Auth', 'Authenticate');
			if (!class_exists($className)) {
				throw new Error\Exception(sprintf('Authentication adapter "%s" was not found.', $class));
			}
			if (!method_exists($className, 'authenticate')) {
				throw new Error\Exception('Authentication objects must implement an authenticate() method.');
			}
			$config = array_merge($global, (array)$config);
			$this->_authenticateObjects[] = new $className($this->_registry, $config);
		}
		return $this->_authenticateObjects;
	}

/**
 * Set a flash message. Uses the Session component, and values from `flash` config.
 *
 * @param string $message The message to set.
 * @param string $type Message type. Defaults to 'error'.
 * @return void
 */
	public function flash($message, $type = 'error') {
		if ($message === false) {
			return;
		}
		$flashConfig = $this->_config['flash'];
		$key = $flashConfig['key'];
		$params = [];
		if (isset($flashConfig['params'])) {
			$params = $flashConfig['params'];
		}
		$this->session->flash($message, 'error', $params + compact('key'));
	}

/**
 * If login was called during this request and the user was successfully
 * authenticated, this function will return the instance of the authentication
 * object that was used for logging the user in.
 *
 * @return \Cake\Auth\BaseAuthenticate|null
 */
	public function authenticationProvider() {
		return $this->_authenticationProvider;
	}

/**
 * If there was any authorization processing for the current request, this function
 * will return the instance of the Authorization object that granted access to the
 * user to the current address.
 *
 * @return \Cake\Auth\BaseAuthorize|null
 */
	public function authorizationProvider() {
		return $this->_authorizationProvider;
	}
}
