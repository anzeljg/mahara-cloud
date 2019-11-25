<?php
/**
 *
 * @package    mahara
 * @subpackage blocktype-zotero
 * @author     Gregor Anzelj
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2012-2017 Gregor Anzelj, info@povsod.com
 *
 */

defined('INTERNAL') || die();

safe_require('artefact', 'cloud');
require_once(get_config('docroot') . 'artefact/cloud/lib/oauth.php');


class PluginBlocktypeZotero extends PluginBlocktypeCloud {
    
    public static function get_title() {
        return get_string('title', 'blocktype.cloud/zotero');
    }

    public static function get_description() {
        return get_string('description', 'blocktype.cloud/zotero');
    }

    public static function get_categories() {
        return array('external');
    }

    public static function render_instance(BlockInstance $instance, $editing=false, $versioning=false) {
        $configdata = $instance->get('configdata');
        $viewid     = $instance->get('view');
        
        $view = new View($viewid);
        $owner = $view->get('owner');

        if (!empty($configdata['artefacts'])) {
            // User selected single collection of references for display...
            if ($configdata['artefacts'][0] != '0') {
                $data = explode('-', $configdata['artefacts'][0], 2);
                if ($data[0] == 'folder') {
                    $collection = $data[1];
                    $tag = null;
                }
                elseif ($data[0] == 'tag') {
                    $collection = '0';
                    $tag = $data[1];
                }
                else {
                    $collection = '0';
                    $tag = null;
                }
                
            }
            // User selected to display all items in the library...
            else {
                $collection = '0';
                $tag = null;
            }
            $bibstyle = $configdata['bibstyle'];
        }
        // Default settings, when nothing is selected...
        else {
            $collection = '0';
            $tag = null;
            $bibstyle = 'iso690-author-date-en';
        }

        $result = '';
        if (isset($configdata['usebibbase']) && $configdata['usebibbase'] == true) {
            // Show bibliography list created by BibBase on Mahara page
            if (basename($_SERVER['PHP_SELF']) == 'view.php') {
                $type = 'collection';
                $result = self::get_bibbase($collection, $type, $owner, $tag);
                // Fix left-margin setting set by bibbase.css
                // See: bootstrap.min.css:561
                $result .= '<style>.row{margin-left:0px}</style>';
            }
            // Don't show it when editing page... It causes
            // instance_config_form not to apprear due to 
            // javascript clash. Show placeholder instead.
            else {
                $logo = '<img style="vertical-align: middle; margin-left: 3px; height: 16px;" src="http://bibbase.org/img/logo.svg"></img>';
                $result = get_string('bibbaseplaceholder', 'blocktype.cloud/zotero', $logo);
            }
        }
        else {
            $result = self::get_filelist($collection, $bibstyle, $owner, $tag);
            $result = html_entity_decode($result, ENT_COMPAT, 'UTF-8');
            // Use regex to make URL links clickable...
            $result = preg_replace("#((http|ftp)+(s)?:\/\/[^<>\s]+)(?<!(?:(\.|\,)))#i", "<a href=\"\\0\" target=\"blank\">\\0</a>", $result);
        }
        
        return $result;
    }

    public static function has_instance_config() {
        return true;
    }

    public static function get_instance_config_javascript(BlockInstance $instance) {
        return array('js/configform.js');
    }

    public static function instance_config_form(BlockInstance $instance) {
        global $USER;
        $instanceid = $instance->get('id');
        $configdata = $instance->get('configdata');
        safe_require('artefact', 'cloud');
        $instance->set('artefactplugin', 'cloud');

        $viewid  = $instance->get('view');
        $view    = new View($viewid);
        $ownerid = $view->get('owner');

        $consumer = self::get_service_consumer();
        if (isset($consumer->usrprefs['access_token']) && !empty($consumer->usrprefs['access_token'])) {
            return array(
                'zoterologo' => array(
                    'type' => 'html',
                    'value' => '<img src="' . get_config('wwwroot') . 'artefact/cloud/blocktype/zotero/theme/raw/static/images/logo.png">',
                ),
                'zoteroisconnect' => array(
                    'type' => 'cancel',
                    'value' => get_string('revokeconnection', 'blocktype.cloud/zotero'),
                    'goto' => get_config('wwwroot') . 'artefact/cloud/blocktype/zotero/account.php?action=logout&sesskey=' . $USER->get('sesskey'),
                ),
                'zoterorefs' => array(
                    'type'     => 'datatables',
                    'labelhtml'    => get_string('selectreferences', 'blocktype.cloud/zotero'),
                    'service'  => 'zotero',
                    'block'    => $instanceid,
                    'fullpath' => (isset($configdata['fullpath']) ? $configdata['fullpath'] : null),
                    'options'  => array(
                        'showFolders'    => true,
                        'showFiles'      => false,
                        'selectFolders'  => true,
                        'selectFiles'    => false,
                        'selectMultiple' => false
                    ),
                ),
                'bibstyle' => array(
                    'type'         => 'select',
                    'title'    => get_string('bibliographystyle', 'blocktype.cloud/zotero'),
                    'options'      => get_zotero_bibstyles(),
                    'defaultvalue' => (isset($configdata['bibstyle']) ? $configdata['bibstyle'] : null),
                    'class'        => ($configdata['usebibbase'] == 1 ? 'hidden' : null),
                ),
                'usebibbase' => array(
                    'type'         => 'switchbox',
                    'title'    => get_string('usebibbase', 'blocktype.cloud/zotero'),
                    'defaultvalue' => (isset($configdata['usebibbase']) ? $configdata['usebibbase'] : null),
                ),
            );
        }
        else {
            return array(
                'zoterologo' => array(
                    'type' => 'html',
                    'value' => '<img src="' . get_config('wwwroot') . 'artefact/cloud/blocktype/zotero/theme/raw/static/images/logo.png">',
                ),
                'zoteroisconnect' => array(
                    'type' => 'cancel',
                    'value' => get_string('connecttozotero', 'blocktype.cloud/zotero'),
                    'goto' => get_config('wwwroot') . 'artefact/cloud/blocktype/zotero/account.php?action=login&view=' . $viewid . '&sesskey=' . $USER->get('sesskey'),
                ),
            );
        }
    }

