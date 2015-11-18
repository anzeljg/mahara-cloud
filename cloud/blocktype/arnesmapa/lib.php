<?php
/**
 *
 * @package    mahara
 * @subpackage blocktype-arnesmapa
 * @author     Gregor Anzelj
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2014 Gregor Anzelj, gregor.anzelj@gmail.com
 *
 */

defined('INTERNAL') || die();

safe_require('artefact', 'cloud');
require_once(get_config('docroot') . 'artefact/cloud/lib/oauth.php');


class PluginBlocktypeArnesmapa extends PluginBlocktypeCloud {

    //const servicepath = 'arnesmapapath';
    
    public static function get_title() {
        return get_string('title', 'blocktype.cloud/arnesmapa');
    }

    public static function get_description() {
        return get_string('description', 'blocktype.cloud/arnesmapa');
    }

    public static function get_categories() {
        return array('external');
    }

    public static function render_instance(BlockInstance $instance, $editing=false) {
        $configdata = $instance->get('configdata');
        $viewid     = $instance->get('view');
        
        $view = new View($viewid);
        $ownerid = $view->get('owner');

        //$fullpath = (!empty($configdata['fullpath']) ? $configdata['fullpath'] : '/|@');
        //list($folder, $path) = explode('|', $fullpath, 2);
        $selected = (!empty($configdata['artefacts']) ? $configdata['artefacts'] : array());
        
        $smarty = smarty_core();
        $data = self::get_filelist($folder, $selected, $ownerid);
        $smarty->assign('folders', $data['folders']);
        $smarty->assign('files', $data['files']);
        $smarty->assign('viewid', $viewid);
        return $smarty->fetch('blocktype:arnesmapa:list.tpl');
    }

    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form($instance) {
        $instanceid = $instance->get('id');
        $configdata = $instance->get('configdata');
        $allowed = (!empty($configdata['allowed']) ? $configdata['allowed'] : array());
        safe_require('artefact', 'cloud');
        $instance->set('artefactplugin', 'cloud');
        $viewid = $instance->get('view');

        $view = new View($viewid);
        $ownerid = $view->get('owner');
        
        $data = ArtefactTypeCloud::get_user_preferences('arnesmapa', $ownerid);
        if ($data) {
            return array(
                'arnesmapalogo' => array(
                    'type' => 'html',
                    'value' => '<img src="' . get_config('wwwroot') . 'artefact/cloud/blocktype/arnesmapa/theme/raw/static/images/logo.png">',
                ),
                'arnesmapaisconnect' => array(
                    'type' => 'cancel',
                    'value' => get_string('revokeconnection', 'blocktype.cloud/arnesmapa'),
                    'goto' => get_config('wwwroot') . 'artefact/cloud/blocktype/arnesmapa/account.php?action=logout',
                ),
                'arnesmapafiles' => array(
                    'type'     => 'datatables',
                    'title'    => get_string('selectfiles','blocktype.cloud/arnesmapa'),
                    'service'  => 'arnesmapa',
                    'block'    => $instanceid,
                    'fullpath' => (isset($configdata['fullpath']) ? $configdata['fullpath'] : null),
                    'options'  => array(
                        'showFolders'    => true,
                        'showFiles'      => true,
                        'selectFolders'  => false,
                        'selectFiles'    => true,
                        'selectMultiple' => true
                    ),
                ),
            );
        }
        else {
            return array(
                'arnesmapalogo' => array(
                    'type' => 'html',
                    'value' => '<img src="' . get_config('wwwroot') . 'artefact/cloud/blocktype/arnesmapa/theme/raw/static/images/logo.png">',
                ),
                'arnesmapaisconnect' => array(
                    'type' => 'cancel',
                    'value' => get_string('connecttoarnesmapa', 'blocktype.cloud/arnesmapa'),
                    'goto' => get_config('wwwroot') . 'artefact/cloud/blocktype/arnesmapa/account.php?action=login',
                ),
            );
        }
    }

