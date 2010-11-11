<?php
/**
 * Copyright 2010 Yuri Timofeev tim4dev@gmail.com
 *
 * Webacula is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Webacula is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Webacula.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Yuri Timofeev <tim4dev@gmail.com>
 * @package webacula
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public License
 *
 */
/**
 * Common code for all Controllers with ACLs
 */

require_once 'Zend/Controller/Action.php';

class MyClass_ControllerAclAction extends Zend_Controller_Action
{

	const DEBUG_LOG = '/tmp/webacula_debug.log';
    protected $_config;
    public $debug_level;

    // ACL
    protected $_webacula_acl;   // webacula acl
    protected $_bacula_acl;     // bacula acl
    protected $_identity;
    protected $_role_name = '';
    protected $_role_id   = null;


    public function init()
    {
        parent::init();
        $this->view->baseUrl = $this->_request->getBaseUrl();
        $this->view->translate = Zend_Registry::get('translate');
        $this->view->language  = Zend_Registry::get('language');

        $this->_config = Zend_Registry::get('config');
        // authentication
        $this->view->identity  = '';
        $this->view->role_name = '';
        if ( $this->isAuth() )  {
            $this->_identity  = Zend_Auth::getInstance()->getIdentity();
            // find current user ACL role
            $table = new Wbroles();
            $row   = $table->find($this->_identity->role_id);
            if ($row->count() == 1) {
                $this->role_name = $row[0]['name'];
                $this->role_id   = $row[0]['id'];
            }
            // for view
            $this->view->role_name = $this->_role_name;
            $this->view->role_id   = $this->_role_id;
            $this->view->identity  = $this->_identity;
            /*
             *  ACLs and cache
             */
            $cache = Zend_Registry::get('cache');
            // проверка, есть ли уже запись в кэше:
            if( !$this->_webacula_acl = $cache->load('MyClass_WebaculaAcl') ) {
                // промах кэша
                $this->_webacula_acl        = new MyClass_WebaculaAcl();
                $cache->save($this->_webacula_acl, 'MyClass_WebaculaAcl');
            }
        }
        // для переадресаций
        $this->_redirector = $this->_helper->getHelper('Redirector');
        $this->_bacula_acl = new MyClass_BaculaAcl();
    }


    public function preDispatch()
    {
        // этот контроллер недоступен без регистрации
        if ( !$this->isAuth() ) {
            $this->_redirect('/auth/login');
            return;
        }
        $controller = $this->getRequest()->getControllerName();
        if ($this->role_name)
            if( !$this->_webacula_acl->isAllowed( $this->role_name, $controller) ) {
                $msg = sprintf( $this->view->translate->_('You try to use Webacula menu "%s".'), $controller );
                $this->_forward('webacula-access-denied', 'error', null, array('msg' => $msg ) ); // action, controller
                return;
            }
        parent::preDispatch();
    }


    /*
     * @return true если юзер залогинился
     */
    /* Использование :
     * // это действие недоступно без регистрации
        if ( !$this->isAuth() ) {
            $this->_redirect('auth/login');
        }
     */
    function isAuth()
    {
        $auth = Zend_Auth::getInstance();
        return $auth->hasIdentity();
    }


}