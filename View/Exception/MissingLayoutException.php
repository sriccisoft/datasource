<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\View\Exception;

use Cake\Core\Exception\Exception;

/**
 * Used when a layout file cannot be found.
 *
 */
class MissingLayoutException extends Exception {

	protected $_messageTemplate = 'Layout file "%s" is missing.';

}