    public static function instance_config_save($values) {
        // Folder and file IDs (and other values) are returned as JSON/jQuery serialized string.
        // We have to parse that string and urldecode it (to correctly convert square brackets)
        // in order to get cloud folder and file IDs - they are stored in $artefacts array.
        parse_str(urldecode($values['zoterorefs']), $params);
        if (!isset($params['artefacts']) || empty($params['artefacts'])) {
            $artefacts = array();
        }
        else {
            $artefacts = $params['artefacts'];
        }

        $values = array(
            'title'       => $values['title'],
            'artefacts'   => $artefacts,
            // Bibliography style
            'bibstyle'    => $values['bibstyle'],
            'usebibbase'  => $values['usebibbase'],
        );
        return $values;
    }

    public static function get_artefacts(BlockInstance $instance) {
        // Not needed, but must be implemented.
    }

    public static function artefactchooser_element($default=null) {
        // Not needed, but must be implemented.
    }

    public static function has_config() {
        return true;
    }

    public static function get_config_options() {
        $elements = array();
        $elements['applicationdesc'] = array(
            'type'  => 'html',
            'value' => get_string('applicationdesc', 'blocktype.cloud/zotero', '<a href="http://www.zotero.org/oauth/apps">', '</a>'),
        );
        $elements['applicationgeneral'] = array(
            'type' => 'fieldset',
            'class' => 'last',
            'collapsible' => true,
            'collapsed' => false,
            'legend' => get_string('applicationgeneral', 'blocktype.cloud/zotero'),
            'elements' => array(
                'applicationname' => array(
                    'type'         => 'text',
                    'title'        => get_string('applicationname', 'blocktype.cloud/zotero'),
                    'defaultvalue' => get_config('sitename'),
                    'description'  => get_string('applicationnamedesc', 'blocktype.cloud/zotero'),
                ),
                'consumerkey' => array(
                    'type'         => 'text',
                    'title'        => get_string('consumerkey', 'blocktype.cloud/zotero'),
                    'defaultvalue' => get_config_plugin('blocktype', 'zotero', 'consumerkey'),
                    'description'  => get_string('consumerkeydesc', 'blocktype.cloud/zotero'),
                    'rules'        => array('required' => true),
                ),
                'consumersecret' => array(
                    'type'         => 'text',
                    'title'        => get_string('consumersecret', 'blocktype.cloud/zotero'),
                    'defaultvalue' => get_config_plugin('blocktype', 'zotero', 'consumersecret'),
                    'description'  => get_string('consumersecretdesc', 'blocktype.cloud/zotero'),
                    'rules'        => array('required' => true),
                ),
                'applicationtype' => array(
                    'type'         => 'radio',
                    'title'        => get_string('applicationtype', 'blocktype.cloud/zotero'),
                    'defaultvalue' => 'browser',
                    'description'  => get_string('applicationtypedesc', 'blocktype.cloud/zotero'),
                    'options'      => array(
                        'client'   => 'Client',
                        'browser'  => 'Browser',
                    ),
                    'disabled'     => true,
                ),
                'applicationweb' => array(
                    'type'         => 'text',
                    'title'        => get_string('applicationweb', 'blocktype.cloud/zotero'),
                    'defaultvalue' => get_config('wwwroot'),
                    'description'  => get_string('applicationwebdesc', 'blocktype.cloud/zotero'),
                ),
                'redirecturl' => array(
                    'type'         => 'text',
                    'title'        => get_string('redirecturl', 'blocktype.cloud/zotero'),
                    'defaultvalue' => get_config('wwwroot') . 'artefact/cloud/blocktype/zotero/callback.php',
                    'description'  => get_string('redirecturldesc', 'blocktype.cloud/zotero'),
                    'rules'        => array('required' => true),
                ),
            )
        );
        return array(
            'class' => 'panel panel-body',
            'elements' => $elements,
        );

    }

    public static function save_config_options(Pieform $form, $values) {
        set_config_plugin('blocktype', 'zotero', 'consumerkey', $values['consumerkey']);
        set_config_plugin('blocktype', 'zotero', 'consumersecret', $values['consumersecret']);
    }

    public static function default_copy_type() {
        return 'shallow';
    }

    /********************************************
     * Methods & stuff for accessing Zotero API *
     ********************************************/
    