    public static function instance_config_save($values) {
        // Folder and file IDs (and other values) are returned as JSON/jQuery serialized string.
        // We have to parse that string and urldecode it (to correctly convert square brackets)
        // in order to get cloud folder and file IDs - they are stored in $artefacts array.
        parse_str(urldecode($values['arnesmapafiles']));
        if (!isset($artefacts) || empty($artefacts)) {
            $artefacts = array();
        }
        
        $values = array(
            'title'     => $values['title'],
            'artefacts' => $artefacts,
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
        return false;
    }

    public static function default_copy_type() {
        return 'shallow';
    }

    /***********************************************
     * Methods & stuff for accessing Arnes Mapa API *
     ***********************************************/
    
    private function get_service_consumer($owner=null) {
        global $USER;
        if (!isset($owner) || is_null($owner)) {
            $owner = $USER->get('id');
        }
        $service = new StdClass();
        $service->ssl        = true;
        $service->version    = ''; // API Version
        $service->title      = get_string('service', 'blocktype.cloud/arnesmapa');
        $service->webdavurl  = 'https://mapa.arnes.si/remote.php/webdav/';
        $service->usrprefs   = ArtefactTypeCloud::get_user_preferences('arnesmapa', $owner);
        if (isset($service->usrprefs['token'])) {
            $service->usrprefs['token'] = base64_decode($service->usrprefs['token']);
        }
        return $service;
    }

    public function service_list() {
        $consumer = self::get_service_consumer();
        $webdavurl = parse_url($consumer->webdavurl);
        if (isset($consumer->usrprefs['token']) && !empty($consumer->usrprefs['token'])) {
            return array(
                'service_name'    => 'arnesmapa',
                'service_url'     => $webdavurl['scheme'].'://'.$webdavurl['host'],
                'service_auth'    => true,
                'service_manage'  => true,
                //'revoke_access'   => true,
            );
        } else {
            return array(
                'service_name'    => 'arnesmapa',
                'service_url'     => $webdavurl['scheme'].'://'.$webdavurl['host'],
                'service_auth'    => false,
                'service_manage'  => false,
                //'revoke_access'   => false,
            );
        }
    }
    
    public function request_token() {
        // Arnes Mapa doesn't use request token, but HTTP Basic Authentication
    }

    public function access_token($request_token) {
        // Arnes Mapa doesn't use access token, but HTTP Basic Authentication
    }

    public function delete_token() {
        global $USER;
        ArtefactTypeCloud::set_user_preferences('arnesmapa', $USER->get('id'), null);
    }
    
    public function revoke_access() {
        // Revoke access to Arnes Mapa by deleting user credentials
        // That happens in delete_token function
    }
    
    public function account_info() {
        $consumer = self::get_service_consumer();
        $webdavurl = parse_url($consumer->webdavurl);
        if (isset($consumer->usrprefs['token']) && !empty($consumer->usrprefs['token'])) {
            $url = $consumer->webdavurl;
            $port = $consumer->ssl ? '443' : '80';
            $header = array();
            $header[] = 'User-Agent: Arnes Mapa API PHP Client';
            $header[] = 'Host: ' . $webdavurl['host'];
            $header[] = 'Content-Type: application/xml; charset=UTF-8';
            $config = array(
                CURLOPT_URL => $url,
                CURLOPT_PORT => $port,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_CUSTOMREQUEST => 'PROPFIND',
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $consumer->usrprefs['token'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
log_debug($result);
            if ($result->info['http_code'] == 207 /* HTTP/1.1 207 Multi-Status */ && !empty($result->data)) {
                $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                $namespaces = $xml->getNameSpaces(true);
                $dav = $xml->children($namespaces['d']);
                // Get user's quota...
                $used  = (float) $dav->response[0]->propstat->prop->{'quota-used-bytes'};
                $total = (float) $dav->response[0]->propstat->prop->{'quota-available-bytes'};
                $user  = explode(':', $consumer->usrprefs['token']);
                return array(
                    'service_name' => 'arnesmapa',
                    'service_auth' => true,
                    'user_id'      => null,
                    'user_name'    => $user[0],
                    'user_email'   => null,
                    'space_used'   => bytes_to_size1024($used),
                    'space_amount' => bytes_to_size1024($total),
                    'space_ratio'  => number_format(($used/$total)*100, 2),
                );
            } else {
                return array(
                    'service_name' => 'arnesmapa',
                    'service_auth' => false,
                    'user_id'      => null,
                    'user_name'    => null,
                    'user_email'   => null,
                    'space_used'   => null,
                    'space_amount' => null,
                    'space_ratio'  => null,
                );
            }
        }
        else {
            throw new ConfigException('Can\'t access Arnes Mapa via WebDAV. Incorrect user credentials.');
        }
    }
    
    
    /*
     * This function returns list of selected files/folders which will be displayed in a view/page.
     *
     * $folder_id   integer   ID of the folder (on Cloud Service), which contents we wish to retrieve
     * $output      array     Function returns array, used to generate list of files/folders to show in Mahara view/page
     */
    public function get_filelist($folder_id='/', $selected=array(), $owner=null) {
        global $SESSION, $THEME;

       // Get folder contents...
        $consumer = self::get_service_consumer($owner);
        $webdavurl = parse_url($consumer->webdavurl);
        if (isset($consumer->usrprefs['token']) && !empty($consumer->usrprefs['token'])) {
            $url = $consumer->webdavurl.ltrim($folder_id, '/');
            $port = $consumer->ssl ? '443' : '80';
            $header = array();
            $header[] = 'User-Agent: Arnes Mapa API PHP Client';
            $header[] = 'Host: ' . $webdavurl['host'];
            $header[] = 'Content-Type: application/xml; charset=UTF-8';
            $config = array(
                CURLOPT_URL => $url,
                CURLOPT_PORT => $port,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_CUSTOMREQUEST => 'PROPFIND',
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $consumer->usrprefs['token'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
			//log_debug($result);
            if ($result->info['http_code'] == 207 /* HTTP/1.1 207 Multi-Status */ && !empty($result->data)) {
                $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                $namespaces = $xml->getNameSpaces(true);
                $dav = $xml->children($namespaces['d']);
                $output = array(
                    'folders' => array(),
                    'files'   => array()
                );
                $isFirst = true;
                if (!empty($dav->response)) {
                    foreach($dav->response as $artefact) {
                        $filepath = (string) $artefact->href;
                        // First entry in $dav->response holds general information
                        // about selected folder...
                        if ($isFirst) {
                            $isFirst = false;
                            $prefix = $filepath;
                            continue;
                        }
                        $filepath = str_replace($webdavurl['path'], '', rtrim($filepath, '/'));
                        // In Arnes Mapa WebDAV id basically means path...
                        $id = $filepath;
                        if (in_array($id, $selected)) {
                            $type         = (isset($artefact->propstat->prop->getcontenttype) ? 'file' : 'folder');
                            $icon         = '<img src="' . $THEME->get_url('images/' . $type . '.png') . '">';
                            $artefactname = basename($filepath);
                            $title        = urldecode($artefactname);
                            $description  = '';
                            $size         = bytes_to_size1024((float) $artefact->propstat->prop->getcontentlength);
                            $created      = format_date(strtotime((string) $artefact->propstat->prop->getlastmodified), 'strftimedaydate');
                            if ($type == 'folder') {
                                $output['folders'][] = array('iconsrc' => $icon, 'id' => $id, 'title' => $title, 'description' => $description, 'size' => $size, 'ctime' => $created);
                            }
                            else
                            {
                                $output['files'][] = array('iconsrc' => $icon, 'id' => $id, 'title' => $title, 'description' => $description, 'size' => $size, 'ctime' => $created);
                            }
                        }
                    }
                }
                return $output;
            }
            else {
                $SESSION->add_error_msg('1: '.get_string('httprequestcode', '', $result->info['http_code']));
            }
        }
        else {
            throw new ConfigException('Can\'t access Arnes Mapa via WebDAV. Incorrect user credentials.');
        }
    }

    /*
     * This function gets folder contents and formats it, so it can be used in blocktype config form
     * (Pieform element) and in manage page.
     *
     * $folder_id   integer   ID of the folder (on Cloud Service), which contents we wish to retrieve
     * $options     integer   List of 6 integers (booleans) to indicate (for all 6 options) if option is used or not
     * $block       integer   ID of the block in given Mahara view/page
     * $fullpath    string    Fullpath to the folder (on Cloud Service), last opened by user
     *
     * $output      array     Function returns JSON encoded array of values that is suitable to feed jQuery Datatables with.
                              jQuery Datatable than draw an enriched HTML table according to values, contained in $output.
     * PLEASE NOTE: For jQuery Datatable to work, the $output array must be properly formatted and JSON encoded.
     *              Please see: http://datatables.net/usage/server-side (Reply from the server)!
     */
    public function get_folder_content($folder_id='/', $options, $block=0, $fullpath='/|@') {
        global $THEME;
        
        // Get selected artefacts (folders and/or files)
        if ($block > 0) {
            $data = unserialize(get_field('block_instance', 'configdata', 'id', $block));
            if (!empty($data) && isset($data['artefacts'])) {
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

        // Set/get return path...
        if ($folder_id == 'init') {
            if (strlen($fullpath) > 3) {
                list($current, $path) = explode('|', $fullpath, 2);
                $_SESSION[self::servicepath] = $current . '|' . $path;
                $folder_id = $current;
            } else {
                // Full path equals path to root folder
                $_SESSION[self::servicepath] = '/|@';
                $folder_id = '/';
            }
        } else {
            if ($folder_id != 'parent') {
                // Go to child folder...
                if (strlen($folder_id) > 1) {
                    list($current, $path) = explode('|', $_SESSION[self::servicepath], 2);
                    if ($current != $folder_id) {
                        $_SESSION[self::servicepath] = $folder_id . '|' . $_SESSION[self::servicepath];
                    }
                }
                // Go to root folder...
                else {
                    $_SESSION[self::servicepath] = '/|@';
                }
            } else {
                // Go to parent folder...
                if (strlen($_SESSION[self::servicepath]) > 3) {
                    list($current, $parent, $path) = explode('|', $_SESSION[self::servicepath], 3);
                    $_SESSION[self::servicepath] = $parent . '|' . $path;
                    $folder_id = $parent;
                }
            }
        }

        list($parent_id, $path) = explode('|', $_SESSION[self::servicepath], 2);
        
        
        // Get folder contents...
        $consumer = self::get_service_consumer();
        $webdavurl = parse_url($consumer->webdavurl);
        if (isset($consumer->usrprefs['token']) && !empty($consumer->usrprefs['token'])) {
            $url = $consumer->webdavurl.ltrim($folder_id, '/');
            $port = $consumer->ssl ? '443' : '80';
            $header = array();
            $header[] = 'User-Agent: Arnes Mapa API PHP Client';
            $header[] = 'Host: ' . $webdavurl['host'];
            $header[] = 'Content-Type: application/xml; charset=UTF-8';
            $config = array(
                CURLOPT_URL => $url,
                CURLOPT_PORT => $port,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_CUSTOMREQUEST => 'PROPFIND',
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $consumer->usrprefs['token'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if ($result->info['http_code'] == 207 /* HTTP/1.1 207 Multi-Status */ && !empty($result->data)) {
                $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                $namespaces = $xml->getNameSpaces(true);
                $dav = $xml->children($namespaces['d']);
                $output = array();
                $count = 0;
                // Add 'parent' row entry to jQuery Datatable...
                if (strlen($_SESSION[self::servicepath]) > 3) {
                    $type        = 'parentfolder';
                    $foldername  = get_string('parentfolder', 'artefact.file');
                    $title       = '<a class="changefolder" href="javascript:void(0)" id="parent" title="' . get_string('gotofolder', 'artefact.file', $foldername) . '"><img src="' . $THEME->get_url('images/parentfolder.png') . '"></a>';
                    $output['aaData'][] = array('', $title, '', $type);
                }
                $isFirst = true;
                if (!empty($dav->response)) {
                    foreach($dav->response as $artefact) {
                        $filepath = (string) $artefact->href;
                        // First entry in $dav->response holds general information
                        // about selected folder...
                        if ($isFirst) {
                            $isFirst = false;
                            $prefix = $filepath;
                            continue;
                        }
                        $filepath = str_replace($webdavurl['path'], '', rtrim($filepath, '/'));
                        // In Arnes Mapa WebDAV id basically means path...
                        $id           = rawurlencode($filepath);
                        $type         = (isset($artefact->propstat->prop->getcontenttype) ? 'file' : 'folder');
                        $icon         = '<img src="' . $THEME->get_url('images/' . $type . '.png') . '">';
                        // Get artefactname by removing parent path from beginning...
                        $artefactname = basename($filepath);
						$title        = urldecode($artefactname);
                        if ($type == 'folder') {
                            $title    = '<a class="changefolder" href="javascript:void(0)" id="' . $id . '" title="' . get_string('gotofolder', 'artefact.file', $title) . '">' . $title . '</a>';
                        } else {
                            $title    = '<a class="filedetails" href="details.php?id=' . $id . '" title="' . get_string('filedetails', 'artefact.cloud', $title) . '">' . $title . '</a>';
                        }
                        $controls = '';
                        $selected = (in_array(urldecode($id), $artefacts) ? ' checked' : '');
                        if ($type == 'folder') {
                            if ($selectFolders) {
                                $controls = '<input type="' . ($selectMultiple ? 'checkbox' : 'radio') . '" name="artefacts[]" id="artefacts[]" value="' . $id . '"' . $selected . '>';
                            }
                        } else {
                            if ($selectFiles && !$manageButtons) {
                                $controls = '<input type="' . ($selectMultiple ? 'checkbox' : 'radio') . '" name="artefacts[]" id="artefacts[]" value="' . $id . '"' . $selected . '>';
                            } elseif ($manageButtons) {
                                $controls  = '<div class="btns2">';
                                $controls .= '<a title="' . get_string('save', 'artefact.cloud') . '" href="download.php?id=' . $id . '&save=1"><img src="' . get_config('wwwroot') . 'artefact/cloud/theme/raw/static/images/btn_save.png" alt="' . get_string('save', 'artefact.cloud') . '"></a>';
                                $controls .= '<a title="' . get_string('download', 'artefact.cloud') . '" href="download.php?id=' . $id . '"><img src="' . get_config('wwwroot') . 'artefact/cloud/theme/raw/static/images/btn_download.png" alt="' . get_string('download', 'artefact.cloud') . '"></a>';
                                $controls .= '</div>';
                            }
                        }
                        $output['aaData'][] = array($icon, $title, $controls, $type);
                        $count++;
                    }
                }
                $output['iTotalRecords'] = $count;
                $output['iTotalDisplayRecords'] = $count;
                return json_encode($output);
            }
            else {
                return array();
            }
        }
        else {
            throw new ConfigException('Can\'t access Arnes Mapa via WebDAV. Incorrect user credentials.');
        }
    }

    public function get_folder_info($folder_id='/', $owner=null) {
        global $SESSION;
        $consumer = self::get_service_consumer($owner);
        $webdavurl = parse_url($consumer->webdavurl);
        if (isset($consumer->usrprefs['token']) && !empty($consumer->usrprefs['token'])) {
            $url = $consumer->webdavurl.ltrim($folder_id, '/');
            $port = $consumer->ssl ? '443' : '80';
            $header = array();
            $header[] = 'User-Agent: Arnes Mapa API PHP Client';
            $header[] = 'Host: ' . $webdavurl['host'];
            $header[] = 'Content-Type: application/xml; charset=UTF-8';
            $config = array(
                CURLOPT_URL => $url,
                CURLOPT_PORT => $port,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_CUSTOMREQUEST => 'PROPFIND',
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $consumer->usrprefs['token'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if ($result->info['http_code'] == 207 /* HTTP/1.1 207 Multi-Status */ && !empty($result->data)) {
                $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                $namespaces = $xml->getNameSpaces(true);
                $dav = $xml->children($namespaces['d']);
                // Get info about artefact (folder)
                $artefact = $dav->response;
                $filepath = (string) $artefact->href;
                $filepath = str_replace($webdavurl['path'], '', rtrim($filepath, '/'));

                $info = array(
                    'id'          => $filepath,
                    'name'        => urldecode(basename($filepath)),
                    'description' => '', // Arnes Mapa doesn't support file/folder descriptions...
                    'updated'     => format_date(strtotime((string) $artefact->propstat->prop->getlastmodified), 'strfdaymonthyearshort'),
                );
                return $info;
            }
            else {
                $SESSION->add_error_msg('2: '.get_string('httprequestcode', '', $result->info['http_code']));
            }
        }
        else {
            throw new ConfigException('Can\'t access Arnes Mapa via WebDAV. Incorrect user credentials.');
        }
    }

    public function get_file_info($file_id='/', $owner=null) {
        global $SESSION;
        $consumer = self::get_service_consumer($owner);
        $webdavurl = parse_url($consumer->webdavurl);
        if (isset($consumer->usrprefs['token']) && !empty($consumer->usrprefs['token'])) {
            $url = $consumer->webdavurl.ltrim($file_id, '/');
            $port = $consumer->ssl ? '443' : '80';
            $header = array();
            $header[] = 'User-Agent: Arnes Mapa API PHP Client';
            $header[] = 'Host: ' . $webdavurl['host'];
            $header[] = 'Content-Type: application/xml; charset=UTF-8';
            $config = array(
                CURLOPT_URL => $url,
                CURLOPT_PORT => $port,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_CUSTOMREQUEST => 'PROPFIND',
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $consumer->usrprefs['token'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => get_config('docroot').'artefact/cloud/cert/cacert.crt'
            );
            $result = mahara_http_request($config);
            if ($result->info['http_code'] == 207 /* HTTP/1.1 207 Multi-Status */ && !empty($result->data)) {
                $xml = simplexml_load_string(substr($result->data, $result->info['header_size']));
                $namespaces = $xml->getNameSpaces(true);
                $dav = $xml->children($namespaces['d']);
                // Get info about artefact (file)
                $artefact = $dav->response;
                $filepath = (string) $artefact->href;
                $filepath = str_replace($webdavurl['path'], '', rtrim($filepath, '/'));
                $filesize = (float) $artefact->propstat->prop->getcontentlength;

                $info = array(
                    'id'          => $filepath,
                    'name'        => urldecode(basename($filepath)),
                    'bytes'       => $filesize,
                    'size'        => bytes_to_size1024($filesize), 
                    'description' => '', // Arnes Mapa doesn't support file/folder descriptions...
                    'updated'     => format_date(strtotime((string) $artefact->propstat->prop->getlastmodified), 'strfdaymonthyearshort'),
                    'mimetype'    => (string) $artefact->propstat->prop->getcontenttype,
                );
                return $info;
            }
            else {
                $SESSION->add_error_msg('3: '.get_string('httprequestcode', '', $result->info['http_code']));
            }
        }
        else {
            throw new ConfigException('Can\'t access Arnes Mapa via WebDAV. Incorrect user credentials.');
        }
    }

    public function download_file($file_id='/', $owner=null) {
        $consumer = self::get_service_consumer($owner);
        $webdavurl = parse_url($consumer->webdavurl);
        if (isset($consumer->usrprefs['token']) && !empty($consumer->usrprefs['token'])) {
            $download_url = $consumer->webdavurl.ltrim($file_id, '/');
            $port = $consumer->ssl ? '443' : '80';
            $header = array();
            $header[] = 'User-Agent: Arnes Mapa API PHP Client';
            $header[] = 'Host: ' . $webdavurl['host'];
            $header[] = 'Content-Type: application/xml; charset=UTF-8';

            $ch = curl_init($download_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_PORT, $port);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $consumer->usrprefs['token']);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        }
        else {
            throw new ConfigException('Can\'t access Arnes Mapa via WebDAV. Incorrect user credentials.');
        }
    }

    public function embed_file($file_id='/', $options=array(), $owner=null) {
        // Arnes Mapa doesn't support embedding of files, so:
        // Nothing to do!
    }

}

?>
