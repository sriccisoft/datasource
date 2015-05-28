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
 * @since         3.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Auth\Storage;

/**
 * Describes the methods that any class representing an Auth data storage should
 * comply with.
 */
interface StorageInterface
{
    /**
     * Get user record.
     *
     * @return array|null
     */
    public function get();

    /**
     * Set user record.
     *
     * @param array $user User record.
     * @return void
     */
    public function set(array $user);

    /**
     * Remove user record.
     *
     * @return void
     */
    public function remove();
}