    private function get_service_consumer($owner=null) {
        global $USER;
        if (!isset($owner) || is_null($owner)) {
            $owner = $USER->get('id');
        }
        $service = new StdClass();
        $service->ssl      = true;
        $service->version  = 2; // API Version
        $service->apiurl   = 'https://api.zotero.org/';
        $service->wwwurl   = 'https://www.zotero.org/';
        $service->oauthurl = 'https://www.zotero.org/oauth/';
        $service->key      = get_config_plugin('blocktype', 'zotero', 'consumerkey');
        $service->secret   = get_config_plugin('blocktype', 'zotero', 'consumersecret');
        // If SSL is set then force SSL URL for callback
        if ($service->ssl) {
            $wwwroot = str_replace('http://', 'https://', get_config('wwwroot'));
        }
        $service->callback = get_config('wwwroot') . 'artefact/cloud/blocktype/zotero/callback.php';
        $service->usrprefs = ArtefactTypeCloud::get_user_preferences('zotero', $owner);
        return $service;
    }

    // SEE: http://www.zotero.org/support/dev/server_api/read_api
    public function service_list() {
        global $SESSION;
        $consumer = self::get_service_consumer();
        $service = new StdClass();
        $service->name = 'zotero';
        $service->url = 'http://www.zotero.com';
        $service->auth = false;
        $service->manage = false;
        $service->pending = false;

        if (!empty($consumer->key) && !empty($consumer->secret)) {
            if (isset($consumer->usrprefs['oauth_token']) && !empty($consumer->usrprefs['oauth_token'])) {
                // Check if the oauth_token has been revoked or not...
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/keys/'.$consumer->usrprefs['oauth_token'];
                $method = 'GET';
                $port = $consumer->ssl ? '443' : '80';
                $params = array(
                    'oauth_version' => '1.0',
                    'oauth_nonce' => mt_rand(),
                    'oauth_timestamp' => time(),
                    'oauth_consumer_key' => $consumer->key,
                    'oauth_token' => $consumer->usrprefs['oauth_token'],
                    'oauth_signature_method' => 'HMAC-SHA1',
                );
                $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
                $header = array();
                $header[] = build_oauth_header($params, "Zotero API PHP Client");
                $header[] = 'Content-Type: application/x-www-form-urlencoded';
                $header[] = 'Zotero-API-Version: '.$consumer->version;
                $config = array(
                    CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                    CURLOPT_PORT => $port,
                    CURLOPT_HEADER => true,
                    CURLOPT_HTTPHEADER => $header,
                    CURLOPT_POST => false,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
                );
                $result = mahara_http_request($config);
                if (isset($result->data) && !empty($result->data) &&
                    isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                    // oauth_token hasn't been revoked yet, so...
                    $service->auth = true;
                    $service->manage = true;
                    $service->account = self::account_info();
                }
            }
        }
        else {
             $service->pending = true;
           $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
        return $service;
    }
    
    public function request_token() {
        global $USER, $SESSION;
        $consumer = self::get_service_consumer();
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            $url = $consumer->oauthurl.'request';
            $method = 'POST';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_callback' => $consumer->callback,
                'oauth_signature_method' => 'HMAC-SHA1',
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, null);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Content-Type: application/x-www-form-urlencoded';
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url,
                CURLOPT_PORT => $port,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => oauth_http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (isset($result->data) && !empty($result->data) &&
                isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                // Store request_token (oauth_token) and request_token_secret (outh_token_secret)
                // We'll need it later...
                $body = substr($result->data, $result->info['header_size']);
                $prefs = oauth_parse_str($body);
                ArtefactTypeCloud::set_user_preferences('zotero', $USER->get('id'), $prefs);
                redirect($consumer->oauthurl.'authorize?'.rfc3986_decode($body).'&oauth_callback='.$consumer->callback);
            }
            else {
                $SESSION->add_error_msg(get_string('requesttokennotreturned', 'artefact.cloud'));
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }

    public function access_token($token) {
        global $USER, $SESSION;
        $consumer = self::get_service_consumer();
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            $url = $consumer->oauthurl.'access';
            $method = 'POST';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_verifier' => $token['oauth_verifier'],
                'oauth_signature_method' => 'HMAC-SHA1',
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Content-Type: application/x-www-form-urlencoded';
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url,
                CURLOPT_PORT => $port,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => oauth_http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (isset($result->data) && !empty($result->data) &&
                isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                // Store access_token (oauth_token) and access_token_secret (outh_token_secret)
                // We'll need it for all API calls...
                $body = substr($result->data, $result->info['header_size']);
                $prefs = oauth_parse_str($body);
                ArtefactTypeCloud::set_user_preferences('zotero', $USER->get('id'), $prefs);
            }
            else {
                $httpstatus = get_http_status($result->info['http_code']);
                $SESSION->add_error_msg($httpstatus);
                log_warn($httpstatus);
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }

    public function delete_token() {
        global $USER;
        ArtefactTypeCloud::set_user_preferences('zotero', $USER->get('id'), null);
    }
    
    public function revoke_access() {
        // Zotero API doesn't allow programmatical access revoking, so:
        // Nothing to do!
    }
    
    // SEE: http://www.zotero.org/support/dev/server_api/read_api
    public function account_info() {
        global $SESSION;
        $consumer = self::get_service_consumer();

        $info = new StdClass();
        $info->service_name = 'zotero';
        $info->service_auth = false;
        $info->user_id      = null;
        $info->user_name    = null;
        $info->user_email   = null;
        $info->user_profile = null;
        $info->space_used   = null;
        $info->space_amount = null;
        $info->space_ratio  = null;

        if (!empty($consumer->key) && !empty($consumer->secret)) {
            $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/keys/'.$consumer->usrprefs['oauth_token'];
            $method = 'GET';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_signature_method' => 'HMAC-SHA1',
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                CURLOPT_PORT => $port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (isset($result->data) && !empty($result->data) &&
                isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                $data = json_decode($result->data);

                $info->service_name = 'zotero';
                $info->service_auth = true;
                $info->user_id      = $consumer->usrprefs['userID'];
                $info->user_name    = $consumer->usrprefs['username'];
                $info->user_profile = $consumer->wwwurl.$consumer->usrprefs['username'];
                $info->space_used   = null;
                $info->space_amount = null;
                $info->space_ratio  = null;
                return $info;
            }
            else {
                $httpstatus = get_http_status($result->info['http_code']);
                $SESSION->add_error_msg($httpstatus);
                log_warn($httpstatus);
            }
         }
         else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
        return $info;
    }
    
    /*
     * This function returns list of selected files/folders which will be displayed in a view/page.
     *
     * $folder_id   integer   ID of the folder (on Cloud Service), which contents we wish to retrieve
     * $output      array     Function returns array, used to generate list of files/folders to show in Mahara view/page
     *
     * SEE: http://www.zotero.org/support/dev/server_api/read_api
     *      get_filelist basically corresponds to get_formatted_list_of_references_in_collection!
     */
    public function get_filelist($folder_id='0', $style='apa', $owner=null, $tag=null) {
        global $THEME, $SESSION;

        // Get owner since this could be part of pubically accessible Mahara page...
        $consumer = self::get_service_consumer();
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            // Use different path for top items/collections
            // than for sub-items/sub-collections...
            if ($folder_id == '0') {
                $rootpath = '';
            }
            else {
                $rootpath = '/collections/'.$folder_id;
            }
            
            // Get all items in given collection...
            $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].$rootpath.'/items';
            $method = 'GET';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                //'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_signature_method' => 'HMAC-SHA1',
                // Method specific prameters...
                'key' => $consumer->usrprefs['oauth_token'],
                'format'  => 'bib',
                'style' => $style,
                'tag' => $tag,
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                CURLOPT_PORT => $port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (isset($result->data) && !empty($result->data) &&
                isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                return substr($result->data, $result->info['header_size']);
            }
            else {
                $httpstatus = get_http_status($result->info['http_code']);
                $SESSION->add_error_msg($httpstatus);
                log_warn($httpstatus);
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }
    
    /*
     * This function gets folder contents and formats it, so it can be used in blocktype config form
     * (Pieform element) and in manage page.
     *
     * $folder_id   integer   ID of the folder (on Cloud Service), which contents we wish to retrieve
     * $options     integer   List of 6 integers (booleans) to indicate (for all 6 options) if option is used or not
     * $block       integer   ID of the block in given Mahara view/page
     *
     * $output      array     Function returns JSON encoded array of values that is suitable to feed jQuery Datatables with.
                              jQuery Datatable than draw an enriched HTML table according to values, contained in $output.
     * PLEASE NOTE: For jQuery Datatable to work, the $output array must be properly formatted and JSON encoded.
     *              Please see: http://datatables.net/usage/server-side (Reply from the server)!
     *
     * SEE: http://www.zotero.org/support/dev/server_api/read_api
     */
    public function get_folder_content($folder_id='0', $options, $block=0) {
        global $SESSION, $THEME, $USER;

        // Get selected artefacts (folders and/or files)
        if ($block > 0) {
            $data = unserialize(get_field('block_instance', 'configdata', 'id', $block));
            if (!empty($data)) {
                $artefacts = $data['artefacts'];
            }
            else {
                $artefacts = array();
            }
        }
        else {
            $artefacts = array();
        }
        
        // Get pieform element display options...
        $manageButtons  = (boolean) $options[0];
        $showFolders    = (boolean) $options[1];
        $showFiles      = (boolean) $options[2];
        $selectFolders  = (boolean) $options[3];
        $selectFiles    = (boolean) $options[4];
        $selectMultiple = (boolean) $options[5];

        // Get user selected citation export format...
        if (get_record('usr_account_preference', 'field', 'zoteroexportformat', 'usr', $USER->get('id'))) {
            $exportformat = get_account_preference($USER->get('id'), 'zoteroexportformat');
        }
        else {
            $exportformat = 'bibtex';
        }

        // Get folder parent...
        $parent_id = '0'; // either 'root' folder itself or parent is 'root' folder
        $folder = self::get_folder_info($folder_id);
        if (!empty($folder['parent_id'])) {
            $parent_id = $folder['parent_id'];
        }

        // Get folder contents...
        $consumer = self::get_service_consumer();
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            // Use different path for top items/collections
            // than for sub-items/sub-collections...
            if ($folder_id == '0') {
                $rootpath = '';
            }
            else {
                $rootpath = '/collections/'.$folder_id;
            }

            // Get all sub-collections of given collection.
            $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].$rootpath.'/collections';
            $method = 'GET';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_signature_method' => 'HMAC-SHA1',
                // Method specific prameters...
                'key' => $consumer->usrprefs['oauth_token'],
                'format'  => 'atom',
                'content' => 'json'
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                CURLOPT_PORT => $port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (isset($result->data) && !empty($result->data) &&
                isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                //Use that namespace
                $namespaces = $xml->getNameSpaces(true);
                //Now we don't have the URL hard-coded
                $zapi = $xml->children($namespaces['zapi']);
                $total = $zapi->totalResults;

                $output = array();
                $count = 0;

                // Add 'parent' row entry to jQuery Datatable...
                if ($folder_id != '0') {
                    $type        = 'parentfolder';
                    $foldername  = get_string('parentfolder', 'artefact.file');
                    $icon        = '<span class="icon-level-up icon icon-lg"></span>';
                    $title       = '<a class="changefolder" href="javascript:void(0)" id="' . $parent_id . '" title="' . get_string('gotofolder', 'artefact.file', $foldername) . '">' . $foldername . '</a>';
                    $output['data'][] = array($icon, $title, '', $type);
                }
                // or 'library items' (= items in the top level of Library) row entry to jQuery Datatable...
                else {
                    $type  = 'parentfolder';
                    $icon  = '<span class="icon-archive icon icon-lg"></span>';
                    $title = '<span class="changefolder">'. get_string('allreferences', 'blocktype.cloud/zotero') . '</span>';
                    if ($selectFolders) {
                        $selected = (in_array('0', $artefacts) ? ' checked' : '');
                        $controls = '<input type="' . ($selectMultiple ? 'checkbox' : 'radio') . '" name="artefacts[]" id="artefacts[]" value="0"' . $selected . '>';
                    }
                    else {
                        $controls  = '<div class="btn-group">';
                        $controls .= '<a class="btn btn-default btn-xs" href="export.php?id=0&type=collection&format=' . $exportformat . '" title="' . get_string('cite', 'blocktype.cloud/zotero') . '"><span class="icon-download icon icon-lg"></span></a>';
                        $controls .= '</div>';
                    }
                    $output['data'][] = array($icon, $title, $controls, $type);
                }

                // Add folders/collections
                for ($i=0; $i<$total; $i++) {
                    if ($showFolders && isset($xml->entry[$i]->content) && !empty($xml->entry[$i]->content)) {
                        $content = json_decode($xml->entry[$i]->content);
                        $parent_id = $content->parentCollection;
                        if (!$parent_id) {
                            $parent_id = '0';
                        }
                        if (isset($xml->entry[$i]->id) && $parent_id == $folder_id) {
                            $id    = basename($xml->entry[$i]->id);
                            $type  = 'folder';
                            $icon  = '<span class="icon-folder-open icon icon-lg"></span>';
                            $title = '<a class="changefolder" href="javascript:void(0)" id="' . $id . '" title="' . get_string('gotofolder', 'artefact.file', $content->name) . '">' . $content->name . '</a>';
                            if ($selectFolders) {
                                $selected = (in_array('folder-'.$id, $artefacts) ? ' checked' : '');
                                $controls = '<input type="' . ($selectMultiple ? 'checkbox' : 'radio') . '" name="artefacts[]" id="artefacts[]" value="folder-' . $id . '"' . $selected . '>';
                            }
                            else {
                                $controls  = '<div class="btn-group">';
                                $controls .= '<a class="btn btn-default btn-xs" title="' . get_string('cite', 'blocktype.cloud/zotero') . '" href="export.php?id=' . $id . '&type=collection&format=' . $exportformat . '" title="' . get_string('cite', 'blocktype.cloud/zotero') . '"><span class="icon-download icon icon-lg"></span></a>';
                                $controls .= '</div>';
                            }
                            $output['data'][] = array($icon, $title, $controls, $type);
                            $count++;
                        }
                    }
                }

                // Get all sub-itesm of given item/collection.
                $url2 = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].$rootpath.'/items';
                $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url2, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
                $header = array();
                $header[] = build_oauth_header($params, "Zotero API PHP Client");
                $header[] = 'Zotero-API-Version: '.$consumer->version;
                $config2 = array(
                    CURLOPT_URL => $url2.'?'.oauth_http_build_query($params),
                    CURLOPT_PORT => $port,
                    CURLOPT_POST => false,
                    CURLOPT_HEADER => true,
                    CURLOPT_HTTPHEADER => $header,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
                );
                $result2 = mahara_http_request($config2);
                if (isset($result2->data) && !empty($result2->data) &&
                    isset($result2->info) && !empty($result2->info) && $result2->info['http_code'] == 200) {
                    $xml = simplexml_load_string(substr($result2->data, $result2->info['header_size']));
                    //Use that namespace
                    $namespaces = $xml->getNameSpaces(true);
                    //Now we don't have the URL hard-coded
                    $zapi = $xml->children($namespaces['zapi']);
                    $total = $zapi->totalResults;

                    for ($i=0; $i<$total; $i++) {
                        if ($showFiles && isset($xml->entry[$i]->content) && !empty($xml->entry[$i]->content)) {
                            $content = json_decode($xml->entry[$i]->content);
                            // Show just entries, without their attachments
                            if (isset($xml->entry[$i]->id) && $content->itemType != 'attachment') {
                                $id    = basename($xml->entry[$i]->id);
                                $type  = 'file';
                                $icon  = '<span class="icon-file icon icon-lg"></span>';
                                $title = '<a class="filedetails" href="' . get_config('wwwroot') . 'artefact/cloud/blocktype/zotero/details.php?id=' . $id . '" title="' . get_string('filedetails', 'artefact.cloud', $content->title) . '">' . $content->title . '</a>';
                                if ($selectFiles) {
                                    $selected = (in_array('file-'.$id, $artefacts) ? ' checked' : '');
                                    $controls = '<input type="' . ($selectMultiple ? 'checkbox' : 'radio') . '" name="artefacts[]" id="artefacts[]" value="file-' . $id . '"' . $selected . '>';
                                }
                                else {
                                    $controls  = '<div class="btn-group">';
                                    $controls .= '<a class="btn btn-default btn-xs" href="export.php?id=' . $id . '&type=item&format=' . $exportformat . '" title="' . get_string('cite', 'blocktype.cloud/zotero') . '"><span class="icon-download icon icon-lg"></span></a>';
                                    $controls .= '</div>';
                                }
                                $output['data'][] = array($icon, $title, $controls, $type);
                                $count++;
                            }
                        }
                    }
                }
                else {
                    $httpstatus = get_http_status($result2->info['http_code']);
                    $SESSION->add_error_msg($httpstatus);
                    log_warn($httpstatus);
                }
                
                // Get all tags...
                $url3 = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].$rootpath.'/tags';
                $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url3, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
                $header = array();
                $header[] = build_oauth_header($params, "Zotero API PHP Client");
                $header[] = 'Zotero-API-Version: '.$consumer->version;
                $config3 = array(
                    CURLOPT_URL => $url3.'?'.oauth_http_build_query($params),
                    CURLOPT_PORT => $port,
                    CURLOPT_POST => false,
                    CURLOPT_HEADER => true,
                    CURLOPT_HTTPHEADER => $header,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
                );
                $result3 = mahara_http_request($config3);
                if (isset($result3->data) && !empty($result3->data) &&
                    isset($result3->info) && !empty($result3->info) && $result3->info['http_code'] == 200) {
                    $xml = simplexml_load_string(substr($result3->data, $result3->info['header_size']));
                    //Use that namespace
                    $namespaces = $xml->getNameSpaces(true);
                    //Now we don't have the URL hard-coded
                    $zapi = $xml->children($namespaces['zapi']);
                    $total = $zapi->totalResults;

                    $tags = array();
                    for ($i=0; $i<$total; $i++) {
                        if ($showFolders && isset($xml->entry[$i]->content) && !empty($xml->entry[$i]->content)) {
                            $content = json_decode($xml->entry[$i]->content);
                            $tags[] = $content->tag;
                            $count++;
                        }
                    }
                    // Natural order, case in-sensitive sort
                    natcasesort($tags);
                    foreach ($tags as $tag) {
                        // Show tags
                        $type  = 'a_tag';
                        $icon  = '<span class="icon-tag icon icon-lg"></span>';
                        $title = $tag;
                        if ($selectFolders) {
                            $selected = (in_array('tag-'.$tag, $artefacts) ? ' checked' : '');
                            $controls = '<input type="' . ($selectMultiple ? 'checkbox' : 'radio') . '" name="artefacts[]" id="artefacts[]" value="tag-' . $tag . '"' . $selected . '>';
                        }
                        else {
                            $controls  = '<div class="btn-group">';
                            $controls .= '<a class="btn btn-default btn-xs" href="export.php?id=' . $tag . '&type=tag&format=' . $exportformat . '" title="' . get_string('cite', 'blocktype.cloud/zotero') . '"><span class="icon-download icon icon-lg"></span></a>';
                            $controls .= '</div>';
                        }
                        $output['data'][] = array($icon, $title, $controls, $type);
                    }
                }
                else {
                    $httpstatus = get_http_status($result3->info['http_code']);
                    $SESSION->add_error_msg($httpstatus);
                    log_warn($httpstatus);
                }
                
                $output['iTotalRecords'] = $count;
                $output['iTotalDisplayRecords'] = $count;
                return json_encode($output);
            }
            else {
                $httpstatus = get_http_status($result->info['http_code']);
                $SESSION->add_error_msg($httpstatus);
                log_warn($httpstatus);
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }
    
