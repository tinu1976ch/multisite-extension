<?php

/**
 * ======================================================================
 * LICENSE: This file is subject to the terms and conditions defined in *
 * file 'license.txt', which is part of this source code package.       *
 * ======================================================================
 */

/**
 * AAM Multisite extension
 *
 * @package AAM
 * @author Vasyl Martyniuk <vasyl@vasyltech.com>
 */
class AAM_Multisite {

    /**
     * Instance of itself
     * 
     * @var AAM_PlusPackage 
     * 
     * @access private
     */
    private static $_instance = null;

    /**
     * Initialize the extension
     * 
     * @return void
     * 
     * @access protected
     */
    protected function __construct() {
        if (is_admin()) {
            if (is_network_admin()) {
                add_action('aam-sidebar-ui-action', array($this, 'getSidebar'));
                //print required JS & CSS
                add_action('admin_print_scripts', array($this, 'printJavascript'));
            }
            
            //add custom ajax handler
            add_filter('aam-ajax-filter', array($this, 'ajax'), 10, 3);
            
            //add another option to the AAM Utilities extension
            add_action('aam-settings-filter', array($this, 'settingsList'), 10, 2);
        }
        
        if (is_multisite()) {
            add_action('wp', array($this, 'wp'), 999);
        }
    }
    
    /**
     * 
     */
    public function wp() {
        $manage = apply_filters('aam-utility-property', 'ms-member-access', false);
        
        if (!is_main_site() && $manage) { //there is no way to restrict main site
            if (!is_user_member_of_blog()) {
                AAM_Core_API::reject();
            }
        }
    }
    
    /**
     * 
     */
    public function settingsList($list, $group) {
        if ($group == 'core') {
            $list['ms-member-access'] = array(
                'title' => __('Multisite None-Member Restriction', AAM_KEY),
                'descr' => __('Restrict access to site for users that are not members of the site.', AAM_KEY),
                'value' => AAM_Core_Config::get('ms-member-access', false)
            );
        }

        return $list;
    }

    /**
     * Render sidebar
     * 
     * @param string $position
     * 
     * @return void
     * 
     * @access public
     */
    public function getSidebar($position) {
        if ($position == 'top') {
            require_once(dirname(__FILE__) . '/view/sidebar.phtml');
        }
    }

    /**
     * Print javascript libraries
     *
     * @return void
     *
     * @access public
     */
    public function printJavascript() {
        if (AAM::isAAM()) {
            $baseurl = $this->getBaseurl('/js');
            wp_enqueue_script(
                    'aam-ms', $baseurl . '/multisite.js', array('aam-main')
            );
            
            $localization = array('current' => get_current_blog_id());
            wp_localize_script('aam-ms', 'aamMultisite', $localization);
        }
    }

    /**
     * Get extension base URL
     * 
     * @param string $path
     * 
     * @return string
     * 
     * @access protected
     */
    protected function getBaseurl($path = '') {
        $contentDir = str_replace('\\', '/', WP_CONTENT_DIR);
        $baseDir = str_replace('\\', '/', dirname(__FILE__));
        
        $relative = str_replace($contentDir, '', $baseDir);
        
        return content_url() . $relative . $path;
    }

    /**
     * Custom ajax handler
     * 
     * @param mixed            $response
     * @param AAM_Core_Subject $subject
     * @param string           $action
     * 
     * @return string
     * 
     * @access public
     */
    public function ajax($response, $subject, $action) {
        if ($action == 'Multisite.getTable') {
            $response = json_encode($this->getTable());
        } elseif ($action == 'Multisite.prepareSync') {
            $response = json_encode($this->prepareSync());
        } elseif ($action == 'Multisite.sync') {
            $response = json_encode($this->sync(
                AAM_Core_Request::post('offset'), 
                AAM_Core_Request::post('data')
            ));
        }

        return $response;
    }

    /**
     * Get site list
     * 
     * @return string JSON Encoded site list
     * 
     * @access protected
     */
    protected function getTable() {
        //retrieve list of users
        $sites = $this->getSites(array(
            'number' => AAM_Core_Request::request('length'),
            'limit'  => AAM_Core_Request::request('length'), //support legacy
            'offset' => AAM_Core_Request::request('start')
        ));
        
        $response = array(
            'recordsTotal'    => get_blog_count(),
            'recordsFiltered' => get_blog_count(),
            'draw'            => AAM_Core_Request::request('draw'),
            'data'            => array(),
        );
        
        $sub = defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL ? 1 : 0;

        foreach ($sites as $site) {
            $siteId  = (is_array($site) ? $site['blog_id'] : $site->blog_id); //TODO - compatibility
            $details = get_blog_details($siteId);
            $main    = is_main_site($siteId) ? 1 : 0;

            if ($sub && !$main) {
                $url = get_admin_url($siteId, 'admin.php?page=aam');
            } else {
                $url = get_admin_url($siteId, 'index.php');
            }
            
            $response['data'][] = array(
                $siteId,
                $url,
                get_admin_url($siteId, 'admin-ajax.php'),
                $details->blogname,
                'manage' . ($main ? ',sync' : ''),
                $main,
                ($sub && !$main ? 1 : 0)
            );
        }

        return $response;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    protected function prepareSync() {
        $exporter = new AAM_Core_Exporter(
            AAM_Core_Config::get('multisite.sync', array('system' => 'roles'))
        );

        return array(
            'sites' => $this->getSites(array('count' => true)),
            'data'  => base64_encode(json_encode($exporter->run()))
        );
    }

    /**
     * Undocumented function
     *
     * @param [type] $limit
     * @param [type] $offset
     * @return void
     */
    protected function getSites($args) {
        if (function_exists('get_sites')) { // since WP 4.6
            $sites = get_sites($args);
        } else {
            $sites = wp_get_sites($args);
        }

        return $sites;
    }

    /**
     * Undocumented function
     *
     * @param [type] $offset
     * @param [type] $groups
     * @return void
     */
    protected function sync($offset, $groups) {
        $result = false;

        //retrieve list of users
        $sites = $this->getSites(array(
            'number' => 1,
            'limit'  => 1, //support legacy
            'offset' => AAM_Core_Request::post('offset'),
            'orderby' => 'id'
        ));

        if (!empty($sites[0]->blog_id)) {
            if (!is_main_site($sites[0]->blog_id)) {
                $importer = new AAM_Core_Importer(
                    base64_decode(AAM_Core_Request::post('data')), 
                    $sites[0]->blog_id
                );
                $importer->run();
            }
            $result = true;
        }

        return array('status' => ($result ? 'continue' : 'stop'));
    }

    /**
     * Bootstrap the extension
     * 
     * @return AAM_PlusPackage
     * 
     * @access public
     */
    public static function bootstrap() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

}