    // SEE: http://www.zotero.org/support/dev/server_api/read_api
    //      get_folder_info basically corresponds to get_collection_info!
    public function get_folder_info($folder_id='0', $owner=null) {
        global $SESSION;
        $consumer = self::get_service_consumer();
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            // Init collection title and data variables...
            $collectionTitle = null;
            $colectionParent = 0;
            $collectionData  = array();
            
            // Get collection title...
            if ($folder_id == '0') {
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/collections';
            }
            else {
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/collections/'.$folder_id;
            }
            $method = 'GET';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_signature_method' => 'HMAC-SHA1',
                // Method specific prameters...
                'key' => $consumer->usrprefs['oauth_token'],
                'format'  => 'atom',
                'content' => 'json'
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                CURLOPT_PORT => $port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (!empty($result) && strlen($folder_id) > 0) {
                if (isset($result->data) && !empty($result->data) &&
                    isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                    $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                    $collectionTitle = $xml->title;
                    $content = json_decode($xml->content);
                    if (!empty($content)) {
                        $collectionParent = $content->parentCollection;
                    } else {
                        $collectionParent = '0';
                    }
                }
                else {
                    $httpstatus = get_http_status($result->info['http_code']);
                    $SESSION->add_error_msg($httpstatus);
                    log_warn($httpstatus);
                }
            }
            else {
                throw new ParameterException('Missing Zotero collection id');
            }
            
            // Get collection data...
            if ($folder_id == '0') {
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/items';
            }
            else {
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/collections/'.$folder_id.'/items';
            }
            $method = 'GET';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_signature_method' => 'HMAC-SHA1',
                // Method specific prameters...
                'key' => $consumer->usrprefs['oauth_token'],
                'format'  => 'atom',
                'content' => 'json'
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                CURLOPT_PORT => $port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (!empty($result) && strlen($folder_id) > 0) {
                if (isset($result->data) && !empty($result->data) &&
                    isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                    $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                    //Use that namespace
                    $namespaces = $xml->getNameSpaces(true);
                    //Now we don't have the URL hard-coded
                    $zapi = $xml->children($namespaces['zapi']);
                    $total = $zapi->totalResults;

                    for ($i=0; $i<$total; $i++) {
                        $content  = json_decode($xml->entry[$i]->content);
                        $author = '';
                        if (isset($content->creators)) {
                            $creators = $content->creators;
                            // Count all the authors...
                            $count = 0;
                            foreach ($creators as $creator) {
                                if ($creator->creatorType == 'author' || $creator->creatorType == 'contributor') $count++;
                            }
                            // Find first author...
                            foreach ($creators as $creator) {
                                if ($creator->creatorType == 'author' || $creator->creatorType == 'contributor') {
                                    if (!empty($creator->firstName) && !empty($creator->lastName)) {
                                        $author = $creator->lastName . ', ' . $creator->firstName;
                                    } elseif (!empty($creator->name)) {
                                        $author = $creator->name;
                                    }
                                    // Add suffix if there are more than 1 author
                                    if ($count > 1) {
                                        $author .= ' et al.';
                                    }
                                    break;
                                }
                            }
                        }

                        $collectionData[] = array(
                            'id'     => basename($xml->entry[$i]->id),
                            'name'   => $content->title,
                            'author' => $author,
                        );
                    }
                    return array('title' => $collectionTitle, 'id' => $folder_id, 'parent_id' => $collectionParent, 'items' => $collectionData);
                }
                else {
                    $httpstatus = get_http_status($result->info['http_code']);
                    $SESSION->add_error_msg($httpstatus);
                    log_warn($httpstatus);
                }
            }
            else {
                throw new ParameterException('Missing Zotero collection id');
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }
    
    // SEE: http://www.zotero.org/support/dev/server_api/read_api
    //      get_file_info basically corresponds to get_item_info!
    public function get_file_info($file_id='', $owner=null) {
        global $SESSION;
        $consumer = self::get_service_consumer();
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/items/'.$file_id;
            $method = 'GET';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_signature_method' => 'HMAC-SHA1',
                // Method specific prameters...
                'key' => $consumer->usrprefs['oauth_token'],
                'format'  => 'atom',
                'content' => 'json'
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                CURLOPT_PORT => $port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (!empty($result) && strlen($file_id) > 0) {
                if (isset($result->data) && !empty($result->data) &&
                    isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                    $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                    $content = json_decode($xml->content);

                    $creatorsList = array();
                    foreach ($content->creators as $creator) {
                        $creatorsList[] = array(
                            'creatorType' => (isset($creator->creatorType) ? $creator->creatorType : 'author'),
                            'firstName' => (isset($creator->firstName) ? $creator->firstName : ''),
                            'lastName'  => (isset($creator->lastName) ? $creator->lastName : ''),
                            'name'      => (isset($creator->name) ? $creator->name : '')
                        );
                    }
                    $info = array(
                        'id'           => basename($xml->id),
                        'name'         => $content->title,
                        'updated'      => format_date(strtotime($xml->updated), 'strfdaymonthyearshort'),
                        'created'      => format_date(strtotime($xml->published), 'strfdaymonthyearshort'),
                        'creators'     => $creatorsList,
                        'type'         => $content->itemType,
                        'abstract'     => (isset($content->abstractNote) ? $content->abstractNote : ''),
                        'series'       => (isset($content->series) ? $content->series : ''),
                        'seriesNumber' => (isset($content->seriesNumber) ? $content->seriesNumber : ''),
                        'volume'       => (isset($content->volume) ? $content->volume : ''),
                        'numVolumes'   => (isset($content->numberOfVolumes) ? $content->numberOfVolumes : ''),
                        'publisher'    => (isset($content->publisher) ? $content->publisher : ''),
                        'edition'      => (isset($content->edition) ? $content->edition : ''),
                        'place'        => (isset($content->place) ? $content->place : ''),
                        'date'         => (isset($content->date) ? format_date(strtotime($content->date), 'strfdaymonthyearshort') : ''),
                        'numPages'     => (isset($content->numPages) ? $content->numPages : ''),
                        'language'     => (isset($content->language) ? $content->language : ''),
                        'ISBN'         => (isset($content->ISBN) ? $content->ISBN : ''),
                        'ISSN'         => (isset($content->ISSN) ? $content->ISSN : ''),
                        'url'          => (isset($content->url) ? $content->url : ''),
                        'accessDate'   => (!empty($content->accessDate) ? format_date(strtotime($content->accessDate), 'strfdaymonthyearshort') : ''),
                    );
                    return $info;
                }
                else {
                    $httpstatus = get_http_status($result->info['http_code']);
                    $SESSION->add_error_msg($httpstatus);
                    log_warn($httpstatus);
                }
            }
            else {
                throw new ParameterException('Missing Zotero item id');
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }

    public function download_file($file_id='', $owner=null) {
        // Zotero API doesn't support downloading of references, so:
        // Nothing to do!
    }

    public function export_citation($id='0', $type='collection', $format='bibtex', $tag=null) {
        global $SESSION;
        $consumer = self::get_service_consumer();
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            if ($id == '0') {
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/items'; // Whole library
            }
            else {
                if ($type == 'collection') {
                    $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/collections/'.$id.'/items'; // Collection
                }
                else {
                    $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/items/'.$id; // Item
                }
            }
            
            $method = 'GET';
            $port = $consumer->ssl ? '443' : '80';
            $params = array(
                'oauth_version' => '1.0',
                'oauth_nonce' => mt_rand(),
                'oauth_timestamp' => time(),
                'oauth_consumer_key' => $consumer->key,
                'oauth_token' => $consumer->usrprefs['oauth_token'],
                'oauth_signature_method' => 'HMAC-SHA1',
                // Method specific prameters...
                'key' => $consumer->usrprefs['oauth_token'],
                'format'  => $format,
                'limit' => 99,
                'style' => 'apa',
                'tag' => $tag,
            );
            $params['oauth_signature'] = oauth_compute_hmac_sig($method, $url, $params, $consumer->secret, $consumer->usrprefs['oauth_token_secret']);
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => $url.'?'.oauth_http_build_query($params),
                CURLOPT_PORT => $port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (!empty($result) && strlen($id) > 0) {
                if (isset($result->data) && !empty($result->data) &&
                    isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                    $content = substr($result->data, $result->info['header_size']);
                    return $content;
                }
                else {
                    $httpstatus = get_http_status($result->info['http_code']);
                    $SESSION->add_error_msg($httpstatus);
                    log_warn($httpstatus);
                }
            }
            else {
                throw new ParameterException('Missing required parameter');
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }

    public function get_bibbase($id='0', $type='collection', $owner=null, $tag=null) {
        global $SESSION;
        $consumer = self::get_service_consumer($owner);
        if (!empty($consumer->key) && !empty($consumer->secret)) {
            if ($type == 'collection' && $id != '0') {
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/collections/'.$id.'/items'; // Collection
            }
            else {
                $url = $consumer->apiurl.'users/'.$consumer->usrprefs['userID'].'/items/'; // Whole library
            }
            $params = array(
               'key' => $consumer->usrprefs['oauth_token'],
               'format' => 'bibtex',
               'limit' => 99,
               'msg' => 'embed',
               'tag' => $tag,
            );
            $zoterourl = rawurlencode($url . '?' . oauth_http_build_query($params));

            $method = 'GET';
            //$port = $consumer->ssl ? '443' : '80';
            $header = array();
            $header[] = build_oauth_header($params, "Zotero API PHP Client");
            $header[] = 'Zotero-API-Version: '.$consumer->version;
            $config = array(
                CURLOPT_URL => 'http://bibbase.org/show?bib='.$zoterourl,
                CURLOPT_PORT => '80', //$port,
                CURLOPT_POST => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if (!empty($result) && strlen($id) > 0) {
                if (isset($result->data) && !empty($result->data) &&
                    isset($result->info) && !empty($result->info) && $result->info['http_code'] == 200) {
                    $content = substr($result->data, $result->info['header_size']);
                    return $content;
                }
                else {
                    $httpstatus = get_http_status($result->info['http_code']);
                    $SESSION->add_error_msg($httpstatus);
                    log_warn($httpstatus);
                }
            }
        }
        else {
            $SESSION->add_error_msg('Can\'t find Zotero consumer key and/or consumer secret.');
        }
    }

    public function embed_file($file_id='', $options=array(), $owner=null) {
        // Zotero API doesn't support embedding of references, so:
        // Nothing to do!
    }

}


function get_zotero_bibstyles() {
    $styles = array(
        'american-anthropological-association',
        'apa-5th-edition',
        'apa',
        'chicago-author-date',
        'chicago-fullnote-bibliography',
        'chicago-note-bibliography',
        'elsevier-with-titles',
        'harvard1',
        'ieee',
        'ieee-with-url',
        'iso690-author-date-en',
        'iso690-numeric-en',
        'modern-humanities-research-association',
        'modern-language-association',
        'modern-language-association-with-url',
        'nature',
        'turabian-author-date',
        'turabian-fullnote-bibliography',
        'vancouver',
    );

    foreach ($styles as $s) {
        $bibstyles[$s] = get_string("style.{$s}", 'blocktype.cloud/zotero');
    };
    uasort($bibstyles, 'strcoll');
    return $bibstyles;
}
